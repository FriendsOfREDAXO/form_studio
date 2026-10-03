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
        var steps = Array.prototype.slice.call(form.querySelectorAll('[data-fs-step]'));
        var progress = Array.prototype.slice.call(form.querySelectorAll('[data-fs-progress-item]'));
        var summary = form.querySelector('[data-fs-summary]');
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

        function show(index, focus) {
            var list = visibleSteps();
            index = Math.max(0, Math.min(index, list.length - 1));
            current = index;
            steps.forEach(function (s) { s.hidden = s !== list[index]; });
            var last = index === list.length - 1;
            Array.prototype.forEach.call(form.querySelectorAll('[data-fs-next]'), function (b) { b.hidden = last; });
            if (submit) { submit.parentNode.hidden = !last; }
            if (summary) { summary.hidden = !last; if (last) { buildSummary(); } }
            progress.forEach(function (p) {
                var i = list.indexOf(steps[+p.getAttribute('data-fs-progress-item')]);
                p.classList.toggle('is-done', i !== -1 && i < index);
                p.classList.toggle('is-current', i === index);
                if (i === index) { p.setAttribute('aria-current', 'step'); } else { p.removeAttribute('aria-current'); }
            });
            if (focus) {
                var legend = list[index].querySelector('legend') || list[index];
                legend.setAttribute('tabindex', '-1');
                legend.focus({ preventScroll: true });
                form.closest('.fs').scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        }

        function stepValid(step) {
            var ok = true;
            var first = null;
            Array.prototype.forEach.call(step.querySelectorAll('input, select, textarea'), function (el) {
                if (el.disabled || el.closest('[hidden]')) { return; }
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

        function buildSummary() {
            var dl = summary && summary.querySelector('[data-fs-summary-list]');
            if (!dl) { return; }
            dl.innerHTML = '';
            Array.prototype.forEach.call(form.querySelectorAll('[data-fs-field]'), function (field) {
                if (field.hidden || field.closest('[data-fs-skipped]')) { return; }
                var labelEl = field.querySelector('.uk-form-label');
                var text = [];
                Array.prototype.forEach.call(field.querySelectorAll('input, select, textarea'), function (el) {
                    if (el.disabled || el.type === 'hidden') { return; }
                    if ((el.type === 'radio' || el.type === 'checkbox')) {
                        if (el.checked) {
                            var l = el.closest('label');
                            text.push(l ? (l.querySelector('.fs-card__label') || l).textContent.trim() : el.value);
                        }
                    } else if (el.tagName === 'SELECT') {
                        if (el.value) { text.push(el.options[el.selectedIndex].text); }
                    } else if (el.value) {
                        var unit = field.querySelector('.fs-counter__unit, .fs-unit__label');
                        text.push(el.value + (unit ? ' ' + unit.textContent : ''));
                    }
                });
                if (!text.length || !labelEl) { return; }
                var dt = document.createElement('dt');
                dt.textContent = labelEl.textContent.replace(/\s*\*\s*$/, '').trim();
                var dd = document.createElement('dd');
                dd.textContent = text.join(', ');
                dl.appendChild(dt);
                dl.appendChild(dd);
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
            form.addEventListener('click', function (e) {
                if (e.target.closest('[data-fs-next]')) {
                    e.preventDefault();
                    if (stepValid(visibleSteps()[current])) { show(current + 1, true); }
                } else if (e.target.closest('[data-fs-prev]')) {
                    e.preventDefault();
                    show(current - 1, true);
                }
            });
        }

        form.addEventListener('submit', function (e) {
            apply();
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
