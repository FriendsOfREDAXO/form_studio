<?php

namespace FriendsOfRedaxo\FormStudio;

use FriendsOfRedaxo\PdfOut\PdfOut;
use rex_addon;
use rex_fragment;
use rex_media;
use rex_path;

/**
 * PDF einer Einsendung (pdfout/Dompdf). Layout: fragments/form_studio/pdf.php (überschreibbar).
 */
final class Pdf
{
    public static function available(): bool
    {
        return rex_addon::get('pdfout')->isAvailable() && class_exists(PdfOut::class);
    }

    public static function html(Form $form, Submission $submission): string
    {
        $sender = Sender::forDomain($submission->domain);
        $fragment = new rex_fragment();
        $fragment->setVar('form', $form, false);
        $fragment->setVar('submission', $submission, false);
        $fragment->setVar('sender', $sender, false);
        $fragment->setVar('logo', self::logoDataUri($sender['logo']), false);
        return $fragment->parse('form_studio/pdf.php');
    }

    /** PDF als Binärstring (für Mail-Anhänge) */
    public static function render(Form $form, Submission $submission): string
    {
        $pdf = new PdfOut();
        $pdf->setPaper('A4', 'portrait');
        $pdf->loadHtml(self::html($form, $submission));
        $pdf->render();
        return (string) $pdf->output();
    }

    public static function filename(Form $form, Submission $submission): string
    {
        $base = preg_replace('/[^a-z0-9]+/i', '-', (string) $form->setting('pdf_title', $form->name)) ?: 'anfrage';
        return strtolower(trim($base, '-')) . '-' . date('Y-m-d', strtotime($submission->created)) . '.pdf';
    }

    private static function logoDataUri(string $file): string
    {
        if ('' === $file || !rex_media::get($file)) {
            return '';
        }
        $path = rex_path::media($file);
        $mime = str_ends_with(strtolower($file), '.svg') ? 'image/svg+xml' : (mime_content_type($path) ?: 'image/png');
        return 'data:' . $mime . ';base64,' . base64_encode((string) file_get_contents($path));
    }
}
