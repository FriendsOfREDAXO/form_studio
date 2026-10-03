<?php

namespace FriendsOfRedaxo\FormStudio\Api;

use FriendsOfRedaxo\FormStudio\Form;
use FriendsOfRedaxo\FormStudio\Pdf;
use FriendsOfRedaxo\FormStudio\Submission;
use rex_api_function;
use rex_api_result;
use rex_response;

/**
 * PDF-Download nach dem Absenden: index.php?rex-api-call=form_studio_pdf&token=…
 * Nur mit dem geheimen Token der Einsendung und nur während der Aufbewahrungsfrist.
 */
class PdfDownload extends rex_api_function
{
    protected $published = true;

    public function execute(): rex_api_result
    {
        rex_response::cleanOutputBuffers();
        $submission = Submission::getByToken(rex_request('token', 'string', ''));
        $form = $submission ? Form::get($submission->formId) : null;
        if (!$submission || !$form || !Pdf::available()) {
            rex_response::setStatus(rex_response::HTTP_NOT_FOUND);
            rex_response::sendContent('Die Anfrage ist nicht mehr verfügbar.', 'text/plain; charset=utf-8');
            exit;
        }
        $pdf = Pdf::render($form, $submission);
        rex_response::setStatus(rex_response::HTTP_OK);
        rex_response::setHeader('X-Robots-Tag', 'noindex');
        rex_response::setHeader('Content-Disposition', 'attachment; filename="' . Pdf::filename($form, $submission) . '"');
        rex_response::sendCacheControl('private, no-store');
        rex_response::sendContent($pdf, 'application/pdf');
        exit;
    }
}
