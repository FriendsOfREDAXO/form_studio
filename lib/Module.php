<?php

namespace FriendsOfRedaxo\FormStudio;

use rex;
use rex_addon;
use rex_i18n;
use rex_url;

/**
 * Hilfen für das Modul „Form Studio: Formular“: Angaben je Einbindung und Vorschau im Backend.
 *
 * REX_VALUE[1] = Formular-ID, REX_VALUE[2] = eigener Text nach dem Absenden (optional),
 * REX_VALUE[3] = zusätzliche CSS-Klasse(n) für den Rahmen (optional).
 * Leere Angaben ändern nichts – bestehende Einbindungen bleiben wie sie sind.
 */
final class Module
{
    /** @return array{success_text?: string, wrapper_class?: string} */
    public static function options(string $successText = '', string $wrapperClass = ''): array
    {
        $options = [];
        $successText = trim($successText);
        if ('' !== $successText) {
            $options['success_text'] = $successText;
        }
        $classes = array_filter(
            preg_split('/\s+/', trim($wrapperClass)) ?: [],
            static fn (string $c): bool => 1 === preg_match('/^-?[A-Za-z_][A-Za-z0-9_-]*$/', $c),
        );
        if ($classes) {
            $options['wrapper_class'] = implode(' ', array_unique($classes));
        }
        return $options;
    }

    /** Frontend-Ausgabe einer Einbindung; inaktive oder fehlende Formulare geben nichts aus */
    public static function output(int $formId, string $successText = '', string $wrapperClass = ''): string
    {
        $form = Form::get($formId);
        if (rex::isBackend()) {
            return self::preview($form, self::options($successText, $wrapperClass));
        }
        if (!$form) {
            return '';
        }
        $html = Processor::handle($form, self::options($successText, $wrapperClass));
        return '' === $html ? '' : '<section class="fs-section"><div class="fs-container">' . $html . '</div></section>';
    }

    /**
     * Vorschau im Backend: Schrittliste und erster Schritt (nur lesend, ohne Skripte, in einem eigenen Dokument,
     * damit sich Frontend- und Backend-Styles nicht beeinflussen).
     *
     * @param array{success_text?: string, wrapper_class?: string} $options
     */
    public static function preview(?Form $form, array $options = []): string
    {
        if (!$form) {
            return '<div class="alert alert-warning">' . rex_i18n::msg('form_studio_module_choose') . '</div>';
        }
        $steps = $form->steps();
        $user = rex::getUser();
        $edit = $user && $user->hasPerm('form_studio[]')
            ? ' <a class="btn btn-default btn-xs" href="' . rex_url::backendPage('form_studio/forms', ['func' => 'edit', 'id' => $form->id]) . '"><i class="rex-icon fa-pencil" aria-hidden="true"></i> ' . rex_i18n::msg('form_studio_module_edit') . '</a>'
            : '';

        $html = '<div class="fs-b-preview">';
        $html .= '<p class="fs-b-preview__head"><i class="rex-icon fa-wpforms" aria-hidden="true"></i> <strong>' . rex_escape($form->name) . '</strong> '
            . '(' . rex_i18n::msg('form_studio_module_counts', count($steps), count($form->valueFields())) . ')' . $edit . '</p>';
        if (!$form->active) {
            $html .= '<div class="alert alert-warning">' . rex_i18n::msg('form_studio_module_inactive') . '</div>';
        }
        if ($options) {
            $html .= '<ul class="fs-b-preview__options list-unstyled">';
            if (isset($options['success_text'])) {
                $html .= '<li>' . rex_i18n::msg('form_studio_module_success_text') . ': <em>' . rex_escape(mb_strimwidth($options['success_text'], 0, 120, ' …')) . '</em></li>';
            }
            if (isset($options['wrapper_class'])) {
                $html .= '<li>' . rex_i18n::msg('form_studio_module_wrapper_class') . ': <code>' . rex_escape($options['wrapper_class']) . '</code></li>';
            }
            $html .= '</ul>';
        }

        if (count($steps) > 1) {
            $html .= '<ol class="fs-b-preview__steps" style="margin:0 0 10px;padding-left:1.4em" aria-label="' . rex_i18n::msg('form_studio_module_steps') . '">';
            foreach ($steps as $i => $step) {
                $html .= '<li' . (0 === $i ? ' aria-current="step" style="font-weight:700"' : '') . '>' . rex_escape((string) ($step['title'] ?? '') ?: rex_i18n::msg('form_studio_step_default', $i + 1))
                    . (!empty($step['show_if']) ? ' (' . rex_i18n::msg('form_studio_conditional') . ')' : '') . '</li>';
            }
            $html .= '</ol>';
        }

        // Erster Schritt so, wie Besucher ihn sehen (ohne JavaScript: alle Schritte untereinander – hier nur der erste)
        $css = rex_url::addonAssets('form_studio', 'form-studio.css');
        $file = rex_addon::get('form_studio')->getAssetsPath('form-studio.css');
        $css .= is_file($file) ? '?v=' . filemtime($file) : '';
        $doc = '<!doctype html><html lang="' . rex_escape(rex_i18n::getLanguage()) . '"><head><meta charset="utf-8"><link rel="stylesheet" href="' . rex_escape($css) . '">'
            . '<style>body{margin:0;padding:16px;background:#fff;color:#333;font:15px/1.5 system-ui,-apple-system,"Segoe UI",Roboto,sans-serif}'
            . '.fs [data-fs-step]:not([data-fs-step="0"]),.fs .fs-progress,.fs .fs-review,.fs .fs-submit,.fs .fs-required-note,.fs .fs-nav,.fs .fs-hp{display:none}</style>'
            . '</head><body>' . self::withoutScripts(Renderer::render($form, [], [], '', $options)) . '</body></html>';
        $html .= '<iframe class="fs-b-preview__frame" style="display:block;width:100%;height:420px;border:1px solid rgba(128,128,128,.4);border-radius:4px;background:#fff" title="' . rex_escape(rex_i18n::rawMsg('form_studio_module_preview_title', $form->name)) . '" sandbox="allow-same-origin" inert tabindex="-1" loading="lazy"'
            . ' onload="try{this.style.height=Math.min(900,this.contentDocument.body.offsetHeight+2)+\'px\'}catch(e){}"'
            . ' srcdoc="' . rex_escape($doc) . '"></iframe>';
        return $html . '</div>';
    }

    /** Skripte gehören nicht in die Vorschau (sie würde ohnehin nichts ausführen) */
    private static function withoutScripts(string $html): string
    {
        return (string) preg_replace('#<script\b[^>]*>.*?</script>#is', '', $html);
    }
}
