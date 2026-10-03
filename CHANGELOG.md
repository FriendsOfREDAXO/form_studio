# Changelog


## Unveröffentlicht

- **Eigenes CSS statt UIkit-Klassen:** Markup nur noch mit `fs-`-Klassen, framework-frei; alles über CSS-Variablen anpassbar (Standardwerte in `:where(.fs)`, eigene Angaben gewinnen immer). Akzent-Standard mit ausreichendem Kontrast.
- **Spamschutz (SpamGuard):** Browser-Nachweis, Link-/Markup-Prüfung, Rate-Limit, Doppel-Erkennung; mit upkeep Badwords und IP-Sperre bei wiederholtem Spam
- **Datums-/Zeitauswahl** mit a11y_datetime_addon (optional)
- **Vorbelegung per Link** (`fs_preset`), **Reiter „Überprüfen“** mit Gruppen je Schritt
## 0.1.0-dev

- Erste Fassung: Builder, 16 Feldtypen, Schritte, Bedingungen (Client + Server), YForm-Abgleich, E-Mail mit Kopie, PDF über pdfout, Spamschutz, Vorlagen „Bankettplaner“ und „Kontaktformular“, Bestuhlungs- und Anlass-Grafiken.
