<?php
$form = FriendsOfRedaxo\FormStudio\Form::get((int) 'REX_VALUE[1]');
if (!$form) {
    echo rex::isBackend() ? '<div class="alert alert-warning">Form Studio: Bitte ein Formular wählen.</div>' : '';
} elseif (rex::isBackend()) {
    echo '<div class="alert alert-info"><strong>Form Studio:</strong> ' . rex_escape($form->name) . ' (' . count($form->steps()) . ' Schritte, ' . count($form->valueFields()) . ' Felder)</div>';
} else {
    echo '<section class="fs-section"><div class="fs-container">' . FriendsOfRedaxo\FormStudio\Processor::handle($form) . '</div></section>';
}
