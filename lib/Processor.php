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
    private const MIN_SECONDS = 3;

    /**
     * Ausgabe für die Modulausgabe: Formular, Fehler oder Erfolgsmeldung.
     */
    public static function handle(Form $form): string
    {
        $done = rex_request::get('fs_done', 'string', '');
        if ('' !== $done && ($submission = Submission::getByToken($done)) && $submission->formId === $form->id) {
            return Renderer::success($form, $done);
        }

        if ('post' !== rex_request::requestMethod() || $form->id !== rex_request::post('form_studio_id', 'int', 0)) {
            return Renderer::render($form);
        }

        $raw = rex_request::post('fs', 'array', []);
        $values = self::sanitize($form, $raw);

        if (!rex_csrf_token::factory('form_studio_' . $form->id)->isValid()) {
            return Renderer::render($form, $values, [], 'Die Sitzung ist abgelaufen. Bitte senden Sie das Formular erneut ab.');
        }
        if ('' !== rex_request::post('form_studio_website', 'string', '') || !self::timeOk(rex_request::post('form_studio_ts', 'string', ''))) {
            // Spam: wie ein Erfolg aussehen lassen, aber nichts tun
            return Renderer::success($form, '');
        }

        $visible = $form->visibility($values);
        $errors = self::validate($form, $values, $visible);
        if ($errors) {
            return Renderer::render($form, $values, $errors);
        }

        // Nur sichtbare Felder übernehmen
        $data = [];
        foreach ($form->valueFields() as $field) {
            $name = (string) $field['name'];
            if (!($visible[$name] ?? true)) {
                continue;
            }
            $data[] = ['name' => $name, 'label' => (string) ($field['label'] ?? $name), 'value' => $values[$name] ?? '', 'display' => self::display($field, $values[$name] ?? '')];
        }
        $data = rex_extension::registerPoint(new rex_extension_point('FORM_STUDIO_DATA', $data, ['form' => $form]));

        $submission = Submission::create($form, $data);
        try {
            if ($form->setting('store_yform', false)) {
                YFormSync::store($form, $data);
            }
            Mailer::send($form, $submission);
        } catch (Throwable $e) {
            rex_logger::logException($e);
        }
        rex_extension::registerPoint(new rex_extension_point('FORM_STUDIO_SUBMITTED', null, ['form' => $form, 'submission' => $submission]));

        // Post/Redirect/Get: Neuladen sendet nicht erneut
        $url = strtok((string) ($_SERVER['REQUEST_URI'] ?? '/'), '#');
        $url .= (str_contains($url, '?') ? '&' : '?') . 'fs_done=' . $submission->token . '#fs-' . $form->id;
        rex_response::sendRedirect($url);
        exit;
    }

    /** Signierter Zeitstempel für die Mindestausfüllzeit */
    public static function timestamp(): string
    {
        $ts = (string) time();
        return $ts . '.' . substr(hash_hmac('sha256', $ts, self::secret()), 0, 16);
    }

    private static function timeOk(string $value): bool
    {
        [$ts, $sig] = array_pad(explode('.', $value, 2), 2, '');
        return ctype_digit($ts) && hash_equals(substr(hash_hmac('sha256', $ts, self::secret()), 0, 16), $sig)
            && time() - (int) $ts >= self::MIN_SECONDS && time() - (int) $ts < 86400;
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
            if ('consent' === $type) {
                $values[$name] = '1' === (string) $value ? '1' : '';
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
            $empty = is_array($value) ? [] === $value : '' === (string) $value;
            $type = (string) ($field['type'] ?? 'text');

            if (!empty($field['required']) && $empty) {
                $errors[$name] = 'consent' === $type ? 'Bitte bestätigen Sie: ' . $label : 'Bitte füllen Sie „' . $label . '“ aus.';
                continue;
            }
            if ($empty) {
                continue;
            }
            if ('email' === $type && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
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

    /** Lesbare Darstellung (Optionstexte statt Werte, Datum deutsch) */
    public static function display(array $field, mixed $value): string
    {
        $type = (string) ($field['type'] ?? 'text');
        if (is_array($value) || Renderer::options($field)) {
            $labels = array_column(Renderer::options($field), 'label', 'value');
            return implode(', ', array_map(static fn ($v) => $labels[(string) $v] ?? (string) $v, (array) $value));
        }
        if ('consent' === $type) {
            return '1' === (string) $value ? 'ja' : 'nein';
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
    }
}
