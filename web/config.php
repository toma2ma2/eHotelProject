<?php
$dbDsn = getenv('HOTEL_DB_DSN') ?: 'pgsql:host=127.0.0.1;dbname=hotelproj';
$dbUser = getenv('HOTEL_DB_USER') ?: getenv('USER') ?: 'postgres';
$dbPass = getenv('HOTEL_DB_PASS') ?: '';

$pdo = new PDO($dbDsn, $dbUser, $dbPass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
