# Changelog

## 0.2.0 (unveröffentlicht)

Übernimmt die Funktionen von formslider – im eigenen Stil. Alle neuen Funktionen sind je Formular einschaltbar und standardmäßig aus; bestehende Formulare bleiben unverändert.

- **Übergang zwischen Schritten** (gleiten/überblenden, ohne Animation bei „Bewegung reduzieren“), **anklickbarer Fortschritt**, **Schrittanzeige** „Schritt 2 von 5“ mit Ansage für Screenreader, **automatisch weiter** nach einer Einfachauswahl.
- **Zwischenstand im Browser** mit „Eingaben löschen“; nach dem Absenden und nach Ablauf gelöscht.
- **Neuer Feldtyp Ja/Nein-Schalter** (auch in Bedingungen, Prüfseite, E-Mail, PDF, YForm).
- **JavaScript-Ereignisse** `fs:step`, `fs:submit`, `fs:success`, `fs:draft-restored`; optional an `dataLayer`.
- **Formular aus YForm-Tabelle übernehmen** (fieldset = Schritt), nicht übernehmbare Felder werden angezeigt.
- **Modul**: eigener Dankestext und CSS-Klassen je Platzierung, Vorschau im Backend; Modulcode wird bei Update aktualisiert.
- **Einstellungen** im Builder nach Themen gegliedert, mit kurzen Erklärungen.
- Fix: Beschreibung ging beim Speichern verloren; Schalter „Aktiv“ (inaktive Formulare erscheinen nicht); Bilder für Kacheln über den Medienpool; Warnung bei ungespeicherten Änderungen.
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
