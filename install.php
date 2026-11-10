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

// Modul „Form Studio: Formular“ anlegen bzw. aktualisieren (auch bei Re-Install und Update).
// Bestehende Blöcke bleiben gültig: REX_VALUE[1] ist weiterhin das Formular, die Werte 2 und 3 sind optional.
// __DIR__ statt $this->getPath(): update.php läuft aus dem Ordner der neuen Version.
// Ein im Projekt angepasstes Modul (Code weicht von allen mitgelieferten Fassungen ab) wird NICHT überschrieben.
$fsModuleHash = static fn (string $code): string => md5(trim(str_replace("\r\n", "\n", $code)));
$fsInput = rex_file::get(__DIR__ . '/module/input.php') ?? '';
$fsOutput = rex_file::get(__DIR__ . '/module/output.php') ?? '';
// mitgelieferte Fassungen (0.1.0-dev, 0.1.0, 0.2.0) und die aktuelle
$fsKnownInput = ['dd562350606ad946e0b723da5a9351be', 'aee2159943b00a262ecf69e15a8be147', $fsModuleHash($fsInput)];
$fsKnownOutput = ['78cdec611b5524455ca642909ba7e1d7', 'b8a3fac69dd44fb799dba67393bfa64c', '765442e06e21510df863063cbf91fd5d', $fsModuleHash($fsOutput)];

$module = rex_sql::factory()->getArray('SELECT id, input, output FROM ' . rex::getTable('module') . ' WHERE `key` = "form_studio"');
$fsCustomized = $module
    && (!in_array($fsModuleHash((string) $module[0]['input']), $fsKnownInput, true)
        || !in_array($fsModuleHash((string) $module[0]['output']), $fsKnownOutput, true));
$this->setConfig('module_customized', $fsCustomized);
if (!$fsCustomized) {
    $sql = rex_sql::factory()->setTable(rex::getTable('module'));
    $sql->setValue('name', 'Form Studio: Formular');
    $sql->setValue('input', $fsInput);
    $sql->setValue('output', $fsOutput);
    $sql->addGlobalUpdateFields('form_studio');
    if ($module) {
        $sql->setWhere(['id' => $module[0]['id']])->update();
    } else {
        $sql->setValue('key', 'form_studio');
        $sql->addGlobalCreateFields('form_studio');
        $sql->insert();
    }
}
