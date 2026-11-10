<?php

namespace FriendsOfRedaxo\FormStudio;

use rex;
use rex_addon;
use rex_csrf_token;
use rex_extension;
use rex_extension_point;
use rex_logger;
use rex_request;
use rex_response;
use rex_sql;
use Throwable;

/**
 * Verarbeitet eine Einsendung: Spamschutz, Werte bereinigen, Bedingungen anwenden, validieren,
 * speichern (eigene Tabelle für den PDF-Download, optional YForm) und E-Mails versenden.
 */
final class Processor
{
    /**
     * Ausgabe für die Modulausgabe: Formular, Fehler oder Erfolgsmeldung.
     *
     * @param array{success_text?: string, wrapper_class?: string} $options Angaben der jeweiligen Einbindung (Modul)
     */
    public static function handle(Form $form, array $options = []): string
    {
        // Inaktive Formulare erscheinen im Frontend nicht und nehmen nichts an
        if (!$form->active) {
            return '';
        }

        $done = rex_request::get('fs_done', 'string', '');
        if ('' !== $done && ($submission = Submission::getByToken($done)) && $submission->formId === $form->id) {
            return Renderer::success($form, $done, $options);
        }

        if ('post' !== rex_request::requestMethod() || $form->id !== rex_request::post('form_studio_id', 'int', 0)) {
            return Renderer::render($form, [], [], '', $options);
        }

        $raw = rex_request::post('fs', 'array', []);
        $values = self::sanitize($form, $raw);

        if (!rex_csrf_token::factory('form_studio_' . $form->id)->isValid()) {
            return Renderer::render($form, $values, [], 'Die Sitzung ist abgelaufen. Bitte senden Sie das Formular erneut ab.', $options);
        }
        $spam = SpamGuard::check(
            $form,
            $values,
            rex_request::post('form_studio_ts', 'string', ''),
            rex_request::post('form_studio_website', 'string', ''),
            rex_request::post('form_studio_js', 'string', ''),
        );
        switch ($spam['status']) {
            case SpamGuard::EXPIRED:
                return Renderer::render($form, $values, [], 'Das Formular war sehr lange geöffnet. Bitte prüfen Sie Ihre Angaben und senden Sie es erneut ab.', $options);
            case SpamGuard::RATE_LIMIT:
                return Renderer::render($form, $values, [], 'Sie haben in kurzer Zeit bereits mehrere Anfragen gesendet. Bitte versuchen Sie es später erneut oder rufen Sie uns an.', $options);
            case SpamGuard::SPAM:
            case SpamGuard::DUPLICATE:
                // Spam und doppelte Klicks: wie ein Erfolg aussehen lassen, aber nichts erneut versenden
                return Renderer::success($form, '', $options);
        }

        // Erst nach der Spamprüfung validieren – Bots bekommen keine Rückmeldung zu Feldern
        $visible = $form->visibility($values);
        $errors = self::validate($form, $values, $visible);
        if ($errors) {
            return Renderer::render($form, $values, $errors, '', $options);
        }

        // Nur sichtbare Felder übernehmen
        $data = [];
        foreach ($form->valueFields() as $field) {
            $name = (string) $field['name'];
            if (!($visible[$name] ?? true)) {
                continue;
            }
            $item = ['name' => $name, 'label' => (string) ($field['label'] ?? $name), 'value' => $values[$name] ?? '', 'display' => self::display($field, $values[$name] ?? '')];
            if (FieldTypes::isSensitive($field)) {
                // für die anfragende Person (PDF-Download, Kopie): nur erkennbar, nicht verwertbar
                $item['display_public'] = self::displayPublic($field, $values[$name] ?? '');
            }
            $data[] = $item;
        }
        $data = rex_extension::registerPoint(new rex_extension_point('FORM_STUDIO_DATA', $data, ['form' => $form]));

        // Zwischenspeicher für den PDF-Download enthält vertrauliche Felder nur maskiert
        $submission = Submission::create($form, $data);
        SpamGuard::accepted($form, $values);
        try {
            if ($form->setting('store_yform', false)) {
                YFormSync::store($form, $data);
            }
            Mailer::send($form, $submission, $data);
        } catch (Throwable $e) {
            rex_logger::logException($e);
        }
        rex_extension::registerPoint(new rex_extension_point('FORM_STUDIO_SUBMITTED', null, ['form' => $form, 'submission' => $submission]));

        // Post/Redirect/Get: Neuladen sendet nicht erneut
        $url = strtok((string) ($_SERVER['REQUEST_URI'] ?? '/'), '#');
        $url .= (str_contains($url, '?') ? '&' : '?') . 'fs_done=' . $submission->token . '#fs-' . $form->id;
        rex_response::sendRedirect($url);
    }

    /** Signierter Zeitstempel für die Mindestausfüllzeit (siehe SpamGuard) */
    public static function timestamp(): string
    {
        return SpamGuard::timestamp();
    }

    public static function secret(): string
    {
        $addon = rex_addon::get('form_studio');
        $secret = (string) $addon->getConfig('secret', '');
        if ('' === $secret) {
            $secret = bin2hex(random_bytes(32));
            $addon->setConfig('secret', $secret);
        }
        return $secret;
    }

    /**
     * Rohwerte auf die Definition zuschneiden (unbekannte Felder und Optionen verwerfen).
     *
     * @param array<string, mixed> $raw
     * @return array<string, mixed>
     */
    public static function sanitize(Form $form, array $raw): array
    {
        $values = [];
        foreach ($form->valueFields() as $field) {
            $name = (string) $field['name'];
            $value = $raw[$name] ?? null;
            $type = (string) ($field['type'] ?? 'text');
            if (Renderer::options($field) && in_array($type, ['select', 'radio', 'checkboxes', 'cards'], true)) {
                $allowed = array_column(Renderer::options($field), 'value');
                if (FieldTypes::isMultiple($field)) {
                    $values[$name] = array_values(array_intersect($allowed, array_map('strval', (array) $value)));
                } else {
                    $values[$name] = in_array((string) $value, $allowed, true) ? (string) $value : '';
                }
                continue;
            }
            if ('consent' === $type || 'switch' === $type) {
                $values[$name] = '1' === (string) $value ? '1' : '';
                continue;
            }
            if ('iban' === $type) {
                $values[$name] = is_scalar($value) ? mb_substr(Iban::normalize((string) $value), 0, 34) : '';
                continue;
            }
            if ('address' === $type) {
                $a = is_array($value) ? $value : [];
                $clean = static fn (string $k, int $len): string => is_scalar($a[$k] ?? null) ? trim(mb_substr((string) $a[$k], 0, $len)) : '';
                $country = strtoupper($clean('country', 2));
                $values[$name] = ['street' => $clean('street', 150), 'zip' => $clean('zip', 12), 'city' => $clean('city', 100), 'country' => isset(Address::COUNTRIES[$country]) ? $country : ''];
                continue;
            }
            if ('repeater' === $type) {
                $subs = Renderer::subfields($field);
                $max = max(1, (int) ($field['max_rows'] ?? 10));
                $rows = [];
                foreach (is_array($value) ? $value : [] as $row) {
                    if (!is_array($row) || count($rows) >= $max) {
                        continue;
                    }
                    $clean = [];
                    foreach ($subs as $sub) {
                        $v = $row[$sub['name']] ?? '';
                        $v = is_scalar($v) ? trim(mb_substr((string) $v, 0, 'textarea' === ($sub['type'] ?? '') ? 2000 : 300)) : '';
                        if ('select' === ($sub['type'] ?? '') && '' !== $v && !in_array($v, array_column(Renderer::options($sub), 'value'), true)) {
                            $v = '';
                        }
                        $clean[$sub['name']] = $v;
                    }
                    // komplett leere Zeilen verwerfen
                    if ('' !== implode('', $clean)) {
                        $rows[] = $clean;
                    }
                }
                $values[$name] = $rows;
                continue;
            }
            $values[$name] = is_scalar($value) ? trim(mb_substr((string) $value, 0, 'textarea' === $type ? 10000 : 500)) : '';
        }
        return $values;
    }

    /**
     * @param array<string, mixed> $values
     * @param array<string, bool> $visible
     * @return array<string, string> Feldname => Meldung
     */
    public static function validate(Form $form, array $values, array $visible): array
    {
        $errors = [];
        foreach ($form->valueFields() as $field) {
            $name = (string) $field['name'];
            if (!($visible[$name] ?? true)) {
                continue;
            }
            $label = (string) ($field['label'] ?? $name);
            $value = $values[$name] ?? '';
            $type = (string) ($field['type'] ?? 'text');
            $empty = 'address' === $type ? '' === implode('', array_intersect_key((array) $value, ['street' => 1, 'zip' => 1, 'city' => 1]))
                : (is_array($value) ? [] === $value : '' === (string) $value);
            if ('repeater' === $type) {
                $min = max(0, (int) ($field['min_rows'] ?? 1));
                $rowLabel = (string) ($field['row_label'] ?? '') ?: 'Eintrag';
                if ((!empty($field['required']) || $min > 0) && count((array) $value) < max(!empty($field['required']) ? 1 : 0, $min)) {
                    $errors[$name] = 'Bitte geben Sie mindestens ' . max(1, $min) . ' ' . ($min > 1 ? 'Einträge' : 'Eintrag') . ' bei „' . $label . '“ an.';
                    continue;
                }
                foreach ((array) $value as $i => $row) {
                    foreach (Renderer::subfields($field) as $sub) {
                        $v = (string) ($row[$sub['name']] ?? '');
                        $subLabel = (string) ($sub['label'] ?? $sub['name']);
                        $where = $rowLabel . ' ' . ($i + 1) . ': ';
                        if (!empty($sub['required']) && '' === $v) {
                            $errors[$name] = $where . 'Bitte füllen Sie „' . $subLabel . '“ aus.';
                            break 2;
                        }
                        if ('' !== $v && 'email' === ($sub['type'] ?? '') && !filter_var($v, FILTER_VALIDATE_EMAIL)) {
                            $errors[$name] = $where . 'Bitte geben Sie eine gültige E-Mail-Adresse an.';
                            break 2;
                        }
                        if ('' !== $v && 'number' === ($sub['type'] ?? '') && !is_numeric($v)) {
                            $errors[$name] = $where . '„' . $subLabel . '“ muss eine Zahl sein.';
                            break 2;
                        }
                        if ('' !== $v && 'date' === ($sub['type'] ?? '') && !\DateTimeImmutable::createFromFormat('!Y-m-d', $v)) {
                            $errors[$name] = $where . 'Bitte geben Sie ein gültiges Datum an.';
                            break 2;
                        }
                    }
                }
                continue;
            }

            if (!empty($field['required']) && $empty) {
                $errors[$name] = match ($type) {
                    'consent' => 'Bitte bestätigen Sie: ' . $label,
                    'switch' => \rex_i18n::msg('form_studio_switch_required', $label),
                    default => 'Bitte füllen Sie „' . $label . '“ aus.',
                };
                continue;
            }
            if ($empty) {
                continue;
            }
            if ('iban' === $type && '' !== ($ibanError = Iban::check((string) $value))) {
                $errors[$name] = $ibanError;
            } elseif ('address' === $type) {
                $a = (array) $value;
                if ('' === (string) ($a['street'] ?? '') || '' === (string) ($a['zip'] ?? '') || '' === (string) ($a['city'] ?? '')) {
                    $errors[$name] = 'Bitte geben Sie die vollständige Adresse an (Straße und Hausnummer, PLZ, Ort).';
                } elseif ('' !== ($zipError = Address::checkZip((string) $a['zip'], (string) ($a['country'] ?: 'DE')))) {
                    $errors[$name] = $zipError;
                }
            } elseif ('email' === $type && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $errors[$name] = 'Bitte geben Sie eine gültige E-Mail-Adresse an.';
            } elseif (in_array($type, ['number', 'counter'], true)) {
                if (!is_numeric($value)) {
                    $errors[$name] = '„' . $label . '“ muss eine Zahl sein.';
                } elseif (isset($field['min']) && '' !== $field['min'] && (float) $value < (float) $field['min']) {
                    $errors[$name] = '„' . $label . '“ muss mindestens ' . $field['min'] . ' sein.';
                } elseif (isset($field['max']) && '' !== $field['max'] && (float) $value > (float) $field['max']) {
                    $errors[$name] = '„' . $label . '“ darf höchstens ' . $field['max'] . ' sein.';
                }
            } elseif ('date' === $type) {
                $date = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) $value);
                if (!$date) {
                    $errors[$name] = 'Bitte geben Sie ein gültiges Datum an.';
                } elseif (!empty($field['min_days_ahead']) && $date < new \DateTimeImmutable('today +' . (int) $field['min_days_ahead'] . ' days')) {
                    $errors[$name] = 'Bitte wählen Sie ein Datum frühestens in ' . (int) $field['min_days_ahead'] . ' Tagen.';
                }
            } elseif ('tel' === $type && !preg_match('/^[\d\s+\/()\-.]{5,}$/', (string) $value)) {
                $errors[$name] = 'Bitte geben Sie eine gültige Telefonnummer an.';
            }
        }
        return $errors;
    }

    /** Maskierte Darstellung vertraulicher Felder (anfragende Person) */
    public static function displayPublic(array $field, mixed $value): string
    {
        if ('iban' === ($field['type'] ?? '') && '' !== (string) $value) {
            $bank = false !== ($field['bank_lookup'] ?? true) ? Iban::bank((string) $value) : null;
            return Iban::mask((string) $value) . ($bank ? ' (' . $bank['name'] . ')' : '');
        }
        return '' === (string) $value ? '' : '••••';
    }

    /** Lesbare Darstellung (Optionstexte statt Werte, Datum deutsch) */
    public static function display(array $field, mixed $value): string
    {
        $type = (string) ($field['type'] ?? 'text');
        if ('iban' === $type) {
            if ('' === (string) $value) {
                return '';
            }
            $bank = false !== ($field['bank_lookup'] ?? true) ? Iban::bank((string) $value) : null;
            return Iban::format((string) $value) . ($bank ? ' (' . $bank['name'] . ('' !== $bank['bic'] ? ', BIC ' . $bank['bic'] : '') . ')' : '');
        }
        if ('address' === $type) {
            return Address::format((array) $value, !empty($field['with_country']));
        }
        if ('repeater' === $type) {
            $lines = [];
            foreach ((array) $value as $i => $row) {
                $parts = [];
                foreach (Renderer::subfields($field) as $sub) {
                    $v = (string) ($row[$sub['name']] ?? '');
                    if ('' === $v) {
                        continue;
                    }
                    if ('select' === ($sub['type'] ?? '')) {
                        $v = array_column(Renderer::options($sub), 'label', 'value')[$v] ?? $v;
                    } elseif ('date' === ($sub['type'] ?? '') && ($d = \DateTimeImmutable::createFromFormat('!Y-m-d', $v))) {
                        $v = $d->format('d.m.Y');
                    }
                    $parts[] = ($sub['label'] ?? $sub['name']) . ': ' . $v;
                }
                $lines[] = ($i + 1) . '. ' . implode(' · ', $parts);
            }
            return implode("\n", $lines);
        }
        if (is_array($value) || Renderer::options($field)) {
            $labels = array_column(Renderer::options($field), 'label', 'value');
            return implode(', ', array_map(static fn ($v) => $labels[(string) $v] ?? (string) $v, (array) $value));
        }
        if ('consent' === $type) {
            return '1' === (string) $value ? 'ja' : 'nein';
        }
        if ('switch' === $type) {
            $on = trim((string) ($field['on_text'] ?? ''));
            $off = trim((string) ($field['off_text'] ?? ''));
            return '1' === (string) $value
                ? ('' !== $on ? $on : \rex_i18n::msg('form_studio_switch_on'))
                : ('' !== $off ? $off : \rex_i18n::msg('form_studio_switch_off'));
        }
        if ('date' === $type && ($d = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) $value))) {
            return $d->format('d.m.Y');
        }
        if (in_array($type, ['number', 'counter'], true) && '' !== (string) $value && !empty($field['unit'])) {
            return $value . ' ' . $field['unit'];
        }
        return (string) $value;
    }

    /** Alte Einsendungen (nur für PDF-Download vorgehalten) löschen */
    public static function cleanup(): void
    {
        $days = max(1, (int) rex_addon::get('form_studio')->getConfig('retention_days', 30));
        rex_sql::factory()->setQuery('DELETE FROM ' . rex::getTable('form_studio_submission') . ' WHERE createdate < ?', [date('Y-m-d H:i:s', strtotime('-' . $days . ' days'))]);
        SpamGuard::cleanup($days);
    }
}
