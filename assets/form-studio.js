/* Form Studio – Frontend (Bedingungen, Schritte, Zusammenfassung, Zähler). Ohne Abhängigkeiten. */
(function () {
    'use strict';

    // Gleiche Logik wie lib/Conditions.php
    function test(actual, op, expected) {
        var list = Array.isArray(actual) ? actual.map(String) : (actual === '' || actual == null ? [] : [String(actual)]);
        expected = expected == null ? '' : String(expected);
        var lower = expected.toLowerCase();
        switch (op) {
            case 'equals': return list.indexOf(expected) !== -1;
            case 'not_equals': return list.indexOf(expected) === -1;
            case 'contains': return expected !== '' && list.some(function (v) { return v.toLowerCase().indexOf(lower) !== -1; });
            case 'not_contains': return !(expected !== '' && list.some(function (v) { return v.toLowerCase().indexOf(lower) !== -1; }));
            case 'in': var set = expected.split(',').map(function (s) { return s.trim(); }); return list.some(function (v) { return set.indexOf(v) !== -1; });
            case 'not_empty': return list.length > 0;
            case 'empty': return list.length === 0;
            case 'gt': return list.length > 0 && parseFloat(list[0]) > parseFloat(expected);
            case 'gte': return list.length > 0 && parseFloat(list[0]) >= parseFloat(expected);
            case 'lt': return list.length > 0 && parseFloat(list[0]) < parseFloat(expected);
            case 'lte': return list.length > 0 && parseFloat(list[0]) <= parseFloat(expected);
            default: return true;
        }
    }

    function config(node) {
        try { return JSON.parse(node.getAttribute('data-fs-config') || '{}') || {}; } catch (e) { return {}; }
    }

    function format(text, args) {
        return String(text || '').replace(/\{(\d+)\}/g, function (m, i) { return args[+i] == null ? '' : args[+i]; });
    }

    // Ereignisse für Statistik/Tracking: CustomEvent am Element, optional window.dataLayer (Einstellung „datalayer“)
    function emit(target, cfg, name, detail) {
        detail = detail || {};
        var wrap = target.closest('.fs');
        detail.form = cfg.key || (wrap ? wrap.id.replace(/^fs-/, '') : '');
        var ev;
        try {
            ev = new CustomEvent('fs:' + name, { bubbles: true, detail: detail });
        } catch (e) {
            ev = document.createEvent('CustomEvent');
            ev.initCustomEvent('fs:' + name, true, false, detail);
        }
        target.dispatchEvent(ev);
        if (cfg.datalayer && window.dataLayer && typeof window.dataLayer.push === 'function') {
            var data = { event: 'fs_' + name.replace(/-/g, '_') };
            Object.keys(detail).forEach(function (k) { data['fs_' + k] = detail[k]; });
            window.dataLayer.push(data);
        }
    }

    function store() {
        try { return window.localStorage || null; } catch (e) { return null; }
    }

    function dropDraft(key) {
        var ls = store();
        if (!ls || !key) { return; }
        try { ls.removeItem(key); } catch (e) { /* Speicher gesperrt */ }
    }

    function reducedMotion() {
        return !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
    }

    function init(form) {
        var cfg = config(form);
        var i18n = cfg.i18n || {};
        // Spamschutz: Nachweis, dass ein echter Browser das Formular ausfüllt (erst bei Interaktion)
        var proof = form.querySelector('[data-fs-proof]');
        if (proof) {
            var arm = function () {
                proof.value = proof.getAttribute('data-fs-proof').split('').reverse().join('');
                form.removeEventListener('focusin', arm);
                form.removeEventListener('pointerdown', arm);
            };
            form.addEventListener('focusin', arm);
            form.addEventListener('pointerdown', arm);
        }
        var steps = Array.prototype.slice.call(form.querySelectorAll('[data-fs-step]'));
        var progress = Array.prototype.slice.call(form.querySelectorAll('[data-fs-progress-item]'));
        var review = form.querySelector('[data-fs-review]');
        var submit = form.querySelector('[data-fs-submit]');
        var multi = steps.length > 1;
        var current = 0;
        var shown = null; // zuletzt gezeigter Schritt (für Ereignisse und Übergänge)
        var reached = 0; // weitester erreichter Schritt (klickbarer Fortschritt)
        var live = form.querySelector('[data-fs-live]');
        var stepCount = form.querySelector('[data-fs-stepcount]');

        function stepTitle(step, index) {
            var legend = step && step.querySelector('legend');
            return legend ? legend.textContent.trim() : 'Schritt ' + (index + 1);
        }

        // Übergang zwischen den Schritten (Einstellung „transition“); bei reduzierter Bewegung aus
        function animate(node, dir) {
            if (!cfg.transition || reducedMotion() || !node) { return; }
            var cls = 'fs-anim-' + cfg.transition;
            node.classList.remove('fs-anim-slide', 'fs-anim-fade', 'fs-anim--back');
            void node.offsetWidth;
            form.classList.add('fs-form--animating');
            node.classList.add(cls);
            node.classList.toggle('fs-anim--back', dir < 0);
            var done = function () {
                node.classList.remove(cls, 'fs-anim--back');
                form.classList.remove('fs-form--animating');
                node.removeEventListener('animationend', done);
            };
            node.addEventListener('animationend', done);
            setTimeout(done, 600);
        }

        // Klickbarer Fortschritt (Einstellung „progress_nav“): erreichte Schritte als Schaltfläche
        function progressButton(item, clickable, isCurrent) {
            var btn = item.querySelector('.fs-progress__btn');
            if (clickable && !btn) {
                btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'fs-progress__btn';
                btn.setAttribute('data-fs-jump', item.getAttribute('data-fs-progress-item'));
                while (item.firstChild) { btn.appendChild(item.firstChild); }
                item.appendChild(btn);
            } else if (!clickable && btn) {
                while (btn.firstChild) { item.insertBefore(btn.firstChild, btn); }
                item.removeChild(btn);
                btn = null;
            }
            if (btn) {
                // aria-current gehört an das bedienbare Element
                item.removeAttribute('aria-current');
                if (isCurrent) { btn.setAttribute('aria-current', 'step'); } else { btn.removeAttribute('aria-current'); }
            }
        }

        function values() {
            var out = {};
            Array.prototype.forEach.call(form.elements, function (el) {
                var m = el.name && el.name.match(/^fs\[([^\]]+)\](\[\])?$/);
                if (!m || el.disabled) { return; }
                var name = m[1];
                if (el.type === 'checkbox' && m[2]) {
                    out[name] = out[name] || [];
                    if (el.checked) { out[name].push(el.value); }
                } else if (el.type === 'radio') {
                    if (el.checked) { out[name] = el.value; } else if (!(name in out)) { out[name] = ''; }
                } else if (el.type === 'checkbox') {
                    out[name] = el.checked ? el.value : '';
                } else {
                    out[name] = el.value;
                }
            });
            return out;
        }

        function visibleFor(node, vals, visible) {
            var raw = node.getAttribute('data-fs-show-if');
            if (!raw) { return true; }
            var cond;
            try { cond = JSON.parse(raw); } catch (e) { return true; }
            var results = (cond.rules || []).map(function (r) {
                var v = visible[r.field] === false ? '' : (vals[r.field] == null ? '' : vals[r.field]);
                return test(v, r.op || 'equals', r.value);
            });
            return cond.logic === 'any' ? results.indexOf(true) !== -1 : results.indexOf(false) === -1;
        }

        function setDisabled(container, disabled) {
            Array.prototype.forEach.call(container.querySelectorAll('input, select, textarea, button[data-fs-inc], button[data-fs-dec]'), function (el) {
                el.disabled = disabled;
            });
        }

        // Bedingungen auswerten (Reihenfolge der Definition)
        function apply() {
            var vals = values();
            var visible = {};
            steps.forEach(function (step, i) {
                var stepVisible = visibleFor(step, vals, visible);
                step.toggleAttribute('data-fs-skipped', !stepVisible);
                if (progress[i]) { progress[i].hidden = !stepVisible; }
                Array.prototype.forEach.call(step.querySelectorAll('[data-fs-field]'), function (field) {
                    var name = field.getAttribute('data-fs-field');
                    var show = stepVisible && visibleFor(field, vals, visible);
                    if (name) { visible[name] = show; }
                    field.hidden = !show;
                    setDisabled(field, !show);
                });
                if (!multi) { step.hidden = !stepVisible; }
            });
            // Klickbarer Fortschritt folgt ein- und ausgeblendeten Schritten
            if (cfg.nav && shown) {
                var list = visibleSteps();
                progress.forEach(function (p) {
                    var key = p.getAttribute('data-fs-progress-item');
                    var i = 'review' === key ? list.length : list.indexOf(steps[+key]);
                    progressButton(p, i !== -1 && !p.hidden && i <= reached, i === current);
                });
            }
            // Zähler „Schritt 2 von 5“ folgt übersprungenen Schritten sofort
            if (stepCount && shown && shown !== review && visibleSteps().indexOf(shown) !== -1) {
                stepCount.textContent = format(i18n.step, [visibleSteps().indexOf(shown) + 1, visibleSteps().length]);
            }
            // Werte ausgeblendeter Felder zählen nicht – neu einlesen für abhängige Bedingungen
            return visible;
        }

        function visibleSteps() {
            return steps.filter(function (s) { return !s.hasAttribute('data-fs-skipped'); });
        }

        // Ansichten: sichtbare Schritte, danach (falls vorhanden) der Reiter „Überprüfen“
        function show(index, focus) {
            var list = visibleSteps();
            var max = review ? list.length : list.length - 1;
            index = Math.max(0, Math.min(index, max));
            var previous = current;
            var target = review && index === list.length ? review : list[index];
            var changed = shown !== target;
            current = index;
            reached = Math.max(reached, index);
            var inReview = review && index === list.length;
            steps.forEach(function (s) { s.hidden = inReview || s !== list[index]; });
            if (review) {
                review.hidden = !inReview;
                if (inReview) { buildSummary(); }
            }
            Array.prototype.forEach.call(form.querySelectorAll('[data-fs-next]'), function (b) { b.hidden = !review && index === max; });
            Array.prototype.forEach.call(form.querySelectorAll('[data-fs-prev]'), function (b) { b.hidden = false; });
            if (submit) { submit.parentNode.hidden = index !== max; }
            progress.forEach(function (p) {
                var key = p.getAttribute('data-fs-progress-item');
                var i = 'review' === key ? list.length : list.indexOf(steps[+key]);
                if ('review' === key) { p.hidden = false; }
                p.classList.toggle('is-done', i !== -1 && i < index);
                p.classList.toggle('is-current', i === index);
                if (i === index) { p.setAttribute('aria-current', 'step'); } else { p.removeAttribute('aria-current'); }
                if (cfg.nav) { progressButton(p, i !== -1 && !p.hidden && i <= reached, i === index); }
            });
            var title = inReview ? (i18n.review || '') : stepTitle(list[index], index);
            if (stepCount) {
                stepCount.textContent = inReview ? title : format(i18n.step, [index + 1, list.length]);
                stepCount.hidden = false;
            }
            if (changed && shown) {
                if (live) { live.textContent = inReview ? title : format(i18n.stepLive, [index + 1, list.length, title]); }
                if (focus) { animate(target, index < previous ? -1 : 1); }
            }
            if (changed) {
                shown = target;
                emit(form, cfg, 'step', { index: index + 1, total: list.length, title: title, review: !!inReview });
            }
            if (focus) {
                var heading = inReview ? review.querySelector('h3') : (list[index].querySelector('legend') || list[index]);
                heading.setAttribute('tabindex', '-1');
                heading.focus({ preventScroll: true });
                form.closest('.fs').scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        }

        // a11y_datetime legt ein sichtbares Ersatzfeld an – Label, Beschreibung und Pflicht dorthin übertragen
        function linkPickers() {
            Array.prototype.forEach.call(form.querySelectorAll('input[data-a11y-fs]'), function (orig) {
                var fp = orig._flatpickr;
                if (!fp || !fp.altInput || orig.getAttribute('data-fs-linked')) { return; }
                var alt = fp.altInput;
                alt.id = orig.id;
                orig.id = orig.id + '-value';
                ['aria-describedby', 'aria-required', 'aria-invalid'].forEach(function (a) {
                    if (orig.hasAttribute(a)) { alt.setAttribute(a, orig.getAttribute(a)); }
                });
                if (orig.hasAttribute('data-fs-required') || orig.required) { alt.required = true; alt.setAttribute('aria-required', 'true'); }
                alt.setAttribute('autocomplete', 'off');
                // aria-expanded/aria-haspopup sind nur mit einer passenden Rolle gültig (a11y_datetime setzt keine)
                if (alt.hasAttribute('aria-expanded') && !alt.hasAttribute('role')) { alt.setAttribute('role', 'combobox'); }
                orig.setAttribute('data-fs-linked', '1');
            });
        }
        window.addEventListener('load', linkPickers);
        document.addEventListener('DOMContentLoaded', function () { setTimeout(linkPickers, 0); });
        // Nachgeladen (z. B. im Ajax-Modal): Picker entstehen erst danach – kurz nachprüfen
        (function poll(tries) {
            linkPickers();
            if (tries < 20 && form.querySelector('input[data-a11y-fs]:not([data-fs-linked])')) { setTimeout(function () { poll(tries + 1); }, 250); }
        })(0);

        // Eigene, einheitliche Meldungen (Sie-Form) statt der Browsertexte
        function message(el) {
            var v = el.validity;
            if (v.valueMissing) {
                if (el.type === 'radio') { return 'Bitte wählen Sie eine Option.'; }
                if (el.type === 'checkbox') { return el.closest('.fs-consent, .fs-switch') ? 'Bitte bestätigen Sie diesen Punkt.' : 'Bitte wählen Sie mindestens eine Option.'; }
                if (el.tagName === 'SELECT') { return 'Bitte wählen Sie einen Eintrag.'; }
                return 'Bitte füllen Sie dieses Feld aus.';
            }
            if (v.typeMismatch && el.type === 'email') { return 'Bitte geben Sie eine gültige E-Mail-Adresse an.'; }
            if (v.rangeUnderflow) { return 'Der Wert muss mindestens ' + el.min + ' sein.'; }
            if (v.rangeOverflow) { return 'Der Wert darf höchstens ' + el.max + ' sein.'; }
            if (v.patternMismatch || v.badInput || v.typeMismatch) { return 'Bitte prüfen Sie Ihre Eingabe.'; }
            return el.validationMessage;
        }

        function stepValid(step) {
            linkPickers();
            var ok = true;
            var first = null;
            // Datums-Picker (a11y_datetime): das Originalfeld ist versteckt – Pflicht am sichtbaren Ersatzfeld prüfen
            Array.prototype.forEach.call(step.querySelectorAll('input[data-a11y-fs]'), function (orig) {
                if (orig.disabled || orig.closest('[hidden]')) { return; }
                var alt = (orig._flatpickr && orig._flatpickr.altInput) || orig;
                var error = orig.closest('[data-fs-field]') && orig.closest('[data-fs-field]').querySelector('.fs-error');
                if ((orig.required || alt.required) && !orig.value) {
                    ok = false;
                    first = first || alt;
                    alt.setAttribute('aria-invalid', 'true');
                    if (error) { error.textContent = 'Bitte wählen Sie ' + (orig.getAttribute('data-noCalendar') === 'true' ? 'eine Uhrzeit' : 'ein Datum') + '.'; }
                } else {
                    alt.removeAttribute('aria-invalid');
                    if (error && alt !== orig) { error.textContent = ''; }
                }
            });
            Array.prototype.forEach.call(step.querySelectorAll('input, select, textarea'), function (el) {
                // Ersatzfelder der Datums-Picker (ohne name) wurden oben geprüft
                if (el.disabled || el.closest('[hidden]') || !el.name || el.hasAttribute('data-a11y-fs')) { return; }
                var error = el.closest('[data-fs-field]') && el.closest('[data-fs-field]').querySelector('.fs-error');
                if (!el.checkValidity()) {
                    ok = false;
                    first = first || el;
                    el.setAttribute('aria-invalid', 'true');
                    if (error) { error.textContent = message(el); }
                } else if (el.getAttribute('aria-invalid') === 'true' && el.type !== 'radio' && el.type !== 'checkbox') {
                    el.removeAttribute('aria-invalid');
                    if (error) { error.textContent = ''; }
                }
            });
            // Gruppen (Radios/Checkboxen): Fehlertext bei gelöster Gruppe entfernen
            Array.prototype.forEach.call(step.querySelectorAll('[data-fs-field]'), function (field) {
                var inputs = field.querySelectorAll('input[type=radio], input[type=checkbox]');
                if (inputs.length && Array.prototype.every.call(inputs, function (i) { return i.checkValidity(); })) {
                    var e = field.querySelector('.fs-error');
                    if (e) { e.textContent = ''; }
                    Array.prototype.forEach.call(inputs, function (i) { i.removeAttribute('aria-invalid'); });
                }
            });
            if (first) { first.focus(); }
            return ok;
        }

        function el(tag, cls, text) {
            var node = document.createElement(tag);
            if (cls) { node.className = cls; }
            if (text != null) { node.textContent = text; }
            return node;
        }

        // Zusammenfassung je Schritt als eigene Gruppe mit „Ändern“
        function buildSummary() {
            var box = review && review.querySelector('[data-fs-summary-list]');
            if (!box) { return; }
            box.innerHTML = '';
            visibleSteps().forEach(function (step, index) {
                var legend = step.querySelector('legend');
                var title = legend ? legend.textContent.trim() : 'Schritt ' + (index + 1);
                var group = el('section', 'fs-review__group');
                var head = el('div', 'fs-review__head');
                var h = el('h4', 'fs-review__title');
                h.appendChild(el('span', 'fs-review__nr', String(index + 1)));
                h.appendChild(document.createTextNode(title));
                var edit = el('button', 'fs-link-button fs-review__edit', 'Ändern');
                edit.type = 'button';
                edit.setAttribute('data-fs-goto', String(index));
                edit.setAttribute('aria-label', title + ' ändern');
                head.appendChild(h);
                head.appendChild(edit);
                group.appendChild(head);
                var dl = el('dl', 'fs-review__list');
                summarizeStep(step, dl);
                if (dl.children.length) {
                    group.appendChild(dl);
                } else {
                    group.appendChild(el('p', 'fs-review__empty', 'Keine Angaben'));
                }
                box.appendChild(group);
            });
        }

        function summarizeStep(step, dl) {
            Array.prototype.forEach.call(step.querySelectorAll('[data-fs-field]'), function (field) {
                // Einwilligungen gehören nicht in die Übersicht
                if (field.hidden || field.querySelector('.fs-consent')) { return; }
                var labelEl = field.querySelector('.fs-label');
                var text = [];
                Array.prototype.forEach.call(field.querySelectorAll('input, select, textarea'), function (el) {
                    if (el.disabled || !el.name || (el.type === 'hidden' && !el.hasAttribute('data-a11y-fs'))) { return; }
                    if (el.getAttribute('role') === 'switch') {
                        text.push(el.getAttribute(el.checked ? 'data-fs-on' : 'data-fs-off') || (el.checked ? 'Ja' : 'Nein'));
                    } else if ((el.type === 'radio' || el.type === 'checkbox')) {
                        if (el.checked) {
                            var l = el.closest('label');
                            text.push(l ? (l.querySelector('.fs-card__label') || l).textContent.trim() : el.value);
                        }
                    } else if (el.tagName === 'SELECT') {
                        if (el.value) { text.push(el.options[el.selectedIndex].text); }
                    } else if ((el.type === 'date' || el.hasAttribute('data-a11y-fs')) && /^\d{4}-\d{2}-\d{2}$/.test(el.value)) {
                        text.push(el.value.split('-').reverse().join('.'));
                    } else if ((el.type === 'time' || el.getAttribute('data-noCalendar') === 'true') && el.value) {
                        text.push(el.value + ' Uhr');
                    } else if (el.value && !(field.querySelector('[data-fs-counter]') && +el.value === 0)) {
                        // Zähler mit 0 (z. B. „0 Zimmer“) weglassen
                        var unit = field.querySelector('.fs-counter__unit, .fs-unit__label');
                        text.push(el.value + (unit ? ' ' + unit.textContent : ''));
                    }
                });
                if (!text.length || !labelEl) { return; }
                var item = el('div', 'fs-review__item' + (field.querySelector('textarea') ? ' fs-review__item--wide' : ''));
                item.appendChild(el('dt', null, labelEl.textContent.replace(/\s*\*\s*$/, '').trim()));
                item.appendChild(el('dd', null, text.join(', ')));
                dl.appendChild(item);
            });
        }

        // Zähler −/+
        Array.prototype.forEach.call(form.querySelectorAll('[data-fs-counter]'), function (c) {
            var input = c.querySelector('input');
            function change(dir) {
                var step = parseFloat(input.step) || 1;
                var v = (parseFloat(input.value) || 0) + dir * step;
                if (input.min !== '') { v = Math.max(parseFloat(input.min), v); }
                if (input.max !== '') { v = Math.min(parseFloat(input.max), v); }
                input.value = v;
                input.dispatchEvent(new Event('input', { bubbles: true }));
            }
            c.querySelector('[data-fs-dec]').addEventListener('click', function () { change(-1); });
            c.querySelector('[data-fs-inc]').addEventListener('click', function () { change(1); });
        });

        form.addEventListener('input', apply);
        form.addEventListener('change', apply);
        // Fehlermeldung eines Felds sofort entfernen, sobald es gültig ist (Auswahl, Karten, Checkboxen, Eingaben).
        // Schon beim Tippen (input), nicht erst beim Verlassen: sonst verschiebt sich das Layout unter dem Mauszeiger und der Klick auf „Weiter“ geht ins Leere.
        var clearFieldError = function (e) {
            var field = e.target.closest && e.target.closest('[data-fs-field]');
            if (!field || e.target.hasAttribute('data-a11y-fs')) { return; }
            var inputs = field.querySelectorAll('input[name], select[name], textarea[name]');
            var valid = Array.prototype.every.call(inputs, function (i) { return i.disabled || i.checkValidity(); });
            if (!valid) { return; }
            Array.prototype.forEach.call(inputs, function (i) { i.removeAttribute('aria-invalid'); });
            var error = field.querySelector('.fs-error');
            if (error) { error.textContent = ''; }
        };
        form.addEventListener('input', clearFieldError);
        form.addEventListener('change', clearFieldError);
        // Datums-Picker: Fehlermeldung sofort entfernen, sobald ein Wert gewählt wurde
        form.addEventListener('change', function (e) {
            var orig = e.target;
            if (!orig.matches || !orig.matches('input[data-a11y-fs]') || !orig.value) { return; }
            var alt = (orig._flatpickr && orig._flatpickr.altInput) || orig;
            alt.removeAttribute('aria-invalid');
            var field = orig.closest('[data-fs-field]');
            var error = field && field.querySelector('.fs-error');
            if (error) { error.textContent = ''; }
        });

        if (multi) {
            form.classList.add('fs-form--wizard');
            // Absenden neben „Zurück“ im Reiter „Überprüfen“
            var reviewNav = review && review.querySelector('.fs-nav');
            if (reviewNav && submit) {
                var wrap = submit.parentNode;
                reviewNav.replaceChild(wrap, reviewNav.lastElementChild);
                wrap.classList.add('fs-submit--inline');
            }
            form.addEventListener('click', function (e) {
                var gotoBtn = e.target.closest('[data-fs-goto]');
                if (e.target.closest('[data-fs-next]')) {
                    e.preventDefault();
                    if (stepValid(visibleSteps()[current])) { show(current + 1, true); }
                } else if (e.target.closest('[data-fs-prev]')) {
                    e.preventDefault();
                    show(current - 1, true);
                } else if (gotoBtn) {
                    e.preventDefault();
                    show(+gotoBtn.getAttribute('data-fs-goto'), true);
                } else if (e.target.closest('[data-fs-jump]')) {
                    e.preventDefault();
                    var key = e.target.closest('[data-fs-jump]').getAttribute('data-fs-jump');
                    jump('review' === key ? visibleSteps().length : visibleSteps().indexOf(steps[+key]));
                }
            });
        }

        // Sprung über den Fortschritt: vorwärts nur, wenn alle Schritte davor gültig sind
        function jump(target) {
            if (target < 0) { return; }
            if (target <= current) {
                if (target !== current) { show(target, true); }
                return;
            }
            var list = visibleSteps();
            for (var k = current; k < Math.min(target, list.length); k++) {
                var step = list[k];
                var was = step.hidden;
                step.hidden = false;
                var ok = stepValid(step);
                step.hidden = was;
                if (!ok) {
                    if (k !== current) { show(k, true); }
                    stepValid(step);
                    return;
                }
            }
            show(target, true);
        }

        // Stille Prüfung ohne Meldungen (für das automatische Weiterblättern)
        function quietlyValid(step) {
            return Array.prototype.every.call(step.querySelectorAll('input, select, textarea'), function (el) {
                if (el.disabled || el.closest('[hidden]') || !el.name) { return true; }
                if (el.hasAttribute('data-a11y-fs')) { return !el.required || !!el.value; }
                return el.checkValidity();
            });
        }

        // Automatisch weiter nach einer Einfachauswahl im letzten Feld des Schritts (Einstellung „auto_advance“).
        // Nicht bei Pfeiltasten – dort wählt man beim Durchgehen nur aus.
        if (multi && cfg.auto) {
            var arrowAt = 0;
            var autoTimer = null;
            form.addEventListener('keydown', function (e) {
                if (/^Arrow/.test(e.key)) { arrowAt = Date.now(); }
            });
            form.addEventListener('change', function (e) {
                var t = e.target;
                if (t.type !== 'radio' || !t.checked || Date.now() - arrowAt < 600) { return; }
                var step = visibleSteps()[current];
                if (!step || !step.contains(t)) { return; }
                var fields = Array.prototype.filter.call(step.querySelectorAll('[data-fs-field]'), function (f) {
                    return !f.hidden && f.querySelector('input:not([type=hidden]), select, textarea');
                });
                if (fields[fields.length - 1] !== t.closest('[data-fs-field]')) { return; }
                if (!review && current >= visibleSteps().length - 1) { return; }
                clearTimeout(autoTimer);
                autoTimer = setTimeout(function () {
                    if (visibleSteps()[current] === step && t.checked && quietlyValid(step)) { show(current + 1, true); }
                }, 350);
            });
        }

        form.addEventListener('submit', function (e) {
            apply();
            // Enter in einem Feld vor dem letzten Reiter: weiterblättern statt absenden
            if (multi && review && current < visibleSteps().length) {
                e.preventDefault();
                if (stepValid(visibleSteps()[current])) { show(current + 1, true); }
                return;
            }
            var invalid = visibleSteps().filter(function (s) { return !stepValid(s); });
            if (invalid.length) {
                e.preventDefault();
                if (multi) { show(visibleSteps().indexOf(invalid[0]), true); }
                return;
            }
            emit(form, cfg, 'submit', {});
            if (submit) { submit.disabled = true; submit.setAttribute('aria-busy', 'true'); }
        });

        // Entwurf im Browser (Einstellung „draft“): Eingaben je Formular in localStorage, nie Einwilligungen/Versteckte/Passwörter
        var restored = false;
        if (cfg.draft) {
            restored = draft();
        }

        apply();
        // Nach Serverfehlern: Schritt mit dem ersten Fehler zeigen
        var firstError = form.querySelector('[aria-invalid="true"]');
        if (multi) {
            var errorStep = firstError ? firstError.closest('[data-fs-step]') : null;
            show(errorStep ? Math.max(0, visibleSteps().indexOf(errorStep)) : 0, false);
        }
        var summaryBox = form.querySelector('[data-fs-error-summary]');
        if (summaryBox) { summaryBox.focus(); }
        if (restored) { emit(form, cfg, 'draft-restored', {}); }

        function draftable(el) {
            if (!el.name || !/^fs\[/.test(el.name) || /^(hidden|password|file|submit|button)$/.test(el.type)) { return false; }
            if (el.closest('.fs-consent')) { return false; }
            var auto = el.getAttribute('autocomplete') || '';
            return !/pass|pwd|iban|cvc|cvv|kreditkarte|credit/i.test(el.name) && !/password|^cc-|one-time-code/.test(auto);
        }

        function draft() {
            var ls = store();
            if (!ls) { return false; }
            var saveTimer = null;
            var save = function () {
                clearTimeout(saveTimer);
                saveTimer = null;
                var data = {};
                Array.prototype.forEach.call(form.elements, function (el) {
                    if (!draftable(el)) { return; }
                    if (el.type === 'radio' || el.type === 'checkbox') {
                        data[el.name] = data[el.name] || [];
                        if (el.checked) { data[el.name].push(el.value); }
                    } else {
                        data[el.name] = el.value;
                    }
                });
                try { ls.setItem(cfg.draft, JSON.stringify({ t: Date.now(), v: data })); } catch (e) { /* voll oder gesperrt */ }
            };
            var later = function () {
                clearTimeout(saveTimer);
                saveTimer = setTimeout(save, 400);
            };
            form.addEventListener('input', later);
            form.addEventListener('change', later);
            window.addEventListener('pagehide', function () { if (saveTimer) { save(); } });

            // Nach Serverfehlern oder mit Vorbelegung per Link gelten die Werte der Seite
            if (form.querySelector('[data-fs-error-summary]') || /[?&]fs_preset/.test(window.location.search)) { return false; }
            var stored = null;
            try { stored = JSON.parse(ls.getItem(cfg.draft) || 'null'); } catch (e) { stored = null; }
            if (!stored || !stored.v || typeof stored.v !== 'object') { return false; }
            if (!stored.t || Date.now() - stored.t > (cfg.draftDays || 7) * 864e5) {
                dropDraft(cfg.draft);
                return false;
            }
            var changed = false;
            Array.prototype.forEach.call(form.elements, function (el) {
                if (!draftable(el) || !(el.name in stored.v)) { return; }
                var v = stored.v[el.name];
                if (el.type === 'radio' || el.type === 'checkbox') {
                    var on = Array.isArray(v) && v.indexOf(el.value) !== -1;
                    if (el.checked !== on) { el.checked = on; changed = true; }
                } else if (typeof v === 'string' && el.value !== v) {
                    el.value = v;
                    changed = true;
                    if (el._flatpickr) { el._flatpickr.setDate(v || null, false); }
                }
            });
            if (!changed) { return false; }
            notice(save, function () { clearTimeout(saveTimer); saveTimer = null; });
            return true;
        }

        // Hinweis „Ihre Eingaben wurden wiederhergestellt“ mit „Eingaben löschen“
        function notice(save, stop) {
            var box = el('div', 'fs-draft');
            box.setAttribute('role', 'status');
            var text = el('p', 'fs-draft__text', i18n.restored || '');
            var btn = el('button', 'fs-link-button fs-draft__clear', i18n.clear || '');
            btn.type = 'button';
            box.appendChild(text);
            box.appendChild(btn);
            var anchor = form.querySelector('[data-fs-progress]') || form.querySelector('[data-fs-step]');
            anchor.parentNode.insertBefore(box, anchor);
            btn.addEventListener('click', function () {
                stop();
                dropDraft(cfg.draft);
                form.reset();
                Array.prototype.forEach.call(form.querySelectorAll('input[data-a11y-fs]'), function (orig) {
                    if (orig._flatpickr) { orig._flatpickr.setDate(orig.defaultValue || null, false); }
                });
                Array.prototype.forEach.call(form.querySelectorAll('[aria-invalid="true"]'), function (n) { n.removeAttribute('aria-invalid'); });
                Array.prototype.forEach.call(form.querySelectorAll('.fs-error'), function (n) { n.textContent = ''; });
                apply();
                reached = 0;
                if (multi) { show(0, false); }
                text.textContent = i18n.cleared || '';
                box.removeChild(btn);
                box.setAttribute('tabindex', '-1');
                box.focus();
            });
        }
    }

    function boot() {
        Array.prototype.forEach.call(document.querySelectorAll('form[data-fs-form]:not([data-fs-ready])'), function (f) {
            f.setAttribute('data-fs-ready', '');
            init(f);
        });
        var success = document.querySelector('[data-fs-success]');
        if (success) {
            success.focus();
            if (!success.hasAttribute('data-fs-ready')) {
                success.setAttribute('data-fs-ready', '');
                var cfg = config(success);
                // Abgeschickt: gespeicherten Entwurf verwerfen
                if (cfg.draft) { dropDraft(cfg.draft); }
                emit(success, cfg, 'success', {});
            }
        }
    }

    if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', boot); } else { boot(); }
})();
