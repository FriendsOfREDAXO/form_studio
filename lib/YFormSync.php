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
    public static function tableName(Form $form): string
    {
        $name = (string) $form->setting('yform_table', '');
        return '' !== $name ? $name : rex::getTablePrefix() . 'form_studio_' . preg_replace('/[^a-z0-9_]/', '_', strtolower($form->key));
    }

    /** @return list<string> Namen der neu angelegten Felder */
    public static function sync(Form $form): array
    {
        $table = self::tableName($form);
        rex_yform_manager_table_api::setTable([
            'table_name' => $table,
            'name' => 'Formular: ' . $form->name,
            'description' => 'Einsendungen aus Form Studio (' . $form->key . ')',
            'status' => 1,
            'list_amount' => 50,
            'list_sortfield' => 'id',
            'list_sortorder' => 'DESC',
        ]);
        $existing = [];
        foreach (rex_yform_manager_table::get($table)?->getFields() ?? [] as $field) {
            $existing[$field->getName()] = true;
        }
        $added = [];
        $prio = count($existing);
        foreach ($form->valueFields() as $field) {
            $name = (string) $field['name'];
            $type = FieldTypes::get((string) $field['type'])['yform'] ?? null;
            if (!$type || isset($existing[$name])) {
                continue;
            }
            $def = ['type_id' => 'value', 'type_name' => $type['type'], 'name' => $name, 'label' => (string) ($field['label'] ?? $name), 'db_type' => $type['db_type'], 'prio' => ++$prio, 'list_hidden' => $prio > 6 ? 1 : 0, 'search' => 0];
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
        rex_yform_manager_table_api::generateTablesAndFields();
        return $added;
    }

    /** @param list<array{name: string, value: mixed}> $data */
    public static function store(Form $form, array $data): void
    {
        $table = self::tableName($form);
        if (!rex_yform_manager_table::get($table)) {
            self::sync($form);
        }
        $columns = array_flip(array_column(rex_sql::factory()->getArray('SHOW COLUMNS FROM ' . rex_sql::factory()->escapeIdentifier($table)), 'Field'));
        $sql = rex_sql::factory()->setTable($table);
        foreach ($data as $item) {
            if (isset($columns[$item['name']])) {
                $sql->setValue($item['name'], is_array($item['value']) ? implode(',', $item['value']) : (string) $item['value']);
            }
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
