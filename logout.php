<?php
// logout.php
require_once 'includes/db.php';

kullaniciCikis();

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header('Location: giris.php');
exit;
