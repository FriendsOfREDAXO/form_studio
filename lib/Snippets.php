<?php

namespace FriendsOfRedaxo\FormStudio;

use FriendsOfREDAXO\Snippets\Repository\SnippetRepository;
use rex;
use rex_addon;
use rex_sql;

/**
 * Legt je Formular ein Snippet im snippets-AddOn an (Einstellung „Snippets automatisch anlegen“):
 *
 *   [[snippet:formular_tagungsplaner]]                       Knopf mit dem Namen des Formulars
 *   [[snippet:formular_tagungsplaner|text=Tagung anfragen]]  eigene Beschriftung
 *
 * Inhalt ist der Platzhalter <form-studio-button>, das Aussehen kommt aus der Knopf-Vorlage.
 * Die Zuordnung Formular → Snippet steht in der Konfiguration (snippet_map), damit ein umbenanntes
 * Formular seinen Schlüssel behält und bestehende Platzhalter weiter funktionieren.
 */
final class Snippets
{
    public const KEY_PREFIX = 'formular_';

    public static function available(): bool
    {
        return rex_addon::get('snippets')->isAvailable() && class_exists(SnippetRepository::class);
    }

    public static function enabled(): bool
    {
        return self::available() && (bool) rex_addon::get('form_studio')->getConfig('snippets_enabled', false);
    }

    /**
     * Snippets mit den Formularen abgleichen: fehlende anlegen, Titel/Status aktualisieren,
     * Snippets gelöschter Formulare entfernen. Von Hand geänderte Inhalte bleiben unangetastet.
     *
     * @return array{created: int, updated: int, deleted: int}
     */
    public static function sync(): array
    {
        $result = ['created' => 0, 'updated' => 0, 'deleted' => 0];
        if (!self::enabled()) {
            return $result;
        }
        $addon = rex_addon::get('form_studio');
        /** @var array<int|string, int> $map */
        $map = (array) $addon->getConfig('snippet_map', []);
        $category = (int) $addon->getConfig('snippets_category', 0);
        $forms = rex_sql::factory()->getArray('SELECT id, `key`, name, status FROM ' . Form::table() . ' ORDER BY id');
        $seen = [];
        foreach ($forms as $row) {
            $id = (int) $row['id'];
            $seen[$id] = true;
            $snippet = isset($map[$id]) ? SnippetRepository::getById((int) $map[$id]) : null;
            $data = [
                'title' => 'Formular: ' . $row['name'] . ' (Knopf)',
                'description' => 'Knopf, der das Formular „' . $row['name'] . '“ öffnet. Eigene Beschriftung: [[snippet:KEY|text=…]]. Automatisch von Form Studio angelegt.',
                'status' => 1 === (int) $row['status'] ? 1 : 0,
            ];
            if (null !== $snippet) {
                $data['description'] = str_replace('KEY', $snippet->getKey(), $data['description']);
                if ($snippet->getTitle() !== $data['title'] || (string) $snippet->getDescription() !== $data['description'] || $snippet->isActive() !== (1 === $data['status'])) {
                    SnippetRepository::save(['id' => $snippet->getId()] + $data);
                    ++$result['updated'];
                }
                continue;
            }
            $key = self::uniqueKey(self::KEY_PREFIX . self::slug((string) $row['name']));
            $data['description'] = str_replace('KEY', $key, $data['description']);
            $map[$id] = SnippetRepository::save($data + [
                'key_name' => $key,
                'content' => '<' . Button::TAG . ' data-form="' . $id . '" data-text="{text}"></' . Button::TAG . '>',
                'content_type' => 'html',
                'context' => 'frontend',
                'category_id' => $category > 0 ? $category : null,
                'is_multilang' => 0,
            ]);
            ++$result['created'];
        }
        foreach ($map as $formId => $snippetId) {
            if (!isset($seen[(int) $formId])) {
                SnippetRepository::delete((int) $snippetId);
                unset($map[$formId]);
                ++$result['deleted'];
            }
        }
        $addon->setConfig('snippet_map', $map);
        return $result;
    }

    /** Kategorien des snippets-AddOns: id => Name */
    public static function categories(): array
    {
        if (!self::available()) {
            return [];
        }
        $out = [];
        foreach (rex_sql::factory()->getArray('SELECT id, name FROM ' . rex::getTable('snippets_category') . ' ORDER BY sort_order, name') as $row) {
            $out[(int) $row['id']] = (string) $row['name'];
        }
        return $out;
    }

    public static function slug(string $name): string
    {
        $slug = strtolower(strtr(trim($name), ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'Ä' => 'ae', 'Ö' => 'oe', 'Ü' => 'ue', 'ß' => 'ss']));
        $slug = trim((string) preg_replace('/[^a-z0-9]+/', '_', $slug), '_');
        return '' !== $slug ? $slug : 'formular';
    }

    private static function uniqueKey(string $key): string
    {
        $candidate = $key;
        for ($i = 2; null !== SnippetRepository::getByKey($candidate); ++$i) {
            $candidate = $key . '_' . $i;
        }
        return $candidate;
    }
}
