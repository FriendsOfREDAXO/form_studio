<?php

use FriendsOfRedaxo\FormStudio\Sender;

$addon = rex_addon::get('form_studio');
$csrf = rex_csrf_token::factory('form_studio_settings');

$hosts = ['*' => 'Alle Domains (Standard)'];
if (rex_addon::get('yrewrite')->isAvailable()) {
    foreach (rex_yrewrite::getDomains() as $domain) {
        if ('default' !== $domain->getName() && '' !== (string) $domain->getHost()) {
            $hosts[strtolower((string) $domain->getHost())] = (string) $domain->getHost();
        }
    }
}

if ('post' === rex_request::requestMethod() && 'pdf_template_create' !== rex_post('func', 'string', '')) {
    if (!$csrf->isValid()) {
        echo rex_view::error(rex_i18n::msg('csrf_token_invalid'));
    } else {
        $senders = [];
        foreach ((array) rex_post('senders', 'array', []) as $host => $values) {
            if (!isset($hosts[$host])) {
                continue;
            }
            $clean = [];
            foreach (Sender::FIELDS as $field) {
                $clean[$field] = trim((string) ($values[$field] ?? ''));
            }
            if (implode('', $clean) !== '') {
                $senders[$host] = $clean;
            }
        }
        $addon->setConfig('senders', $senders);
        $addon->setConfig('retention_days', max(1, rex_post('retention_days', 'int', 30)));
        $addon->setConfig('datepicker', 1 === rex_post('datepicker', 'int', 0));
        $addon->setConfig('no_datetime_default', 1 === rex_post('no_datetime_default', 'int', 0));
        $addon->setConfig('load_css', 1 === rex_post('load_css', 'int', 0));
        $cssClasses = [];
        foreach (FriendsOfRedaxo\FormStudio\Renderer::CLASS_KEYS as $classKey) {
            $value = trim(preg_replace('/[^A-Za-z0-9_:\/.\s-]/', '', (string) (rex_post('css_classes', 'array', [])[$classKey] ?? '')) ?? '');
            if ('' !== $value) {
                $cssClasses[$classKey] = $value;
            }
        }
        $addon->setConfig('css_classes', $cssClasses);
        // Knöpfe zu Formularen und automatische Snippets
        $buttonTemplate = trim(rex_post('button_template', 'string', ''));
        $addon->setConfig('button_template', FriendsOfRedaxo\FormStudio\Button::DEFAULT_TEMPLATE === $buttonTemplate ? '' : $buttonTemplate);
        $addon->setConfig('snippets_enabled', 1 === rex_post('snippets_enabled', 'int', 0));
        $addon->setConfig('snippets_category', max(0, rex_post('snippets_category', 'int', 0)));
        $addon->setConfig('spam_rate_limit', max(0, rex_post('spam_rate_limit', 'int', 5)));
        $addon->setConfig('spam_max_links', max(0, rex_post('spam_max_links', 'int', 1)));
        $addon->setConfig('spam_upkeep_badwords', 1 === rex_post('spam_upkeep_badwords', 'int', 0));
        $addon->setConfig('spam_upkeep_block', 1 === rex_post('spam_upkeep_block', 'int', 0));
        $addon->setConfig('spam_upkeep_block_after', max(1, rex_post('spam_upkeep_block_after', 'int', 3)));
        $duration = rex_post('spam_upkeep_block_duration', 'string', '24h');
        $addon->setConfig('spam_upkeep_block_duration', in_array($duration, ['1h', '6h', '24h', '7d', '30d'], true) ? $duration : '24h');
        // Gespeicherte Abfragen für Auswahlfelder (nur Lese-Abfragen; Spalte 1 = Wert, Spalte 2 = Text)
        $queries = [];
        foreach ((array) rex_post('queries', 'array', []) as $q) {
            $key = rex_string::normalize(trim((string) ($q['key'] ?? '')));
            $sqlText = trim((string) ($q['sql'] ?? ''));
            if ('' === $key || '' === $sqlText) {
                continue;
            }
            if (!FriendsOfRedaxo\FormStudio\OptionSource::isSafeSelect($sqlText)) {
                echo rex_view::warning('Abfrage „' . rex_escape($key) . '“ nicht gespeichert: nur einfache SELECT-Abfragen ohne Semikolon sind erlaubt.');
                continue;
            }
            $queries[$key] = ['label' => trim((string) ($q['label'] ?? '')) ?: $key, 'sql' => $sqlText];
        }
        $addon->setConfig('queries', $queries);
        $synced = FriendsOfRedaxo\FormStudio\Snippets::sync();
        echo rex_view::success('Einstellungen gespeichert.' . ($synced['created'] + $synced['updated'] + $synced['deleted'] > 0
            ? ' Snippets: ' . $synced['created'] . ' angelegt, ' . $synced['updated'] . ' aktualisiert, ' . $synced['deleted'] . ' entfernt.' : ''));
    }
}

if ($addon->getConfig('module_customized', false)) {
    echo rex_view::info('Das Modul „Form Studio: Formular“ wurde im Projekt angepasst und wird deshalb bei Updates nicht überschrieben. Neue Moduloptionen (eigener Dankestext, CSS-Klassen je Platzierung) stehen erst zur Verfügung, wenn Sie den Code aus dem Ordner <code>module/</code> des AddOns übernehmen.');
}
$senders = (array) $addon->getConfig('senders', []);
$content = '<p class="help-block">Diese Angaben erscheinen im Kopf der PDFs und als Absender der E-Mails. Leere Felder übernehmen den Wert von „Alle Domains“. '
    . 'Entwickler können die Daten auch über den Extension Point <code>FORM_STUDIO_SENDER</code> liefern.</p>';
$i = 0;
foreach ($hosts as $host => $label) {
    $s = array_merge(array_fill_keys(Sender::FIELDS, ''), (array) ($senders[$host] ?? []));
    $p = 'senders[' . rex_escape($host) . ']';
    $content .= '<fieldset><legend>' . rex_escape($label) . '</legend><div class="row">'
        . '<div class="col-sm-6"><label>Logo (Medienpool)</label>' . rex_var_media::getWidget(100 + ++$i, $p . '[logo]', $s['logo']) . '</div>'
        . '<div class="col-sm-6"><label>Name<input class="form-control" name="' . $p . '[name]" value="' . rex_escape($s['name']) . '"></label></div>'
        . '<div class="col-sm-6"><label>Anschrift<textarea class="form-control" rows="3" name="' . $p . '[address]">' . rex_escape($s['address']) . '</textarea></label></div>'
        . '<div class="col-sm-3"><label>Telefon<input class="form-control" name="' . $p . '[phone]" value="' . rex_escape($s['phone']) . '"></label>'
        . '<label>E-Mail (Empfänger)<input class="form-control" type="email" name="' . $p . '[email]" value="' . rex_escape($s['email']) . '"></label></div>'
        . '<div class="col-sm-3"><label>Website<input class="form-control" name="' . $p . '[web]" value="' . rex_escape($s['web']) . '"></label>'
        . '<label>Akzentfarbe<input class="form-control" type="color" name="' . $p . '[color]" value="' . rex_escape($s['color'] ?: '#333333') . '"></label></div>'
        . '</div></fieldset>';
}
$content .= '<fieldset><legend>Datenschutz</legend><label>Einsendungen für den PDF-Download vorhalten (Tage)<input class="form-control" type="number" min="1" name="retention_days" value="' . (int) $addon->getConfig('retention_days', 30) . '"></label>'
    . '<p class="help-block">Danach werden sie automatisch gelöscht. Dauerhaft gespeichert wird nur in einer YForm-Tabelle, wenn das im Formular eingeschaltet ist.</p></fieldset>';

$upkeep = rex_addon::get('upkeep')->isAvailable();
$stats = FriendsOfRedaxo\FormStudio\SpamGuard::stats();
$checked = static fn (string $key, bool $default = true): string => $addon->getConfig('spam_' . $key, $default) ? ' checked' : '';
$durations = '';
foreach (['1h' => '1 Stunde', '6h' => '6 Stunden', '24h' => '24 Stunden', '7d' => '7 Tage', '30d' => '30 Tage'] as $value => $label) {
    $durations .= '<option value="' . $value . '"' . ($value === $addon->getConfig('spam_upkeep_block_duration', '24h') ? ' selected' : '') . '>' . $label . '</option>';
}
// Gespeicherte Abfragen: Liste mit Testergebnis, zwei leere Zeilen zum Ergänzen
$queryRows = '';
$saved = (array) $addon->getConfig('queries', []);
$i = 0;
foreach (array_merge($saved, ['' => ['label' => '', 'sql' => ''], ' ' => ['label' => '', 'sql' => '']]) as $key => $q) {
    $key = trim((string) $key);
    $test = '';
    if ('' !== $key) {
        try {
            $rows = rex_sql::factory()->getArray(str_replace('rex_', rex::getTablePrefix(), (string) $q['sql']) . (stripos((string) $q['sql'], ' limit ') === false ? ' LIMIT 1000' : ''));
            $first = $rows ? array_values($rows[0]) : [];
            $test = '<span class="text-success">' . count($rows) . ' Einträge' . ($first ? ', z. B. „' . rex_escape((string) ($first[1] ?? $first[0])) . '“' : '') . '</span>';
        } catch (Throwable $e) {
            $test = '<span class="text-danger">Fehler: ' . rex_escape($e->getMessage()) . '</span>';
        }
    }
    $queryRows .= '<tr><td><input class="form-control" name="queries[' . $i . '][key]" value="' . rex_escape($key) . '" placeholder="z. B. raeume"></td>'
        . '<td><input class="form-control" name="queries[' . $i . '][label]" value="' . rex_escape((string) $q['label']) . '" placeholder="Räume"></td>'
        . '<td><textarea class="form-control" rows="2" name="queries[' . $i . '][sql]" placeholder="SELECT id, name FROM rex_… WHERE status = 1 ORDER BY name" style="font-family:monospace">' . rex_escape((string) $q['sql']) . '</textarea>' . ('' !== $test ? '<small>' . $test . '</small>' : '') . '</td></tr>';
    ++$i;
}
$content .= '<fieldset><legend>Gespeicherte Abfragen für Auswahlfelder</legend>'
    . '<p class="help-block">Auswahlfelder können ihre Optionen aus einer dieser Abfragen beziehen (Builder → Feld → „Herkunft der Auswahlmöglichkeiten“). '
    . 'Erste Spalte = gespeicherter Wert, zweite Spalte = angezeigter Text. Nur Lese-Abfragen (SELECT) – das SQL steht nur hier, nicht in den Formularen. '
    . 'Einfacher geht es oft direkt mit „Aus YForm-Tabelle“ im Builder.</p>'
    . '<table class="table table-condensed"><thead><tr><th style="width:18%">Schlüssel</th><th style="width:22%">Bezeichnung</th><th>SQL</th></tr></thead><tbody>' . $queryRows . '</tbody></table></fieldset>';
$content .= '<fieldset><legend>Datum & Uhrzeit</legend>'
    . (rex_addon::get('a11y_datetime_addon')->isAvailable()
        ? '<div class="checkbox"><label><input type="checkbox" name="datepicker" value="1"' . ($addon->getConfig('datepicker', true) ? ' checked' : '') . '> Barrierefreien Kalender (a11y_datetime_addon) für Datums- und Zeitfelder verwenden</label></div>'
        : '<p class="help-block">Tipp: Mit dem AddOn <strong>a11y_datetime_addon</strong> erhalten Datums- und Zeitfelder einen barrierefreien Kalender. Ohne bleibt das Eingabefeld des Browsers.</p>')
    . '<div class="checkbox"><label><input type="checkbox" name="no_datetime_default" value="1" aria-describedby="fs-no-datetime-default-help"' . ($addon->getConfig('no_datetime_default', false) ? ' checked' : '') . '> Datum und Uhrzeit nie vorbelegen</label>'
    . '<p class="help-block" id="fs-no-datetime-default-help">Datums- und Zeitfelder bleiben leer, auch wenn im Formular ein Vorgabewert steht (fest oder relativ wie <code>now</code>, <code>now+4h</code>, <code>today+7d</code>). Gäste wählen Termin und Uhrzeit selbst.</p></div>'
    . '</fieldset>';
$cssClasses = (array) $addon->getConfig('css_classes', []);
$classLabels = [
    'form' => ['Formular', ''],
    'control' => ['Eingabefelder', 'uk-input / form-control'],
    'select' => ['Auswahllisten (leer = wie Eingabefelder)', 'uk-select / form-select'],
    'textarea' => ['Mehrzeilige Felder (leer = wie Eingabefelder)', 'uk-textarea / form-control'],
    'checkbox' => ['Checkboxen', 'uk-checkbox / form-check-input'],
    'radio' => ['Optionsfelder', 'uk-radio / form-check-input'],
    'button_primary' => ['Haupt-Button (Weiter, Absenden)', 'uk-button uk-button-primary / btn btn-primary'],
    'button_secondary' => ['Weitere Buttons (Zurück, PDF, − / +)', 'uk-button uk-button-default / btn btn-outline-secondary'],
];
$classFields = '';
foreach ($classLabels as $classKey => [$classLabel, $classExample]) {
    $classFields .= '<div class="col-sm-6"><label for="fs-css-' . $classKey . '">' . $classLabel . '</label>'
        . '<input class="form-control" id="fs-css-' . $classKey . '" name="css_classes[' . $classKey . ']" value="' . rex_escape((string) ($cssClasses[$classKey] ?? '')) . '"' . ('' !== $classExample ? ' placeholder="' . rex_escape($classExample) . '"' : '') . ' autocomplete="off" spellcheck="false"></div>';
}
$content .= '<fieldset><legend>Darstellung: eigenes CSS und Frameworks</legend>'
    . '<div class="checkbox"><label><input type="checkbox" name="load_css" value="1" aria-describedby="fs-load-css-help"' . (false !== $addon->getConfig('load_css', true) ? ' checked' : '') . '> Stylesheet von Form Studio laden</label>'
    . '<p class="help-block" id="fs-load-css-help">Aus: Form Studio gibt nur Markup mit <code>fs-</code>-Klassen aus, die Gestaltung übernimmt die Website (z. B. UIkit, Bootstrap, Tailwind). Das Skript für Schritte und Prüfung wird weiter geladen. '
    . 'An: Standardwerte stehen in CSS-Variablen (<code>--fs-accent</code> usw.) und lassen sich im Theme überschreiben – siehe README „Eigenes CSS und Frameworks“.</p></div>'
    . '<p class="help-block">Zusätzliche CSS-Klassen für alle Formulare – sie ergänzen die <code>fs-</code>-Klassen. Im Formular unter Einstellungen → CSS-Klassen (Frameworks) lassen sie sich je Formular ersetzen. Leer = keine zusätzlichen Klassen.</p>'
    . '<div class="row">' . $classFields . '</div>'
    . '</fieldset>';
$buttonPlaceholders = '';
foreach (FriendsOfRedaxo\FormStudio\Button::PLACEHOLDERS as $ph => $desc) {
    $buttonPlaceholders .= '<li><code>' . rex_escape($ph) . '</code> – ' . rex_escape($desc) . '</li>';
}
$snippetsAvailable = FriendsOfRedaxo\FormStudio\Snippets::available();
$categoryOptions = '<option value="0">– keine –</option>';
foreach (FriendsOfRedaxo\FormStudio\Snippets::categories() as $catId => $catName) {
    $categoryOptions .= '<option value="' . $catId . '"' . ($catId === (int) $addon->getConfig('snippets_category', 0) ? ' selected' : '') . '>' . rex_escape($catName) . '</option>';
}
$content .= '<fieldset><legend>Knöpfe zu Formularen & Snippets</legend>'
    . '<p class="help-block">Ein Knopf öffnet die Seite, auf der das Formular mit dem Modul „Form Studio: Formular“ eingebunden ist (aktuelle Domain zuerst). '
    . 'Einsetzbar überall, wo HTML ausgegeben wird: <code>&lt;form-studio-button data-form="ID" data-text="Beschriftung" data-preset="event_type=tagung"&gt;&lt;/form-studio-button&gt;</code> – oder bequemer über die Snippets unten.</p>'
    . '<label for="fs-button-template">Knopf-Vorlage</label>'
    . '<textarea class="form-control" id="fs-button-template" name="button_template" rows="3" style="font-family:monospace" aria-describedby="fs-button-template-help">' . rex_escape(FriendsOfRedaxo\FormStudio\Button::template()) . '</textarea>'
    . '<div class="help-block" id="fs-button-template-help">Platzhalter:<ul>' . $buttonPlaceholders . '</ul>'
    . 'Beispiele: UIkit <code>&lt;a class="uk-button uk-button-primary" href="{{url}}"&gt;{{text}}&lt;/a&gt;</code>, Bootstrap <code>&lt;a class="btn btn-primary" href="{{url}}"&gt;{{text}}&lt;/a&gt;</code>. '
    . 'Soll sich das Formular als Overlay öffnen, die Klasse ergänzen, auf die das Overlay-Skript der Website reagiert. Leer = Standard.</div>'
    . ($snippetsAvailable
        ? '<div class="checkbox"><label><input type="checkbox" name="snippets_enabled" value="1" aria-describedby="fs-snippets-help"' . ($addon->getConfig('snippets_enabled', false) ? ' checked' : '') . '> Für jedes Formular automatisch ein Snippet anlegen</label>'
            . '<p class="help-block" id="fs-snippets-help">Z. B. <code>[[snippet:formular_tagungsplaner]]</code> oder mit eigener Beschriftung <code>[[snippet:formular_tagungsplaner|text=Tagung anfragen]]</code>. '
            . 'Neue, duplizierte und gelöschte Formulare werden automatisch abgeglichen; ein umbenanntes Formular behält seinen Snippet-Schlüssel. Inaktive Formulare: Snippet inaktiv.</p></div>'
            . '<label for="fs-snippets-category">Kategorie der Snippets</label><select class="form-control" id="fs-snippets-category" name="snippets_category" style="max-width:20em">' . $categoryOptions . '</select>'
        : '<p class="help-block">Tipp: Mit dem AddOn <strong>snippets</strong> legt Form Studio für jedes Formular automatisch ein Snippet für den Knopf an.</p>')
    . '</fieldset>';
$content .= '<fieldset><legend>Spamschutz</legend>'
    . '<p class="help-block">Immer aktiv: unsichtbares Fangfeld, Mindestausfüllzeit, signierter Zeitstempel, Browser-Nachweis, Link- und HTML-Prüfung – ohne Captcha und ohne Drittanbieter. '
    . 'Letzte 30 Tage: <strong>' . $stats['ok'] . '</strong> Einsendungen, <strong>' . $stats['spam'] . '</strong> als Spam abgewiesen, <strong>' . $stats['rate_limit'] . '</strong> wegen zu vieler Versuche gebremst.</p>'
    . '<div class="row"><div class="col-sm-4"><label>Höchstens Einsendungen je Besucher und Stunde<input class="form-control" type="number" min="0" name="spam_rate_limit" value="' . (int) $addon->getConfig('spam_rate_limit', 5) . '"></label><p class="help-block">0 = keine Begrenzung</p></div>'
    . '<div class="col-sm-4"><label>Erlaubte Links im Text<input class="form-control" type="number" min="0" name="spam_max_links" value="' . (int) $addon->getConfig('spam_max_links', 1) . '"></label></div></div>'
    . ($upkeep
        ? '<h4>upkeep</h4>'
            . '<div class="checkbox"><label><input type="checkbox" name="spam_upkeep_badwords" value="1"' . $checked('upkeep_badwords') . '> Texte mit der Badword-Liste von upkeep prüfen</label></div>'
            . '<div class="checkbox"><label><input type="checkbox" name="spam_upkeep_block" value="1"' . $checked('upkeep_block') . '> Bei wiederholtem Spam die IP über die Intrusion Prevention von upkeep sperren</label></div>'
            . '<div class="row"><div class="col-sm-4"><label>Sperren ab … Spam-Versuchen in 24 Stunden<input class="form-control" type="number" min="1" name="spam_upkeep_block_after" value="' . (int) $addon->getConfig('spam_upkeep_block_after', 3) . '"></label></div>'
            . '<div class="col-sm-4"><label>Sperrdauer<select class="form-control" name="spam_upkeep_block_duration">' . $durations . '</select></label></div></div>'
            . '<p class="help-block">Die Sperre greift nur, wenn die Intrusion Prevention in upkeep aktiv ist. Freigaben und die Positivliste verwalten Sie in upkeep.</p>'
        : '<p class="help-block">Tipp: Mit dem AddOn <strong>upkeep</strong> können wiederholte Spam-Absender automatisch gesperrt und Texte mit einer Badword-Liste geprüft werden.</p>')
    . '</fieldset>';

$fragment = new rex_fragment();
$fragment->setVar('title', 'Absender, Datenschutz, Abfragen & Spamschutz', false);
$fragment->setVar('body', $content, false);
$fragment->setVar('buttons', '<button class="btn btn-save" type="submit">Speichern</button>', false);
echo '<form method="post" action="' . rex_url::currentBackendPage() . '">' . $csrf->getHiddenField() . $fragment->parse('core/page/section.php') . '</form>';

// PDF-Vorlagen: Kopien des Standard-Layouts als REDAXO-Templates, im Formular unter „PDF“ auswählbar
$pdfCsrf = rex_csrf_token::factory('form_studio_pdf_templates');
if ('post' === rex_request::requestMethod() && 'pdf_template_create' === rex_post('func', 'string', '')) {
    if (!$pdfCsrf->isValid()) {
        echo rex_view::error(rex_i18n::msg('csrf_token_invalid'));
    } else {
        $name = trim(rex_post('pdf_template_name', 'string', '')) ?: rex_i18n::msg('form_studio_pdf_tpl_default_name');
        $newId = FriendsOfRedaxo\FormStudio\PdfTemplates::create($name);
        echo rex_view::success(rex_i18n::msg('form_studio_pdf_tpl_created', rex_escape($name)) . ' <a href="' . rex_url::backendPage('templates', ['function' => 'edit', 'template_id' => $newId]) . '">' . rex_i18n::msg('form_studio_pdf_tpl_edit') . '</a>');
    }
}
$pdfTemplates = rex_sql::factory()->getArray('SELECT id, `key`, name FROM ' . rex::getTable('template') . ' WHERE `key` LIKE ? ORDER BY name', [FriendsOfRedaxo\FormStudio\PdfTemplates::KEY_PREFIX . '%']);
$pdfBody = '<p>' . rex_i18n::msg('form_studio_pdf_tpl_help') . '</p>';
if (!FriendsOfRedaxo\FormStudio\Pdf::available()) {
    $pdfBody .= rex_view::warning(rex_i18n::msg('form_studio_pdf_tpl_no_pdfout'));
}
if ($pdfTemplates) {
    $pdfBody .= '<ul class="list-unstyled">';
    foreach ($pdfTemplates as $row) {
        $pdfBody .= '<li><i class="rex-icon fa-file-pdf-o" aria-hidden="true"></i> <a href="' . rex_url::backendPage('templates', ['function' => 'edit', 'template_id' => (int) $row['id']]) . '">' . rex_escape((string) $row['name']) . '</a> <code>' . rex_escape((string) $row['key']) . '</code></li>';
    }
    $pdfBody .= '</ul>';
} else {
    $pdfBody .= '<p class="text-muted">' . rex_i18n::msg('form_studio_pdf_tpl_none') . '</p>';
}
$pdfBody .= '<div class="form-inline"><input type="hidden" name="func" value="pdf_template_create">'
    . '<label for="fs-pdf-tpl-name">' . rex_i18n::msg('form_studio_pdf_tpl_name') . '</label> '
    . '<input class="form-control" id="fs-pdf-tpl-name" name="pdf_template_name" placeholder="' . rex_escape(rex_i18n::msg('form_studio_pdf_tpl_default_name')) . '"> '
    . '<button class="btn btn-default" type="submit"><i class="rex-icon fa-plus" aria-hidden="true"></i> ' . rex_i18n::msg('form_studio_pdf_tpl_create') . '</button></div>';
$fragment = new rex_fragment();
$fragment->setVar('title', rex_i18n::msg('form_studio_pdf_tpl_title'), false);
$fragment->setVar('body', $pdfBody, false);
echo '<form method="post" action="' . rex_url::currentBackendPage() . '">' . $pdfCsrf->getHiddenField() . $fragment->parse('core/page/section.php') . '</form>';
