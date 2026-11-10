<?php

namespace FriendsOfRedaxo\FormStudio;

use rex;
use rex_addon;
use rex_extension;
use rex_extension_point;
use rex_logger;
use rex_sql;
use rex_yform_manager_table;
use Throwable;

/**
 * Optionen für Auswahlfelder aus einer Datenquelle statt manueller Liste.
 *
 * Feld-Einstellungen (Builder):
 *   source = ''            manuell (field.options)
 *   source = 'yform'       YForm-Tabelle: source_table, source_label, source_value (Standard id),
 *                          source_filter_field + source_filter_value (optional), source_order (optional)
 *   source = 'query:<key>' gespeicherte Abfrage aus den Einstellungen (nur Admins legen SQL an; Spalten value, label)
 *   source = 'ep:<key>'    eigene Quelle per Extension Point FORM_STUDIO_OPTION_SOURCES
 *
 * EP FORM_STUDIO_OPTION_SOURCES: Subject = [key => ['label' => '…', 'options' => callable(array $field): list<array{value, label}>]]
 */
final class OptionSource
{
    /** @var array<string, list<array{value: string, label: string}>> */
    private static array $cache = [];

    /** @return array<string, array<string, mixed>> Angaben aus dem EP (label, options) – ungeprüft, daher locker typisiert */
    public static function custom(): array
    {
        static $sources = null;
        return $sources ??= (array) rex_extension::registerPoint(new rex_extension_point('FORM_STUDIO_OPTION_SOURCES', []));
    }

    /** @return array<string, array{label: string, sql: string}> gespeicherte Abfragen (Einstellungen) */
    public static function queries(): array
    {
        return (array) rex_addon::get('form_studio')->getConfig('queries', []);
    }

    /** Für den Builder: verfügbare Quellen und YForm-Tabellen mit Feldern */
    public static function builderConfig(): array
    {
        $tables = [];
        if (rex_addon::get('yform')->isAvailable() && class_exists(rex_yform_manager_table::class)) {
            foreach (rex_yform_manager_table::getAll() as $table) {
                $fields = ['id' => 'id'];
                foreach ($table->getValueFields() as $f) {
                    $fields[$f->getName()] = $f->getLabel() ?: $f->getName();
                }
                $tables[$table->getTableName()] = ['label' => $table->getNameLocalized(), 'fields' => $fields];
            }
        }
        $queries = [];
        foreach (self::queries() as $key => $q) {
            $queries['query:' . $key] = '' !== $q['label'] ? $q['label'] : $key;
        }
        foreach (self::custom() as $key => $s) {
            $queries['ep:' . $key] = (string) ($s['label'] ?? $key);
        }
        return ['tables' => $tables, 'named' => $queries];
    }

    /**
     * @param array<string, mixed> $field
     * @return list<array{value: string, label: string}>|null null = keine Datenquelle (manuelle Optionen)
     */
    public static function load(array $field): ?array
    {
        $source = (string) ($field['source'] ?? '');
        if ('' === $source) {
            return null;
        }
        $cacheKey = md5((string) json_encode([$source, $field['source_table'] ?? '', $field['source_label'] ?? '', $field['source_value'] ?? '', $field['source_filter_field'] ?? '', $field['source_filter_value'] ?? '', $field['source_order'] ?? '']));
        if (isset(self::$cache[$cacheKey])) {
            return self::$cache[$cacheKey];
        }
        $rows = [];
        try {
            if ('yform' === $source) {
                $rows = self::fromYform($field);
            } elseif (str_starts_with($source, 'query:')) {
                $q = self::queries()[substr($source, 6)] ?? null;
                if ($q && self::isSafeSelect((string) $q['sql'])) {
                    foreach (rex_sql::factory()->getArray(str_replace('rex_', rex::getTablePrefix(), (string) $q['sql'])) as $row) {
                        $row = array_values($row);
                        $rows[] = ['value' => (string) ($row[0] ?? ''), 'label' => (string) ($row[1] ?? $row[0] ?? '')];
                    }
                }
            } elseif (str_starts_with($source, 'ep:')) {
                $s = self::custom()[substr($source, 3)] ?? null;
                if ($s && is_callable($s['options'] ?? null)) {
                    foreach ((array) ($s['options'])($field) as $row) {
                        $rows[] = ['value' => (string) ($row['value'] ?? ''), 'label' => (string) ($row['label'] ?? $row['value'] ?? '')];
                    }
                }
            }
        } catch (Throwable $e) {
            rex_logger::logException($e);
            $rows = [];
        }
        $rows = array_values(array_filter($rows, static fn (array $r): bool => '' !== trim($r['value'])));
        return self::$cache[$cacheKey] = $rows;
    }

    /**
     * Nur einfache Lese-Abfragen zulassen (Schutz vor Versehen; angelegt werden sie ohnehin nur von Admins).
     */
    public static function isSafeSelect(string $sql): bool
    {
        $sql = trim($sql);
        return (bool) preg_match('/^SELECT\s/i', $sql)
            && !str_contains($sql, ';')
            && !preg_match('/\b(INSERT|UPDATE|DELETE|REPLACE|DROP|ALTER|CREATE|TRUNCATE|GRANT|INTO\s+OUTFILE|LOAD_FILE|SLEEP|BENCHMARK)\b/i', $sql);
    }

    /** @return list<array{value: string, label: string}> */
    private static function fromYform(array $field): array
    {
        $tableName = (string) ($field['source_table'] ?? '');
        $table = class_exists(rex_yform_manager_table::class) ? rex_yform_manager_table::get($tableName) : null;
        if (!$table) {
            return [];
        }
        // Spalten nur aus den tatsächlich vorhandenen Feldern der Tabelle (kein freies SQL)
        $columns = ['id' => true];
        foreach ($table->getValueFields() as $f) {
            $columns[$f->getName()] = true;
        }
        $label = (string) ($field['source_label'] ?? '');
        $value = (string) ($field['source_value'] ?? 'id');
        if (!isset($columns[$label])) {
            return [];
        }
        $value = isset($columns[$value]) ? $value : 'id';
        $sql = rex_sql::factory();
        $query = 'SELECT ' . $sql->escapeIdentifier($value) . ' AS v, ' . $sql->escapeIdentifier($label) . ' AS l FROM ' . $sql->escapeIdentifier($tableName);
        $params = [];
        $filterField = (string) ($field['source_filter_field'] ?? '');
        if ('' !== $filterField && isset($columns[$filterField])) {
            $query .= ' WHERE ' . $sql->escapeIdentifier($filterField) . ' = ?';
            $params[] = (string) ($field['source_filter_value'] ?? '');
        }
        $order = (string) ($field['source_order'] ?? '');
        $query .= ' ORDER BY ' . $sql->escapeIdentifier(isset($columns[$order]) ? $order : $label) . ' LIMIT 1000';
        $rows = [];
        foreach ($sql->getArray($query, $params) as $row) {
            $rows[] = ['value' => (string) $row['v'], 'label' => (string) $row['l']];
        }
        return $rows;
    }
}
