<?php
// includes/db.php

$host     = 'localhost';
$dbname   = 'efsaneba_uygulama';  // Örnek veritabanı adı
$user     = 'efsaneba_serhan';         // DB kullanıcı adı
$password = 'Aooh8x!!!4189';             // DB şifresi

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Veritabanı bağlantı hatası: " . $e->getMessage());
}
