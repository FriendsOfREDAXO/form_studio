<?php

namespace FriendsOfRedaxo\FormStudio;

use KLXM\Upkeep\IntrusionPrevention;
use KLXM\Upkeep\MailSecurityFilter;
use rex;
use rex_addon;
use rex_extension;
use rex_extension_point;
use rex_logger;
use rex_sql;
use Throwable;

/**
 * Spamschutz für Einsendungen – mehrstufig, ohne Captcha und ohne Drittanbieter:
 *
 * - harte Signale: Honeypot ausgefüllt, zu schnell abgeschickt, Zeitstempel manipuliert
 * - Punkte: kein JavaScript-Nachweis, viele Links, HTML/BBCode, Treffer in der upkeep-Badword-Liste
 * - Rate-Limit je IP und Formular, doppelte Einsendungen werden zusammengefasst
 * - upkeep (falls installiert und Intrusion Prevention aktiv): IP-Sperre erst bei wiederholtem Spam
 *
 * IP-Adressen werden nur als gesalzener Hash gespeichert und mit den Einsendungen gelöscht.
 * Erweiterbar über den Extension Point FORM_STUDIO_SPAM_CHECK (Subject: Ergebnis-Array).
 */
final class SpamGuard
{
    public const MIN_SECONDS = 3;
    public const MAX_AGE = 86400;
    public const THRESHOLD = 5;

    /** Ergebnis der Prüfung */
    public const OK = 'ok';
    public const SPAM = 'spam';
    public const EXPIRED = 'expired';
    public const RATE_LIMIT = 'rate_limit';
    public const DUPLICATE = 'duplicate';

    public static function table(): string
    {
        return rex::getTable('form_studio_attempt');
    }

    /** Signierter Zeitstempel (Mindestausfüllzeit, Ablauf) */
    public static function timestamp(): string
    {
        $ts = (string) time();
        return $ts . '.' . self::sign($ts);
    }

    /** Wert, den form-studio.js in das Feld form_studio_js schreibt (Nachweis für echten Browser) */
    public static function jsProof(string $timestamp): string
    {
        return self::sign('js|' . $timestamp);
    }

    /**
     * @param array<string, mixed> $values bereinigte Werte
     * @return array{status: string, score: int, reasons: list<string>}
     */
    public static function check(Form $form, array $values, string $timestamp, string $honeypot, string $jsProof): array
    {
        $reasons = [];
        $score = 0;
        [$ts, $sig] = array_pad(explode('.', $timestamp, 2), 2, '');

        if ('' !== $honeypot) {
            $reasons[] = 'honeypot';
            $score += 100;
        }
        if (!ctype_digit($ts) || !hash_equals(self::sign($ts), $sig)) {
            $reasons[] = 'timestamp_invalid';
            $score += 100;
        } elseif (time() - (int) $ts < self::MIN_SECONDS) {
            $reasons[] = 'too_fast';
            $score += 100;
        } elseif (time() - (int) $ts > self::MAX_AGE && $score < 100) {
            // Echte Besucher, die das Formular lange offen hatten: nicht als Spam werten
            return ['status' => self::EXPIRED, 'score' => 0, 'reasons' => ['expired']];
        }
        if ('' === $jsProof || !hash_equals(self::jsProof($timestamp), $jsProof)) {
            $reasons[] = 'no_js';
            $score += 2;
        }

        $text = self::freeText($form, $values);
        $links = preg_match_all('@(https?://|www\.)\S+@i', $text);
        $maxLinks = (int) self::config('max_links', 1);
        if ($links > $maxLinks) {
            $reasons[] = 'links:' . $links;
            $score += 3 * ($links - $maxLinks);
        }
        if (preg_match('@\[(url|link)=|<a\s|</?(script|iframe|div|span)\b@i', $text)) {
            $reasons[] = 'markup';
            $score += 4;
        }
        if (self::config('upkeep_badwords', true) && ($hits = self::badwords($text))) {
            foreach ($hits as $severity) {
                $score += match ($severity) {
                    'critical' => 10,
                    'high' => 5,
                    'medium' => 3,
                    default => 1,
                };
            }
            $reasons[] = 'badwords:' . count($hits);
        }

        $status = $score >= self::THRESHOLD ? self::SPAM : self::OK;
        if (self::OK === $status) {
            $limit = (int) self::config('rate_limit', 5);
            if ($limit > 0 && self::recent($form->id, self::OK, 3600) >= $limit) {
                $status = self::RATE_LIMIT;
                $reasons[] = 'rate_limit';
            } elseif (self::isDuplicate($form->id, $values)) {
                $status = self::DUPLICATE;
                $reasons[] = 'duplicate';
            }
        }

        $result = ['status' => $status, 'score' => $score, 'reasons' => $reasons];
        $result = rex_extension::registerPoint(new rex_extension_point('FORM_STUDIO_SPAM_CHECK', $result, ['form' => $form, 'values' => $values]));

        // „ok“ wird erst nach erfolgreicher Einsendung protokolliert (accepted), damit Tippfehler nicht ins Rate-Limit laufen
        if (self::OK !== $result['status']) {
            self::log($form->id, $result, $values);
        }
        if (self::SPAM === $result['status']) {
            self::maybeBlock();
        }
        return $result;
    }

    /** Freitext aller Felder (für Link- und Badword-Prüfung) */
    private static function freeText(Form $form, array $values): string
    {
        $parts = [];
        foreach ($form->valueFields() as $field) {
            $value = $values[(string) $field['name']] ?? '';
            if (is_string($value) && in_array((string) ($field['type'] ?? 'text'), ['text', 'textarea', 'email', 'tel'], true)) {
                $parts[] = $value;
            }
        }
        return implode("\n", $parts);
    }

    /**
     * Treffer in der Badword-Liste von upkeep (falls installiert).
     *
     * @return list<string> Schweregrade der Treffer
     */
    private static function badwords(string $text): array
    {
        if ('' === trim($text) || !rex_addon::get('upkeep')->isAvailable() || !class_exists(MailSecurityFilter::class)) {
            return [];
        }
        $hits = [];
        try {
            foreach (MailSecurityFilter::getBadwords() as $word) {
                $pattern = (string) $word['pattern'];
                $match = $word['is_regex']
                    ? @preg_match('~' . str_replace('~', '\~', $pattern) . '~iu', $text) === 1
                    : '' !== $pattern && false !== mb_stripos($text, $pattern);
                if ($match) {
                    $hits[] = (string) $word['severity'];
                }
            }
        } catch (Throwable $e) {
            rex_logger::logException($e);
        }
        return $hits;
    }

    /** Bei wiederholtem Spam von derselben IP: Sperre über upkeep (Intrusion Prevention) */
    private static function maybeBlock(): void
    {
        if (!self::config('upkeep_block', true) || !rex_addon::get('upkeep')->isAvailable() || !class_exists(IntrusionPrevention::class)) {
            return;
        }
        try {
            if (!IntrusionPrevention::isActive() || self::recent(null, self::SPAM, 86400) < (int) self::config('upkeep_block_after', 3)) {
                return;
            }
            $ip = self::ip();
            if ('' === $ip || false === filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return;
            }
            $duration = (string) self::config('upkeep_block_duration', '24h');
            $duration = in_array($duration, ['1h', '6h', '24h', '7d', '30d'], true) ? $duration : '24h';
            IntrusionPrevention::blockIpManually($ip, $duration, 'Form Studio: wiederholte Spam-Einsendungen');
        } catch (Throwable $e) {
            rex_logger::logException($e);
        }
    }

    /** Anzahl Versuche dieser IP (optional je Formular) mit Status im Zeitraum */
    private static function recent(?int $formId, string $status, int $seconds): int
    {
        $where = 'ip_hash = ? AND status = ? AND createdate > ?';
        $params = [self::ipHash(), $status, date('Y-m-d H:i:s', time() - $seconds)];
        if (null !== $formId) {
            $where .= ' AND form_id = ?';
            $params[] = $formId;
        }
        return (int) (rex_sql::factory()->getArray('SELECT COUNT(*) n FROM ' . self::table() . ' WHERE ' . $where, $params)[0]['n'] ?? 0);
    }

    private static function isDuplicate(int $formId, array $values): bool
    {
        return (bool) rex_sql::factory()->getArray(
            'SELECT id FROM ' . self::table() . ' WHERE form_id = ? AND payload_hash = ? AND status = ? AND createdate > ? LIMIT 1',
            [$formId, self::payloadHash($values), self::OK, date('Y-m-d H:i:s', time() - 600)],
        );
    }

    /**
     * Erfolgreiche Einsendung protokollieren (Rate-Limit, Doppel-Erkennung).
     *
     * @param array<string, mixed> $values
     */
    public static function accepted(Form $form, array $values): void
    {
        self::log($form->id, ['status' => self::OK, 'score' => 0, 'reasons' => []], $values);
    }

    /** @param array{status: string, score: int, reasons: list<string>} $result */
    private static function log(int $formId, array $result, array $values): void
    {
        rex_sql::factory()->setTable(self::table())
            ->setValue('form_id', $formId)
            ->setValue('ip_hash', self::ipHash())
            ->setValue('payload_hash', self::payloadHash($values))
            ->setValue('status', $result['status'])
            ->setValue('score', $result['score'])
            ->setValue('reasons', implode(',', $result['reasons']))
            ->setValue('createdate', date('Y-m-d H:i:s'))
            ->insert();
    }

    /** @return array{spam: int, ok: int, rate_limit: int} Zahlen der letzten 30 Tage für die Einstellungsseite */
    public static function stats(): array
    {
        $out = ['spam' => 0, 'ok' => 0, 'rate_limit' => 0];
        foreach (rex_sql::factory()->getArray('SELECT status, COUNT(*) n FROM ' . self::table() . ' WHERE createdate > ? GROUP BY status', [date('Y-m-d H:i:s', strtotime('-30 days'))]) as $row) {
            $out[$row['status']] = (int) $row['n'];
        }
        return $out;
    }

    /** Protokoll nach der Aufbewahrungsfrist löschen */
    public static function cleanup(int $days): void
    {
        rex_sql::factory()->setQuery('DELETE FROM ' . self::table() . ' WHERE createdate < ?', [date('Y-m-d H:i:s', strtotime('-' . max(1, $days) . ' days'))]);
    }

    private static function ip(): string
    {
        return (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    }

    private static function ipHash(): string
    {
        return substr(hash_hmac('sha256', self::ip(), Processor::secret()), 0, 32);
    }

    private static function payloadHash(array $values): string
    {
        return substr(hash('sha256', (string) json_encode($values)), 0, 32);
    }

    private static function sign(string $value): string
    {
        return substr(hash_hmac('sha256', $value, Processor::secret()), 0, 16);
    }

    private static function config(string $key, mixed $default): mixed
    {
        return rex_addon::get('form_studio')->getConfig('spam_' . $key, $default);
    }
}
