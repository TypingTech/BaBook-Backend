<?php
try {
    $pdo = new PDO('mysql:host=db;dbname=babook', 'root', 'root');
    echo "Koneksi database BERHASIL";
} catch (PDOException $e) {
    echo "Koneksi GAGAL: " . $e->getMessage();
}