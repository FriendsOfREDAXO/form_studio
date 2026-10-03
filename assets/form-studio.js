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

    function init(form) {
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
            current = index;
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
            });
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
                    if (error) { error.textContent = el.validationMessage; }
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
                var edit = el('button', 'uk-button uk-button-link fs-review__edit', 'Ändern');
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
                var labelEl = field.querySelector('.uk-form-label');
                var text = [];
                Array.prototype.forEach.call(field.querySelectorAll('input, select, textarea'), function (el) {
                    if (el.disabled || !el.name || (el.type === 'hidden' && !el.hasAttribute('data-a11y-fs'))) { return; }
                    if ((el.type === 'radio' || el.type === 'checkbox')) {
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
                }
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
            if (submit) { submit.disabled = true; submit.setAttribute('aria-busy', 'true'); }
        });

        apply();
        // Nach Serverfehlern: Schritt mit dem ersten Fehler zeigen
        var firstError = form.querySelector('[aria-invalid="true"]');
        if (multi) {
            var errorStep = firstError ? firstError.closest('[data-fs-step]') : null;
            show(errorStep ? Math.max(0, visibleSteps().indexOf(errorStep)) : 0, false);
        }
        var summaryBox = form.querySelector('[data-fs-error-summary]');
        if (summaryBox) { summaryBox.focus(); }
    }

    function boot() {
        Array.prototype.forEach.call(document.querySelectorAll('form[data-fs-form]:not([data-fs-ready])'), function (f) {
            f.setAttribute('data-fs-ready', '');
            init(f);
        });
        var success = document.querySelector('[data-fs-success]');
        if (success) { success.focus(); }
    }

    if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', boot); } else { boot(); }
})();
