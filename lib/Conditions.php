<?php

namespace FriendsOfRedaxo\FormStudio;

/**
 * Bedingungen „Feld anzeigen, wenn …“ – dieselbe Logik wie im Browser (assets/form-studio.js).
 *
 * Format: show_if = {logic: "all"|"any", rules: [{field, op, value}]}
 * Operatoren: equals, not_equals, contains, not_contains, empty, not_empty, gt, gte, lt, lte, in
 */
final class Conditions
{
    public const OPERATORS = [
        'equals' => 'ist gleich',
        'not_equals' => 'ist nicht',
        'contains' => 'enthält',
        'not_contains' => 'enthält nicht',
        'in' => 'ist eines von (Komma-Liste)',
        'not_empty' => 'ist ausgefüllt',
        'empty' => 'ist leer',
        'gt' => 'größer als',
        'gte' => 'mindestens',
        'lt' => 'kleiner als',
        'lte' => 'höchstens',
    ];

    /**
     * Ist ein Feld bei den gegebenen Werten sichtbar?
     *
     * @param array<string, mixed> $field
     * @param array<string, mixed> $values Formularwerte (Name => Wert bzw. Liste)
     * @param array<string, bool> $visible bereits bestimmte Sichtbarkeit (versteckte Felder zählen als leer)
     */
    public static function isVisible(array $field, array $values, array $visible = []): bool
    {
        $condition = $field['show_if'] ?? null;
        if (!is_array($condition) || empty($condition['rules'])) {
            return true;
        }
        $results = [];
        foreach ($condition['rules'] as $rule) {
            $name = (string) ($rule['field'] ?? '');
            $value = ($visible[$name] ?? true) ? ($values[$name] ?? '') : '';
            $results[] = self::test($value, (string) ($rule['op'] ?? 'equals'), $rule['value'] ?? '');
        }
        return 'any' === ($condition['logic'] ?? 'all') ? in_array(true, $results, true) : !in_array(false, $results, true);
    }

    /** @param mixed $actual Wert oder Liste (Mehrfachauswahl) */
    public static function test(mixed $actual, string $op, mixed $expected): bool
    {
        $list = is_array($actual) ? array_map('strval', $actual) : ('' === (string) $actual ? [] : [(string) $actual]);
        $expected = (string) $expected;
        return match ($op) {
            'equals' => in_array($expected, $list, true),
            'not_equals' => !in_array($expected, $list, true),
            'contains' => (bool) array_filter($list, static fn ($v) => '' !== $expected && str_contains(mb_strtolower($v), mb_strtolower($expected))),
            'not_contains' => !array_filter($list, static fn ($v) => '' !== $expected && str_contains(mb_strtolower($v), mb_strtolower($expected))),
            'in' => (bool) array_intersect($list, array_map('trim', explode(',', $expected))),
            'not_empty' => [] !== $list,
            'empty' => [] === $list,
            'gt' => [] !== $list && (float) $list[0] > (float) $expected,
            'gte' => [] !== $list && (float) $list[0] >= (float) $expected,
            'lt' => [] !== $list && (float) $list[0] < (float) $expected,
            'lte' => [] !== $list && (float) $list[0] <= (float) $expected,
            default => true,
        };
    }

    /**
     * Sichtbarkeit aller Felder bestimmen (Reihenfolge der Definition; Bedingungen dürfen nur auf
     * vorherige Felder verweisen, sonst zählen spätere als sichtbar).
     *
     * @param list<array<string, mixed>> $fields
     * @param array<string, mixed> $values
     * @return array<string, bool> Name/ID => sichtbar
     */
    public static function resolve(array $fields, array $values): array
    {
        $visible = [];
        foreach ($fields as $field) {
            $key = (string) ($field['name'] ?? $field['id'] ?? '');
            $visible[$key] = self::isVisible($field, $values, $visible);
        }
        return $visible;
    }
}
