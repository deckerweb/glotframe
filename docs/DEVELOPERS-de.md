# Aufbau der GlotFrame-Integration

[English](DEVELOPERS.md)

GlotFrame verwendet WordPress- und GlotPress-APIs. Über `gp_tmpl_load_locations` werden nur `header.php` und `footer.php` bereitgestellt; ausdrücklich übergebene GlotPress-Template-Verzeichnisse behalten Vorrang. Die inneren Ansichten, Berechtigungen und Routen verwaltet GlotPress.

- `Settings`: vollständige Standardwerte, normalisiertes websitebezogenes Schema und Prüfung über die Settings API. Gespeicherte Option: `glotframe_settings`, Schema 5. Vorhandene Einstellungen der ersten Fassung erhalten sichere Standardwerte für die neuen Optionen. Layout-IDs gelten auf der aktuellen Website. Unbekannte Eingabefelder werden verworfen.
- `Layouts`: administrativer Inhaltstyp `glotframe_layout`, Block-Auswahl im Editor und begrenzte Gestaltungsausgabe. Verschachtelte Blöcke werden bei der Ausgabe erneut geprüft. Der eigene REST-Controller begrenzt den Dokumentzugriff auf `manage_options`.
- `Shell`: Template-Auswahl, Inhaltsbereiche pro Anfrage, Asset-Vorbereitung, Head-Integration und eingegrenzte Designausgabe. Die Layouts werden vor dem Head gerendert, damit Block-Assets gesammelt werden. Die Head-/Footer-Hooks von GlotPress und die Head-/Body-/Footer-Hooks von WordPress bleiben verfügbar.
- `Admin`: Einstellungen, Auswahlfelder, lokale Hilfe und zugänglicher Änderungsverlauf im Dialog.
- `Components`: Registrierung des fest eingebundenen Updaters sowie Prüfung von Paketidentität, Version und Plattformanforderungen. Gemeinsame Komponenten werden in ihren jeweiligen Projekten gepflegt.

Das Plugin legt keine eigenen Datenbanktabellen oder Cronaufgaben an. Die Library verwaltet ihre gemeinsamen Daten und deren Bereinigung selbst. GlotFrame-Layouts und Einstellungen bleiben beim Deaktivieren und beim standardmäßigen Löschen erhalten. Die ausdrückliche Löschoption gilt pro Website und umfasst Revisionen, Papierkorb und automatische Entwürfe. GlotPress-Inhalte werden niemals gelöscht.

Migrationen müssen vorhandene Benutzereinstellungen erhalten. Schemaänderungen ausdrücklich behandeln und Updates mit dem tatsächlichen Vorgängerpaket prüfen. Gestaltungsänderungen dürfen API, Downloads und die Formularverträge des Übersetzungseditors nicht verändern. Nicht öffentliche Klassenmethoden sind keine stabilen Schnittstellen für Drittanbieter.

`Routing` filtert den GlotPress-Basispfad und leitet nur geeignete GET-/HEAD-Startseitenaufrufe weiter. Die WordPress-Leseeinstellungen bleiben erhalten. GlotPress aktualisiert seine Rewrite-Regeln bei einer Änderung der erzeugten Basisregeln selbst. `Navigation` verwendet eine Projektabfrage und die nativen `gp_projects`-Filter, begrenzt die Unterprojekttiefe und beachtet die aktuelle Website. `Presets` verwaltet Farbvorlagen, Luminanzberechnungen sowie semantische Farben für Bedienelemente und Statusmeldungen.
