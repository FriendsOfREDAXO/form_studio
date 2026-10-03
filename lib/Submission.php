<?php

namespace FriendsOfRedaxo\FormStudio;

use rex;
use rex_clang;
use rex_sql;

/**
 * Eine Einsendung. Wird zeitlich begrenzt vorgehalten (Einstellung „Aufbewahrung“),
 * damit die anfragende Person ihr PDF herunterladen kann.
 */
final class Submission
{
    /** @param list<array{name: string, label: string, value: mixed, display: string}> $data */
    private function __construct(
        public readonly int $id,
        public readonly int $formId,
        public readonly string $token,
        public readonly array $data,
        public readonly string $domain,
        public readonly string $created,
    ) {}

    public static function table(): string
    {
        return rex::getTable('form_studio_submission');
    }

    /** @param list<array<string, mixed>> $data */
    public static function create(Form $form, array $data): self
    {
        Processor::cleanup();
        $token = bin2hex(random_bytes(24));
        $domain = (string) ($_SERVER['HTTP_HOST'] ?? '');
        $created = date('Y-m-d H:i:s');
        $sql = rex_sql::factory()->setTable(self::table());
        $sql->setValue('form_id', $form->id);
        $sql->setValue('token', $token);
        $sql->setValue('data', json_encode($data, JSON_UNESCAPED_UNICODE));
        $sql->setValue('domain', $domain);
        $sql->setValue('clang_id', rex_clang::getCurrentId());
        $sql->setValue('createdate', $created);
        $sql->insert();
        return new self((int) $sql->getLastId(), $form->id, $token, $data, $domain, $created);
    }

    public static function getByToken(string $token): ?self
    {
        if (!preg_match('/^[a-f0-9]{48}$/', $token)) {
            return null;
        }
        $row = rex_sql::factory()->getArray('SELECT * FROM ' . self::table() . ' WHERE token = ?', [$token])[0] ?? null;
        if (!$row) {
            return null;
        }
        return new self((int) $row['id'], (int) $row['form_id'], (string) $row['token'], json_decode((string) $row['data'], true) ?: [], (string) $row['domain'], (string) $row['createdate']);
    }

    /** Wert eines Felds (Rohwert) */
    public function value(string $name): mixed
    {
        foreach ($this->data as $item) {
            if ($item['name'] === $name) {
                return $item['value'];
            }
        }
        return null;
    }
}
