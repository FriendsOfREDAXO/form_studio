<?php

/** @var rex_addon $this */

rex_sql_table::get(rex::getTable('form_studio_form'))
    ->ensurePrimaryIdColumn()
    ->ensureColumn(new rex_sql_column('key', 'varchar(191)'))
    ->ensureColumn(new rex_sql_column('name', 'varchar(191)'))
    ->ensureColumn(new rex_sql_column('description', 'text', true))
    ->ensureColumn(new rex_sql_column('definition', 'longtext'))
    ->ensureColumn(new rex_sql_column('settings', 'longtext'))
    ->ensureColumn(new rex_sql_column('status', 'tinyint(1)', false, '1'))
    ->ensureGlobalColumns()
    ->ensureIndex(new rex_sql_index('key', ['key'], rex_sql_index::UNIQUE))
    ->ensure();

rex_sql_table::get(rex::getTable('form_studio_submission'))
    ->ensurePrimaryIdColumn()
    ->ensureColumn(new rex_sql_column('form_id', 'int(10) unsigned'))
    ->ensureColumn(new rex_sql_column('token', 'varchar(64)'))
    ->ensureColumn(new rex_sql_column('data', 'longtext'))
    ->ensureColumn(new rex_sql_column('domain', 'varchar(191)'))
    ->ensureColumn(new rex_sql_column('clang_id', 'int(10) unsigned'))
    ->ensureColumn(new rex_sql_column('createdate', 'datetime'))
    ->ensureIndex(new rex_sql_index('token', ['token'], rex_sql_index::UNIQUE))
    ->ensureIndex(new rex_sql_index('createdate', ['createdate']))
    ->ensure();

// Protokoll für Spamschutz und Rate-Limit (IP nur als Hash)
rex_sql_table::get(rex::getTable('form_studio_attempt'))
    ->ensurePrimaryIdColumn()
    ->ensureColumn(new rex_sql_column('form_id', 'int(10) unsigned'))
    ->ensureColumn(new rex_sql_column('ip_hash', 'varchar(64)'))
    ->ensureColumn(new rex_sql_column('payload_hash', 'varchar(64)'))
    ->ensureColumn(new rex_sql_column('status', 'varchar(20)'))
    ->ensureColumn(new rex_sql_column('score', 'int(10)', false, '0'))
    ->ensureColumn(new rex_sql_column('reasons', 'varchar(255)', true))
    ->ensureColumn(new rex_sql_column('createdate', 'datetime'))
    ->ensureIndex(new rex_sql_index('ip_status', ['ip_hash', 'status', 'createdate']))
    ->ensureIndex(new rex_sql_index('createdate', ['createdate']))
    ->ensure();

// Modul „Form Studio: Formular“ anlegen bzw. aktualisieren
$module = rex_sql::factory()->getArray('SELECT id FROM ' . rex::getTable('module') . ' WHERE `key` = "form_studio"');
$sql = rex_sql::factory()->setTable(rex::getTable('module'));
$sql->setValue('name', 'Form Studio: Formular');
$sql->setValue('input', rex_file::get($this->getPath('module/input.php')));
$sql->setValue('output', rex_file::get($this->getPath('module/output.php')));
$sql->addGlobalUpdateFields('form_studio');
if ($module) {
    $sql->setWhere(['id' => $module[0]['id']])->update();
} else {
    $sql->setValue('key', 'form_studio');
    $sql->addGlobalCreateFields('form_studio');
    $sql->insert();
}
