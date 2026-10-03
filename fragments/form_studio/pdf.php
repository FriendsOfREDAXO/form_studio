<?php
/**
 * PDF-Layout einer Einsendung. Eigene Version: fragments/form_studio/pdf.php im project-AddOn ablegen.
 *
 * @var rex_fragment $this
 * @var FriendsOfRedaxo\FormStudio\Form $form
 * @var FriendsOfRedaxo\FormStudio\Submission $submission
 * @var array{logo: string, name: string, address: string, phone: string, email: string, web: string, color: string} $sender
 */
$form = $this->getVar('form');
$submission = $this->getVar('submission');
$sender = $this->getVar('sender');
$logo = $this->getVar('logo');
$color = $sender['color'];
$title = (string) $form->setting('pdf_title', $form->name);
$intro = (string) $form->setting('pdf_intro', 'Vielen Dank für Ihre Anfrage. Diese Übersicht enthält Ihre Angaben; wir melden uns schnellstmöglich bei Ihnen.');
$date = date('d.m.Y, H:i', strtotime($submission->created)) . ' Uhr';
$contact = array_filter([$sender['phone'] ? 'Tel. ' . $sender['phone'] : '', $sender['email'], $sender['web']]);
// Angaben nach Schritten gliedern (wie „Ihre Angaben im Überblick“)
$byName = [];
foreach ($submission->data as $item) {
    if ('' !== (string) $item['display']) {
        $byName[(string) $item['name']] = $item;
    }
}
$groups = [];
foreach ($form->steps() as $i => $step) {
    $items = [];
    foreach ($step['fields'] ?? [] as $field) {
        $name = (string) ($field['name'] ?? '');
        if (isset($byName[$name]) && 'consent' !== ($field['type'] ?? '')) {
            $items[] = $byName[$name];
            unset($byName[$name]);
        }
    }
    if ($items) {
        $groups[] = ['title' => (string) ($step['title'] ?? 'Schritt ' . ($i + 1)), 'items' => $items];
    }
}
if ($byName) {
    $groups[] = ['title' => 'Weitere Angaben', 'items' => array_values($byName)];
}
?><!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<style>
    @page { margin: 22mm 18mm 24mm 18mm; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 10pt; color: #222; line-height: 1.45; }
    .head { width: 100%; table-layout: fixed; border-collapse: collapse; border-bottom: 1.5pt solid <?= $color ?>; margin-bottom: 16px; }
    .head td { vertical-align: bottom; padding: 0 0 8px 0; }
    .head .brand { width: 55%; }
    .logo { height: 15mm; width: auto; }
    .sender { width: 45%; text-align: right; font-size: 8pt; line-height: 1.5; color: #555; }
    .sender .name { display: block; margin-bottom: 2px; color: <?= $color ?>; font-size: 9.5pt; font-weight: bold; }
    h2 { font-size: 10.5pt; color: <?= $color ?>; margin: 16px 0 4px 0; padding-bottom: 3px; border-bottom: 0.5pt solid #d6cdbf; text-transform: uppercase; letter-spacing: 0.6pt; }
    h1 { font-size: 17pt; color: <?= $color ?>; margin: 0 0 4px 0; font-weight: normal; }
    .meta { color: #666; font-size: 8.5pt; margin-bottom: 14px; }
    .intro { margin-bottom: 16px; }
    table.data { width: 100%; border-collapse: collapse; }
    table.data th { text-align: left; width: 38%; font-weight: bold; color: #444; padding: 6px 8px; vertical-align: top; }
    table.data td { padding: 6px 8px; vertical-align: top; }
    table.data tr:nth-child(even) th, table.data tr:nth-child(even) td { background: #f5f2ec; }
    .footer { position: fixed; bottom: -14mm; left: 0; right: 0; font-size: 7.5pt; color: #888; text-align: center; border-top: 1px solid #ddd; padding-top: 4px; }
</style>
</head>
<body>
<table class="head"><tr>
    <td class="brand"><?php if ('' !== $logo): ?><img class="logo" src="<?= $logo ?>" alt=""><?php else: ?><strong style="font-size:14pt;color:<?= $color ?>"><?= rex_escape($sender['name']) ?></strong><?php endif; ?></td>
    <td class="sender">
        <?php if ('' !== $sender['name']): ?><span class="name"><?= rex_escape($sender['name']) ?></span><?php endif; ?>
        <?= nl2br(rex_escape($sender['address'])) ?>
        <?php foreach ($contact as $line): ?><br><?= rex_escape($line) ?><?php endforeach; ?>
    </td>
</tr></table>

<h1><?= rex_escape($title) ?></h1>
<div class="meta">Eingegangen am <?= rex_escape($date) ?> · Vorgang <?= rex_escape(strtoupper(substr($submission->token, 0, 8))) ?></div>
<?php if ('' !== $intro): ?><div class="intro"><?= nl2br(rex_escape($intro)) ?></div><?php endif; ?>

<?php foreach ($groups as $group): ?>
<h2><?= rex_escape($group['title']) ?></h2>
<table class="data">
<?php foreach ($group['items'] as $item): ?>
    <tr><th><?= rex_escape($item['label']) ?></th><td><?= nl2br(rex_escape((string) $item['display'])) ?></td></tr>
<?php endforeach; ?>
</table>
<?php endforeach; ?>

<div class="footer"><?= rex_escape(implode(' · ', array_filter([$sender['name'], str_replace("\n", ', ', $sender['address'])]))) ?></div>
</body>
</html>
