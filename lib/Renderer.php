<?php

namespace FriendsOfRedaxo\FormStudio;

use rex_addon;
use rex_csrf_token;
use rex_media;
use rex_url;

/**
 * Frontend-Ausgabe eines Formulars (UIkit-Markup, progressive enhancement mit assets/form-studio.js).
 * Ohne JavaScript ist es ein vollständiges, einseitiges Formular.
 */
final class Renderer
{
    /**
     * @param array<string, mixed> $values bisherige Eingaben (nach Fehlern)
     * @param array<string, string> $errors Feldname => Meldung
     */
    public static function render(Form $form, array $values = [], array $errors = [], string $formError = ''): string
    {
        $id = 'fs-' . $form->id;
        $steps = $form->steps();
        $multi = count($steps) > 1;
        $visible = $form->visibility($values);

        $html = '<div class="fs" id="' . $id . '">';
        $html .= self::assets();
        $html .= '<form class="fs-form uk-form-stacked" method="post" action="' . rex_escape(self::actionUrl()) . '#' . $id . '" novalidate data-fs-form'
            . ($multi ? ' data-fs-steps="' . count($steps) . '"' : '') . '>';
        $html .= '<input type="hidden" name="form_studio_id" value="' . $form->id . '">';
        $html .= rex_csrf_token::factory('form_studio_' . $form->id)->getHiddenField();
        $html .= '<input type="hidden" name="form_studio_ts" value="' . rex_escape(Processor::timestamp()) . '">';
        // Honeypot (für Menschen unsichtbar, auch für Screenreader ausgeblendet)
        $html .= '<div class="fs-hp" aria-hidden="true"><label>Website<input type="text" name="form_studio_website" tabindex="-1" autocomplete="off"></label></div>';

        if ('' !== $formError || $errors) {
            $html .= '<div class="uk-alert-danger fs-alert" uk-alert role="alert" tabindex="-1" data-fs-error-summary><p><strong>'
                . rex_escape($formError ?: 'Bitte prüfen Sie die markierten Angaben.') . '</strong></p>';
            if ($errors) {
                $html .= '<ul class="uk-list uk-margin-remove">';
                foreach ($errors as $name => $message) {
                    $html .= '<li><a href="#' . $id . '-' . rex_escape($name) . '">' . rex_escape($message) . '</a></li>';
                }
                $html .= '</ul>';
            }
            $html .= '</div>';
        }

        if ($multi) {
            $html .= '<ol class="fs-progress" aria-label="Fortschritt" data-fs-progress>';
            foreach ($steps as $i => $step) {
                $html .= '<li data-fs-progress-item="' . $i . '"' . self::condAttr($step) . '><span class="fs-progress__nr">' . ($i + 1) . '</span><span class="fs-progress__label">'
                    . rex_escape((string) ($step['title'] ?? 'Schritt ' . ($i + 1))) . '</span></li>';
            }
            // Eigener letzter Reiter zur Kontrolle (nur mit JavaScript, ohne JS bleibt es ein einseitiges Formular)
            $html .= '<li data-fs-progress-item="review" hidden><span class="fs-progress__nr" aria-hidden="true">✓</span><span class="fs-progress__label">'
                . rex_escape((string) $form->setting('review_title', 'Überprüfen')) . '</span></li>';
            $html .= '</ol>';
        }

        foreach ($steps as $i => $step) {
            $html .= '<fieldset class="fs-step" data-fs-step="' . $i . '"' . self::condAttr($step) . '>';
            if (!empty($step['title'])) {
                $html .= '<legend class="fs-step__title uk-h3">' . rex_escape((string) $step['title']) . '</legend>';
            }
            if (!empty($step['intro'])) {
                $html .= '<p class="fs-step__intro">' . nl2br(rex_escape((string) $step['intro'])) . '</p>';
            }
            $html .= '<div class="uk-grid-small" uk-grid>';
            foreach ($step['fields'] ?? [] as $field) {
                $html .= self::field($form, $field, $values, $errors, $visible);
            }
            $html .= '</div>';
            if ($multi) {
                $html .= '<div class="fs-nav">'
                    . ($i > 0 ? '<button type="button" class="uk-button uk-button-default" data-fs-prev hidden>Zurück</button>' : '<span></span>')
                    . '<button type="button" class="uk-button uk-button-primary" data-fs-next hidden>Weiter</button>'
                    . '</div>';
            }
            $html .= '</fieldset>';
        }

        if ($multi) {
            $html .= '<section class="fs-review" data-fs-review hidden aria-labelledby="' . $id . '-review">'
                . '<h3 class="fs-step__title uk-h3" id="' . $id . '-review" tabindex="-1">Ihre Angaben im Überblick</h3>'
                . '<p class="fs-step__intro">Bitte prüfen Sie Ihre Angaben. Über „Ändern“ gelangen Sie direkt zum jeweiligen Schritt.</p>'
                . '<div class="fs-review__groups" data-fs-summary-list></div>'
                . '<div class="fs-nav"><button type="button" class="uk-button uk-button-default" data-fs-prev>Zurück</button><span></span></div>'
                . '</section>';
        }
        $html .= '<div class="fs-submit"><button type="submit" class="uk-button uk-button-primary uk-button-large" data-fs-submit>'
            . rex_escape((string) $form->setting('submit_label', 'Anfrage senden')) . '</button></div>';
        $html .= '<p class="fs-required-note uk-text-small"><span aria-hidden="true">*</span> Pflichtangabe</p>';
        $html .= '</form></div>';
        return $html;
    }

    /** Erfolgsmeldung nach dem Absenden, optional mit PDF-Download */
    public static function success(Form $form, string $token): string
    {
        $html = '<div class="fs" id="fs-' . $form->id . '">' . self::assets()
            . '<div class="fs-success uk-card uk-card-default uk-card-body" role="status" tabindex="-1" data-fs-success>'
            . '<h2 class="uk-h3">' . rex_escape((string) $form->setting('success_title', 'Vielen Dank für Ihre Anfrage!')) . '</h2>'
            . '<p>' . nl2br(rex_escape((string) $form->setting('success_text', 'Wir melden uns schnellstmöglich bei Ihnen.'))) . '</p>';
        if ($form->setting('pdf', true) && '' !== $token) {
            // frontendController() liefert die URL bereits HTML-maskiert (&amp;) – nicht erneut escapen
            $url = rex_url::frontendController(['rex-api-call' => 'form_studio_pdf', 'token' => $token]);
            $html .= '<p><a class="uk-button uk-button-default" href="' . $url . '" download>'
                . '<span uk-icon="download" aria-hidden="true"></span> Ihre Anfrage als PDF speichern</a></p>';
        }
        return $html . '</div></div>';
    }

    /**
     * @param array<string, mixed> $field
     * @param array<string, mixed> $values
     * @param array<string, string> $errors
     * @param array<string, bool> $visible
     */
    private static function field(Form $form, array $field, array $values, array $errors, array $visible): string
    {
        $type = (string) ($field['type'] ?? 'text');
        $name = (string) ($field['name'] ?? '');
        $width = preg_match('/^\d-\d$/', (string) ($field['width'] ?? '')) ? $field['width'] : '1-1';
        $hidden = '' !== $name && false === ($visible[$name] ?? true);
        $wrap = '<div class="uk-width-1-1 uk-width-' . $width . '@s fs-field fs-field--' . rex_escape($type) . '" data-fs-field="' . rex_escape($name) . '"'
            . self::condAttr($field) . ($hidden ? ' hidden' : '') . '>';

        if ('heading' === $type) {
            $level = in_array($field['level'] ?? '', ['h3', 'h4', 'h5'], true) ? $field['level'] : 'h4';
            return $wrap . '<' . $level . ' class="fs-heading">' . rex_escape((string) ($field['text'] ?? '')) . '</' . $level . '></div>';
        }
        if ('info' === $type) {
            $style = in_array($field['style'] ?? '', ['primary', 'success', 'warning'], true) ? ' uk-alert-' . $field['style'] : '';
            return $wrap . '<div class="uk-alert' . $style . ' fs-info">' . nl2br(rex_escape((string) ($field['text'] ?? ''))) . '</div></div>';
        }
        if ('hidden' === $type) {
            return '<input type="hidden" name="fs[' . rex_escape($name) . ']" value="' . rex_escape((string) ($values[$name] ?? $field['default'] ?? '')) . '">';
        }

        $id = 'fs-' . $form->id . '-' . $name;
        $required = !empty($field['required']);
        $value = $values[$name] ?? ($field['default'] ?? '');
        $error = $errors[$name] ?? '';
        $describedBy = [];
        $label = rex_escape((string) ($field['label'] ?? $name)) . ($required ? ' <span class="fs-req" aria-hidden="true">*</span>' : '');
        $help = '';
        if (!empty($field['help'])) {
            $help = '<div class="fs-help uk-text-small" id="' . $id . '-help">' . nl2br(rex_escape((string) $field['help'])) . '</div>';
            $describedBy[] = $id . '-help';
        }
        $err = '<div class="fs-error uk-text-danger uk-text-small" id="' . $id . '-error" aria-live="polite">' . rex_escape($error) . '</div>';
        $describedBy[] = $id . '-error';
        $aria = ' aria-describedby="' . implode(' ', $describedBy) . '"' . ($required ? ' aria-required="true"' : '') . ('' !== $error ? ' aria-invalid="true"' : '');
        $inputName = 'fs[' . rex_escape($name) . ']';
        $req = $required ? ' required' : '';

        $control = '';
        switch ($type) {
            case 'textarea':
                $control = '<textarea class="uk-textarea" id="' . $id . '" name="' . $inputName . '" rows="' . max(2, (int) ($field['rows'] ?? 4)) . '"'
                    . self::attr('placeholder', $field['placeholder'] ?? null) . self::attr('maxlength', $field['maxlength'] ?? null) . $req . $aria . '>'
                    . rex_escape((string) $value) . '</textarea>';
                break;
            case 'select':
                $control = '<select class="uk-select" id="' . $id . '" name="' . $inputName . '"' . $req . $aria . '>'
                    . '<option value="">' . rex_escape((string) ($field['placeholder'] ?? 'Bitte wählen …')) . '</option>';
                foreach (self::options($field) as $opt) {
                    $control .= '<option value="' . rex_escape($opt['value']) . '"' . ((string) $value === $opt['value'] ? ' selected' : '') . '>' . rex_escape($opt['label']) . '</option>';
                }
                $control .= '</select>';
                break;
            case 'radio':
            case 'checkboxes':
                $multiple = 'checkboxes' === $type;
                $selected = array_map('strval', (array) $value);
                $control = '<div class="fs-options' . (!empty($field['inline']) ? ' fs-options--inline' : '') . '" role="' . ($multiple ? 'group' : 'radiogroup') . '" aria-labelledby="' . $id . '-label"' . $aria . '>';
                foreach (self::options($field) as $k => $opt) {
                    $control .= '<label class="fs-option"><input class="uk-' . ($multiple ? 'checkbox' : 'radio') . '" type="' . ($multiple ? 'checkbox' : 'radio') . '" name="' . $inputName . ($multiple ? '[]' : '') . '" value="' . rex_escape($opt['value']) . '"'
                        . (in_array($opt['value'], $selected, true) ? ' checked' : '') . ($required && !$multiple ? ' required' : '') . ($k === 0 ? ' id="' . $id . '"' : '') . '> ' . rex_escape($opt['label']) . '</label>';
                }
                $control .= '</div>';
                return $wrap . '<div class="uk-form-label" id="' . $id . '-label">' . $label . '</div>' . $help . $control . $err . '</div>';
            case 'cards':
                $multiple = !empty($field['multiple']);
                $selected = array_map('strval', (array) $value);
                $cols = max(2, min(5, (int) ($field['columns'] ?? 3)));
                $control = '<div class="fs-cards uk-child-width-1-2 uk-child-width-1-' . $cols . '@m uk-grid-small" uk-grid role="' . ($multiple ? 'group' : 'radiogroup') . '" aria-labelledby="' . $id . '-label"' . $aria . '>';
                foreach (self::options($field) as $k => $opt) {
                    $control .= '<div><label class="fs-card"><input class="fs-card__input" type="' . ($multiple ? 'checkbox' : 'radio') . '" name="' . $inputName . ($multiple ? '[]' : '') . '" value="' . rex_escape($opt['value']) . '"'
                        . (in_array($opt['value'], $selected, true) ? ' checked' : '') . ($required && !$multiple ? ' required' : '') . ($k === 0 ? ' id="' . $id . '"' : '') . '>'
                        . '<span class="fs-card__body">' . self::optionImage($opt) . '<span class="fs-card__label">' . rex_escape($opt['label']) . '</span>'
                        . ('' !== $opt['description'] ? '<span class="fs-card__desc">' . rex_escape($opt['description']) . '</span>' : '') . '</span></label></div>';
                }
                $control .= '</div>';
                return $wrap . '<div class="uk-form-label" id="' . $id . '-label">' . $label . '</div>' . $help . $control . $err . '</div>';
            case 'consent':
                $control = '<label class="fs-consent"><input class="uk-checkbox" type="checkbox" id="' . $id . '" name="' . $inputName . '" value="1"' . (!empty($value) ? ' checked' : '') . $req . $aria . '> '
                    . self::inlineLinks((string) ($field['text'] ?? $field['label'] ?? '')) . ($required ? ' <span class="fs-req" aria-hidden="true">*</span>' : '') . '</label>';
                return $wrap . $control . $err . '</div>';
            case 'counter':
                $min = (int) ($field['min'] ?? 0);
                $max = isset($field['max']) && '' !== $field['max'] ? (int) $field['max'] : null;
                $control = '<div class="fs-counter" data-fs-counter>'
                    . '<button type="button" class="uk-button uk-button-default fs-counter__btn" data-fs-dec aria-label="weniger" aria-controls="' . $id . '">−</button>'
                    . '<input class="uk-input fs-counter__input" type="number" inputmode="numeric" id="' . $id . '" name="' . $inputName . '" value="' . rex_escape((string) $value) . '"'
                    . ' min="' . $min . '"' . (null !== $max ? ' max="' . $max . '"' : '') . ' step="' . max(1, (int) ($field['step'] ?? 1)) . '"' . $req . $aria . '>'
                    . '<button type="button" class="uk-button uk-button-default fs-counter__btn" data-fs-inc aria-label="mehr" aria-controls="' . $id . '">+</button>'
                    . (!empty($field['unit']) ? '<span class="fs-counter__unit">' . rex_escape((string) $field['unit']) . '</span>' : '')
                    . '</div>';
                break;
            default:
                $inputType = in_array($type, ['email', 'tel', 'number', 'date', 'time'], true) ? $type : 'text';
                $min = $field['min'] ?? null;
                if ('date' === $type && !empty($field['min_days_ahead'])) {
                    $min = date('Y-m-d', strtotime('+' . (int) $field['min_days_ahead'] . ' days'));
                }
                $auto = $field['autocomplete'] ?? ['email' => 'email', 'tel' => 'tel'][$type] ?? null;
                $control = '<input class="uk-input" type="' . $inputType . '" id="' . $id . '" name="' . $inputName . '" value="' . rex_escape((string) $value) . '"'
                    . self::attr('placeholder', $field['placeholder'] ?? null) . self::attr('min', $min) . self::attr('max', $field['max'] ?? null)
                    . self::attr('step', $field['step'] ?? null) . self::attr('maxlength', $field['maxlength'] ?? null) . self::attr('autocomplete', $auto)
                    . $req . $aria . '>';
                if (!empty($field['unit'])) {
                    $control = '<div class="fs-unit">' . $control . '<span class="fs-unit__label">' . rex_escape((string) $field['unit']) . '</span></div>';
                }
        }
        return $wrap . '<label class="uk-form-label" for="' . $id . '">' . $label . '</label>' . $help . '<div class="uk-form-controls">' . $control . '</div>' . $err . '</div>';
    }

    /** @return list<array{value: string, label: string, image: string, description: string}> */
    public static function options(array $field): array
    {
        $out = [];
        foreach ((array) ($field['options'] ?? []) as $opt) {
            if (is_string($opt)) {
                [$v, $l] = array_pad(array_map('trim', explode('|', $opt, 2)), 2, '');
                $opt = ['value' => $v, 'label' => '' !== $l ? $l : $v];
            }
            $value = trim((string) ($opt['value'] ?? ''));
            if ('' === $value) {
                continue;
            }
            $out[] = ['value' => $value, 'label' => (string) ($opt['label'] ?? $value), 'image' => (string) ($opt['image'] ?? ''), 'description' => (string) ($opt['description'] ?? '')];
        }
        return $out;
    }

    private static function optionImage(array $opt): string
    {
        $image = $opt['image'];
        if ('' === $image) {
            return '';
        }
        // Mitgelieferte Grafik (assets/seating/…) oder Datei aus dem Medienpool
        if (str_starts_with($image, 'fs:')) {
            $file = 'seating/' . basename(substr($image, 3)) . '.svg';
            $addon = rex_addon::get('form_studio');
            $src = $addon->getAssetsUrl($file) . '?v=' . @filemtime($addon->getAssetsPath($file));
        } elseif (rex_media::get($image)) {
            $src = class_exists(\WellingsImage::class) ? \WellingsImage::url($image, '4_3', 480) : rex_url::media($image);
        } else {
            return '';
        }
        return '<span class="fs-card__media"><img src="' . rex_escape($src) . '" alt="" loading="lazy" width="320" height="240"></span>';
    }

    /** Markdown-artige Links [Text](URL) in Einwilligungstexten erlauben, sonst alles maskiert */
    private static function inlineLinks(string $text): string
    {
        $escaped = rex_escape($text);
        return preg_replace_callback('/\[([^\]]+)\]\((https?:\/\/[^\s)]+|\/[^\s)]*)\)/', static fn ($m) => '<a href="' . $m[2] . '" target="_blank" rel="noopener">' . $m[1] . '</a>', $escaped);
    }

    private static function condAttr(array $item): string
    {
        $cond = $item['show_if'] ?? null;
        if (!is_array($cond) || empty($cond['rules'])) {
            return '';
        }
        return ' data-fs-show-if="' . rex_escape(json_encode($cond, JSON_UNESCAPED_UNICODE)) . '"';
    }

    private static function attr(string $name, mixed $value): string
    {
        return null === $value || '' === $value ? '' : ' ' . $name . '="' . rex_escape((string) $value) . '"';
    }

    private static function actionUrl(): string
    {
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        return preg_replace('/([?&])fs_done=[^&#]*&?/', '$1', $uri);
    }

    private static bool $assetsDone = false;

    private static function assets(): string
    {
        if (self::$assetsDone) {
            return '';
        }
        self::$assetsDone = true;
        $addon = rex_addon::get('form_studio');
        $v = static fn (string $f) => $addon->getAssetsUrl($f) . '?v=' . @filemtime($addon->getAssetsPath($f));
        return '<link rel="stylesheet" href="' . $v('form-studio.css') . '"><script src="' . $v('form-studio.js') . '" defer></script>';
    }
}
