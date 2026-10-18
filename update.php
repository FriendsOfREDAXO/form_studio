<?php

/** @var rex_addon $this */

// Beim Update über den Installer: Tabellen und Modul wie bei der Installation abgleichen
// (läuft aus dem Ordner der neuen Version, daher __DIR__)
include __DIR__ . '/install.php';
