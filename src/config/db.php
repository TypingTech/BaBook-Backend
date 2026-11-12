<?php
function getDB(): PDO
{
    static $pdo = null; // koneksi dibuat sekali, lalu dipakai ulang

    if ($pdo === null) {
        $dsn = 'mysql:host=' . getenv('DB_HOST')
             . ';dbname=' . getenv('DB_NAME')
             . ';charset=utf8mb4';

        $pdo = new PDO($dsn, getenv('DB_USER'), getenv('DB_PASS'), [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }
    return $pdo;
}