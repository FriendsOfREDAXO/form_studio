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

Das Markup nutzt UIkit-Klassen. Farben und Rundungen über CSS-Variablen:

```css
.fs { --fs-accent: #005d40; --fs-radius: 4px; }
```

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
