<?php

use FriendsOfRedaxo\FormStudio\Conditions;
use FriendsOfRedaxo\FormStudio\FieldTypes;
use FriendsOfRedaxo\FormStudio\Form;
use FriendsOfRedaxo\FormStudio\Templates;
use FriendsOfRedaxo\FormStudio\YFormSync;

$func = rex_request('func', 'string', '');
$id = rex_request('id', 'int', 0);
$csrf = rex_csrf_token::factory('form_studio_forms');
$message = '';

// Aktionen
if ('' !== $func && in_array($func, ['create', 'delete', 'sync', 'save'], true) && !$csrf->isValid()) {
    echo rex_view::error(rex_i18n::msg('csrf_token_invalid'));
    $func = '';
}
if ('create' === $func) {
    $tpl = Templates::all()[rex_request('template', 'string', '')] ?? ['name' => 'Neues Formular', 'description' => '', 'definition' => ['steps' => [['title' => 'Schritt 1', 'fields' => []]]], 'settings' => []];
    $key = rex_string::normalize(rex_request('name', 'string', '') ?: $tpl['name']);
    $base = $key;
    for ($i = 2; Form::getByKey($key); ++$i) {
        $key = $base . '_' . $i;
    }
    $id = Form::save($key, rex_request('name', 'string', '') ?: $tpl['name'], $tpl['definition'], $tpl['settings'], $tpl['description']);
    $func = 'edit';
    $message = rex_view::success('Formular angelegt.');
} elseif ('delete' === $func && $id) {
    rex_sql::factory()->setQuery('DELETE FROM ' . Form::table() . ' WHERE id = ?', [$id]);
    $message = rex_view::success('Formular gelöscht. Eine zugehörige YForm-Tabelle bleibt erhalten.');
    $func = '';
} elseif ('sync' === $func && ($form = Form::get($id))) {
    try {
        $added = YFormSync::sync($form);
        $message = rex_view::success('YForm-Tabelle <code>' . rex_escape(YFormSync::tableName($form)) . '</code> abgeglichen' . ($added ? ': neue Felder ' . rex_escape(implode(', ', $added)) : ', keine neuen Felder') . '.');
    } catch (Throwable $e) {
        $message = rex_view::error('YForm-Abgleich fehlgeschlagen: ' . rex_escape($e->getMessage()));
    }
    $func = 'edit';
} elseif ('save' === $func && ($form = Form::get($id))) {
    $definition = json_decode(rex_post('definition', 'string', ''), true);
    $settings = json_decode(rex_post('settings', 'string', ''), true);
    $name = trim(rex_post('name', 'string', ''));
    if (!is_array($definition) || !is_array($settings) || '' === $name) {
        $message = rex_view::error('Speichern fehlgeschlagen: ungültige Daten.');
    } else {
        Form::save($form->key, $name, $definition, $settings, rex_post('description', 'string', ''), $form->id);
        $message = rex_view::success('Gespeichert.');
        if (!empty($settings['store_yform'])) {
            try {
                $added = YFormSync::sync(Form::get($id));
                if ($added) {
                    $message .= rex_view::info('YForm-Tabelle ergänzt: ' . rex_escape(implode(', ', $added)));
                }
            } catch (Throwable $e) {
                $message .= rex_view::warning('YForm-Abgleich fehlgeschlagen: ' . rex_escape($e->getMessage()));
            }
        }
    }
    $func = 'edit';
}

echo $message;

if ('edit' === $func && ($form = Form::get($id))) {
    $config = [
        'types' => FieldTypes::all(),
        'operators' => Conditions::OPERATORS,
        'graphics' => array_map(static fn ($f) => basename($f, '.svg'), glob(rex_addon::get('form_studio')->getAssetsPath('seating/*.svg')) ?: []),
        'graphicsUrl' => rex_addon::get('form_studio')->getAssetsUrl('seating/'),
        'yformTable' => YFormSync::tableName($form),
    ];
    $data = ['name' => $form->name, 'definition' => $form->definition, 'settings' => $form->settings];
    $usage = rex_sql::factory()->getArray('SELECT s.article_id, a.name FROM ' . rex::getTable('article_slice') . ' s JOIN ' . rex::getTable('module') . ' m ON m.id = s.module_id AND m.`key` = "form_studio" JOIN ' . rex::getTable('article') . ' a ON a.id = s.article_id AND a.clang_id = s.clang_id WHERE s.value1 = ?', [(string) $form->id]);
    $usageHtml = $usage ? 'Verwendet auf: ' . implode(', ', array_map(static fn ($u) => '<a href="' . rex_url::backendPage('content/edit', ['article_id' => $u['article_id'], 'mode' => 'edit']) . '">' . rex_escape($u['name']) . '</a>', $usage)) : 'Noch auf keiner Seite eingebunden (Modul „Form Studio: Formular“).';
    ?>
    <form method="post" action="<?= rex_url::currentBackendPage(['func' => 'save', 'id' => $form->id]) ?>" class="fs-builder-form" data-fs-builder-form>
        <?= $csrf->getHiddenField() ?>
        <textarea name="definition" hidden data-fs-out="definition"></textarea>
        <textarea name="settings" hidden data-fs-out="settings"></textarea>
        <div class="panel panel-default">
            <header class="panel-heading"><div class="panel-title">Formular „<?= rex_escape($form->name) ?>“ <small class="text-muted">(<?= rex_escape($form->key) ?>)</small></div></header>
            <div class="panel-body">
                <div class="row">
                    <div class="col-sm-6"><label>Name <input class="form-control" name="name" value="<?= rex_escape($form->name) ?>" required></label></div>
                    <div class="col-sm-6 text-right">
                        <a class="btn btn-default" href="<?= rex_url::currentBackendPage() ?>">Zur Übersicht</a>
                        <a class="btn btn-default" href="<?= rex_url::currentBackendPage(['func' => 'sync', 'id' => $form->id] + $csrf->getUrlParams()) ?>">YForm-Tabelle abgleichen</a>
                        <button class="btn btn-save" type="submit">Speichern</button>
                    </div>
                </div>
                <p class="help-block"><?= $usageHtml ?></p>
                <div id="fs-builder" data-config="<?= rex_escape(json_encode($config, JSON_UNESCAPED_UNICODE)) ?>" data-form="<?= rex_escape(json_encode($data, JSON_UNESCAPED_UNICODE)) ?>"></div>
            </div>
        </div>
    </form>
    <?php
    return;
}

// Übersicht
$templates = Templates::all();
$list = rex_list::factory('SELECT id, name, `key`, updatedate FROM ' . Form::table() . ' ORDER BY name', 50);
$list->addTableAttribute('class', 'table-striped');
$list->removeColumn('id');
$list->setColumnLabel('name', 'Formular');
$list->setColumnLabel('key', 'Schlüssel');
$list->setColumnLabel('updatedate', 'Geändert');
$list->setColumnParams('name', ['func' => 'edit', 'id' => '###id###']);
$list->addColumn('actions', '', -1, ['<th>Aktionen</th>', '<td>###VALUE###</td>']);
$list->setColumnFormat('actions', 'custom', static function (array $p) use ($csrf) {
    $id = (int) $p['list']->getValue('id');
    return '<a href="' . rex_url::currentBackendPage(['func' => 'edit', 'id' => $id]) . '"><i class="rex-icon fa-pencil"></i> Gestalten</a> &nbsp; '
        . '<a href="' . rex_url::currentBackendPage(['func' => 'delete', 'id' => $id] + $csrf->getUrlParams()) . '" data-confirm="Formular löschen?"><i class="rex-icon rex-icon-delete"></i> Löschen</a>';
});

$create = '<form method="post" action="' . rex_url::currentBackendPage(['func' => 'create']) . '" class="form-inline">' . $csrf->getHiddenField()
    . '<input class="form-control" name="name" placeholder="Name des Formulars"> '
    . '<select class="form-control" name="template"><option value="">Leeres Formular</option>';
foreach ($templates as $key => $tpl) {
    $create .= '<option value="' . rex_escape($key) . '">Vorlage: ' . rex_escape($tpl['name']) . '</option>';
}
$create .= '</select> <button class="btn btn-primary" type="submit">Anlegen</button></form>';

$fragment = new rex_fragment();
$fragment->setVar('title', 'Neues Formular', false);
$fragment->setVar('body', $create, false);
echo $fragment->parse('core/page/section.php');

$fragment = new rex_fragment();
$fragment->setVar('title', 'Formulare', false);
$fragment->setVar('content', $list->get(), false);
echo $fragment->parse('core/page/section.php');
