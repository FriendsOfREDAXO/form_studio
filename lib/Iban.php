<?php

namespace FriendsOfRedaxo\FormStudio;

use rex_file;
use rex_path;
use rex_socket;
use Throwable;

/**
 * IBAN: Prüfung (Länge je Land + Prüfsumme nach ISO 13616) und Bank/BIC.
 *
 * Datenschutz: Für die Bankabfrage (openiban.com, kostenlos) wird nie die Kontonummer der anfragenden Person
 * übertragen – nur eine Test-IBAN mit derselben Bankleitzahl und Kontonummer 0. Ergebnisse werden je
 * Bankleitzahl zwischengespeichert. Bankabfrage für DE, AT, CH; sonst nur Prüfsumme.
 */
final class Iban
{
    /** Länge je Land (SEPA-Raum und häufige Nachbarn) */
    private const LENGTHS = [
        'AD' => 24, 'AT' => 20, 'BE' => 16, 'BG' => 22, 'CH' => 21, 'CY' => 28, 'CZ' => 24, 'DE' => 22, 'DK' => 18, 'EE' => 20,
        'ES' => 24, 'FI' => 18, 'FR' => 27, 'GB' => 22, 'GI' => 23, 'GR' => 27, 'HR' => 21, 'HU' => 28, 'IE' => 22, 'IS' => 26,
        'IT' => 27, 'LI' => 21, 'LT' => 20, 'LU' => 20, 'LV' => 21, 'MC' => 27, 'MT' => 31, 'NL' => 18, 'NO' => 15, 'PL' => 28,
        'PT' => 25, 'RO' => 24, 'SE' => 24, 'SI' => 19, 'SK' => 24, 'SM' => 27, 'VA' => 22,
    ];

    /** Aufbau der BBAN für die Bankabfrage: Länge der Bankleitzahl, Länge der Kontonummer */
    private const LOOKUP = ['DE' => [8, 10], 'AT' => [5, 11], 'CH' => [5, 12]];

    private const CACHE_TTL = 90 * 86400;

    public static function normalize(string $iban): string
    {
        return strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $iban));
    }

    /** Lesbar in Vierergruppen */
    public static function format(string $iban): string
    {
        return trim(chunk_split(self::normalize($iban), 4, ' '));
    }

    /**
     * Für die anfragende Person erkennbar, für andere nicht verwertbar: Land, Prüfziffer und letzte 4 Stellen.
     * DE89 3704 0044 0532 0130 00 → DE89 •••• •••• •••• ••30 00
     */
    public static function mask(string $iban): string
    {
        $iban = self::normalize($iban);
        if (strlen($iban) < 8) {
            return '' === $iban ? '' : '••••';
        }
        $masked = substr($iban, 0, 4) . str_repeat('•', strlen($iban) - 8) . substr($iban, -4);
        return trim(implode(' ', mb_str_split($masked, 4)));
    }

    /** @return string '' = gültig, sonst Fehlermeldung */
    public static function check(string $iban): string
    {
        $iban = self::normalize($iban);
        if (!preg_match('/^[A-Z]{2}\d{2}[A-Z0-9]{8,30}$/', $iban)) {
            return 'Bitte geben Sie eine vollständige IBAN an (z. B. DE89 3704 0044 0532 0130 00).';
        }
        $country = substr($iban, 0, 2);
        if (isset(self::LENGTHS[$country]) && strlen($iban) !== self::LENGTHS[$country]) {
            return 'Eine IBAN aus ' . $country . ' hat ' . self::LENGTHS[$country] . ' Zeichen – bitte prüfen Sie die Eingabe.';
        }
        return 1 === self::mod97(substr($iban, 4) . substr($iban, 0, 4)) ? '' : 'Die IBAN ist nicht gültig (Prüfziffer stimmt nicht). Bitte prüfen Sie die Eingabe.';
    }

    /**
     * Bank zur IBAN (nur Name, BIC, Ort). null, wenn unbekannt oder Dienst nicht erreichbar.
     *
     * @return array{name: string, bic: string, city: string}|null
     */
    public static function bank(string $iban): ?array
    {
        $iban = self::normalize($iban);
        $country = substr($iban, 0, 2);
        if ('' !== self::check($iban) || !isset(self::LOOKUP[$country])) {
            return null;
        }
        [$codeLength, $accountLength] = self::LOOKUP[$country];
        $code = substr($iban, 4, $codeLength);
        $cacheFile = rex_path::addonCache('form_studio', 'banks.json');
        $cache = is_file($cacheFile) ? (array) json_decode((string) rex_file::get($cacheFile), true) : [];
        $key = $country . $code;
        if (isset($cache[$key]) && ($cache[$key]['t'] ?? 0) > time() - self::CACHE_TTL) {
            return $cache[$key]['bank'] ?: null;
        }
        // Test-IBAN mit gleicher Bankleitzahl, Kontonummer 0 – die echte Kontonummer verlässt den Server nicht
        $bban = $code . str_repeat('0', $accountLength);
        $check = str_pad((string) (98 - self::mod97($bban . self::letters($country) . '00')), 2, '0', STR_PAD_LEFT);
        $probe = $country . $check . $bban;
        $bank = null;
        try {
            $socket = rex_socket::factoryUrl('https://openiban.com/validate/' . $probe . '?getBIC=true&validateBankCode=true');
            $socket->setTimeout(6);
            $response = $socket->doGet();
            if ($response->isOk()) {
                $data = json_decode($response->getBody(), true);
                $b = is_array($data) ? (array) ($data['bankData'] ?? []) : [];
                if ('' !== trim((string) ($b['name'] ?? ''))) {
                    $bank = ['name' => trim((string) $b['name']), 'bic' => trim((string) ($b['bic'] ?? '')), 'city' => trim((string) ($b['city'] ?? ''))];
                }
            } else {
                return null; // Dienst gestört: nicht als „unbekannt“ merken
            }
        } catch (Throwable) {
            return null;
        }
        $cache[$key] = ['t' => time(), 'bank' => $bank];
        rex_file::put($cacheFile, (string) json_encode($cache, JSON_UNESCAPED_UNICODE));
        return $bank;
    }

    private static function letters(string $s): string
    {
        return (string) preg_replace_callback('/[A-Z]/', static fn (array $m): string => (string) (ord($m[0]) - 55), $s);
    }

    /** Rest modulo 97 für lange Ziffernfolgen (Buchstaben werden zu 10–35) */
    private static function mod97(string $value): int
    {
        $digits = self::letters(strtoupper($value));
        $mod = 0;
        foreach (str_split($digits) as $d) {
            $mod = ($mod * 10 + (int) $d) % 97;
        }
        return $mod;
    }
}
