<?php
// Keep portal tables isolated from the existing public-site tables.
require_once dirname(__DIR__, 2) . '/config/db.php';
$portalPdo = db();
$portalPdo->exec('SET search_path TO rc2000_admin, public');
