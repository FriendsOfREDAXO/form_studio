<?php

namespace FriendsOfRedaxo\FormStudio;

use rex_i18n;
use rex_sql;
use rex_yform_manager_field;
use rex_yform_manager_table;

/**
 * Übernimmt eine YForm-Tabelle als neues Formular (Konvention wie formslider):
 * Jedes YForm-Feld vom Typ „fieldset“ beginnt einen neuen Schritt, die Werte-Felder
 * werden auf Form-Studio-Feldtypen abgebildet, die Validierung „empty“ wird zu „Pflichtfeld“.
 *
 * Gelesen wird direkt aus der Felddefinition (rex_yform_field), damit auch ältere
 * Feldtypen (select, radio aus YForm 3) erkannt werden. Die Tabelle selbst bleibt unverändert.
 */
final class YFormImport
{
    /** YForm-Werttyp => Form-Studio-Feldtyp (choice wird gesondert bestimmt) */
    private const MAP = [
        'text' => 'text',
        'textarea' => 'textarea',
        'email' => 'email',
        'select' => 'select',
        'radio' => 'radio',
        'checkbox' => 'switch',
        'date' => 'date',
        'time' => 'time',
        'number' => 'number',
        'integer' => 'number',
        'hidden' => 'hidden',
        'html' => 'info',
    ];

    /** Typen, die bewusst nichts übernehmen (Technik ohne Eingabe) – werden nicht als Problem gemeldet */
    private const SILENT = ['submit', 'csrf', 'spam_protection', 'objparams', 'index', 'prio', 'uuid', 'generate_key', 'datestamp', 'ip', 'resetbutton'];

    /** @return array<string, string> Tabellenname => Anzeigename */
    public static function tables(): array
    {
        $out = [];
        if (!class_exists(rex_yform_manager_table::class)) {
            return $out;
        }
        foreach (rex_yform_manager_table::getAll() as $table) {
            $out[$table->getTableName()] = $table->getNameLocalized() . ' (' . $table->getTableName() . ')';
        }
        asort($out, SORT_NATURAL | SORT_FLAG_CASE);
        return $out;
    }

    /**
     * @return array{name: string, description: string, definition: array{steps: list<array<string, mixed>>}, settings: array<string, mixed>, skipped: list<string>}
     */
    public static function fromTable(string $tableName): array
    {
        $table = rex_yform_manager_table::get($tableName);
        if (!$table) {
            throw new \InvalidArgumentException(rex_i18n::msg('form_studio_import_table_missing', $tableName));
        }
        $rows = rex_sql::factory()->getArray(
            'SELECT * FROM ' . rex_yform_manager_field::table() . ' WHERE table_name = ? ORDER BY prio, id',
            [$tableName],
        );

        // Pflichtfelder und E-Mail-Prüfungen aus den Validierungen
        $required = [];
        $emailCheck = [];
        foreach ($rows as $row) {
            if ('validate' !== ($row['type_id'] ?? '')) {
                continue;
            }
            $names = array_filter(array_map('trim', explode(',', (string) ($row['name'] ?? ''))));
            if ('empty' === $row['type_name']) {
                foreach ($names as $n) {
                    $required[$n] = true;
                }
            } elseif ('email' === $row['type_name']) {
                foreach ($names as $n) {
                    $emailCheck[$n] = true;
                }
            }
        }

        $steps = [];
        $current = null;
        $skipped = [];
        $used = [];
        foreach ($rows as $row) {
            if ('value' !== ($row['type_id'] ?? '')) {
                continue;
            }
            $type = (string) $row['type_name'];
            $name = (string) $row['name'];
            $label = self::label($row);

            if ('fieldset' === $type) {
                if (null !== $current) {
                    $steps[] = $current;
                }
                $current = ['title' => '' !== $label ? $label : self::humanize($name), 'fields' => []];
                continue;
            }
            if (in_array($type, self::SILENT, true)) {
                continue;
            }

            $field = self::field($row, $type, $name, $label, $required, $emailCheck);
            if (null === $field) {
                $skipped[] = ('' !== $label ? $label . ' ' : '') . '(' . $name . ', ' . $type . ')';
                continue;
            }
            if (isset($field['name'])) {
                if (isset($used[$field['name']])) {
                    $skipped[] = $label . ' (' . $name . ', ' . rex_i18n::msg('form_studio_import_duplicate') . ')';
                    continue;
                }
                $used[$field['name']] = true;
            }
            $current ??= ['title' => rex_i18n::msg('form_studio_step_default', 1), 'fields' => []];
            $current['fields'][] = $field;
        }
        if (null !== $current) {
            $steps[] = $current;
        }
        if (!$steps) {
            $steps[] = ['title' => rex_i18n::msg('form_studio_step_default', 1), 'fields' => []];
        }

        return [
            'name' => $table->getNameLocalized(),
            'description' => rex_i18n::msg('form_studio_import_description', $tableName),
            'definition' => ['steps' => $steps],
            // Tabelle vormerken; das Speichern darin schaltet die Redaktion bewusst selbst ein
            'settings' => ['yform_table' => $tableName, 'store_yform' => false],
            'skipped' => $skipped,
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @param array<string, true> $required
     * @param array<string, true> $emailCheck
     * @return array<string, mixed>|null
     */
    private static function field(array $row, string $type, string $name, string $label, array $required, array $emailCheck): ?array
    {
        if ('html' === $type) {
            $text = trim(html_entity_decode(strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>'], "\n", (string) ($row['html'] ?? ''))), ENT_QUOTES | ENT_HTML5));
            return '' === $text ? null : ['type' => 'info', 'text' => $text];
        }
        if ('choice' === $type) {
            $expanded = '1' === (string) ($row['expanded'] ?? '');
            $multiple = '1' === (string) ($row['multiple'] ?? '');
            $mapped = $multiple ? 'checkboxes' : ($expanded ? 'radio' : 'select');
        } else {
            $mapped = self::MAP[$type] ?? null;
        }
        if (null === $mapped || !preg_match('/^[a-z0-9_]+$/', $name)) {
            return null;
        }
        if ('text' === $mapped && isset($emailCheck[$name])) {
            $mapped = 'email';
        }

        $field = ['type' => $mapped, 'name' => $name];
        if ('hidden' !== $mapped) {
            $field['label'] = '' !== $label ? $label : self::humanize($name);
        }
        if (isset($required[$name]) && 'hidden' !== $mapped) {
            $field['required'] = true;
        }
        $notice = trim((string) ($row['notice'] ?? ''));
        if ('' !== $notice && 'hidden' !== $mapped) {
            $field['help'] = rex_i18n::translate($notice, false);
        }

        if (in_array($mapped, ['select', 'radio', 'checkboxes'], true)) {
            $field['options'] = self::choices(trim((string) ($row['choices'] ?? '')) ?: (string) ($row['options'] ?? ''));
            if (!$field['options']) {
                return null;
            }
            $placeholder = trim((string) ($row['placeholder'] ?? ''));
            if ('select' === $mapped && '' !== $placeholder) {
                $field['placeholder'] = rex_i18n::translate($placeholder, false);
            }
        }

        $default = (string) ($row['default'] ?? ($row['value'] ?? ''));
        if ('switch' === $mapped) {
            if ('1' === $default) {
                $field['default'] = true;
            }
        } elseif ('' !== $default && in_array($mapped, ['text', 'textarea', 'number', 'hidden', 'select', 'radio'], true)) {
            $field['default'] = $default;
        }
        if ('number' === $mapped && '' !== trim((string) ($row['unit'] ?? ''))) {
            $field['unit'] = trim((string) $row['unit']);
        }
        if ('number' === $mapped && 'integer' === $type) {
            $field['step'] = 1;
        }
        return $field;
    }

    /**
     * Auswahlmöglichkeiten wie YForm lesen: JSON {"Text": "wert"}, SQL-Abfrage oder „Text=wert,Text2=wert2“.
     *
     * @return list<array{value: string, label: string}>
     */
    private static function choices(string $choices): array
    {
        $choices = trim($choices);
        $pairs = [];
        if ('' === $choices) {
            return [];
        }
        if (str_starts_with($choices, '{') && !str_starts_with($choices, '{{')) {
            foreach ((array) json_decode($choices, true) as $label => $value) {
                if (is_array($value)) {
                    foreach ($value as $l => $v) {
                        $pairs[] = [(string) $l, (string) $v];
                    }
                } else {
                    $pairs[] = [(string) $label, (string) $value];
                }
            }
        } elseif ('SELECT' === rex_sql::getQueryType($choices)) {
            // Momentaufnahme der Abfrage: Spalten id/name bzw. die ersten beiden
            foreach (rex_sql::factory()->getArray($choices) as $r) {
                $values = array_values($r);
                $value = (string) ($r['id'] ?? $values[0] ?? '');
                $label = (string) ($r['name'] ?? $r['label'] ?? $values[1] ?? $value);
                $pairs[] = [$label, $value];
            }
        } else {
            foreach (explode(',', $choices) as $part) {
                if ('' === trim($part)) {
                    continue;
                }
                $bits = explode('=', $part, 2);
                $pairs[] = [trim($bits[0]), trim($bits[1] ?? $bits[0])];
            }
        }
        $out = [];
        $seen = [];
        foreach ($pairs as [$label, $value]) {
            $value = trim($value);
            if ('' === $value || isset($seen[$value])) {
                continue;
            }
            $seen[$value] = true;
            $out[] = ['value' => $value, 'label' => rex_i18n::translate(trim($label), false)];
        }
        return $out;
    }

    /** @param array<string, mixed> $row */
    private static function label(array $row): string
    {
        return trim(rex_i18n::translate((string) ($row['label'] ?? ''), false));
    }

    private static function humanize(string $name): string
    {
        return ucfirst(str_replace('_', ' ', $name));
    }
}
