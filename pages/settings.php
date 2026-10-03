<?php

use FriendsOfRedaxo\FormStudio\Sender;

$addon = rex_addon::get('form_studio');
$csrf = rex_csrf_token::factory('form_studio_settings');

$hosts = ['*' => 'Alle Domains (Standard)'];
if (rex_addon::get('yrewrite')->isAvailable()) {
    foreach (rex_yrewrite::getDomains() as $domain) {
        if ('default' !== $domain->getName() && null !== $domain->getHost()) {
            $hosts[strtolower((string) $domain->getHost())] = (string) $domain->getHost();
        }
    }
}

if ('post' === rex_request::requestMethod()) {
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
        echo rex_view::success('Einstellungen gespeichert.');
    }
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

$fragment = new rex_fragment();
$fragment->setVar('title', 'Absender & Datenschutz', false);
$fragment->setVar('body', $content, false);
$fragment->setVar('buttons', '<button class="btn btn-save" type="submit">Speichern</button>', false);
echo '<form method="post" action="' . rex_url::currentBackendPage() . '">' . $csrf->getHiddenField() . $fragment->parse('core/page/section.php') . '</form>';
