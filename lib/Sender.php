<?php

namespace FriendsOfRedaxo\FormStudio;

use rex_addon;
use rex_extension;
use rex_extension_point;

/**
 * Absenderdaten für PDF und E-Mail je Domain (Logo, Name, Anschrift, Kontakt, Farbe).
 *
 * Quelle: Einstellungen → Absender (je Domain bzw. „*“ für alle). Über den Extension Point
 * FORM_STUDIO_SENDER lassen sich die Daten aus anderen Quellen liefern (z. B. Template Manager).
 */
final class Sender
{
    public const FIELDS = ['logo', 'name', 'address', 'phone', 'email', 'web', 'color'];

    /** @return array{logo: string, name: string, address: string, phone: string, email: string, web: string, color: string} */
    public static function forDomain(string $host): array
    {
        $host = strtolower(preg_replace('/:\d+$/', '', $host) ?? '');
        $all = (array) rex_addon::get('form_studio')->getConfig('senders', []);
        $sender = array_merge(array_fill_keys(self::FIELDS, ''), (array) ($all['*'] ?? []), (array) ($all[$host] ?? []));
        $sender['color'] = preg_match('/^#[0-9a-f]{6}$/i', $sender['color']) ? $sender['color'] : '#333333';
        return rex_extension::registerPoint(new rex_extension_point('FORM_STUDIO_SENDER', $sender, ['host' => $host]));
    }
}
