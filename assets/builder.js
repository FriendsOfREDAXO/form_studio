/* Form Studio – Builder (Backend). Vanilla JS, Bootstrap-3-Markup des REDAXO-Backends. */
(function () {
    'use strict';

    var WIDTHS = { '1-1': 'Ganze Breite', '1-2': 'Halbe Breite', '1-3': 'Ein Drittel', '2-3': 'Zwei Drittel', '1-4': 'Ein Viertel', '3-4': 'Drei Viertel' };
    var PROP_LABELS = {
        label: 'Beschriftung', name: 'Feldname (technisch)', help: 'Hilfetext', required: 'Pflichtfeld', width: 'Breite',
        placeholder: 'Platzhalter', default: 'Vorgabewert', maxlength: 'Max. Zeichen', autocomplete: 'Autovervollständigung',
        min: 'Minimum', max: 'Maximum', step: 'Schrittweite', unit: 'Einheit', rows: 'Zeilen', min_days_ahead: 'Frühestens in … Tagen',
        options: 'Auswahlmöglichkeiten', multiple: 'Mehrfachauswahl erlauben', columns: 'Kacheln pro Reihe', inline: 'Nebeneinander anzeigen',
        text: 'Text', level: 'Größe', style: 'Darstellung', show_if: 'Anzeigen, wenn …'
    };
    var SETTINGS = [
        ['submit_label', 'text', 'Beschriftung des Absende-Buttons', 'Anfrage senden'],
        ['success_title', 'text', 'Überschrift nach dem Absenden', 'Vielen Dank für Ihre Anfrage!'],
        ['success_text', 'textarea', 'Text nach dem Absenden', 'Wir melden uns schnellstmöglich bei Ihnen.'],
        ['mail_to', 'text', 'E-Mail an (mehrere mit Komma; leer = E-Mail des Absenders aus den Einstellungen)', ''],
        ['mail_subject', 'text', 'Betreff der E-Mail an Sie', ''],
        ['copy_to_sender', 'checkbox', 'Kopie an die anfragende Person senden', true],
        ['sender_field', 'emailfield', 'E-Mail-Feld der anfragenden Person', ''],
        ['copy_subject', 'text', 'Betreff der Kopie', ''],
        ['copy_intro', 'textarea', 'Einleitung der Kopie', ''],
        ['store_yform', 'checkbox', 'Einsendungen in YForm-Tabelle speichern', false],
        ['yform_table', 'text', 'Tabellenname (leer = automatisch)', ''],
        ['pdf', 'checkbox', 'PDF zum Herunterladen und als Anhang', true],
        ['pdf_title', 'text', 'Titel im PDF', ''],
        ['pdf_intro', 'textarea', 'Einleitung im PDF', '']
    ];

    function el(tag, attrs, children) {
        var e = document.createElement(tag);
        Object.keys(attrs || {}).forEach(function (k) {
            if (k === 'text') { e.textContent = attrs[k]; } else if (k === 'html') { e.innerHTML = attrs[k]; } else if (k.indexOf('on') === 0) { e.addEventListener(k.slice(2), attrs[k]); } else if (attrs[k] !== null && attrs[k] !== false) { e.setAttribute(k, attrs[k] === true ? '' : attrs[k]); }
        });
        (children || []).forEach(function (c) { if (c) { e.appendChild(typeof c === 'string' ? document.createTextNode(c) : c); } });
        return e;
    }
    function slug(s) {
        return String(s || '').toLowerCase().replace(/ä/g, 'ae').replace(/ö/g, 'oe').replace(/ü/g, 'ue').replace(/ß/g, 'ss').replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '').slice(0, 48) || 'feld';
    }
    function clone(o) { return JSON.parse(JSON.stringify(o)); }

    function Builder(root) {
        this.root = root;
        this.config = JSON.parse(root.getAttribute('data-config'));
        var data = JSON.parse(root.getAttribute('data-form'));
        this.definition = data.definition && data.definition.steps ? data.definition : { steps: [{ title: 'Schritt 1', fields: [] }] };
        this.settings = data.settings || {};
        this.step = 0;
        this.selected = null; // Index des Felds im aktuellen Schritt, oder 'step'
        this.tab = 'build';
        this.render();
    }

    Builder.prototype.fields = function () { return this.definition.steps[this.step].fields; };

    Builder.prototype.allValueFields = function (untilStep, untilIndex) {
        var out = [];
        var types = this.config.types;
        this.definition.steps.forEach(function (st, si) {
            st.fields.forEach(function (f, fi) {
                if (untilStep !== undefined && (si > untilStep || (si === untilStep && fi >= untilIndex))) { return; }
                if (types[f.type] && types[f.type].value && f.name) { out.push(f); }
            });
        });
        return out;
    };

    Builder.prototype.uniqueName = function (base, except) {
        var names = {};
        this.definition.steps.forEach(function (st) { st.fields.forEach(function (f) { if (f !== except && f.name) { names[f.name] = true; } }); });
        var name = base, i = 2;
        while (names[name]) { name = base + '_' + i++; }
        return name;
    };

    Builder.prototype.render = function () {
        var self = this;
        this.root.innerHTML = '';
        var tabs = el('ul', { class: 'nav nav-tabs fs-b-tabs' }, [
            el('li', { class: this.tab === 'build' ? 'active' : '' }, [el('a', { href: '#', onclick: function (e) { e.preventDefault(); self.tab = 'build'; self.render(); } }, ['Aufbau'])]),
            el('li', { class: this.tab === 'settings' ? 'active' : '' }, [el('a', { href: '#', onclick: function (e) { e.preventDefault(); self.tab = 'settings'; self.render(); } }, ['Einstellungen'])])
        ]);
        this.root.appendChild(tabs);
        if (this.tab === 'settings') { this.root.appendChild(this.renderSettings()); return; }
        var row = el('div', { class: 'row fs-b' }, [
            el('div', { class: 'col-md-3' }, [this.renderPalette()]),
            el('div', { class: 'col-md-5' }, [this.renderCanvas()]),
            el('div', { class: 'col-md-4' }, [this.renderProps()])
        ]);
        this.root.appendChild(row);
    };

    Builder.prototype.renderPalette = function () {
        var self = this;
        var groups = { input: 'Eingabe', choice: 'Auswahl', layout: 'Gestaltung' };
        var box = el('div', { class: 'fs-b-palette' }, [el('h4', { text: 'Feld hinzufügen' })]);
        Object.keys(groups).forEach(function (g) {
            box.appendChild(el('div', { class: 'fs-b-palette__group', text: groups[g] }));
            Object.keys(self.config.types).forEach(function (t) {
                var def = self.config.types[t];
                if (def.group !== g) { return; }
                box.appendChild(el('button', { type: 'button', class: 'btn btn-default btn-block fs-b-palette__item', onclick: function () { self.addField(t); } }, [
                    el('i', { class: 'rex-icon fa ' + def.icon, 'aria-hidden': 'true' }), ' ' + def.label
                ]));
            });
        });
        return box;
    };

    Builder.prototype.addField = function (type) {
        var def = this.config.types[type];
        var field = { type: type };
        if (def.value) { field.label = def.label; field.name = this.uniqueName(slug(def.label)); }
        if (def.options) { field.options = [{ value: 'option_1', label: 'Option 1' }, { value: 'option_2', label: 'Option 2' }]; }
        if (type === 'heading' || type === 'info') { field.text = def.label; }
        if (type === 'consent') { field.label = 'Einwilligung'; field.text = 'Ich bin mit der Verarbeitung meiner Angaben einverstanden. Details in der [Datenschutzerklärung](/datenschutz/).'; field.required = true; }
        if (type === 'counter') { field.default = 1; field.min = 0; }
        var fields = this.fields();
        var at = typeof this.selected === 'number' ? this.selected + 1 : fields.length;
        fields.splice(at, 0, field);
        this.selected = at;
        this.render();
    };

    Builder.prototype.renderCanvas = function () {
        var self = this;
        var box = el('div', { class: 'fs-b-canvas' });
        // Schritte
        var steps = el('div', { class: 'fs-b-steps' });
        this.definition.steps.forEach(function (st, i) {
            steps.appendChild(el('button', { type: 'button', class: 'btn btn-sm ' + (i === self.step ? 'btn-primary' : 'btn-default') + (st.show_if ? ' fs-b-has-cond' : ''),
                onclick: function () { self.step = i; self.selected = 'step'; self.render(); } }, [(i + 1) + '. ' + (st.title || 'Schritt')]));
        });
        steps.appendChild(el('button', { type: 'button', class: 'btn btn-sm btn-default', title: 'Schritt hinzufügen', onclick: function () {
            self.definition.steps.push({ title: 'Schritt ' + (self.definition.steps.length + 1), fields: [] });
            self.step = self.definition.steps.length - 1; self.selected = 'step'; self.render();
        } }, ['+ Schritt']));
        box.appendChild(steps);

        var list = el('ol', { class: 'fs-b-fields' });
        var fields = this.fields();
        if (!fields.length) { list.appendChild(el('li', { class: 'fs-b-empty', text: 'Noch keine Felder – links ein Feld auswählen.' })); }
        fields.forEach(function (f, i) {
            var def = self.config.types[f.type] || { label: f.type, icon: 'fa-question' };
            var title = f.label || f.text || def.label;
            var item = el('li', { class: 'fs-b-field' + (self.selected === i ? ' is-selected' : ''), draggable: 'true', 'data-index': i,
                onclick: function () { self.selected = i; self.render(); } }, [
                el('span', { class: 'fs-b-field__drag', title: 'Ziehen zum Sortieren', 'aria-hidden': 'true', text: '⋮⋮' }),
                el('i', { class: 'rex-icon fa ' + def.icon, 'aria-hidden': 'true' }),
                el('span', { class: 'fs-b-field__title', text: ' ' + String(title).slice(0, 60) }),
                f.required ? el('span', { class: 'label label-danger', text: 'Pflicht' }) : null,
                f.show_if ? el('span', { class: 'label label-info', title: 'Wird nur unter Bedingungen angezeigt', text: 'bedingt' }) : null,
                f.name ? el('code', { class: 'fs-b-field__name', text: f.name }) : null,
                el('span', { class: 'fs-b-field__actions' }, [
                    el('button', { type: 'button', class: 'btn btn-xs btn-default', title: 'nach oben', onclick: function (e) { e.stopPropagation(); self.move(i, -1); } }, ['↑']),
                    el('button', { type: 'button', class: 'btn btn-xs btn-default', title: 'nach unten', onclick: function (e) { e.stopPropagation(); self.move(i, 1); } }, ['↓']),
                    el('button', { type: 'button', class: 'btn btn-xs btn-default', title: 'duplizieren', onclick: function (e) { e.stopPropagation(); var c = clone(f); if (c.name) { c.name = self.uniqueName(c.name); } fields.splice(i + 1, 0, c); self.selected = i + 1; self.render(); } }, ['⧉']),
                    el('button', { type: 'button', class: 'btn btn-xs btn-danger', title: 'löschen', onclick: function (e) { e.stopPropagation(); if (confirm('Feld löschen?')) { fields.splice(i, 1); self.selected = null; self.render(); } } }, ['×'])
                ])
            ]);
            item.addEventListener('dragstart', function (e) { e.dataTransfer.setData('text/plain', String(i)); item.classList.add('is-dragging'); });
            item.addEventListener('dragend', function () { item.classList.remove('is-dragging'); });
            item.addEventListener('dragover', function (e) { e.preventDefault(); item.classList.add('is-over'); });
            item.addEventListener('dragleave', function () { item.classList.remove('is-over'); });
            item.addEventListener('drop', function (e) {
                e.preventDefault();
                var from = parseInt(e.dataTransfer.getData('text/plain'), 10);
                if (isNaN(from) || from === i) { return; }
                var moved = fields.splice(from, 1)[0];
                fields.splice(i, 0, moved);
                self.selected = i; self.render();
            });
            list.appendChild(item);
        });
        box.appendChild(list);
        return box;
    };

    Builder.prototype.move = function (i, dir) {
        var fields = this.fields();
        var j = i + dir;
        if (j < 0 || j >= fields.length) { return; }
        var tmp = fields[i]; fields[i] = fields[j]; fields[j] = tmp;
        this.selected = j; this.render();
    };

    Builder.prototype.renderProps = function () {
        var self = this;
        var box = el('div', { class: 'fs-b-props panel panel-default' });
        var body = el('div', { class: 'panel-body' });
        box.appendChild(body);

        if (this.selected === 'step' || this.selected === null) {
            var st = this.definition.steps[this.step];
            body.appendChild(el('h4', { text: 'Schritt ' + (this.step + 1) }));
            body.appendChild(this.input('Titel', 'text', st.title || '', function (v) { st.title = v; self.renderCanvasOnly(); }));
            body.appendChild(this.input('Einleitung', 'textarea', st.intro || '', function (v) { st.intro = v; }));
            body.appendChild(this.conditionEditor(st, this.step, 0));
            var actions = el('div', { class: 'btn-group' });
            if (this.step > 0) { actions.appendChild(el('button', { type: 'button', class: 'btn btn-default btn-sm', onclick: function () { var s = self.definition.steps; s.splice(self.step - 1, 0, s.splice(self.step, 1)[0]); self.step--; self.render(); } }, ['← Schritt nach vorne'])); }
            if (this.definition.steps.length > 1) { actions.appendChild(el('button', { type: 'button', class: 'btn btn-danger btn-sm', onclick: function () { if (confirm('Schritt mit allen Feldern löschen?')) { self.definition.steps.splice(self.step, 1); self.step = 0; self.selected = null; self.render(); } } }, ['Schritt löschen'])); }
            body.appendChild(actions);
            return box;
        }

        var field = this.fields()[this.selected];
        var def = this.config.types[field.type];
        body.appendChild(el('h4', {}, [el('i', { class: 'rex-icon fa ' + def.icon }), ' ' + def.label]));
        var nameTouched = !!field.name && field.name !== slug(field.label || '');
        def.props.forEach(function (p) {
            if (p === 'label') {
                body.appendChild(self.input(PROP_LABELS.label, 'text', field.label || '', function (v) {
                    field.label = v;
                    if (!nameTouched && def.value && field.type !== 'hidden') { field.name = self.uniqueName(slug(v), field); var n = body.querySelector('[data-prop=name]'); if (n) { n.value = field.name; } }
                    self.renderCanvasOnly();
                }));
            } else if (p === 'name') {
                var inp = self.input(PROP_LABELS.name, 'text', field.name || '', function (v) { nameTouched = true; field.name = slug(v); self.renderCanvasOnly(); }, 'Nur Kleinbuchstaben, Ziffern und _. Wird als Spalte in YForm verwendet.');
                inp.querySelector('input').setAttribute('data-prop', 'name');
                body.appendChild(inp);
            } else if (p === 'required' || p === 'multiple' || p === 'inline') {
                body.appendChild(self.input(PROP_LABELS[p], 'checkbox', !!field[p], function (v) { field[p] = v; self.renderCanvasOnly(); }));
            } else if (p === 'width') {
                body.appendChild(self.input(PROP_LABELS.width, 'select', field.width || '1-1', function (v) { field.width = v; }, '', WIDTHS));
            } else if (p === 'columns') {
                body.appendChild(self.input(PROP_LABELS.columns, 'select', String(field.columns || 3), function (v) { field.columns = parseInt(v, 10); }, '', { 2: '2', 3: '3', 4: '4', 5: '5' }));
            } else if (p === 'level') {
                body.appendChild(self.input(PROP_LABELS.level, 'select', field.level || 'h4', function (v) { field.level = v; }, '', { h3: 'Groß', h4: 'Mittel', h5: 'Klein' }));
            } else if (p === 'style') {
                body.appendChild(self.input(PROP_LABELS.style, 'select', field.style || '', function (v) { field.style = v; }, '', { '': 'Neutral', primary: 'Hervorgehoben', success: 'Positiv', warning: 'Warnung' }));
            } else if (p === 'help' || p === 'text') {
                body.appendChild(self.input(PROP_LABELS[p], 'textarea', field[p] || '', function (v) { field[p] = v; self.renderCanvasOnly(); }, p === 'text' && field.type === 'consent' ? 'Links als [Text](/pfad/) möglich.' : ''));
            } else if (p === 'options') {
                body.appendChild(self.optionsEditor(field));
            } else if (p === 'show_if') {
                body.appendChild(self.conditionEditor(field, self.step, self.selected));
            } else {
                var type = ['min', 'max', 'step', 'rows', 'maxlength', 'min_days_ahead'].indexOf(p) !== -1 && field.type !== 'date' && field.type !== 'time' ? 'number' : (field.type === 'date' && (p === 'min' || p === 'max') ? 'date' : 'text');
                body.appendChild(self.input(PROP_LABELS[p] || p, type, field[p] == null ? '' : field[p], function (v) { field[p] = type === 'number' && v !== '' ? Number(v) : v; }));
            }
        });
        return box;
    };

    Builder.prototype.renderCanvasOnly = function () {
        var canvas = this.root.querySelector('.fs-b-canvas');
        if (canvas) { canvas.parentNode.replaceChild(this.renderCanvas(), canvas); }
    };

    Builder.prototype.input = function (label, type, value, onchange, help, options) {
        var id = 'fsb-' + Math.random().toString(36).slice(2, 9);
        var control;
        if (type === 'textarea') {
            control = el('textarea', { class: 'form-control', id: id, rows: 3 });
            control.value = value;
        } else if (type === 'select') {
            control = el('select', { class: 'form-control', id: id });
            Object.keys(options).forEach(function (k) { control.appendChild(el('option', { value: k, selected: String(k) === String(value) }, [options[k]])); });
        } else if (type === 'checkbox') {
            control = el('input', { type: 'checkbox', id: id, checked: !!value });
            var wrap = el('div', { class: 'checkbox' }, [el('label', { for: id }, [control, ' ' + label])]);
            control.addEventListener('change', function () { onchange(control.checked); });
            return wrap;
        } else {
            control = el('input', { class: 'form-control', id: id, type: type });
            control.value = value;
        }
        control.addEventListener(type === 'select' ? 'change' : 'input', function () { onchange(control.value); });
        return el('div', { class: 'form-group' }, [el('label', { for: id, text: label }), control, help ? el('p', { class: 'help-block', text: help }) : null]);
    };

    Builder.prototype.optionsEditor = function (field) {
        var self = this;
        var wrap = el('div', { class: 'form-group fs-b-options' }, [el('label', { text: PROP_LABELS.options })]);
        var withImage = field.type === 'cards';
        var table = el('table', { class: 'table table-condensed' });
        var head = el('tr', {}, [el('th', { text: 'Text' }), el('th', { text: 'Wert' })].concat(withImage ? [el('th', { text: 'Bild' }), el('th', { text: 'Beschreibung' })] : []).concat([el('th')]));
        table.appendChild(head);
        (field.options = field.options || []).forEach(function (opt, i) {
            var labelIn = el('input', { class: 'form-control input-sm', value: opt.label || '' });
            var valueIn = el('input', { class: 'form-control input-sm', value: opt.value || '' });
            labelIn.addEventListener('input', function () {
                var auto = !opt.value || opt.value === slug(opt._prevLabel || '');
                opt._prevLabel = labelIn.value; opt.label = labelIn.value;
                if (auto) { opt.value = slug(labelIn.value); valueIn.value = opt.value; }
            });
            valueIn.addEventListener('input', function () { opt.value = slug(valueIn.value); });
            var cells = [el('td', {}, [labelIn]), el('td', {}, [valueIn])];
            if (withImage) {
                var sel = el('select', { class: 'form-control input-sm' }, [el('option', { value: '', text: '—' })]);
                self.config.graphics.forEach(function (g) { sel.appendChild(el('option', { value: 'fs:' + g, selected: opt.image === 'fs:' + g, text: g.replace(/_/g, ' ') })); });
                if (opt.image && opt.image.indexOf('fs:') !== 0) { sel.appendChild(el('option', { value: opt.image, selected: true, text: opt.image })); }
                sel.appendChild(el('option', { value: '__media', text: 'Datei aus Medienpool …' }));
                sel.addEventListener('change', function () {
                    if (sel.value === '__media') {
                        var file = prompt('Dateiname im Medienpool (z. B. saal_1.jpg):', opt.image && opt.image.indexOf('fs:') !== 0 ? opt.image : '');
                        if (file) { opt.image = file.trim(); }
                        self.render();
                        return;
                    }
                    opt.image = sel.value;
                });
                var desc = el('input', { class: 'form-control input-sm', value: opt.description || '' });
                desc.addEventListener('input', function () { opt.description = desc.value; });
                cells.push(el('td', {}, [sel]), el('td', {}, [desc]));
            }
            cells.push(el('td', {}, [el('button', { type: 'button', class: 'btn btn-xs btn-danger', title: 'entfernen', onclick: function () { field.options.splice(i, 1); self.render(); } }, ['×'])]));
            table.appendChild(el('tr', {}, cells));
        });
        wrap.appendChild(table);
        wrap.appendChild(el('button', { type: 'button', class: 'btn btn-xs btn-default', onclick: function () {
            var n = field.options.length + 1; field.options.push({ value: 'option_' + n, label: 'Option ' + n }); self.render();
        } }, ['+ Option']));
        return wrap;
    };

    Builder.prototype.conditionEditor = function (target, stepIndex, fieldIndex) {
        var self = this;
        var candidates = this.allValueFields(stepIndex, fieldIndex);
        var wrap = el('div', { class: 'form-group fs-b-cond' }, [el('label', { text: PROP_LABELS.show_if })]);
        if (!target.show_if) {
            wrap.appendChild(el('p', { class: 'help-block', text: candidates.length ? 'Immer anzeigen.' : 'Bedingungen beziehen sich auf vorherige Felder.' }));
            if (candidates.length) {
                wrap.appendChild(el('button', { type: 'button', class: 'btn btn-xs btn-default', onclick: function () {
                    target.show_if = { logic: 'all', rules: [{ field: candidates[candidates.length - 1].name, op: 'equals', value: '' }] }; self.render();
                } }, ['+ Bedingung']));
            }
            return wrap;
        }
        var logic = el('select', { class: 'form-control input-sm' }, [
            el('option', { value: 'all', selected: target.show_if.logic !== 'any', text: 'alle Bedingungen erfüllt' }),
            el('option', { value: 'any', selected: target.show_if.logic === 'any', text: 'mindestens eine erfüllt' })
        ]);
        logic.addEventListener('change', function () { target.show_if.logic = logic.value; });
        wrap.appendChild(logic);
        target.show_if.rules.forEach(function (rule, i) {
            var fieldSel = el('select', { class: 'form-control input-sm' });
            candidates.forEach(function (c) { fieldSel.appendChild(el('option', { value: c.name, selected: c.name === rule.field, text: (c.label || c.name) })); });
            var opSel = el('select', { class: 'form-control input-sm' });
            Object.keys(self.config.operators).forEach(function (op) { opSel.appendChild(el('option', { value: op, selected: op === rule.op, text: self.config.operators[op] })); });
            var ref = candidates.filter(function (c) { return c.name === rule.field; })[0];
            var valueEl;
            if (ref && ref.options && ref.options.length && rule.op !== 'in') {
                valueEl = el('select', { class: 'form-control input-sm' }, [el('option', { value: '', text: '—' })]);
                ref.options.forEach(function (o) { valueEl.appendChild(el('option', { value: o.value, selected: o.value === rule.value, text: o.label })); });
            } else {
                valueEl = el('input', { class: 'form-control input-sm', value: rule.value || '', placeholder: 'Wert' });
            }
            var noValue = rule.op === 'empty' || rule.op === 'not_empty';
            valueEl.hidden = noValue;
            fieldSel.addEventListener('change', function () { rule.field = fieldSel.value; rule.value = ''; self.render(); });
            opSel.addEventListener('change', function () { rule.op = opSel.value; self.render(); });
            valueEl.addEventListener(valueEl.tagName === 'SELECT' ? 'change' : 'input', function () { rule.value = valueEl.value; });
            wrap.appendChild(el('div', { class: 'fs-b-cond__rule' }, [fieldSel, opSel, valueEl,
                el('button', { type: 'button', class: 'btn btn-xs btn-danger', title: 'Bedingung entfernen', onclick: function () {
                    target.show_if.rules.splice(i, 1); if (!target.show_if.rules.length) { delete target.show_if; } self.render();
                } }, ['×'])]));
        });
        wrap.appendChild(el('button', { type: 'button', class: 'btn btn-xs btn-default', onclick: function () {
            target.show_if.rules.push({ field: candidates[0] ? candidates[0].name : '', op: 'equals', value: '' }); self.render();
        } }, ['+ weitere Bedingung']));
        return wrap;
    };

    Builder.prototype.renderSettings = function () {
        var self = this;
        var box = el('div', { class: 'fs-b-settings' });
        var emailFields = { '': 'erstes E-Mail-Feld' };
        this.allValueFields().forEach(function (f) { if (f.type === 'email') { emailFields[f.name] = f.label || f.name; } });
        SETTINGS.forEach(function (s) {
            var key = s[0], type = s[1], label = s[2], def = s[3];
            var value = self.settings[key] === undefined ? def : self.settings[key];
            if (type === 'emailfield') {
                box.appendChild(self.input(label, 'select', value, function (v) { self.settings[key] = v; }, '', emailFields));
            } else {
                box.appendChild(self.input(label, type, value, function (v) { self.settings[key] = v; }, key === 'yform_table' ? 'Standard: ' + self.config.yformTable : ''));
            }
        });
        return box;
    };

    Builder.prototype.serialize = function () {
        // Hilfswerte entfernen
        this.definition.steps.forEach(function (st) { st.fields.forEach(function (f) { (f.options || []).forEach(function (o) { delete o._prevLabel; }); }); });
        return { definition: JSON.stringify(this.definition), settings: JSON.stringify(this.settings) };
    };

    function boot() {
        var root = document.getElementById('fs-builder');
        if (!root || root.getAttribute('data-ready')) { return; }
        root.setAttribute('data-ready', '1');
        var builder = new Builder(root);
        var form = root.closest('form');
        form.addEventListener('submit', function (e) {
            var names = {};
            var dupes = [];
            builder.definition.steps.forEach(function (st) { st.fields.forEach(function (f) { if (f.name) { if (names[f.name]) { dupes.push(f.name); } names[f.name] = true; } }); });
            if (dupes.length) { e.preventDefault(); alert('Feldnamen doppelt vergeben: ' + dupes.join(', ')); return; }
            var data = builder.serialize();
            form.querySelector('[data-fs-out=definition]').value = data.definition;
            form.querySelector('[data-fs-out=settings]').value = data.settings;
        });
    }
    if (typeof jQuery !== 'undefined') { jQuery(document).on('rex:ready', boot); }
    document.addEventListener('DOMContentLoaded', boot);
})();
