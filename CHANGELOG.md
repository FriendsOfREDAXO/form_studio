# Changelog


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
