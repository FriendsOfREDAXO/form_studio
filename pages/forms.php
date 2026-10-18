<?php

use FriendsOfRedaxo\FormStudio\Conditions;
use FriendsOfRedaxo\FormStudio\FieldTypes;
use FriendsOfRedaxo\FormStudio\Form;
use FriendsOfRedaxo\FormStudio\PdfTemplates;
use FriendsOfRedaxo\FormStudio\Templates;
use FriendsOfRedaxo\FormStudio\YFormImport;
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
    $source = rex_request('template', 'string', '');
    $skipped = null;
    $tpl = null;
    if ('__yform' === $source) {
        try {
            $tpl = YFormImport::fromTable(rex_request('yform_table', 'string', ''));
            $skipped = $tpl['skipped'];
        } catch (Throwable $e) {
            $message = rex_view::error(rex_i18n::msg('form_studio_import_failed') . ' ' . rex_escape($e->getMessage()));
            $func = '';
        }
    } else {
        $tpl = Templates::all()[$source] ?? ['name' => 'Neues Formular', 'description' => '', 'definition' => ['steps' => [['title' => 'Schritt 1', 'fields' => []]]], 'settings' => []];
    }
    if ($tpl) {
        $key = rex_string::normalize(rex_request('name', 'string', '') ?: $tpl['name']);
        $base = $key;
        for ($i = 2; Form::getByKey($key); ++$i) {
            $key = $base . '_' . $i;
        }
        $id = Form::save($key, rex_request('name', 'string', '') ?: $tpl['name'], $tpl['definition'], $tpl['settings'], $tpl['description']);
        $func = 'edit';
        $message = rex_view::success('Formular angelegt.');
        if (null !== $skipped) {
            $message .= rex_view::info(rex_i18n::msg('form_studio_import_done', (string) $tpl['settings']['yform_table']));
            if ($skipped) {
                $message .= rex_view::warning(rex_i18n::msg('form_studio_import_skipped') . '<ul><li>' . implode('</li><li>', array_map('rex_escape', $skipped)) . '</li></ul>');
            }
        }
    }
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
        Form::save($form->key, $name, $definition, $settings, trim(rex_post('description', 'string', '')), $form->id, 1 === rex_post('status', 'int', 0));
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
    // Texte für den Builder (assets/builder.js)
    $i18nKeys = [
        'form_studio_b_unsaved', 'form_studio_b_media_pick', 'form_studio_b_media_remove', 'form_studio_b_media_option', 'form_studio_b_media_none', 'form_studio_b_media_file',
        'form_studio_b_on_text', 'form_studio_b_off_text', 'form_studio_b_switch_default',
        'form_studio_s_flow', 'form_studio_s_flow_help', 'form_studio_s_success', 'form_studio_s_success_help', 'form_studio_s_mail', 'form_studio_s_mail_help',
        'form_studio_s_pdf', 'form_studio_s_pdf_help', 'form_studio_s_store', 'form_studio_s_store_help', 'form_studio_s_draft', 'form_studio_s_draft_help',
        'form_studio_s_analytics', 'form_studio_s_analytics_help', 'form_studio_s_spam', 'form_studio_s_spam_help', 'form_studio_s_spam_link',
        'form_studio_set_submit_label', 'form_studio_set_review_title', 'form_studio_set_transition', 'form_studio_set_transition_help',
        'form_studio_set_transition_none', 'form_studio_set_transition_slide', 'form_studio_set_transition_fade',
        'form_studio_set_progress_nav', 'form_studio_set_progress_nav_help', 'form_studio_set_step_counter', 'form_studio_set_step_counter_help',
        'form_studio_set_auto_advance', 'form_studio_set_auto_advance_help',
        'form_studio_set_success_title', 'form_studio_set_success_text', 'form_studio_set_success_text_help',
        'form_studio_set_mail_to', 'form_studio_set_mail_to_help', 'form_studio_set_mail_subject', 'form_studio_set_copy_to_sender', 'form_studio_set_sender_field', 'form_studio_set_sender_field_first',
        'form_studio_set_copy_subject', 'form_studio_set_copy_intro',
        'form_studio_set_pdf', 'form_studio_set_pdf_attach_team', 'form_studio_set_pdf_attach_copy', 'form_studio_set_pdf_title', 'form_studio_set_pdf_intro',
        'form_studio_set_pdf_outro', 'form_studio_set_pdf_outro_help', 'form_studio_set_pdf_signature', 'form_studio_set_pdf_signature_help',
        'form_studio_set_pdf_signature_labels', 'form_studio_set_pdf_signature_labels_help', 'form_studio_set_pdf_template', 'form_studio_set_pdf_template_help', 'form_studio_set_pdf_template_default',
        'form_studio_set_store_yform', 'form_studio_set_store_yform_help', 'form_studio_set_yform_table', 'form_studio_set_yform_table_help',
        'form_studio_set_draft', 'form_studio_set_draft_help', 'form_studio_set_draft_days',
        'form_studio_set_datalayer', 'form_studio_set_datalayer_help',
    ];
    $config = [
        'types' => FieldTypes::all(),
        'operators' => Conditions::OPERATORS,
        'graphics' => array_map(static fn ($f) => basename($f, '.svg'), glob(rex_addon::get('form_studio')->getAssetsPath('seating/*.svg')) ?: []),
        'graphicsUrl' => rex_addon::get('form_studio')->getAssetsUrl('seating/'),
        'yformTable' => YFormSync::tableName($form),
        'defaults' => Form::DEFAULTS,
        'pdfTemplates' => PdfTemplates::all(),
        'mediaTypes' => 'jpg,jpeg,png,gif,svg,webp,avif',
        'i18n' => array_combine($i18nKeys, array_map(static fn (string $k): string => rex_i18n::rawMsg($k), $i18nKeys)),
        'settingsUrl' => rex::getUser()?->isAdmin() ? rex_url::backendPage('form_studio/settings') : '',
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
                <div class="row fs-b-head">
                    <div class="col-sm-6">
                        <div class="form-group"><label for="fs-b-name">Name</label> <input class="form-control" id="fs-b-name" name="name" value="<?= rex_escape($form->name) ?>" required></div>
                        <div class="form-group">
                            <label for="fs-b-description"><?= rex_i18n::msg('form_studio_description') ?></label>
                            <textarea class="form-control" id="fs-b-description" name="description" rows="2" aria-describedby="fs-b-description-help"><?= rex_escape($form->description) ?></textarea>
                            <p class="help-block" id="fs-b-description-help"><?= rex_i18n::msg('form_studio_description_help') ?></p>
                        </div>
                        <div class="checkbox fs-b-status">
                            <label><input type="checkbox" name="status" value="1" aria-describedby="fs-b-status-help"<?= $form->active ? ' checked' : '' ?>> <strong><?= rex_i18n::msg('form_studio_active') ?></strong></label>
                            <p class="help-block" id="fs-b-status-help"><?= rex_i18n::msg('form_studio_active_help') ?></p>
                        </div>
                    </div>
                    <div class="col-sm-6 text-right fs-b-head__actions">
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
$list = rex_list::factory('SELECT id, name, `key`, status, updatedate FROM ' . Form::table() . ' ORDER BY name', 50);
$list->addTableAttribute('class', 'table-striped');
$list->removeColumn('id');
$list->setColumnLabel('name', 'Formular');
$list->setColumnLabel('key', 'Schlüssel');
$list->setColumnLabel('updatedate', 'Geändert');
$list->setColumnLabel('status', rex_i18n::msg('form_studio_status'));
$list->setColumnFormat('status', 'custom', static fn (array $p): string => 1 === (int) $p['list']->getValue('status')
    ? '<i class="rex-icon rex-icon-online rex-online" aria-hidden="true"></i> ' . rex_i18n::msg('form_studio_active')
    : '<i class="rex-icon rex-icon-offline rex-offline" aria-hidden="true"></i> ' . rex_i18n::msg('form_studio_inactive'));
$list->setColumnParams('name', ['func' => 'edit', 'id' => '###id###']);
$list->addColumn('actions', '', -1, ['<th>Aktionen</th>', '<td>###VALUE###</td>']);
$list->setColumnFormat('actions', 'custom', static function (array $p) use ($csrf) {
    $id = (int) $p['list']->getValue('id');
    return '<a href="' . rex_url::currentBackendPage(['func' => 'edit', 'id' => $id]) . '"><i class="rex-icon fa-pencil"></i> Gestalten</a> &nbsp; '
        . '<a href="' . rex_url::currentBackendPage(['func' => 'delete', 'id' => $id] + $csrf->getUrlParams()) . '" data-confirm="Formular löschen?"><i class="rex-icon rex-icon-delete"></i> Löschen</a>';
});

$yformTables = rex_addon::get('yform')->isAvailable() ? YFormImport::tables() : [];
$create = '<form method="post" action="' . rex_url::currentBackendPage(['func' => 'create']) . '" class="form-inline fs-b-create" data-fs-create>' . $csrf->getHiddenField()
    . '<label class="sr-only" for="fs-create-name">' . rex_i18n::msg('form_studio_create_name') . '</label>'
    . '<input class="form-control" id="fs-create-name" name="name" placeholder="' . rex_i18n::msg('form_studio_create_name') . '"> '
    . '<label class="sr-only" for="fs-create-source">' . rex_i18n::msg('form_studio_create_source') . '</label>'
    . '<select class="form-control" id="fs-create-source" name="template" data-fs-create-source><option value="">Leeres Formular</option>';
foreach ($templates as $key => $tpl) {
    $create .= '<option value="' . rex_escape($key) . '">Vorlage: ' . rex_escape($tpl['name']) . '</option>';
}
if ($yformTables) {
    $create .= '<option value="__yform">' . rex_i18n::msg('form_studio_create_from_yform') . '</option>';
}
$create .= '</select> ';
if ($yformTables) {
    $create .= '<span class="fs-b-create__table" data-fs-create-table><label for="fs-create-table">' . rex_i18n::msg('form_studio_create_table') . '</label> <select class="form-control" id="fs-create-table" name="yform_table">';
    foreach ($yformTables as $table => $label) {
        $create .= '<option value="' . rex_escape($table) . '">' . rex_escape($label) . '</option>';
    }
    $create .= '</select></span> ';
}
$create .= '<button class="btn btn-primary" type="submit">Anlegen</button>';
if ($yformTables) {
    $create .= '<p class="help-block" data-fs-create-help>' . rex_i18n::msg('form_studio_create_from_yform_help') . '</p>';
}
$create .= '</form>';

$fragment = new rex_fragment();
$fragment->setVar('title', 'Neues Formular', false);
$fragment->setVar('body', $create, false);
echo $fragment->parse('core/page/section.php');

$fragment = new rex_fragment();
$fragment->setVar('title', 'Formulare', false);
$fragment->setVar('content', $list->get(), false);
echo $fragment->parse('core/page/section.php');
