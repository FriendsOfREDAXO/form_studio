<?php

namespace FriendsOfRedaxo\FormStudio;

/**
 * Vorlagen für neue Formulare (im Builder unter „Vorlage laden“).
 * Eigene Vorlagen über den Extension Point FORM_STUDIO_TEMPLATES ergänzen.
 */
final class Templates
{
    /** @return array<string, array{name: string, description: string, definition: array, settings: array}> */
    public static function all(): array
    {
        return \rex_extension::registerPoint(new \rex_extension_point('FORM_STUDIO_TEMPLATES', [
            'bankettplaner' => self::bankettplaner(),
            'kontakt' => self::kontakt(),
        ]));
    }

    private static function when(string $field, string $op, string $value = '', string $logic = 'all'): array
    {
        return ['logic' => $logic, 'rules' => [['field' => $field, 'op' => $op, 'value' => $value]]];
    }

    public static function bankettplaner(): array
    {
        $celebration = self::when('event_type', 'in', 'hochzeit,geburtstag,familienfeier,firmenfeier');
        return [
            'name' => 'Bankettplaner',
            'description' => 'Anfrage für Feiern und Tagungen: Anlass, Termin, Gäste, Bestuhlung, Verpflegung, Technik, Kontakt.',
            'definition' => ['steps' => [
                [
                    'title' => 'Ihre Veranstaltung',
                    'intro' => 'Erzählen Sie uns, was Sie planen – wir erstellen Ihnen ein unverbindliches Angebot.',
                    'fields' => [
                        ['type' => 'cards', 'name' => 'event_type', 'label' => 'Welchen Anlass planen Sie?', 'required' => true, 'columns' => 4, 'options' => [
                            ['value' => 'hochzeit', 'label' => 'Hochzeit', 'image' => 'fs:event_hochzeit'],
                            ['value' => 'geburtstag', 'label' => 'Geburtstag', 'image' => 'fs:event_geburtstag'],
                            ['value' => 'familienfeier', 'label' => 'Familienfeier', 'description' => 'Taufe, Kommunion, Jubiläum', 'image' => 'fs:event_familienfeier'],
                            ['value' => 'firmenfeier', 'label' => 'Firmen- & Weihnachtsfeier', 'image' => 'fs:event_firmenfeier'],
                            ['value' => 'tagung', 'label' => 'Tagung / Meeting', 'image' => 'fs:event_tagung'],
                            ['value' => 'trauerfeier', 'label' => 'Trauerfeier', 'image' => 'fs:event_trauerfeier'],
                            ['value' => 'sonstiges', 'label' => 'Etwas anderes', 'image' => 'fs:event_sonstiges'],
                        ]],
                        ['type' => 'text', 'name' => 'event_other', 'label' => 'Um welche Veranstaltung geht es?', 'required' => true, 'show_if' => self::when('event_type', 'equals', 'sonstiges')],
                        ['type' => 'date', 'name' => 'date', 'label' => 'Wunschtermin', 'required' => true, 'min_days_ahead' => 7, 'width' => '1-2'],
                        ['type' => 'date', 'name' => 'date_alt', 'label' => 'Ausweichtermin', 'width' => '1-2', 'min_days_ahead' => 7],
                        ['type' => 'time', 'name' => 'time_from', 'label' => 'Beginn (ca.)', 'width' => '1-2'],
                        ['type' => 'time', 'name' => 'time_to', 'label' => 'Ende (ca.)', 'width' => '1-2'],
                        ['type' => 'counter', 'name' => 'guests', 'label' => 'Anzahl Gäste', 'required' => true, 'default' => 30, 'min' => 8, 'max' => 400, 'step' => 1, 'unit' => 'Personen', 'width' => '1-2'],
                        ['type' => 'counter', 'name' => 'children', 'label' => 'davon Kinder (bis 12 Jahre)', 'default' => 0, 'min' => 0, 'max' => 200, 'unit' => 'Kinder', 'width' => '1-2',
                            'show_if' => $celebration],
                        ['type' => 'radio', 'name' => 'house', 'label' => 'Bevorzugtes Haus', 'inline' => true, 'options' => [
                            ['value' => 'parkhotel', 'label' => 'Wellings Parkhotel, Kamp-Lintfort'],
                            ['value' => 'linde', 'label' => 'Romantik Hotel zur Linde, Moers'],
                            ['value' => 'egal', 'label' => 'Bitte beraten Sie mich'],
                        ]],
                    ],
                ],
                [
                    'title' => 'Raum & Bestuhlung',
                    'intro' => 'Wählen Sie die gewünschte Bestuhlung. Den passenden Raum schlagen wir Ihnen anhand der Gästezahl vor.',
                    'fields' => [
                        ['type' => 'cards', 'name' => 'seating', 'label' => 'Bestuhlung', 'required' => true, 'columns' => 4, 'options' => [
                            ['value' => 'bankett_rund', 'label' => 'Bankett', 'description' => 'Runde Tische, ideal für Feiern', 'image' => 'fs:bankett_rund'],
                            ['value' => 'tafel', 'label' => 'Festtafel', 'description' => 'Lange Tafeln, gesellig', 'image' => 'fs:tafel'],
                            ['value' => 'cabaret', 'label' => 'Cabaret', 'description' => 'Halbrund zur Bühne', 'image' => 'fs:cabaret'],
                            ['value' => 'stehempfang', 'label' => 'Stehempfang', 'description' => 'Stehtische, locker', 'image' => 'fs:stehempfang'],
                            ['value' => 'theater', 'label' => 'Reihe / Theater', 'description' => 'Vorträge, viele Personen', 'image' => 'fs:theater'],
                            ['value' => 'parlament', 'label' => 'Parlament', 'description' => 'Schulung mit Tischen', 'image' => 'fs:parlament'],
                            ['value' => 'u_form', 'label' => 'U-Form', 'description' => 'Workshops, Diskussion', 'image' => 'fs:u_form'],
                            ['value' => 'block', 'label' => 'Block / Konferenz', 'description' => 'Besprechung bis ca. 20 Personen', 'image' => 'fs:block'],
                        ]],
                        ['type' => 'radio', 'name' => 'dance', 'label' => 'Wünschen Sie eine Tanzfläche?', 'inline' => true, 'show_if' => $celebration,
                            'options' => [['value' => 'ja', 'label' => 'Ja'], ['value' => 'nein', 'label' => 'Nein'], ['value' => 'offen', 'label' => 'Noch offen']]],
                        ['type' => 'checkboxes', 'name' => 'music', 'label' => 'Musik', 'inline' => true, 'show_if' => $celebration,
                            'options' => [['value' => 'dj', 'label' => 'DJ'], ['value' => 'band', 'label' => 'Live-Band'], ['value' => 'eigene', 'label' => 'Eigene Musik / Playlist'], ['value' => 'keine', 'label' => 'Keine Musik']]],
                    ],
                ],
                [
                    'title' => 'Verpflegung',
                    'fields' => [
                        ['type' => 'checkboxes', 'name' => 'catering', 'label' => 'Was dürfen wir für Sie vorbereiten?', 'options' => [
                            ['value' => 'empfang', 'label' => 'Sektempfang / Empfangsgetränk'],
                            ['value' => 'kaffee', 'label' => 'Kaffee & Kuchen'],
                            ['value' => 'tagungspauschale', 'label' => 'Tagungspauschale (Pausen, Getränke, Mittagessen)'],
                            ['value' => 'mittag', 'label' => 'Mittagessen'],
                            ['value' => 'abend', 'label' => 'Abendessen'],
                            ['value' => 'mitternacht', 'label' => 'Mitternachtsimbiss'],
                        ]],
                        ['type' => 'radio', 'name' => 'meal_style', 'label' => 'Wie möchten Sie essen?', 'inline' => true,
                            'show_if' => ['logic' => 'any', 'rules' => [['field' => 'catering', 'op' => 'equals', 'value' => 'mittag'], ['field' => 'catering', 'op' => 'equals', 'value' => 'abend']]],
                            'options' => [['value' => 'menue', 'label' => 'Serviertes Menü'], ['value' => 'buffet', 'label' => 'Buffet'], ['value' => 'flying', 'label' => 'Flying Dinner'], ['value' => 'beratung', 'label' => 'Bitte beraten Sie mich']]],
                        ['type' => 'radio', 'name' => 'drinks', 'label' => 'Getränke', 'inline' => true,
                            'options' => [['value' => 'pauschale', 'label' => 'Getränkepauschale'], ['value' => 'verbrauch', 'label' => 'Nach Verbrauch'], ['value' => 'beratung', 'label' => 'Bitte beraten Sie mich']]],
                        ['type' => 'checkboxes', 'name' => 'diet', 'label' => 'Besondere Ernährungswünsche', 'inline' => true,
                            'options' => [['value' => 'vegetarisch', 'label' => 'vegetarisch'], ['value' => 'vegan', 'label' => 'vegan'], ['value' => 'glutenfrei', 'label' => 'glutenfrei'], ['value' => 'laktosefrei', 'label' => 'laktosefrei'], ['value' => 'allergien', 'label' => 'Allergien']]],
                        ['type' => 'textarea', 'name' => 'diet_details', 'label' => 'Welche Allergien bzw. für wie viele Personen?', 'rows' => 3,
                            'show_if' => self::when('diet', 'not_empty')],
                        ['type' => 'select', 'name' => 'budget', 'label' => 'Budget für Essen pro Person (optional)', 'width' => '1-2', 'placeholder' => 'Keine Angabe',
                            'options' => [['value' => 'bis40', 'label' => 'bis 40 €'], ['value' => '40-60', 'label' => '40 – 60 €'], ['value' => '60-90', 'label' => '60 – 90 €'], ['value' => 'ab90', 'label' => 'über 90 €']]],
                    ],
                ],
                [
                    'title' => 'Technik & Ausstattung',
                    'fields' => [
                        ['type' => 'checkboxes', 'name' => 'tech', 'label' => 'Technik', 'help' => 'Für Tagungen und Präsentationen – wir stellen alles bereit.', 'options' => [
                            ['value' => 'beamer', 'label' => 'Beamer & Leinwand'],
                            ['value' => 'bildschirm', 'label' => 'Großbildschirm'],
                            ['value' => 'flipchart', 'label' => 'Flipchart'],
                            ['value' => 'pinnwand', 'label' => 'Pinnwand & Moderationskoffer'],
                            ['value' => 'mikrofon', 'label' => 'Funkmikrofon'],
                            ['value' => 'rednerpult', 'label' => 'Rednerpult'],
                            ['value' => 'musikanlage', 'label' => 'Musikanlage'],
                            ['value' => 'video', 'label' => 'Videokonferenz-Technik'],
                            ['value' => 'wlan', 'label' => 'WLAN für alle Gäste'],
                        ]],
                        ['type' => 'checkboxes', 'name' => 'extras', 'label' => 'Dekoration & Extras', 'show_if' => self::when('event_type', 'not_equals', 'tagung'), 'options' => [
                            ['value' => 'blumen', 'label' => 'Tischdekoration & Blumen'],
                            ['value' => 'hussen', 'label' => 'Stuhlhussen'],
                            ['value' => 'menuekarten', 'label' => 'Menükarten'],
                            ['value' => 'torte', 'label' => 'Torte'],
                            ['value' => 'kinder', 'label' => 'Kinderbetreuung'],
                            ['value' => 'fotograf', 'label' => 'Empfehlung Fotograf'],
                        ]],
                        ['type' => 'counter', 'name' => 'rooms', 'label' => 'Benötigen Sie Zimmer für Ihre Gäste?', 'default' => 0, 'min' => 0, 'max' => 120, 'unit' => 'Zimmer', 'width' => '1-2'],
                    ],
                ],
                [
                    'title' => 'Ihre Kontaktdaten',
                    'fields' => [
                        ['type' => 'select', 'name' => 'salutation', 'label' => 'Anrede', 'width' => '1-3', 'placeholder' => 'Keine Angabe',
                            'options' => [['value' => 'frau', 'label' => 'Frau'], ['value' => 'herr', 'label' => 'Herr'], ['value' => 'divers', 'label' => 'Divers']]],
                        ['type' => 'text', 'name' => 'first_name', 'label' => 'Vorname', 'required' => true, 'width' => '1-3', 'autocomplete' => 'given-name'],
                        ['type' => 'text', 'name' => 'last_name', 'label' => 'Nachname', 'required' => true, 'width' => '1-3', 'autocomplete' => 'family-name'],
                        ['type' => 'text', 'name' => 'company', 'label' => 'Firma', 'autocomplete' => 'organization',
                            'show_if' => self::when('event_type', 'in', 'tagung,firmenfeier')],
                        ['type' => 'email', 'name' => 'email', 'label' => 'E-Mail', 'required' => true, 'width' => '1-2'],
                        ['type' => 'tel', 'name' => 'phone', 'label' => 'Telefon', 'required' => true, 'width' => '1-2'],
                        ['type' => 'radio', 'name' => 'contact_by', 'label' => 'Wie dürfen wir Sie kontaktieren?', 'inline' => true,
                            'options' => [['value' => 'telefon', 'label' => 'Telefon'], ['value' => 'email', 'label' => 'E-Mail'], ['value' => 'egal', 'label' => 'Egal']]],
                        ['type' => 'textarea', 'name' => 'message', 'label' => 'Ihre Wünsche und Fragen', 'rows' => 5, 'placeholder' => 'z. B. Ablauf, besondere Wünsche, Fragen zur Anreise …'],
                        ['type' => 'consent', 'name' => 'privacy', 'required' => true, 'label' => 'Datenschutz',
                            'text' => 'Ich bin einverstanden, dass meine Angaben zur Bearbeitung der Anfrage gespeichert werden. Details in der [Datenschutzerklärung](/datenschutz/).'],
                    ],
                ],
            ]],
            'settings' => [
                'submit_label' => 'Unverbindlich anfragen',
                'success_title' => 'Vielen Dank für Ihre Anfrage!',
                'success_text' => "Wir haben Ihre Angaben erhalten und melden uns innerhalb von zwei Werktagen mit einem Angebot.\nEine Kopie haben wir Ihnen per E-Mail geschickt.",
                'store_yform' => true,
                'copy_to_sender' => true,
                'sender_field' => 'email',
                'mail_subject' => 'Veranstaltungsanfrage',
                'pdf' => true,
                'pdf_title' => 'Ihre Veranstaltungsanfrage',
            ],
        ];
    }

    public static function kontakt(): array
    {
        return [
            'name' => 'Kontaktformular',
            'description' => 'Einfaches Kontaktformular.',
            'definition' => ['steps' => [['title' => '', 'fields' => [
                ['type' => 'text', 'name' => 'name', 'label' => 'Name', 'required' => true, 'width' => '1-2', 'autocomplete' => 'name'],
                ['type' => 'email', 'name' => 'email', 'label' => 'E-Mail', 'required' => true, 'width' => '1-2'],
                ['type' => 'textarea', 'name' => 'message', 'label' => 'Nachricht', 'required' => true, 'rows' => 6],
                ['type' => 'consent', 'name' => 'privacy', 'required' => true, 'text' => 'Ich bin mit der Verarbeitung meiner Angaben einverstanden. Details in der [Datenschutzerklärung](/datenschutz/).'],
            ]]]],
            'settings' => ['submit_label' => 'Senden', 'copy_to_sender' => false, 'pdf' => false],
        ];
    }
}
