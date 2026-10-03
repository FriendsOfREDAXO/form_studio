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
        $addon->setConfig('spam_rate_limit', max(0, rex_post('spam_rate_limit', 'int', 5)));
        $addon->setConfig('spam_max_links', max(0, rex_post('spam_max_links', 'int', 1)));
        $addon->setConfig('spam_upkeep_badwords', 1 === rex_post('spam_upkeep_badwords', 'int', 0));
        $addon->setConfig('spam_upkeep_block', 1 === rex_post('spam_upkeep_block', 'int', 0));
        $addon->setConfig('spam_upkeep_block_after', max(1, rex_post('spam_upkeep_block_after', 'int', 3)));
        $duration = rex_post('spam_upkeep_block_duration', 'string', '24h');
        $addon->setConfig('spam_upkeep_block_duration', in_array($duration, ['1h', '6h', '24h', '7d', '30d'], true) ? $duration : '24h');
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

$upkeep = rex_addon::get('upkeep')->isAvailable();
$stats = FriendsOfRedaxo\FormStudio\SpamGuard::stats();
$checked = static fn (string $key, bool $default = true): string => $addon->getConfig('spam_' . $key, $default) ? ' checked' : '';
$durations = '';
foreach (['1h' => '1 Stunde', '6h' => '6 Stunden', '24h' => '24 Stunden', '7d' => '7 Tage', '30d' => '30 Tage'] as $value => $label) {
    $durations .= '<option value="' . $value . '"' . ($value === $addon->getConfig('spam_upkeep_block_duration', '24h') ? ' selected' : '') . '>' . $label . '</option>';
}
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
$fragment->setVar('title', 'Absender, Datenschutz & Spamschutz', false);
$fragment->setVar('body', $content, false);
$fragment->setVar('buttons', '<button class="btn btn-save" type="submit">Speichern</button>', false);
echo '<form method="post" action="' . rex_url::currentBackendPage() . '">' . $csrf->getHiddenField() . $fragment->parse('core/page/section.php') . '</form>';
