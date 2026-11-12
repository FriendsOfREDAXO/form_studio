# Form Studio

Visueller Formular-Builder für REDAXO: Formulare in **Schritten** mit **Bedingungen**, Speicherung in einer automatisch angelegten **YForm-Tabelle**, **E-Mail** an das Haus und eine Kopie an die anfragende Person, **PDF** zum Herunterladen (pdfout) – barrierefrei und ohne Abhängigkeiten im Frontend.

> Status: 0.3.0 – entstanden für einen Hotel-Bankettplaner, seit 0.2.0 mit den Funktionen von formslider, seit 0.3.0 mit Datenquellen, Wiederholgruppe, IBAN, Adresse und Live-Vorschau.

## Funktionen

- **Builder im Backend** (Form Studio → Formulare): Feldpalette, Schritte, Drag & Drop, Eigenschaften je Feldtyp, Bedingungs-Editor, Einstellungen.
- **20 Feldtypen**: Text, E-Mail, Telefon, Zahl, Zähler (− / +), mehrzeiliger Text, Datum (mit Mindestvorlauf), Uhrzeit, Auswahlliste, Einfach-/Mehrfachauswahl, **Bildkacheln** (z. B. Bestuhlung), Einwilligung (mit Links), **Ja/Nein-Schalter**, **Wiederholgruppe**, **IBAN**, **Adresse**, Zwischenüberschrift, Hinweistext, verstecktes Feld. Weitere über den Extension Point `FORM_STUDIO_FIELD_TYPES`.
- **Bedingungen** für Felder und ganze Schritte („anzeigen, wenn …“, alle/eine der Regeln; ist gleich, enthält, ist eines von, ausgefüllt, größer/kleiner …) – im Browser **und** auf dem Server ausgewertet. Ausgeblendete Felder werden weder geprüft noch gespeichert.
- **Mehrstufiger Assistent** mit Fortschritt, Prüfung pro Schritt und Zusammenfassung vor dem Absenden. Ohne JavaScript ein normales Formular.
- **Speichern**: optional in YForm (Tabelle und Felder werden angelegt/ergänzt, nie gelöscht).
- **E-Mail** an Empfänger des Formulars, **Kopie** an die anfragende Person, jeweils mit PDF im Anhang.
- **PDF** mit Logo und Absenderdaten je Domain (Einstellungen oder Extension Point `FORM_STUDIO_SENDER`); Layout überschreibbar (`fragments/form_studio/pdf.php`).
- **Spamschutz** (Honeypot, signierte Mindestausfüllzeit), CSRF-Schutz, Post/Redirect/Get.
- **Datenschutz**: Einsendungen für den PDF-Download werden nach einstellbarer Frist automatisch gelöscht.
- **Formular duplizieren** (Formularliste → „Duplizieren“): Kopie mit Definition und Einstellungen, inaktiv, mit eigenem Schlüssel und eigener YForm-Tabelle.
- **Vorlagen**: „Bankettplaner“ (Anlass, Termin, Gäste, Bestuhlung mit Grafiken, Verpflegung, Technik, Kontakt) und „Kontaktformular“. Eigene über `FORM_STUDIO_TEMPLATES`.

## Ablauf und Darstellung (je Formular einschaltbar)

Alles im Builder unter **Einstellungen → Ablauf & Darstellung**. Standard ist jeweils „aus“ – bestehende Formulare bleiben unverändert.

- **Übergang zwischen Schritten**: keiner, gleiten oder überblenden. Bei „Bewegung reduzieren“ im System immer ohne Animation.
- **Fortschritt anklickbar**: erreichte Schritte sind Knöpfe; nach vorn nur, wenn die Schritte davor gültig sind.
- **Schrittanzeige** „Schritt 2 von 5“; übersprungene (bedingte) Schritte zählen nicht mit. Schrittwechsel werden Screenreadern angesagt.
- **Automatisch weiter** nach einer Einfachauswahl (Optionen, Bildkacheln), wenn es das letzte Feld des Schritts ist – nicht beim Blättern mit den Pfeiltasten.
- **Zwischenstand im Browser**: Eingaben bleiben beim Verlassen der Seite im Browser (localStorage) erhalten, mit Hinweis und Knopf „Eingaben löschen“; nach dem Absenden und nach Ablauf (Tage einstellbar) gelöscht. Einwilligungen, versteckte Felder und Bildkacheln werden nie gespeichert.
- **Statistik**: Ereignisse zusätzlich an `window.dataLayer` (Google Tag Manager u. a.).

### JavaScript-Ereignisse

Am `<form>` (bzw. an der Erfolgsmeldung) werden immer `CustomEvent`s ausgelöst, sie steigen auf (`bubbles`):

| Ereignis | `detail` |
| --- | --- |
| `fs:step` | `index`, `total`, `title`, `review` (Prüfseite), `form` (Schlüssel) |
| `fs:submit` | `form` |
| `fs:success` | `form` |
| `fs:draft-restored` | `form` |

```js
document.addEventListener('fs:step', e => console.log(e.detail.index, e.detail.title));
```

Mit „Statistik“ an wird zusätzlich `dataLayer.push({event: 'fs_step', fs_form: …})` usw. ausgeführt.

## PDF

Mit dem AddOn **pdfout** erzeugt Form Studio je Einsendung ein PDF: zum Herunterladen nach dem Absenden und als Anhang der E-Mails (je getrennt schaltbar). Im Builder unter **Einstellungen → PDF**: Titel, Einleitung, **Schlusstext**, **Unterschriftszeilen** (Beschriftungen mit `|` trennen) und die **Vorlage**.

**Eigene Vorlagen** legen Sie unter Form Studio → Einstellungen → **PDF-Vorlagen** an. Jede Vorlage ist ein (inaktives) REDAXO-Template mit dem Schlüssel `form_studio_pdf…`, angelegt als Kopie des Standard-Layouts, und wird unter **Templates** bearbeitet. Im Template stehen dieselben Daten wie im Fragment bereit: `$this->getVar('form')`, `'submission'`, `'sender'`, `'logo'`. Fehlt die gewählte Vorlage, gilt das Standard-Layout (`fragments/form_studio/pdf.php`, im project-AddOn überschreibbar).

Einsendungen (und damit der PDF-Download) werden nach der eingestellten Frist gelöscht; PDFs werden nicht dauerhaft auf dem Server abgelegt.

## Formular aus YForm-Tabelle übernehmen

Unter **Neues Formular → Quelle „YForm-Tabelle übernehmen“** wird eine bestehende Tabelle eingelesen (Umstieg z. B. von formslider): jedes `fieldset` beginnt einen Schritt, Validierung `empty` wird zu „Pflichtfeld“, `email` zu einem E-Mail-Feld, `checkbox` zum Ja/Nein-Schalter. Was nicht übernommen werden kann (z. B. Upload), wird angezeigt. Speichern in die Tabelle bleibt zunächst aus; wird es eingeschaltet, ergänzt Form Studio die Spalten `fs_created` und `fs_domain`.

## Einbindung

Bei der Installation wird das Modul **„Form Studio: Formular“** angelegt – Formular auswählen, fertig. Bei Update und Re-Install wird der Modulcode nur aktualisiert, solange er einer mitgelieferten Fassung entspricht; ein im Projekt angepasstes Modul bleibt unverändert (Hinweis unter Einstellungen). Je Platzierung optional: eigener Dankestext und zusätzliche CSS-Klassen. Im Backend zeigt das Modul eine Vorschau (Schritte und erster Schritt). Inaktive Formulare (Schalter „Aktiv“ im Builder) erscheinen im Frontend nicht. In PHP:

```php
$form = FriendsOfRedaxo\FormStudio\Form::getByKey('bankettplaner');
echo FriendsOfRedaxo\FormStudio\Processor::handle($form);
// optional je Platzierung:
echo FriendsOfRedaxo\FormStudio\Processor::handle($form, ['success_text' => 'Danke!', 'wrapper_class' => 'mein-rahmen']);
```

### Knöpfe zu Formularen und automatische Snippets

Ein Knopf öffnet die Online-Seite, auf der ein Formular mit dem Modul „Form Studio: Formular“ eingebunden ist – bei mehreren Domains (YRewrite) bevorzugt die aktuelle. Überall einsetzbar, wo HTML ausgegeben wird:

```html
<form-studio-button data-form="5" data-text="Tagung anfragen" data-preset="event_type=tagung"></form-studio-button>
```

`data-form` = ID, Schlüssel oder Name; `data-text` optional (leer = Name des Formulars); `data-preset` optional, im Format einer Query (`feld=wert&feld2=wert2`, siehe *Vorbelegung per Link*). Fehlt das Formular oder ist es auf keiner Online-Seite eingebunden, erscheint nichts – angemeldete Redakteure sehen einen Hinweis.

**Knopf-Vorlage** (Einstellungen → Knöpfe zu Formularen & Snippets): bestimmt das Markup, Platzhalter `{{url}}`, `{{text}}`, `{{form_name}}`, `{{form_key}}`, `{{form_id}}`. Beispiele:

```html
<a class="uk-button uk-button-primary" href="{{url}}">{{text}}</a>          <!-- UIkit -->
<a class="btn btn-primary" href="{{url}}">{{text}}</a>                       <!-- Bootstrap -->
<a class="uk-button uk-button-primary ajaxmodal" href="{{url}}">{{text}}</a> <!-- Overlay per Website-Skript -->
```

Form Studio öffnet selbst kein Overlay – soll das Formular über der Seite erscheinen, die Klasse oder das Attribut eintragen, auf das das Overlay-Skript der Website reagiert.

**Snippets automatisch anlegen** (mit dem AddOn *snippets*): Für jedes Formular entsteht ein Snippet `formular_<name>`, z. B. `[[snippet:formular_tagungsplaner]]` oder mit eigener Beschriftung `[[snippet:formular_tagungsplaner|text=Tagung anfragen]]`. Neue, duplizierte und gelöschte Formulare werden beim Speichern abgeglichen, inaktive Formulare haben ein inaktives Snippet. Ein umbenanntes Formular behält seinen Snippet-Schlüssel, damit bestehende Platzhalter funktionieren. Kategorie wählbar. In PHP: `FriendsOfRedaxo\FormStudio\Button::render('tagungsplaner', 'Tagung anfragen', ['event_type' => 'tagung'])`, `Button::url($formId, $preset)`, `Snippets::sync()`.

## Gestaltung

Form Studio bringt eigenes, framework-freies CSS mit (`assets/form-studio.css`, Präfix `fs-`). Es funktioniert ohne UIkit, Bootstrap & Co., harmoniert aber mit UIkit 3. Alles lässt sich über CSS-Variablen im eigenen Theme anpassen – die Standardwerte stehen in `:where(.fs)` (Spezifität 0), eigene Angaben gewinnen also immer, unabhängig von der Ladereihenfolge:

```css
.fs {
    --fs-accent: #005d40;          /* Akzent: Buttons, Auswahl, Fortschritt */
    --fs-radius: 2px;              /* Rundungen von Feldern, Karten, Buttons */
    --fs-button-transform: uppercase;
    --fs-button-spacing: 0.12em;
    --fs-label-color: #005d40;
    --fs-title-color: #005d40;
}
```

| Variable | Standard | Wirkung |
| --- | --- | --- |
| `--fs-accent` / `--fs-accent-hover` / `--fs-accent-contrast` | `#0f6fc6` / dunkler / `#fff` | Akzentfarbe (Kontrast ≥ 4,5:1 auf Weiß) |
| `--fs-text` / `--fs-muted` | `inherit` / `#666` | Text, Hinweise |
| `--fs-surface` / `--fs-surface-alt` | `#fff` / `#f8f8f8` | Felder, Karten, Bildflächen |
| `--fs-border` / `--fs-border-strong` | `#e5e5e5` / `#ccc` | Rahmen, Hover |
| `--fs-error` / `--fs-error-bg` | `#c0392b` / `#fef4f6` | Fehler |
| `--fs-info-bg` / `--fs-success-bg` / `--fs-warning-bg` | | Hinweis-Felder |
| `--fs-focus` | `var(--fs-accent)` | Fokusrahmen |
| `--fs-font` / `--fs-font-size` / `--fs-small` | `inherit` / `1rem` / `0.875rem` | Schrift |
| `--fs-radius` | `4px` | Rundungen |
| `--fs-control-height` / `--fs-control-padding` | `40px` / `0 10px` | Eingabefelder |
| `--fs-gap` / `--fs-gap-small` | `1rem` / `0.5rem` | Abstände im Raster |
| `--fs-label-size` / `--fs-label-color` / `--fs-label-weight` | | Feldbeschriftungen |
| `--fs-title-size` / `--fs-title-color` / `--fs-title-weight` | | Schritt-Überschriften |
| `--fs-button-height` / `--fs-button-padding` / `--fs-button-radius` | `40px` / `0 30px` / `var(--fs-radius)` | Buttons |
| `--fs-button-transform` / `--fs-button-spacing` / `--fs-button-size` / `--fs-button-weight` | `none` / `normal` / `0.875rem` / `400` | Button-Schrift |
| `--fs-button-secondary-bg` / `--fs-button-secondary-color` / `--fs-button-secondary-border` | `transparent` / `inherit` / `var(--fs-border-strong)` | Zweit-Buttons (Zurück, PDF, − / +) |
| `--fs-focus-width` | `3px` | Stärke des Fokusrahmens |
| `--fs-switch-off` | `#767676` | Ja/Nein-Schalter im Zustand „Nein“ |
| `--fs-transition-duration` / `--fs-transition-distance` | `0.32s` / `2rem` | Übergang zwischen Schritten (gleiten/überblenden) |

Feldbreiten (`width` in der Definition: `1-2`, `1-3`, `2-3`, `1-4`, `3-4`) gelten ab 640 px; darunter sind alle Felder einspaltig.

### Eigenes CSS und Frameworks

Form Studio verträgt sich mit eigenem CSS und mit UIkit, Bootstrap, Tailwind & Co.:

- **Nur eigene Klassen:** Alle Regeln treffen ausschließlich Elemente mit `fs-`-Klassen (bzw. innerhalb von `.fs`). Es gibt keine globalen Selektoren wie `input`, `button`, `label`, `body` oder `*`; `!important` nur für `[hidden]` und das unsichtbare Fangfeld. Die Regeln haben niedrige Spezifität (eine Klasse), die Variablen stehen in `:where(.fs)` (Spezifität 0).
- **Variablen:** Farben, Rundungen, Abstände, Schrift, Fokusrahmen, Fehlerfarbe und Buttons über die Variablen oben.
- **Stylesheet abschalten:** Einstellungen → *Darstellung* → „Stylesheet von Form Studio laden“ aus. Dann kommt nur Markup mit `fs-`-Klassen; das Skript (Schritte, Bedingungen, Prüfung) arbeitet weiter.
- **Klassen des Frameworks ergänzen:** global unter Einstellungen → *Darstellung*, je Formular im Builder unter Einstellungen → *CSS-Klassen (Frameworks)* (ersetzt die globale Angabe), je Platzierung im Modul die Klassen des Rahmens. Möglich für Formular, Eingabefelder, Auswahllisten, mehrzeilige Felder, Checkboxen, Optionsfelder, Haupt- und Zweit-Buttons. Die Klassen ergänzen die `fs-`-Klassen, sie ersetzen sie nicht.
- **JavaScript** ohne Framework-Abhängigkeit; jedes Formular arbeitet nur in seinem eigenen Element (mehrere Formulare je Seite). Nachgeladene Formulare (Ajax, Modal) startet `FormStudio.init(element)`.

UIkit (Stylesheet von Form Studio an, nur Buttons und Felder im UIkit-Stil):

| Element | Klassen |
| --- | --- |
| Eingabefelder / Auswahllisten / mehrzeilig | `uk-input` / `uk-select` / `uk-textarea` |
| Checkboxen / Optionsfelder | `uk-checkbox` / `uk-radio` |
| Haupt-Button / Zweit-Buttons | `uk-button uk-button-primary` / `uk-button uk-button-default` |

Bootstrap 5 (am besten mit abgeschaltetem Form-Studio-Stylesheet; Raster, Schritte und Fortschritt gestaltet dann das Theme):

| Element | Klassen |
| --- | --- |
| Eingabefelder / Auswahllisten / mehrzeilig | `form-control` / `form-select` / `form-control` |
| Checkboxen / Optionsfelder | `form-check-input` |
| Haupt-Button / Zweit-Buttons | `btn btn-primary` / `btn btn-outline-secondary` |

Nur Farben an das Theme anpassen, ohne Klassen:

```css
.fs { --fs-accent: var(--brand, #005d40); --fs-error: #b00020; --fs-focus-width: 2px; --fs-button-radius: 0; }
```

## Besondere Feldtypen

### Auswahl aus Datenquellen
Auswahlliste, Einfach-/Mehrfachauswahl und Bildkacheln können ihre Optionen statt aus einer manuellen Liste beziehen aus
- **einer YForm-Tabelle** (Tabelle, angezeigtes Feld, gespeicherter Wert, optional Filter „Feld = Wert“ und Sortierung) – ohne SQL,
- **einer gespeicherten Abfrage** (Einstellungen, nur Admins; erste Spalte = Wert, zweite = Text; nur `SELECT`) – das SQL steht nie im Formular,
- **einer eigenen Quelle** per Extension Point `FORM_STUDIO_OPTION_SOURCES`.

Eingaben werden immer gegen die aktuellen Optionen geprüft.

### Wiederholgruppe
Beliebig viele Einträge mit eigenen Unterfeldern (Text, E-Mail, Telefon, Zahl, Datum, Auswahl, Mehrzeilig), z. B. Teilnehmende. Mindest-/Höchstzahl, Bezeichnung („Person“) und Knopftext einstellbar. Hinzufügen und Entfernen sind per Tastatur bedienbar und werden Screenreadern angesagt. In E-Mail und PDF als nummerierte Liste.

### IBAN
Gruppierung beim Tippen, Prüfziffer im Browser und auf dem Server. Bank und BIC (DE, AT, CH) über openIBAN.com – **ohne die Kontonummer zu übertragen**: abgefragt wird nur eine Test-IBAN mit derselben Bankleitzahl, das Ergebnis je Bankleitzahl zwischengespeichert. Abschaltbar je Feld.

**Datenschutz:** Die anfragende Person sieht ihre IBAN nur maskiert (`DE89 •••• •••• •••• ••30 00`, mit Bank) – in der Kopie-E-Mail, im PDF-Anhang der Kopie und im PDF zum Herunterladen. Auch der Zwischenspeicher der Einsendungen (`rex_form_studio_submission`) enthält nur die maskierte Fassung. Vollständig erhält die IBAN nur das Haus: E-Mail und PDF an das Team sowie die YForm-Tabelle (dort verschlüsselt, siehe unten).

### Adresse
Straße und Hausnummer, PLZ, Ort, optional Land (Auswahl einschränkbar, z. B. `DE,NL,BE`). Vorschläge beim Tippen über Photon (OpenStreetMap) – serverseitig abgefragt, zwischengespeichert und je Besucher begrenzt; der Browser spricht keinen Drittanbieter an. Barrierefreie Combobox (Pfeiltasten, Enter, Escape). PLZ-Prüfung je Land.

### YForm und das AddOn „Fields“
Mit „Einsendungen in YForm-Tabelle speichern“ legt Form Studio die Tabelle an. Ist das AddOn [Fields](https://github.com/FriendsOfREDAXO/fields) installiert, wird die Tabelle im Backend komfortabler: **je Schritt ein Reiter**, IBAN als `fields_iban`, Wiederholgruppen als `fields_table` (Kopfzeile = Unterfelder). Die Ausgabe im Frontend übernimmt immer Form Studio selbst.

### Verschlüsselung mit yform_encryption
Ist das AddOn [yform_encryption](https://github.com/FriendsOfREDAXO/yform_encryption) (ab 1.3.0) installiert und eingerichtet, verschlüsselt Form Studio beim Abgleich automatisch **IBAN, Adressen und Wiederholgruppen** in der YForm-Tabelle (libsodium, Präfix `ENC:`); im Backend werden sie für berechtigte Nutzer entschlüsselt angezeigt. Weitere Felder lassen sich in der Feldzuordnung von yform_encryption ergänzen. Mit der Feldeinstellung `encrypt_personal = false` bleiben Adresse/Wiederholgruppe unverschlüsselt. Schlüssel sichern – ohne ihn sind die Daten verloren.

## Extension Points

| EP | Zweck |
| --- | --- |
| `FORM_STUDIO_FIELD_TYPES` | Feldtypen ergänzen/ändern |
| `FORM_STUDIO_TEMPLATES` | Vorlagen ergänzen |
| `FORM_STUDIO_SENDER` | Absenderdaten (Logo, Name, Anschrift …) je Domain liefern |
| `FORM_STUDIO_DATA` | Daten vor dem Speichern anpassen |
| `FORM_STUDIO_MAIL` | E-Mail (rex_mailer) vor dem Versand anpassen; `kind` = team/copy |
| `FORM_STUDIO_SUBMITTED` | nach erfolgreicher Einsendung (z. B. CRM-Anbindung) |
| `FORM_STUDIO_OPTION_SOURCES` | eigene Datenquellen für Auswahlfelder: `[key => ['label' => …, 'options' => fn (array $field) => [['value' => …, 'label' => …], …]]]` |
| `FORM_STUDIO_DUPLICATE_SETTINGS` | Einstellungen der Kopie beim Duplizieren anpassen (`original`, `key`); `yform_table` ist bereits entfernt |
| `FORM_STUDIO_BUTTON_PRESET` | Vorbelegung eines Knopfs ergänzen (Subject: `['feld' => 'wert']`, Param `form`: `Form`), z. B. das Haus der aktuellen Domain |
| `FORM_STUDIO_PREVIEW_HEAD` | zusätzliches HTML im `<head>` der Live-Vorschau, z. B. die Stylesheets der Website, damit die Vorschau wie im Frontend aussieht |

### Live-Vorschau im Builder

Unter dem Builder zeigt die **Live-Vorschau** den aktuellen, noch nicht gespeicherten Stand – gerendert vom normalen Frontend-Renderer, also genau so wie auf der Website. Sie aktualisiert sich bei jeder Änderung, zeigt den im Builder gewählten Schritt und lässt sich auf Desktop-, Tablet- und Smartphone-Breite umschalten. Absenden ist in der Vorschau ausgeschaltet. Ohne eigene Styles erscheint das Formular mit dem Form-Studio-CSS; mit `FORM_STUDIO_PREVIEW_HEAD` kommen die Styles der Website dazu:

```php
rex_extension::register('FORM_STUDIO_PREVIEW_HEAD', static fn (rex_extension_point $ep) => $ep->getSubject()
    . '<link rel="stylesheet" href="' . rex_url::assets('css/theme.css') . '">');
```

## Datum & Uhrzeit

Ist das AddOn **a11y_datetime_addon** installiert, erhalten Felder vom Typ `date` und `time` automatisch einen barrierefreien Kalender bzw. eine Zeitauswahl (abschaltbar unter *Einstellungen*). Angezeigt wird im Format der Sprache (z. B. 20.10.2026), gespeichert und geprüft wird weiterhin ISO (`2026-10-20`, `18:00`). `min_days_ahead` bzw. `max` werden an den Kalender übergeben. Ohne das AddOn bleibt das native Eingabefeld des Browsers.

**Vorgabewerte relativ zur aktuellen Zeit:** Im Builder (Eigenschaft „Vorgabewert“) bzw. in der Felddefinition `"default": "now"` (Uhrzeit jetzt, auf 15 Minuten gerundet), `"now+4h"`, `"now+30m"` bzw. für Datumsfelder `"today"`, `"today+7d"`. Feste Werte (`18:00`, `2026-12-24`) gehen ebenso.

**Nie vorbelegen:** Mit Einstellungen → *Datum & Uhrzeit* → „Datum und Uhrzeit nie vorbelegen“ bleiben alle Datums- und Zeitfelder leer – auch wenn im Formular ein Vorgabewert steht. Gäste müssen Termin und Uhrzeit dann selbst wählen. Standard: aus.

## Spamschutz

Ohne Captcha und ohne Drittanbieter: unsichtbares Fangfeld, Mindestausfüllzeit, signierter Zeitstempel, Browser-Nachweis, Link- und Markup-Prüfung, Rate-Limit je Besucher und Doppel-Erkennung. Mit **upkeep** zusätzlich Badword-Prüfung und – bei wiederholtem Spam – IP-Sperre über die Intrusion Prevention. IP-Adressen werden nur gehasht gespeichert. Eigene Prüfungen über den Extension Point `FORM_STUDIO_SPAM_CHECK`.

## Vorbelegung per Link

`?fs_preset[feld]=wert` belegt Auswahlfelder (select, radio, checkboxes, cards) vor – nur mit gültigen Optionen, Freitext lässt sich so nicht einschleusen. Beispiel: `?fs_preset[event_type]=tagung&fs_preset[house]=linde`.

## Voraussetzungen

REDAXO ≥ 5.18, PHP ≥ 8.2, YForm ≥ 4. Optional: phpmailer (E-Mail), pdfout (PDF).

## Lizenz

MIT – Friends Of REDAXO
