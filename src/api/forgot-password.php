<?php
require __DIR__ . '/../helpers.php';
require __DIR__ . '/../config/db.php';

require_method('POST');
$input = get_json_input();

$email = strtolower(trim($input['email'] ?? ''));

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_response(422, false, 'Format email tidak valid');
}

// Pesan yang sama dikirim apa pun hasilnya, agar email terdaftar tidak bisa ditebak
$genericMessage = 'Jika email terdaftar, kode verifikasi telah dikirim';

try {
    $pdo = getDB();

    $stmt = $pdo->prepare('SELECT id, name FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user) {
        $ttl = (int) (getenv('OTP_TTL_MINUTES') ?: 10);

        // Hapus OTP lama milik user ini
        $pdo->prepare('DELETE FROM password_resets WHERE user_id = ?')
            ->execute([$user['id']]);

        // Buat kode 6 digit (random_int aman secara kriptografi)
        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $pdo->prepare(
            'INSERT INTO password_resets (user_id, otp_hash, expires_at)
             VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? MINUTE))'
        )->execute([$user['id'], hash('sha256', $otp), $ttl]);

        send_mail(
            $email,
            'Kode reset password BaBook',
            "Halo {$user['name']},\n\nKode reset password kamu: {$otp}\n"
            . "Kode berlaku {$ttl} menit. Jangan bagikan kode ini ke siapa pun.\n\n"
            . "Abaikan email ini jika kamu tidak merasa memintanya."
        );
    }

    json_response(200, true, $genericMessage);
} catch (PDOException $e) {
    error_log($e->getMessage());
    json_response(500, false, 'Terjadi kesalahan pada server');
}