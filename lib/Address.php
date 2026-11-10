<?php

namespace FriendsOfRedaxo\FormStudio;

use rex_file;
use rex_path;
use rex_socket;
use Throwable;

/**
 * Adressvorschläge beim Tippen über Photon (komoot, OpenStreetMap) – serverseitig abgefragt (Proxy),
 * der Browser spricht keinen Drittanbieter an. Photon ist für Autovervollständigung gedacht (Nominatim untersagt sie).
 * Ergebnisse 7 Tage zwischengespeichert, Abrufe je Besucher begrenzt (RateLimit).
 */
final class Address
{
    private const CACHE_TTL = 7 * 86400;

    /** Länder für die Auswahl (ISO-Code => Name) */
    public const COUNTRIES = [
        'DE' => 'Deutschland', 'NL' => 'Niederlande', 'BE' => 'Belgien', 'AT' => 'Österreich', 'CH' => 'Schweiz', 'LU' => 'Luxemburg',
        'FR' => 'Frankreich', 'DK' => 'Dänemark', 'PL' => 'Polen', 'CZ' => 'Tschechien', 'IT' => 'Italien', 'ES' => 'Spanien', 'GB' => 'Vereinigtes Königreich',
    ];

    /** PLZ-Muster je Land (nur bekannte; sonst frei) */
    private const ZIP = ['DE' => '/^\d{5}$/', 'AT' => '/^\d{4}$/', 'CH' => '/^\d{4}$/', 'NL' => '/^\d{4}\s?[A-Z]{2}$/i', 'BE' => '/^\d{4}$/', 'LU' => '/^(L-)?\d{4}$/i', 'DK' => '/^\d{4}$/', 'FR' => '/^\d{5}$/', 'PL' => '/^\d{2}-\d{3}$/'];

    /**
     * @return list<array{street: string, zip: string, city: string, country: string, label: string}>
     */
    public static function suggest(string $query, string $lang = 'de', string $countries = ''): array
    {
        $query = trim((string) preg_replace('/\s+/u', ' ', $query));
        if (mb_strlen($query) < 3 || mb_strlen($query) > 120) {
            return [];
        }
        $lang = in_array($lang, ['de', 'en', 'fr', 'it'], true) ? $lang : 'de';
        $allowed = array_values(array_filter(explode(',', strtoupper($countries)), static fn (string $c): bool => isset(self::COUNTRIES[$c])));
        $cacheFile = rex_path::addonCache('form_studio', 'address/' . md5($lang . '|' . implode(',', $allowed) . '|' . mb_strtolower($query)) . '.json');
        if (is_file($cacheFile) && filemtime($cacheFile) > time() - self::CACHE_TTL) {
            return (array) json_decode((string) rex_file::get($cacheFile), true);
        }
        $params = ['q' => $query, 'lang' => $lang, 'limit' => 8];
        $url = 'https://photon.komoot.io/api/?' . http_build_query($params, '', '&') . '&layer=house&layer=street';
        try {
            $socket = rex_socket::factoryUrl($url);
            $socket->setTimeout(5);
            $socket->addHeader('User-Agent', 'REDAXO Form Studio (' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . ')');
            $response = $socket->doGet();
            if (!$response->isOk()) {
                return [];
            }
            $data = json_decode($response->getBody(), true);
        } catch (Throwable) {
            return [];
        }
        $out = [];
        foreach ((array) ($data['features'] ?? []) as $feature) {
            $p = (array) ($feature['properties'] ?? []);
            $country = strtoupper((string) ($p['countrycode'] ?? ''));
            if ($allowed && !in_array($country, $allowed, true)) {
                continue;
            }
            $street = trim((string) ($p['street'] ?? ('street' === ($p['type'] ?? '') ? ($p['name'] ?? '') : '')));
            if ('' === $street) {
                continue;
            }
            $street = trim($street . ' ' . (string) ($p['housenumber'] ?? ''));
            $zip = trim((string) ($p['postcode'] ?? ''));
            $city = trim((string) ($p['city'] ?? $p['town'] ?? $p['village'] ?? $p['district'] ?? ''));
            $label = $street . ', ' . trim($zip . ' ' . $city) . ('DE' !== $country && isset(self::COUNTRIES[$country]) ? ', ' . self::COUNTRIES[$country] : '');
            $key = mb_strtolower($label);
            if (isset($out[$key])) {
                continue;
            }
            $out[$key] = ['street' => $street, 'zip' => $zip, 'city' => $city, 'country' => $country, 'label' => $label];
            if (count($out) >= 6) {
                break;
            }
        }
        $out = array_values($out);
        rex_file::put($cacheFile, (string) json_encode($out, JSON_UNESCAPED_UNICODE));
        return $out;
    }

    /** @return string '' = gültig, sonst Meldung */
    public static function checkZip(string $zip, string $country): string
    {
        $pattern = self::ZIP[strtoupper($country)] ?? null;
        return null === $pattern || '' === $zip || preg_match($pattern, trim($zip)) ? '' : 'Bitte prüfen Sie die Postleitzahl.';
    }

    /** @param array<string, string> $a */
    public static function format(array $a, bool $withCountry = true): string
    {
        $country = strtoupper((string) ($a['country'] ?? ''));
        $line2 = trim(($a['zip'] ?? '') . ' ' . ($a['city'] ?? ''));
        return implode(', ', array_filter([
            trim((string) ($a['street'] ?? '')),
            $line2,
            $withCountry && '' !== $country ? (self::COUNTRIES[$country] ?? $country) : '',
        ]));
    }
}
