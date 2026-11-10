<?php

namespace FriendsOfRedaxo\FormStudio\Api;

use FriendsOfRedaxo\FormStudio\Form;
use FriendsOfRedaxo\FormStudio\Renderer;
use rex;
use rex_api_exception;
use rex_api_function;
use rex_api_result;
use rex_csrf_token;
use rex_extension;
use rex_extension_point;
use rex_response;
use Throwable;

use function is_array;
use function strlen;

/**
 * Live-Vorschau im Builder: rendert den aktuellen, noch nicht gespeicherten Stand (Definition + Einstellungen)
 * mit dem normalen Frontend-Renderer und liefert ein eigenständiges HTML-Dokument für ein iframe.
 *
 * Nur im Backend mit Form-Studio-Recht und gültigem CSRF-Token der Builder-Seite (form_studio_forms).
 * Absenden ist in der Vorschau abgeschaltet.
 *
 * Eigene Frontend-Styles (damit die Vorschau wie die Website aussieht) per Extension Point:
 *   rex_extension::register('FORM_STUDIO_PREVIEW_HEAD', fn (rex_extension_point $ep) => $ep->getSubject() . '<link rel="stylesheet" href="…">');
 */
class Preview extends rex_api_function
{
    protected $published = false;

    private const MAX_LENGTH = 500000;

    public function execute(): rex_api_result
    {
        rex_response::cleanOutputBuffers();
        $user = rex::getUser();
        if (null === $user || (!$user->isAdmin() && !$user->hasPerm('form_studio[]'))) {
            throw new rex_api_exception('Keine Berechtigung.');
        }
        if (!rex_csrf_token::factory('form_studio_forms')->isValid()) {
            throw new rex_api_exception('Ungültiges CSRF-Token.');
        }

        $definitionJson = rex_post('definition', 'string', '');
        $settingsJson = rex_post('settings', 'string', '');
        $step = max(0, rex_post('step', 'int', 0));
        $body = '';
        if (strlen($definitionJson) > self::MAX_LENGTH || strlen($settingsJson) > self::MAX_LENGTH) {
            $body = '<p class="fs-preview-note">Das Formular ist für die Vorschau zu groß.</p>';
        } else {
            $definition = json_decode($definitionJson, true);
            $settings = json_decode($settingsJson, true);
            try {
                $form = Form::preview(is_array($definition) ? $definition : ['steps' => []], is_array($settings) ? $settings : [], rex_post('name', 'string', 'Vorschau'));
                $body = Renderer::render($form);
            } catch (Throwable $e) {
                $body = '<p class="fs-preview-note fs-preview-note--error">Vorschau nicht möglich: ' . rex_escape($e->getMessage()) . '</p>';
            }
        }

        rex_response::setHeader('X-Robots-Tag', 'noindex');
        rex_response::sendContent($this->document($body, $step), 'text/html; charset=utf-8');
        exit;
    }

    private function document(string $body, int $step): string
    {
        // Relative Asset-Pfade („../assets/…“ im Backend) gegen den Backend-Ordner auflösen – das iframe nutzt srcdoc
        $scheme = (!empty($_SERVER['HTTPS']) && 'off' !== $_SERVER['HTTPS']) ? 'https' : 'http';
        $base = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . rtrim(dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/redaxo/index.php')), '/') . '/';
        $head = (string) rex_extension::registerPoint(new rex_extension_point('FORM_STUDIO_PREVIEW_HEAD', ''));

        return '<!DOCTYPE html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<base href="' . rex_escape($base) . '"><meta name="robots" content="noindex">' . $head
            . '<style>body{margin:0;padding:24px}.fs-preview-banner{margin:0 0 16px;padding:6px 10px;border-radius:4px;background:#fff3cd;color:#664d03;font:13px/1.4 system-ui,sans-serif}.fs-preview-note{font:14px/1.5 system-ui,sans-serif;color:#555}.fs-preview-note--error{color:#a3261c}@keyframes fsPf{0%{box-shadow:0 0 0 3px #f0ad4e}100%{box-shadow:0 0 0 3px rgba(240,173,78,0)}}.fs-preview-focus{animation:fsPf 1.6s ease-out;border-radius:4px}</style>'
            . '</head><body class="fs-preview">'
            . '<p class="fs-preview-banner" role="note">Vorschau – Absenden ist hier ausgeschaltet.</p>'
            . $body
            . '<script>'
            // Absenden verhindern
            . 'document.addEventListener("submit",function(e){e.preventDefault();e.stopImmediatePropagation();},true);'
            // im Builder gewählten Schritt zeigen (nach der Initialisierung von form-studio.js)
            . 'window.addEventListener("load",function(){var n=' . $step . ';var f=document.querySelector("[data-fs-form]");if(!f||!n){return;}'
            . 'var steps=[].slice.call(f.querySelectorAll("[data-fs-step]"));if(!steps[n]){return;}'
            . 'steps.forEach(function(s,i){s.hidden=i!==n;});'
            . '[].forEach.call(f.querySelectorAll("[data-fs-progress-item]"),function(p){var i=+p.getAttribute("data-fs-progress-item");p.classList.toggle("is-current",i===n);p.classList.toggle("is-done",i<n);});'
            . 'var last=n===steps.length-1;[].forEach.call(f.querySelectorAll("[data-fs-next]"),function(b){b.hidden=last;});var sb=f.querySelector("[type=submit]");if(sb&&sb.parentNode){sb.parentNode.hidden=!last;}});'
            // Höhe an den Builder melden
            . 'function fsH(){parent.postMessage({fsPreviewHeight:document.documentElement.scrollHeight},"*");}'
            . 'window.addEventListener("load",function(){fsH();parent.postMessage({fsPreviewReady:1},"*");});new ResizeObserver(fsH).observe(document.body);'
            // im Builder gewähltes Feld anspringen und kurz hervorheben – nur im iframe scrollen
            // (scrollIntoView würde auch die Builder-Seite zur Vorschau ziehen)
            . 'window.addEventListener("message",function(e){if(!e.data||!e.data.fsFocus){return;}var f=document.querySelector("[data-fs-field=\""+String(e.data.fsFocus).replace(/[^a-z0-9_]/g,"")+"\"]");if(!f){return;}'
            . 'var st=f.closest("[data-fs-step]");if(st&&st.hidden){return;}var r=f.getBoundingClientRect();window.scrollTo({top:Math.max(0,window.scrollY+r.top-(window.innerHeight-r.height)/2),behavior:"smooth"});f.classList.remove("fs-preview-focus");void f.offsetWidth;f.classList.add("fs-preview-focus");});'
            . '</script></body></html>';
    }
}
