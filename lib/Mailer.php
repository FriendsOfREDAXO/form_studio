<?php

namespace FriendsOfRedaxo\FormStudio;

use rex_addon;
use rex_extension;
use rex_extension_point;
use rex_logger;
use rex_mailer;

/**
 * E-Mail an die Empfänger des Formulars und optional eine Kopie an die anfragende Person,
 * jeweils mit PDF im Anhang (wenn pdfout verfügbar).
 */
final class Mailer
{
    public static function send(Form $form, Submission $submission): void
    {
        if (!rex_addon::get('phpmailer')->isAvailable()) {
            return;
        }
        $sender = Sender::forDomain($submission->domain);
        $subject = (string) $form->setting('mail_subject', 'Neue Anfrage: ' . $form->name);
        $pdf = Pdf::available() && $form->setting('pdf', true) ? Pdf::render($form, $submission) : '';
        $filename = Pdf::filename($form, $submission);
        $replyTo = self::senderEmail($form, $submission);

        // An das Haus
        $recipients = array_filter(array_map('trim', preg_split('/[,;\s]+/', (string) $form->setting('mail_to', $sender['email'])) ?: []), static fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL));
        if ($recipients) {
            $mail = self::mailer($sender);
            foreach ($recipients as $to) {
                $mail->addAddress($to);
            }
            if ('' !== $replyTo) {
                $mail->addReplyTo($replyTo);
            }
            self::compose($mail, $subject, self::body($form, $submission, $sender, true), $form->setting('pdf_attach_team', true) ? $pdf : '', $filename);
            self::dispatch($mail, $form, 'team');
        }

        // Kopie an die anfragende Person
        if ($form->setting('copy_to_sender', true) && '' !== $replyTo) {
            $mail = self::mailer($sender);
            $mail->addAddress($replyTo);
            if (filter_var($sender['email'], FILTER_VALIDATE_EMAIL)) {
                $mail->addReplyTo($sender['email'], $sender['name']);
            }
            self::compose($mail, (string) $form->setting('copy_subject', 'Ihre Anfrage bei ' . ($sender['name'] ?: $form->name)), self::body($form, $submission, $sender, false), $form->setting('pdf_attach_copy', true) ? $pdf : '', $filename);
            self::dispatch($mail, $form, 'copy');
        }
    }

    private static function mailer(array $sender): rex_mailer
    {
        $mail = new rex_mailer();
        if ('' !== $sender['name']) {
            $mail->FromName = $sender['name'];
        }
        return $mail;
    }

    private static function compose(rex_mailer $mail, string $subject, string $html, string $pdf, string $filename): void
    {
        $mail->CharSet = 'utf-8';
        $mail->Subject = $subject;
        $mail->isHTML(true);
        $mail->Body = $html;
        $mail->AltBody = trim(html_entity_decode(strip_tags(preg_replace('/<(br|\/tr|\/p|\/h2)>/i', "\n", $html) ?? '')));
        if ('' !== $pdf) {
            $mail->addStringAttachment($pdf, $filename, 'base64', 'application/pdf');
        }
    }

    private static function dispatch(rex_mailer $mail, Form $form, string $kind): void
    {
        $mail = rex_extension::registerPoint(new rex_extension_point('FORM_STUDIO_MAIL', $mail, ['form' => $form, 'kind' => $kind]));
        if ($mail instanceof rex_mailer && !$mail->send()) {
            rex_logger::factory()->warning('Form Studio: E-Mail ({kind}) konnte nicht gesendet werden: {error}', ['kind' => $kind, 'error' => $mail->ErrorInfo]);
        }
    }

    /** E-Mail der anfragenden Person: Feld aus den Einstellungen, sonst erstes E-Mail-Feld */
    private static function senderEmail(Form $form, Submission $submission): string
    {
        $field = (string) $form->setting('sender_field', '');
        if ('' === $field) {
            foreach ($form->valueFields() as $f) {
                if ('email' === ($f['type'] ?? '')) {
                    $field = (string) $f['name'];
                    break;
                }
            }
        }
        $email = (string) ($submission->value($field) ?? '');
        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '';
    }

    private static function body(Form $form, Submission $submission, array $sender, bool $team): string
    {
        $color = $sender['color'];
        $rows = '';
        foreach ($submission->data as $item) {
            if ('' === (string) $item['display']) {
                continue;
            }
            $rows .= '<tr><th style="text-align:left;vertical-align:top;padding:6px 10px;color:#444;width:38%">' . rex_escape($item['label']) . '</th>'
                . '<td style="vertical-align:top;padding:6px 10px">' . nl2br(rex_escape((string) $item['display'])) . '</td></tr>';
        }
        $intro = $team
            ? 'Über das Formular „' . rex_escape($form->name) . '“ ist eine neue Anfrage eingegangen (' . rex_escape($submission->domain) . ').'
            : nl2br(rex_escape((string) $form->setting('copy_intro', 'vielen Dank für Ihre Anfrage. Hier noch einmal Ihre Angaben im Überblick – das PDF im Anhang können Sie für Ihre Unterlagen speichern. Wir melden uns schnellstmöglich bei Ihnen.')));
        return '<div style="font-family:Arial,sans-serif;font-size:14px;color:#222;max-width:640px">'
            . '<h2 style="color:' . $color . ';font-weight:normal">' . rex_escape((string) $form->setting('pdf_title', $form->name)) . '</h2>'
            . '<p>' . ($team ? '' : 'Guten Tag,<br><br>') . $intro . '</p>'
            . '<table style="border-collapse:collapse;width:100%;border-top:2px solid ' . $color . '">' . $rows . '</table>'
            . ($team ? '' : '<p style="margin-top:20px">Herzliche Grüße<br>' . rex_escape($sender['name']) . '</p>')
            . '</div>';
    }
}
