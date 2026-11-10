<?php

namespace FriendsOfRedaxo\FormStudio;

use rex_dir;
use rex_file;
use rex_path;

/**
 * Einfache Begrenzung öffentlicher Hilfsabfragen (Adressvorschläge, IBAN-Bank) je Besucher.
 * Speichert nur einen Hash der IP-Adresse mit Zeitstempeln, keine Klartext-IPs.
 */
final class RateLimit
{
    public static function allow(string $bucket, int $max, int $seconds): bool
    {
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        $dir = rex_path::addonCache('form_studio', 'rl/');
        rex_dir::create($dir);
        $file = $dir . $bucket . '_' . hash('sha256', $ip . Processor::secret()) . '.json';
        $now = time();
        $hits = is_file($file) ? array_filter((array) json_decode((string) rex_file::get($file), true), static fn ($t): bool => (int) $t > $now - $seconds) : [];
        if (count($hits) >= $max) {
            return false;
        }
        $hits[] = $now;
        rex_file::put($file, (string) json_encode(array_values($hits)));
        // gelegentlich aufräumen
        if (1 === random_int(1, 200)) {
            foreach (glob($dir . '*.json') ?: [] as $old) {
                if (filemtime($old) < $now - 86400) {
                    @unlink($old);
                }
            }
        }
        return true;
    }
}
