<?php

namespace FriendsOfRedaxo\FormStudio;

use rex;
use rex_article;
use rex_backend_login;
use rex_clang;
use rex_extension;
use rex_extension_point;
use rex_sql;
use rex_yrewrite;

/**
 * Knopf bzw. Link zu einem Formular – überall einsetzbar, z. B. in Snippets oder Texten:
 *
 *   <form-studio-button data-form="5" data-text="Tagung anfragen" data-preset="event_type=tagung"></form-studio-button>
 *
 * Ziel ist die Online-Seite, auf der das Formular mit dem Modul „Form Studio: Formular“ eingebunden ist
 * (aktuelle Domain zuerst). Wie der Knopf aussieht, legt die Knopf-Vorlage in den Einstellungen fest
 * (z. B. mit einer Klasse, die die Seite als Overlay öffnet).
 *
 * Extension Point FORM_STUDIO_BUTTON_PRESET: weitere Vorbelegungen ergänzen (Subject: array, Param form: Form).
 */
final class Button
{
    public const TAG = 'form-studio-button';

    public const DEFAULT_TEMPLATE = '<a class="form-studio-button" href="{{url}}">{{text}}</a>';

    /** Platzhalter der Knopf-Vorlage → Beschreibung (für die Einstellungen) */
    public const PLACEHOLDERS = [
        '{{url}}' => 'Adresse der Seite mit dem Formular (inkl. Vorbelegung)',
        '{{text}}' => 'Beschriftung (leer = Name des Formulars)',
        '{{form_name}}' => 'Name des Formulars',
        '{{form_key}}' => 'Schlüssel des Formulars',
        '{{form_id}}' => 'ID des Formulars',
    ];

    public static function template(): string
    {
        $template = trim((string) \rex_addon::get('form_studio')->getConfig('button_template', ''));
        return '' !== $template ? $template : self::DEFAULT_TEMPLATE;
    }

    /** OUTPUT_FILTER: <form-studio-button …></form-studio-button> ersetzen */
    public static function replace(string $html): string
    {
        if (!str_contains($html, '<' . self::TAG)) {
            return $html;
        }
        return (string) preg_replace_callback(
            '@(?:<p>\s*)?<' . self::TAG . '((?:\s+data-[a-z]+="[^"]*")*)\s*>\s*</' . self::TAG . '>(?:\s*</p>)?@',
            static function (array $m): string {
                preg_match_all('@data-([a-z]+)="([^"]*)"@', $m[1], $attrs, PREG_SET_ORDER);
                $a = [];
                foreach ($attrs as $attr) {
                    $value = html_entity_decode($attr[2], ENT_QUOTES);
                    // nicht gesetzte Snippet-Parameter („{text}“) zählen als leer
                    $a[$attr[1]] = 1 === preg_match('/^\{\w+\}$/', $value) ? '' : trim($value);
                }
                parse_str($a['preset'] ?? '', $preset);
                return self::render($a['form'] ?? '', $a['text'] ?? '', array_map('strval', array_filter($preset, 'is_scalar')));
            },
            $html
        );
    }

    /**
     * Knopf zum Formular nach der Knopf-Vorlage; leer, wenn das Formular fehlt oder auf keiner Online-Seite steht
     * (angemeldete Redakteure sehen dann einen Hinweis).
     *
     * @param array<string, string> $preset Vorbelegung, z. B. ['event_type' => 'tagung']
     */
    public static function render(string $form, string $text = '', array $preset = []): string
    {
        $row = self::find($form);
        if (null === $row) {
            return self::hint('Formular „' . $form . '“ nicht gefunden.');
        }
        $preset = rex_extension::registerPoint(new rex_extension_point('FORM_STUDIO_BUTTON_PRESET', $preset, ['form' => $row]));
        // Rückgabe eines EP-Handlers absichern: nur skalare Werte als Zeichenkette
        $url = self::url($row->id, array_map('strval', array_filter((array) $preset, 'is_scalar')));
        if (null === $url) {
            return self::hint('Das Formular „' . $row->name . '“ ist auf keiner Online-Seite eingebunden (Modul „Form Studio: Formular“).');
        }
        $label = '' !== trim($text) ? trim($text) : $row->name;
        return strtr(self::template(), [
            '{{url}}' => rex_escape($url),
            '{{text}}' => rex_escape($label),
            '{{form_name}}' => rex_escape($row->name),
            '{{form_key}}' => rex_escape($row->key),
            '{{form_id}}' => (string) $row->id,
        ]);
    }

    /** Formular nach ID, Schlüssel oder Name (Groß-/Kleinschreibung egal); nur aktive */
    public static function find(string $form): ?Form
    {
        $form = trim($form);
        if ('' === $form) {
            return null;
        }
        $found = ctype_digit($form) ? Form::get((int) $form) : Form::getByKey($form);
        if (null === $found) {
            $rows = rex_sql::factory()->getArray('SELECT id FROM ' . Form::table() . ' WHERE LOWER(name) = LOWER(?) LIMIT 1', [$form]);
            $found = $rows ? Form::get((int) $rows[0]['id']) : null;
        }
        return null !== $found && $found->active ? $found : null;
    }

    /**
     * Adresse der Online-Seite mit dem Formular (aktuelle Sprache, aktuelle Domain zuerst).
     *
     * @param array<string, string> $preset
     */
    public static function url(int $formId, array $preset = []): ?string
    {
        $article = self::article($formId);
        if (null === $article) {
            return null;
        }
        $preset = array_filter($preset, static fn (string $v): bool => '' !== $v);
        return [] !== $preset ? $article->getUrl(['fs_preset' => $preset], '&') : $article->getUrl();
    }

    public static function article(int $formId): ?rex_article
    {
        $module = rex_sql::factory()->getArray('SELECT id FROM ' . rex::getTable('module') . ' WHERE `key` = "form_studio"');
        if (!$module) {
            return null;
        }
        $clang = rex_clang::getCurrentId();
        $rows = rex_sql::factory()->getArray(
            'SELECT DISTINCT article_id FROM ' . rex::getTable('article_slice') . ' WHERE module_id = ? AND clang_id = ? AND value1 = ? AND status = 1 ORDER BY article_id',
            [(int) $module[0]['id'], $clang, (string) $formId]
        );
        $yrewrite = class_exists(rex_yrewrite::class);
        $current = $yrewrite ? rex_yrewrite::getCurrentDomain()?->getId() : null;
        $fallback = null;
        foreach ($rows as $row) {
            $article = rex_article::get((int) $row['article_id'], $clang);
            if (!$article || !$article->isOnline()) {
                continue;
            }
            if (null === $current || rex_yrewrite::getDomainByArticleId($article->getId(), $clang)?->getId() === $current) {
                return $article;
            }
            $fallback ??= $article;
        }
        return $fallback;
    }

    /** Hinweis nur für angemeldete Redakteure */
    private static function hint(string $message): string
    {
        if (!rex::isBackend() && !rex_backend_login::hasSession()) {
            return '';
        }
        return '<p class="form-studio-button-hint" role="note"><strong>Form Studio:</strong> ' . rex_escape($message) . '</p>';
    }
}
