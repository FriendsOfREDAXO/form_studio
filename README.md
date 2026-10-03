# Form Studio

Visueller Formular-Builder für REDAXO: Formulare in **Schritten** mit **Bedingungen**, Speicherung in einer automatisch angelegten **YForm-Tabelle**, **E-Mail** an das Haus und eine Kopie an die anfragende Person, **PDF** zum Herunterladen (pdfout) – barrierefrei und ohne Abhängigkeiten im Frontend.

> Status: 0.1.0-dev – in Entwicklung, entstanden für einen Hotel-Bankettplaner.

## Funktionen

- **Builder im Backend** (Form Studio → Formulare): Feldpalette, Schritte, Drag & Drop, Eigenschaften je Feldtyp, Bedingungs-Editor, Einstellungen.
- **16 Feldtypen**: Text, E-Mail, Telefon, Zahl, Zähler (− / +), mehrzeiliger Text, Datum (mit Mindestvorlauf), Uhrzeit, Auswahlliste, Einfach-/Mehrfachauswahl, **Bildkacheln** (z. B. Bestuhlung), Einwilligung (mit Links), Zwischenüberschrift, Hinweistext, verstecktes Feld. Weitere über den Extension Point `FORM_STUDIO_FIELD_TYPES`.
- **Bedingungen** für Felder und ganze Schritte („anzeigen, wenn …“, alle/eine der Regeln; ist gleich, enthält, ist eines von, ausgefüllt, größer/kleiner …) – im Browser **und** auf dem Server ausgewertet. Ausgeblendete Felder werden weder geprüft noch gespeichert.
- **Mehrstufiger Assistent** mit Fortschritt, Prüfung pro Schritt und Zusammenfassung vor dem Absenden. Ohne JavaScript ein normales Formular.
- **Speichern**: optional in YForm (Tabelle und Felder werden angelegt/ergänzt, nie gelöscht).
- **E-Mail** an Empfänger des Formulars, **Kopie** an die anfragende Person, jeweils mit PDF im Anhang.
- **PDF** mit Logo und Absenderdaten je Domain (Einstellungen oder Extension Point `FORM_STUDIO_SENDER`); Layout überschreibbar (`fragments/form_studio/pdf.php`).
- **Spamschutz** (Honeypot, signierte Mindestausfüllzeit), CSRF-Schutz, Post/Redirect/Get.
- **Datenschutz**: Einsendungen für den PDF-Download werden nach einstellbarer Frist automatisch gelöscht.
- **Vorlagen**: „Bankettplaner“ (Anlass, Termin, Gäste, Bestuhlung mit Grafiken, Verpflegung, Technik, Kontakt) und „Kontaktformular“. Eigene über `FORM_STUDIO_TEMPLATES`.

## Einbindung

Bei der Installation wird das Modul **„Form Studio: Formular“** angelegt – Formular auswählen, fertig. In PHP:

```php
$form = FriendsOfRedaxo\FormStudio\Form::getByKey('bankettplaner');
echo FriendsOfRedaxo\FormStudio\Processor::handle($form);
```

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

Feldbreiten (`width` in der Definition: `1-2`, `1-3`, `2-3`, `1-4`, `3-4`) gelten ab 640 px; darunter sind alle Felder einspaltig.

## Extension Points

| EP | Zweck |
| --- | --- |
| `FORM_STUDIO_FIELD_TYPES` | Feldtypen ergänzen/ändern |
| `FORM_STUDIO_TEMPLATES` | Vorlagen ergänzen |
| `FORM_STUDIO_SENDER` | Absenderdaten (Logo, Name, Anschrift …) je Domain liefern |
| `FORM_STUDIO_DATA` | Daten vor dem Speichern anpassen |
| `FORM_STUDIO_MAIL` | E-Mail (rex_mailer) vor dem Versand anpassen; `kind` = team/copy |
| `FORM_STUDIO_SUBMITTED` | nach erfolgreicher Einsendung (z. B. CRM-Anbindung) |

## Voraussetzungen

REDAXO ≥ 5.18, PHP ≥ 8.2, YForm ≥ 4. Optional: phpmailer (E-Mail), pdfout (PDF).

## Lizenz

MIT – Friends Of REDAXO


## Datum & Uhrzeit

Ist das AddOn **a11y_datetime_addon** installiert, erhalten Felder vom Typ `date` und `time` automatisch einen barrierefreien Kalender bzw. eine Zeitauswahl (abschaltbar unter *Einstellungen*). Angezeigt wird im Format der Sprache (z. B. 20.10.2026), gespeichert und geprüft wird weiterhin ISO (`2026-10-20`, `18:00`). `min_days_ahead` bzw. `max` werden an den Kalender übergeben. Ohne das AddOn bleibt das native Eingabefeld des Browsers.

**Vorgabewerte relativ zur aktuellen Zeit:** In der Felddefinition `"default": "now"` (Uhrzeit jetzt, auf 15 Minuten gerundet), `"now+4h"`, `"now+30m"` bzw. für Datumsfelder `"today"`, `"today+7d"`.

## Spamschutz

Ohne Captcha und ohne Drittanbieter: unsichtbares Fangfeld, Mindestausfüllzeit, signierter Zeitstempel, Browser-Nachweis, Link- und Markup-Prüfung, Rate-Limit je Besucher und Doppel-Erkennung. Mit **upkeep** zusätzlich Badword-Prüfung und – bei wiederholtem Spam – IP-Sperre über die Intrusion Prevention. IP-Adressen werden nur gehasht gespeichert. Eigene Prüfungen über den Extension Point `FORM_STUDIO_SPAM_CHECK`.

## Vorbelegung per Link

`?fs_preset[feld]=wert` belegt Auswahlfelder (select, radio, checkboxes, cards) vor – nur mit gültigen Optionen, Freitext lässt sich so nicht einschleusen. Beispiel: `?fs_preset[event_type]=tagung&fs_preset[house]=linde`.
