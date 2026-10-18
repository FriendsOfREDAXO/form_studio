<?php

namespace FriendsOfRedaxo\FormStudio;

use Closure;
use rex;
use rex_addon;
use rex_fragment;
use rex_sql;
use rex_template;
use rex_template_cache;
use Throwable;

/**
 * Eigene PDF-Vorlagen als REDAXO-Templates (Schlüssel beginnt mit „form_studio_pdf“).
 * Redakteure mit Template-Rechten passen sie unter „Templates“ an; im Formular wird die Vorlage ausgewählt.
 * Im Template stehen dieselben Variablen wie im Fragment form_studio/pdf.php bereit ($this->getVar('form') usw.).
 */
final class PdfTemplates
{
    public const KEY_PREFIX = 'form_studio_pdf';

    /** @return array<string, string> Schlüssel => Name */
    public static function all(): array
    {
        $list = [];
        $rows = rex_sql::factory()->getArray('SELECT `key`, name FROM ' . rex::getTable('template') . ' WHERE `key` LIKE ? ORDER BY name', [self::KEY_PREFIX . '%']);
        foreach ($rows as $row) {
            $list[(string) $row['key']] = (string) $row['name'];
        }
        return $list;
    }

    /** Legt eine neue Vorlage als Kopie des Standard-Layouts an (inaktiv, also nicht als Seiten-Template wählbar). */
    public static function create(string $name): int
    {
        $existing = self::all();
        $key = self::KEY_PREFIX;
        for ($i = 2; isset($existing[$key]); ++$i) {
            $key = self::KEY_PREFIX . '_' . $i;
        }
        $content = (string) file_get_contents(rex_addon::get('form_studio')->getPath('fragments/form_studio/pdf.php'));
        $sql = rex_sql::factory()->setTable(rex::getTable('template'));
        $sql->setValue('key', $key);
        $sql->setValue('name', $name);
        $sql->setValue('content', $content);
        $sql->setValue('active', 0);
        $sql->setValue('attributes', json_encode(['ctype' => [], 'modules' => [1 => ['all' => '1']], 'categories' => ['all' => '1']]));
        $sql->addGlobalCreateFields();
        $sql->addGlobalUpdateFields();
        $sql->insert();
        $id = (int) $sql->getLastId();
        rex_template_cache::delete($id);
        return $id;
    }

    /** HTML der Vorlage oder null, wenn es sie nicht (mehr) gibt – dann gilt das Standard-Layout. */
    public static function render(string $key, rex_fragment $fragment): ?string
    {
        if (!str_starts_with($key, self::KEY_PREFIX)) {
            return null;
        }
        $template = rex_template::forKey($key);
        $file = $template?->getFile();
        if (!$file) {
            return null;
        }
        // Wie ein Fragment ausführen: $this ist das Fragment mit form, submission, sender, logo
        $run = Closure::bind(function (string $__file): string {
            ob_start();
            try {
                include $__file;
            } catch (Throwable $e) {
                ob_end_clean();
                throw $e;
            }
            return (string) ob_get_clean();
        }, $fragment, rex_fragment::class);
        return $run($file);
    }
}
