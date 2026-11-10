<?php

namespace FriendsOfRedaxo\FormStudio\Api;

use FriendsOfRedaxo\FormStudio\Address;
use FriendsOfRedaxo\FormStudio\Iban;
use FriendsOfRedaxo\FormStudio\RateLimit;
use rex_api_function;
use rex_api_result;
use rex_response;

/**
 * Öffentliche Hilfsabfragen für Formulare (Frontend), serverseitig mit Zwischenspeicher und Begrenzung je Besucher:
 *   ?rex-api-call=form_studio_lookup&kind=address&q=Neuendick…&countries=DE,NL
 *   ?rex-api-call=form_studio_lookup&kind=iban&iban=DE89…
 */
class Lookup extends rex_api_function
{
    protected $published = true;

    public function execute(): rex_api_result
    {
        rex_response::cleanOutputBuffers();
        // YRewrite meldet für /index.php ohne Artikel 404 – die Antwort ist gültig
        rex_response::setStatus(rex_response::HTTP_OK);
        rex_response::setHeader('X-Robots-Tag', 'noindex');
        rex_response::sendCacheControl('private, max-age=300');
        $kind = rex_get('kind', 'string', '');

        if (!RateLimit::allow($kind ?: 'x', 'address' === $kind ? 120 : 30, 600)) {
            rex_response::setStatus('429 Too Many Requests');
            rex_response::sendJson(['error' => 'Zu viele Anfragen – bitte kurz warten.']);
            exit;
        }
        if ('address' === $kind) {
            rex_response::sendJson(['items' => Address::suggest(rex_get('q', 'string', ''), rex_get('lang', 'string', 'de'), rex_get('countries', 'string', ''))]);
            exit;
        }
        if ('iban' === $kind) {
            $iban = rex_get('iban', 'string', '');
            $error = Iban::check($iban);
            $bank = '' === $error ? Iban::bank($iban) : null;
            rex_response::sendJson(['valid' => '' === $error, 'message' => $error, 'formatted' => Iban::format($iban), 'bank' => $bank]);
            exit;
        }
        rex_response::setStatus(rex_response::HTTP_BAD_REQUEST);
        rex_response::sendJson(['error' => 'unknown kind']);
        exit;
    }
}
