<?php

namespace FriendsOfRedaxo\FormStudio;

use rex;
use rex_sql;

/**
 * Ein Formular: Definition (Schritte → Felder) und Einstellungen.
 *
 * definition = {steps: [{title, intro, show_if?, fields: [...]}]}
 * settings   = {submit_label, success_title, success_text, store_yform, yform_table,
 *               mail_to, mail_subject, copy_to_sender, sender_field, pdf, pdf_title, pdf_intro,
 *               transition, progress_nav, step_counter, auto_advance, draft, draft_days, datalayer}
 *
 * Fehlende Schlüssel gelten mit ihrem Standardwert (siehe DEFAULTS bzw. setting()).
 */
final class Form
{
    /**
     * Standardwerte der Ablauf-Optionen. Sie entsprechen dem bisherigen Verhalten –
     * Formulare ohne diese Schlüssel sehen aus und funktionieren wie vorher.
     */
    public const DEFAULTS = [
        'transition' => 'none',
        'progress_nav' => false,
        'step_counter' => false,
        'auto_advance' => false,
        'draft' => false,
        'draft_days' => 7,
        'datalayer' => false,
        'pdf_outro' => '',
        'pdf_signature' => false,
        'pdf_signature_labels' => 'Ort, Datum | Unterschrift',
        'pdf_template' => '',
    ];

    public const TRANSITIONS = ['none', 'slide', 'fade'];

    /**
     * @param array{steps?: list<array<string, mixed>>} $definition
     * @param array<string, mixed> $settings
     */
    private function __construct(
        public readonly int $id,
        public readonly string $key,
        public readonly string $name,
        public readonly array $definition,
        public readonly array $settings,
        public readonly bool $active,
        public readonly string $description = '',
    ) {}

    public static function table(): string
    {
        return rex::getTable('form_studio_form');
    }

    public static function get(int $id): ?self
    {
        $row = rex_sql::factory()->getArray('SELECT * FROM ' . self::table() . ' WHERE id = ?', [$id])[0] ?? null;
        return $row ? self::fromRow($row) : null;
    }

    public static function getByKey(string $key): ?self
    {
        $row = rex_sql::factory()->getArray('SELECT * FROM ' . self::table() . ' WHERE `key` = ?', [$key])[0] ?? null;
        return $row ? self::fromRow($row) : null;
    }

    /**
     * @param bool $all auch inaktive Formulare (z. B. damit eine Modulauswahl nicht verloren geht)
     * @return array<int, string> ID => Name (für Auswahlfelder)
     */
    public static function options(bool $all = false): array
    {
        $out = [];
        foreach (rex_sql::factory()->getArray('SELECT id, name, status FROM ' . self::table() . ($all ? '' : ' WHERE status = 1') . ' ORDER BY name') as $row) {
            $out[(int) $row['id']] = (string) $row['name'] . ($all && 1 !== (int) $row['status'] ? ' (' . \rex_i18n::msg('form_studio_inactive') . ')' : '');
        }
        return $out;
    }

    /** @param array<string, mixed> $row */
    private static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            (string) $row['key'],
            (string) $row['name'],
            json_decode((string) $row['definition'], true) ?: ['steps' => []],
            json_decode((string) $row['settings'], true) ?: [],
            1 === (int) $row['status'],
            (string) ($row['description'] ?? ''),
        );
    }

    /**
     * Formular anlegen oder aktualisieren (nach Schlüssel).
     *
     * @param array<string, mixed> $definition
     * @param array<string, mixed> $settings
     * @param bool|null $active null = Status nicht ändern (neue Formulare sind aktiv)
     */
    public static function save(string $key, string $name, array $definition, array $settings, string $description = '', ?int $id = null, ?bool $active = null): int
    {
        $sql = rex_sql::factory()->setTable(self::table());
        $sql->setValue('key', $key);
        $sql->setValue('name', $name);
        $sql->setValue('description', $description);
        $sql->setValue('definition', json_encode($definition, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $sql->setValue('settings', json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $user = rex::getUser()?->getLogin() ?? 'form_studio';
        $sql->addGlobalUpdateFields($user);
        $existing = $id ?? (self::getByKey($key)?->id);
        if (null !== $active) {
            $sql->setValue('status', $active ? 1 : 0);
        }
        if ($existing) {
            $sql->setWhere(['id' => $existing])->update();
            return $existing;
        }
        if (null === $active) {
            $sql->setValue('status', 1);
        }
        $sql->addGlobalCreateFields($user);
        $sql->insert();
        return (int) $sql->getLastId();
    }

    /** @return list<array<string, mixed>> */
    public function steps(): array
    {
        return array_values($this->definition['steps'] ?? []);
    }

    /**
     * Alle Felder (über alle Schritte), jeweils mit Schritt-Index.
     *
     * @return list<array<string, mixed>>
     */
    public function fields(): array
    {
        $fields = [];
        foreach ($this->steps() as $i => $step) {
            foreach ($step['fields'] ?? [] as $field) {
                $field['_step'] = $i;
                $fields[] = $field;
            }
        }
        return $fields;
    }

    /** @return list<array<string, mixed>> nur Felder mit Wert */
    public function valueFields(): array
    {
        return array_values(array_filter($this->fields(), static fn ($f) => FieldTypes::hasValue($f) && '' !== (string) ($f['name'] ?? '')));
    }

    /**
     * Sichtbarkeit aller Felder inkl. ausgeblendeter Schritte.
     *
     * @param array<string, mixed> $values
     * @return array<string, bool>
     */
    public function visibility(array $values): array
    {
        $visible = [];
        foreach ($this->steps() as $step) {
            $stepVisible = Conditions::isVisible($step, $values, $visible);
            foreach ($step['fields'] ?? [] as $field) {
                $key = (string) ($field['name'] ?? $field['id'] ?? '');
                $visible[$key] = $stepVisible && Conditions::isVisible($field, $values, $visible);
            }
        }
        return $visible;
    }

    /**
     * Einstellung lesen. Ohne eigenen Standardwert gilt der aus DEFAULTS.
     */
    public function setting(string $key, mixed $default = null): mixed
    {
        $value = $this->settings[$key] ?? null;
        if (null === $default && array_key_exists($key, self::DEFAULTS)) {
            $default = self::DEFAULTS[$key];
        }
        return null === $value || '' === $value ? $default : $value;
    }
}
