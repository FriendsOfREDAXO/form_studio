<?php

namespace FriendsOfRedaxo\FormStudio;

use rex;
use rex_sql;
use rex_yform_manager_table;
use rex_yform_manager_table_api;

/**
 * Legt zur Formulardefinition eine YForm-Tabelle an bzw. ergänzt fehlende Felder
 * und speichert Einsendungen darin. Bestehende Felder werden nie gelöscht.
 */
final class YFormSync
{
    /** YForm-Typ für Feldtypen, falls die Registry keinen liefert */
    private const FALLBACK = [
        'switch' => ['type' => 'checkbox', 'db_type' => 'tinyint(1)'],
    ];

    /** Ja/Nein-Felder werden als 0/1 gespeichert (Spalte tinyint) */
    private const BOOLEAN_TYPES = ['consent', 'switch'];

    public static function tableName(Form $form): string
    {
        $name = (string) $form->setting('yform_table', '');
        return '' !== $name ? $name : rex::getTablePrefix() . 'form_studio_' . preg_replace('/[^a-z0-9_]/', '_', strtolower($form->key));
    }

    /** @return list<string> Namen der neu angelegten Felder */
    public static function sync(Form $form): array
    {
        $table = self::tableName($form);
        $current = rex_yform_manager_table::get($table);
        // Fremde Tabellen (z. B. per Import übernommen) behalten Name und Beschreibung
        if (!$current || str_starts_with((string) $current->getDescription(), 'Einsendungen aus Form Studio')) {
            rex_yform_manager_table_api::setTable([
                'table_name' => $table,
                'name' => 'Formular: ' . $form->name,
                'description' => 'Einsendungen aus Form Studio (' . $form->key . ')',
                'status' => 1,
                'list_amount' => 50,
                'list_sortfield' => 'id',
                'list_sortorder' => 'DESC',
            ]);
        }
        $existing = [];
        foreach (rex_yform_manager_table::get($table)?->getFields() ?? [] as $field) {
            $existing[$field->getName()] = true;
        }
        $added = [];
        $prio = count($existing);
        $fields = self::fieldsAddon();
        $order = [];
        $steps = $form->steps();
        // Mit dem AddOn „Fields“: je Schritt ein Reiter im YForm-Formular (fields_interactive, ohne DB-Spalte)
        $tabs = $fields && count($steps) > 1;
        foreach ($steps as $i => $step) {
            if ($tabs) {
                $marker = 'fs_step_' . $i;
                $order[] = $marker;
                if (!isset($existing[$marker])) {
                    rex_yform_manager_table_api::setTableField($table, ['type_id' => 'value', 'type_name' => 'fields_interactive', 'name' => $marker,
                        'label' => (string) ($step['title'] ?? 'Schritt ' . ($i + 1)), 'type' => 'tab', 'group_id' => 'fs_steps', 'active' => 0 === $i ? 1 : 0, 'db_type' => 'none', 'prio' => ++$prio]);
                }
            }
            foreach ($step['fields'] ?? [] as $field) {
                if (!FieldTypes::hasValue($field) || '' === (string) ($field['name'] ?? '')) {
                    continue;
                }
                $order[] = (string) $field['name'];
            }
        }
        if ($tabs) {
            $order[] = 'fs_steps_end';
            if (!isset($existing['fs_steps_end'])) {
                rex_yform_manager_table_api::setTableField($table, ['type_id' => 'value', 'type_name' => 'fields_interactive', 'name' => 'fs_steps_end', 'label' => '', 'type' => 'end_group', 'group_id' => 'fs_steps', 'db_type' => 'none', 'prio' => ++$prio]);
            }
        }
        foreach ($form->valueFields() as $field) {
            $name = (string) $field['name'];
            $type = self::yformType($field, $fields);
            if (!$type || isset($existing[$name])) {
                continue;
            }
            $def = ['type_id' => 'value', 'type_name' => $type['type'], 'name' => $name, 'label' => (string) ($field['label'] ?? $name), 'db_type' => $type['db_type'], 'prio' => ++$prio, 'list_hidden' => $prio > 6 ? 1 : 0, 'search' => 0];
            if ('fields_table' === $type['type']) {
                $cols = count(Renderer::subfields($field));
                $def += ['min_cols' => $cols, 'max_cols' => $cols, 'min_rows' => 1, 'max_rows' => '', 'header_row_policy' => 'yes', 'header_col_policy' => 'no', 'enable_textarea' => 1];
            }
            if ('choice' === $type['type']) {
                $def['choices'] = implode(',', array_map(static fn ($o) => str_replace([',', '='], ' ', $o['label']) . '=' . $o['value'], Renderer::options($field)));
                $def['multiple'] = FieldTypes::isMultiple($field) ? 1 : 0;
                $def['expanded'] = 0;
            }
            rex_yform_manager_table_api::setTableField($table, $def);
            $added[] = $name;
        }
        foreach (['fs_created' => ['datestamp', 'datetime', 'Eingegangen'], 'fs_domain' => ['text', 'varchar(191)', 'Domain']] as $name => [$t, $db, $label]) {
            if (!isset($existing[$name])) {
                $def = ['type_id' => 'value', 'type_name' => $t, 'name' => $name, 'label' => $label, 'db_type' => $db, 'prio' => ++$prio];
                if ('datestamp' === $t) {
                    $def['only_empty'] = 1;
                }
                rex_yform_manager_table_api::setTableField($table, $def);
                $added[] = $name;
            }
        }
        // Vertrauliche und personenbezogene Felder verschlüsselt speichern (AddOn YForm Encryption)
        self::mapEncryption($form, $table);

        // Reihenfolge wie im Formular (Reiter, Felder), danach Eingangsdatum/Domain und übrige alte Felder
        $order = array_merge($order, ['fs_created', 'fs_domain']);
        $sql = rex_sql::factory();
        foreach (rex_yform_manager_table::get($table)?->getFields() ?? [] as $f) {
            if (!in_array($f->getName(), $order, true)) {
                $order[] = $f->getName();
            }
        }
        foreach ($order as $p => $name) {
            $sql->setQuery('UPDATE ' . rex::getTable('yform_field') . ' SET prio = ? WHERE table_name = ? AND name = ?', [$p + 1, $table, $name]);
        }
        rex_yform_manager_table_api::generateTablesAndFields();
        return $added;
    }

    /** AddOn „YForm Encryption“ verfügbar und Schlüssel eingerichtet? */
    public static function encryptionAvailable(): bool
    {
        if (!\rex_addon::get('yform_encryption')->isAvailable() || !class_exists(\FriendsOfREDAXO\YFormEncryption\Helper::class)) {
            return false;
        }
        try {
            \FriendsOfREDAXO\YFormEncryption\KeyManager::getInstance()->getKey();
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Felder, die verschlüsselt gespeichert werden sollen: IBAN immer, Adresse und Wiederholgruppe (personenbezogen),
     * solange das Formular das nicht abschaltet (Einstellung encrypt_personal = false).
     *
     * @return list<string>
     */
    public static function encryptedFields(Form $form): array
    {
        $names = [];
        foreach ($form->valueFields() as $field) {
            $type = (string) ($field['type'] ?? '');
            if (FieldTypes::isSensitive($field) || (in_array($type, ['address', 'repeater'], true) && false !== $form->setting('encrypt_personal', true))) {
                $names[] = (string) $field['name'];
            }
        }
        return $names;
    }

    /** Zuordnung in YForm Encryption anlegen, Spalten erweitern und db_type festhalten (sonst verkleinert YForm sie wieder) */
    private static function mapEncryption(Form $form, string $table): void
    {
        if (!self::encryptionAvailable()) {
            return;
        }
        $fields = self::encryptedFields($form);
        if (!$fields) {
            return;
        }
        $mapper = \FriendsOfREDAXO\YFormEncryption\FieldMapper::getInstance();
        foreach ($fields as $name) {
            if (!$mapper->isFieldEncrypted($table, $name)) {
                $mapper->addField($table, $name);
            }
        }
        \FriendsOfREDAXO\YFormEncryption\ColumnMigrator::migrateColumns($table, $fields);
        // auch mit älteren YForm-Encryption-Versionen: Spaltentyp in der Felddefinition auf text
        foreach ($fields as $name) {
            rex_sql::factory()->setQuery('UPDATE ' . rex::getTable('yform_field') . ' SET db_type = "text" WHERE table_name = ? AND name = ? AND type_id = "value" AND db_type LIKE "varchar%"', [$table, $name]);
        }
    }

    /** AddOn „Fields“ (FriendsOfREDAXO) verfügbar? Dann IBAN, Tabellen und Reiter mit dessen Feldtypen */
    public static function fieldsAddon(): bool
    {
        return \rex_addon::get('fields')->isAvailable() && class_exists(\rex_yform_value_fields_interactive::class);
    }

    /**
     * YForm-Feldtyp für ein Form-Studio-Feld.
     *
     * @return array{type: string, db_type: string}|null
     */
    public static function yformType(array $field, ?bool $fields = null): ?array
    {
        $fields ??= self::fieldsAddon();
        $type = (string) ($field['type'] ?? '');
        if ($fields && 'iban' === $type) {
            return ['type' => 'fields_iban', 'db_type' => 'varchar(34)'];
        }
        if ($fields && 'repeater' === $type) {
            return ['type' => 'fields_table', 'db_type' => 'mediumtext'];
        }
        return FieldTypes::get($type)['yform'] ?? self::FALLBACK[$type] ?? null;
    }

    /**
     * Wiederholgruppe als fields_table-JSON: Kopfzeile mit den Beschriftungen, je Eintrag eine Zeile.
     *
     * @param list<array<string, string>> $rows
     */
    public static function tableJson(array $field, array $rows): string
    {
        $subs = Renderer::subfields($field);
        $data = [
            'caption' => (string) ($field['label'] ?? ''),
            'has_header_row' => true,
            'has_header_col' => false,
            'cols' => array_map(static fn (array $s): array => ['type' => 'number' === ($s['type'] ?? '') ? 'number' : ('textarea' === ($s['type'] ?? '') ? 'textarea' : 'text')], $subs),
            'rows' => [array_map(static fn (array $s): string => (string) ($s['label'] ?? $s['name']), $subs)],
        ];
        foreach ($rows as $row) {
            $line = [];
            foreach ($subs as $sub) {
                $v = (string) ($row[$sub['name']] ?? '');
                if ('select' === ($sub['type'] ?? '')) {
                    $v = array_column(Renderer::options($sub), 'label', 'value')[$v] ?? $v;
                }
                $line[] = $v;
            }
            $data['rows'][] = $line;
        }
        return (string) json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /** @param list<array{name: string, value: mixed, display?: string}> $data */
    public static function store(Form $form, array $data): void
    {
        $table = self::tableName($form);
        if (!rex_yform_manager_table::get($table)) {
            self::sync($form);
        }
        $columns = array_flip(array_column(rex_sql::factory()->getArray('SHOW COLUMNS FROM ' . rex_sql::factory()->escapeIdentifier($table)), 'Field'));
        // Feldtypen der Tabelle (fields_table speichert JSON)
        $typeOf = [];
        foreach (rex_yform_manager_table::get($table)?->getFields() ?? [] as $f) {
            $typeOf[$f->getName()] = $f->getTypeName();
        }
        $definition = [];
        foreach ($form->valueFields() as $f) {
            $definition[(string) $f['name']] = $f;
        }
        $encrypt = [];
        if (self::encryptionAvailable()) {
            self::mapEncryption($form, $table);
            $encrypt = array_flip(\FriendsOfREDAXO\YFormEncryption\FieldMapper::getInstance()->getEncryptedFields($table));
        }
        $sql = rex_sql::factory()->setTable($table);
        // Werte setzen; zugeordnete Felder verschlüsselt (Form Studio schreibt direkt – der YForm-Automatismus greift hier nicht)
        $set = static function (string $name, string $value) use ($sql, $encrypt): void {
            $sql->setValue($name, isset($encrypt[$name]) && '' !== $value ? \FriendsOfREDAXO\YFormEncryption\Helper::encryptValue($value) : $value);
        };
        foreach ($data as $item) {
            if (!isset($columns[$item['name']])) {
                continue;
            }
            $fieldType = (string) ($definition[$item['name']]['type'] ?? '');
            // Ja/Nein-Felder (Einwilligung, Schalter) als 0/1 – tinyint-Spalte, strikte Datenbank
            if (in_array($fieldType, self::BOOLEAN_TYPES, true)) {
                $sql->setValue($item['name'], '1' === (string) $item['value'] ? 1 : 0);
                continue;
            }
            if ('fields_table' === ($typeOf[$item['name']] ?? '') && isset($definition[$item['name']])) {
                $set($item['name'], self::tableJson($definition[$item['name']], (array) $item['value']));
                continue;
            }
            // Listen (Mehrfachauswahl) kommagetrennt; zusammengesetzte Werte (Adresse, Wiederholgruppe, IBAN mit Bank) lesbar
            $value = $item['value'];
            $isList = is_array($value) && array_is_list($value) && [] === array_filter($value, 'is_array');
            $set($item['name'], $isList ? implode(',', $value) : (is_array($value) ? (string) ($item['display'] ?? '') : (string) $value));
        }
        if (isset($columns['fs_created'])) {
            $sql->setValue('fs_created', date('Y-m-d H:i:s'));
        }
        if (isset($columns['fs_domain'])) {
            $sql->setValue('fs_domain', (string) ($_SERVER['HTTP_HOST'] ?? ''));
        }
        $sql->insert();
    }
}
