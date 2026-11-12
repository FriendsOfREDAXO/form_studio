# Changelog

## 0.4.0 (2026-11-12)

- **Knöpfe zu Formularen:** `<form-studio-button data-form="…" data-text="…" data-preset="…">` öffnet die Online-Seite, auf der das Formular eingebunden ist (aktuelle Domain zuerst, Vorbelegung per `fs_preset`). Markup über die neue **Knopf-Vorlage** in den Einstellungen (Platzhalter `{{url}}`, `{{text}}`, `{{form_name}}`, `{{form_key}}`, `{{form_id}}`) – passend zu UIkit, Bootstrap oder einem Overlay-Skript der Website. PHP: `Button::render()`, `Button::url()`. EP `FORM_STUDIO_BUTTON_PRESET`.
- **Snippets automatisch anlegen** (mit dem AddOn *snippets*, Standard aus): je Formular ein Snippet `formular_<name>`, z. B. `[[snippet:formular_tagungsplaner|text=Tagung anfragen]]`. Abgleich beim Anlegen, Duplizieren, Speichern und Löschen von Formularen und beim Speichern der Einstellungen; inaktive Formulare → inaktives Snippet; Umbenennen ändert den Schlüssel nicht; Kategorie wählbar.

## 0.3.0 (2026-11-10)

Führt die Linie 0.2.0 (formslider-Funktionen) mit den bisher unveröffentlichten Funktionen aus dem Projekteinsatz zusammen. Bestehende Formulare, Einstellungen und Einbindungen bleiben unverändert; alle neuen Funktionen sind standardmäßig aus.

- **Formular duplizieren** (Formularliste): Kopie „<Name> (Kopie)“ mit neuem Schlüssel, Definition, Einstellungen und Beschreibung, inaktiv. Eine fest eingetragene YForm-Tabelle wird nicht übernommen – die Kopie bekommt beim ersten Abgleich eine eigene Tabelle. EP `FORM_STUDIO_DUPLICATE_SETTINGS`.
- **Datum und Uhrzeit nie vorbelegen** (Einstellungen → Datum & Uhrzeit, Standard aus): Datums- und Zeitfelder bleiben leer, auch bei festen oder relativen Vorgabewerten (`now`, `now+4h`, `today+7d`). Der Vorgabewert ist jetzt auch im Builder einstellbar.
- **Eigenes CSS und Frameworks:** Stylesheet abschaltbar (Einstellungen → Darstellung); zusätzliche CSS-Klassen für Formular, Felder, Checkboxen/Optionsfelder und Buttons global und je Formular (z. B. UIkit, Bootstrap); weitere CSS-Variablen (`--fs-focus-width`, `--fs-button-secondary-*`); `FormStudio.init(element)` für nachgeladene Formulare; Skript arbeitet nur im eigenen Formular (mehrere Formulare und Erfolgsmeldungen je Seite).
- **Modul:** Ein im Projekt angepasstes Modul „Form Studio: Formular“ wird bei Update/Re-Install nicht mehr überschrieben (Hinweis in den Einstellungen).
- **Auswahl aus Datenquellen:** YForm-Tabelle (ohne SQL), gespeicherte Abfragen (nur Admins, nur SELECT) und eigene Quellen (EP `FORM_STUDIO_OPTION_SOURCES`) für Auswahlliste, Einfach-/Mehrfachauswahl und Bildkacheln.
- **Neue Feldtypen:** Wiederholgruppe (Unterfelder, Min/Max, barrierefreies Hinzufügen/Entfernen), IBAN (Prüfziffer, Bank/BIC über openIBAN ohne Übertragung der Kontonummer), Adresse (Vorschläge über Photon/OpenStreetMap per Server-Proxy mit Zwischenspeicher und Begrenzung, PLZ-Prüfung je Land).
- **YForm mit dem AddOn „Fields“:** Reiter je Schritt (`fields_interactive`), `fields_iban`, `fields_table` für Wiederholgruppen; Feldreihenfolge folgt dem Formular.
- **IBAN-Datenschutz:** Kopie-E-Mail, Kopie-PDF, PDF-Download und Zwischenspeicher zeigen die IBAN nur maskiert (Land, Prüfziffer, letzte 4 Stellen, Bank); E-Mail und PDF an das Haus vollständig.
- **Verschlüsselung mit yform_encryption:** IBAN, Adressen und Wiederholgruppen werden in der YForm-Tabelle automatisch verschlüsselt (Feldzuordnung und Spaltentyp werden beim Abgleich gesetzt).
- Vorschau: Höhe begrenzt (eigenes Scrollen), Auswahl eines Felds im Builder springt in der Vorschau dorthin.
- **Live-Vorschau im Builder:** zeigt den ungespeicherten Stand mit dem Frontend-Renderer (API `form_studio_preview`, nur Backend mit Recht und CSRF-Token), aktualisiert sich bei jeder Änderung, folgt dem gewählten Schritt, Breiten Desktop/Tablet/Smartphone, Absenden deaktiviert; eigene Website-Styles über den EP `FORM_STUDIO_PREVIEW_HEAD`. Neu: `Form::preview()` für ungespeicherte Stände.
- Fix: Ja/Nein-Felder werden auch neben den neuen Feldtypen (Wiederholgruppe als `fields_table`, verschlüsselte Felder) als 0/1 in YForm gespeichert.

## 0.2.0 (2026-10-18)

Übernimmt die Funktionen von formslider – im eigenen Stil. Alle neuen Funktionen sind je Formular einschaltbar und standardmäßig aus; bestehende Formulare bleiben unverändert.

- **Übergang zwischen Schritten** (gleiten/überblenden, ohne Animation bei „Bewegung reduzieren“), **anklickbarer Fortschritt**, **Schrittanzeige** „Schritt 2 von 5“ mit Ansage für Screenreader, **automatisch weiter** nach einer Einfachauswahl.
- **Zwischenstand im Browser** mit „Eingaben löschen“; nach dem Absenden und nach Ablauf gelöscht.
- **Neuer Feldtyp Ja/Nein-Schalter** (auch in Bedingungen, Prüfseite, E-Mail, PDF, YForm).
- **JavaScript-Ereignisse** `fs:step`, `fs:submit`, `fs:success`, `fs:draft-restored`; optional an `dataLayer`.
- **Formular aus YForm-Tabelle übernehmen** (fieldset = Schritt), nicht übernehmbare Felder werden angezeigt.
- **Modul**: eigener Dankestext und CSS-Klassen je Platzierung, Vorschau im Backend; Modulcode wird bei Update aktualisiert.
- **PDF-Vorlagen** als REDAXO-Templates: unter Einstellungen → PDF-Vorlagen anlegen (Kopie des Standard-Layouts), unter Templates anpassen, je Formular auswählen. Dazu **Schlusstext** und **Unterschriftszeilen** (Beschriftung frei, z. B. „Ort, Datum | Unterschrift“).
- **Einstellungen** im Builder nach Themen gegliedert, mit kurzen Erklärungen.
- Fix: Beschreibung ging beim Speichern verloren; Schalter „Aktiv“ (inaktive Formulare erscheinen nicht); Bilder für Kacheln über den Medienpool; Warnung bei ungespeicherten Änderungen.
- Fix: Einwilligungen erschienen im PDF unter „Weitere Angaben“.
- Fix: Einwilligung und Schalter werden in YForm als 0/1 gespeichert (Fehler bei strikter Datenbank).
- Fix: Feldfehler verschwinden schon beim Tippen – der Klick auf „Weiter“ ging sonst ins Leere; Einwilligungstext mit Link bricht nicht mehr schmal um.


## 0.1.0

Erste veröffentlichte Version, entstanden für einen Hotel-Bankettplaner.

- Erste Fassung: Builder, 16 Feldtypen, Schritte, Bedingungen (Client + Server), YForm-Abgleich, E-Mail mit Kopie, PDF über pdfout, Spamschutz, Vorlagen „Bankettplaner“ und „Kontaktformular“, Bestuhlungs- und Anlass-Grafiken.
- **Eigenes CSS statt UIkit-Klassen:** Markup nur noch mit `fs-`-Klassen, framework-frei; alles über CSS-Variablen anpassbar (Standardwerte in `:where(.fs)`, eigene Angaben gewinnen immer). Akzent-Standard mit ausreichendem Kontrast.
- **Spamschutz (SpamGuard):** Browser-Nachweis, Link-/Markup-Prüfung, Rate-Limit, Doppel-Erkennung; mit upkeep Badwords und IP-Sperre bei wiederholtem Spam
- **Datums-/Zeitauswahl** mit a11y_datetime_addon (optional), auch in nachgeladenen Formularen (z. B. Ajax-Modal)
- **Relative Vorgabewerte** für Uhrzeit und Datum: `now` (auf 15 Minuten gerundet), `now+4h`, `now+30m`, `today`, `today+7d`
- **Vorbelegung per Link** (`fs_preset`), **Reiter „Überprüfen“** mit Gruppen je Schritt
- **Validierung:** eigene Fehlermeldungen in Sie-Form; Feld- und Datumsfehler verschwinden, sobald der Wert gültig ist
- **PDF:** überarbeiteter Kopf, Angaben nach Schritten gruppiert; der PDF-Anhang lässt sich für die E-Mail ans Haus und für die Kopie an die anfragende Person getrennt schalten
- Vorlage „Bankettplaner“: Bestuhlung „Bitte beraten Sie mich“
