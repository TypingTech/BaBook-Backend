<?php
require __DIR__ . '/../helpers.php';
require __DIR__ . '/../config/db.php';

require_method('POST');
$input = get_json_input();

$name     = trim($input['name'] ?? '');
$email    = strtolower(trim($input['email'] ?? ''));
$password = $input['password'] ?? '';

// 1. Validasi di sisi server
$errors = [];

if ($name === '' || mb_strlen($name) > 100) {
    $errors['name'] = 'Nama wajib diisi (maksimal 100 karakter)';
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150) {
    $errors['email'] = 'Format email tidak valid';
}
if (strlen($password) < 8) {
    $errors['password'] = 'Password minimal 8 karakter';
}

if ($errors) {
    json_response(422, false, 'Validasi gagal', ['errors' => $errors]);
}

try {
    $pdo = getDB();

    // 2. Cek apakah email sudah terdaftar
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        json_response(409, false, 'Email sudah terdaftar');
    }

    // 3. Simpan user dengan password yang di-hash
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare('INSERT INTO users (name, email, password_hash) VALUES (?, ?, ?)');
    $stmt->execute([$name, $email, $hash]);

    json_response(201, true, 'Registrasi berhasil', [
        'user' => [
            'id'    => (int) $pdo->lastInsertId(),
            'name'  => $name,
            'email' => $email,
        ],
    ]);
} catch (PDOException $e) {
    error_log($e->getMessage()); // detail error masuk log, bukan ke user
    json_response(500, false, 'Terjadi kesalahan pada server');
}