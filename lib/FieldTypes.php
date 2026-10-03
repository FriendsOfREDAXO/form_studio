<?php

namespace FriendsOfRedaxo\FormStudio;

use rex_extension;
use rex_extension_point;

/**
 * Registry der Feldtypen. Eine Quelle für Builder, Renderer, Validierung und YForm-Abgleich.
 *
 * Eigene Typen lassen sich über den Extension Point FORM_STUDIO_FIELD_TYPES ergänzen.
 */
final class FieldTypes
{
    /** @var array<string, array<string, mixed>>|null */
    private static ?array $types = null;

    /**
     * @return array<string, array{
     *     label: string, icon: string, group: string, value: bool, options?: bool, multiple?: bool,
     *     yform: ?array{type: string, db_type: string}, props: list<string>
     * }>
     */
    public static function all(): array
    {
        if (null !== self::$types) {
            return self::$types;
        }
        $common = ['label', 'name', 'help', 'required', 'width', 'show_if'];
        $types = [
            'text' => ['label' => 'Text', 'icon' => 'fa-font', 'group' => 'input', 'value' => true,
                'yform' => ['type' => 'text', 'db_type' => 'varchar(191)'], 'props' => [...$common, 'placeholder', 'default', 'maxlength', 'autocomplete']],
            'email' => ['label' => 'E-Mail', 'icon' => 'fa-at', 'group' => 'input', 'value' => true,
                'yform' => ['type' => 'email', 'db_type' => 'varchar(191)'], 'props' => [...$common, 'placeholder', 'autocomplete']],
            'tel' => ['label' => 'Telefon', 'icon' => 'fa-phone', 'group' => 'input', 'value' => true,
                'yform' => ['type' => 'text', 'db_type' => 'varchar(191)'], 'props' => [...$common, 'placeholder', 'autocomplete']],
            'number' => ['label' => 'Zahl', 'icon' => 'fa-hashtag', 'group' => 'input', 'value' => true,
                'yform' => ['type' => 'integer', 'db_type' => 'int'], 'props' => [...$common, 'placeholder', 'default', 'min', 'max', 'step', 'unit']],
            'textarea' => ['label' => 'Mehrzeiliger Text', 'icon' => 'fa-align-left', 'group' => 'input', 'value' => true,
                'yform' => ['type' => 'textarea', 'db_type' => 'text'], 'props' => [...$common, 'placeholder', 'rows', 'maxlength']],
            'date' => ['label' => 'Datum', 'icon' => 'fa-calendar', 'group' => 'input', 'value' => true,
                'yform' => ['type' => 'date', 'db_type' => 'date'], 'props' => [...$common, 'min', 'max', 'min_days_ahead']],
            'time' => ['label' => 'Uhrzeit', 'icon' => 'fa-clock-o', 'group' => 'input', 'value' => true,
                'yform' => ['type' => 'time', 'db_type' => 'time'], 'props' => [...$common, 'min', 'max', 'step']],
            'select' => ['label' => 'Auswahlliste', 'icon' => 'fa-caret-square-o-down', 'group' => 'choice', 'value' => true, 'options' => true,
                'yform' => ['type' => 'choice', 'db_type' => 'varchar(191)'], 'props' => [...$common, 'options', 'placeholder']],
            'radio' => ['label' => 'Einfachauswahl', 'icon' => 'fa-dot-circle-o', 'group' => 'choice', 'value' => true, 'options' => true,
                'yform' => ['type' => 'choice', 'db_type' => 'varchar(191)'], 'props' => [...$common, 'options', 'inline']],
            'checkboxes' => ['label' => 'Mehrfachauswahl', 'icon' => 'fa-check-square-o', 'group' => 'choice', 'value' => true, 'options' => true, 'multiple' => true,
                'yform' => ['type' => 'choice', 'db_type' => 'text'], 'props' => [...$common, 'options', 'inline']],
            'cards' => ['label' => 'Bildauswahl (Kacheln)', 'icon' => 'fa-th-large', 'group' => 'choice', 'value' => true, 'options' => true,
                'yform' => ['type' => 'choice', 'db_type' => 'text'], 'props' => [...$common, 'options', 'multiple', 'columns']],
            'counter' => ['label' => 'Anzahl (− / +)', 'icon' => 'fa-users', 'group' => 'input', 'value' => true,
                'yform' => ['type' => 'integer', 'db_type' => 'int'], 'props' => [...$common, 'default', 'min', 'max', 'step', 'unit']],
            'consent' => ['label' => 'Einwilligung', 'icon' => 'fa-check', 'group' => 'choice', 'value' => true,
                'yform' => ['type' => 'checkbox', 'db_type' => 'tinyint(1)'], 'props' => ['label', 'name', 'required', 'text', 'show_if']],
            'heading' => ['label' => 'Zwischenüberschrift', 'icon' => 'fa-header', 'group' => 'layout', 'value' => false,
                'yform' => null, 'props' => ['text', 'level', 'show_if']],
            'info' => ['label' => 'Hinweistext', 'icon' => 'fa-info-circle', 'group' => 'layout', 'value' => false,
                'yform' => null, 'props' => ['text', 'style', 'show_if']],
            'hidden' => ['label' => 'Verstecktes Feld', 'icon' => 'fa-eye-slash', 'group' => 'input', 'value' => true,
                'yform' => ['type' => 'text', 'db_type' => 'varchar(191)'], 'props' => ['name', 'default']],
        ];
        self::$types = rex_extension::registerPoint(new rex_extension_point('FORM_STUDIO_FIELD_TYPES', $types));
        return self::$types;
    }

    public static function get(string $type): ?array
    {
        return self::all()[$type] ?? null;
    }

    public static function hasValue(array $field): bool
    {
        return (bool) (self::get((string) ($field['type'] ?? ''))['value'] ?? false);
    }

    public static function isMultiple(array $field): bool
    {
        $type = self::get((string) ($field['type'] ?? '')) ?? [];
        return !empty($type['multiple']) || ('cards' === ($field['type'] ?? '') && !empty($field['multiple']));
    }
}
