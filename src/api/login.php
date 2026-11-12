<?php
require __DIR__ . '/../helpers.php';
require __DIR__ . '/../config/db.php';

require_method('POST');
$input = get_json_input();

$email    = strtolower(trim($input['email'] ?? ''));
$password = $input['password'] ?? '';

if ($email === '' || $password === '') {
    json_response(422, false, 'Email dan password wajib diisi');
}

try {
    $pdo = getDB();

    $stmt = $pdo->prepare('SELECT id, name, email, password_hash FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    // password_hash bisa NULL (user Google), jadi dicek dulu sebelum password_verify
    $valid = $user
        && $user['password_hash'] !== null
        && password_verify($password, $user['password_hash']);

    // Pesan sengaja sama untuk "email tidak ada" dan "password salah"
    if (!$valid) {
        json_response(401, false, 'Email atau password salah');
    }

    $token = create_token($pdo, (int) $user['id']);

    json_response(200, true, 'Login berhasil', [
        'token' => $token,
        'user'  => [
            'id'    => (int) $user['id'],
            'name'  => $user['name'],
            'email' => $user['email'],
        ],
    ]);
} catch (PDOException $e) {
    error_log($e->getMessage());
    json_response(500, false, 'Terjadi kesalahan pada server');
}