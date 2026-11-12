<?php

use FriendsOfRedaxo\FormStudio\Api\PdfDownload;

/** @var rex_addon $this */

rex_api_function::register('form_studio_pdf', PdfDownload::class);
rex_api_function::register('form_studio_preview', FriendsOfRedaxo\FormStudio\Api\Preview::class);
rex_api_function::register('form_studio_lookup', FriendsOfRedaxo\FormStudio\Api\Lookup::class);

// Knöpfe zu Formularen (<form-studio-button>, z. B. aus den automatisch angelegten Snippets)
if (rex::isFrontend()) {
    rex_extension::register('OUTPUT_FILTER', static fn (rex_extension_point $ep): string => FriendsOfRedaxo\FormStudio\Button::replace((string) $ep->getSubject()), rex_extension::LATE);
}

if (rex::isBackend() && rex::getUser()) {
    rex_perm::register('form_studio[]');
    if ('form_studio' === rex_be_controller::getCurrentPagePart(1)) {
        rex_view::addCssFile($this->getAssetsUrl('builder.css'));
        rex_view::addJsFile($this->getAssetsUrl('builder.js'));
    }
}
