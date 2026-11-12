<?php
require __DIR__ . '/../helpers.php';
require __DIR__ . '/../config/db.php';

require_method('POST');
$input = get_json_input();

$email       = strtolower(trim($input['email'] ?? ''));
$otp         = trim($input['otp'] ?? '');
$newPassword = $input['new_password'] ?? '';

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !preg_match('/^\d{6}$/', $otp)) {
    json_response(422, false, 'Email atau kode tidak valid');
}
if (strlen($newPassword) < 8) {
    json_response(422, false, 'Password minimal 8 karakter');
}

try {
    $pdo = getDB();

    $stmt = $pdo->prepare(
        'SELECT r.id AS reset_id, r.user_id
         FROM password_resets r
         JOIN users u ON u.id = r.user_id
         WHERE u.email = ?
           AND r.otp_hash = ?
           AND r.expires_at > NOW()
           AND r.used_at IS NULL'
    );
    $stmt->execute([$email, hash('sha256', $otp)]);
    $reset = $stmt->fetch();

    if (!$reset) {
        json_response(400, false, 'Kode salah atau sudah kedaluwarsa');
    }

    $pdo->beginTransaction();

    // Ganti password
    $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
        ->execute([password_hash($newPassword, PASSWORD_DEFAULT), $reset['user_id']]);

    // OTP hanya boleh dipakai sekali
    $pdo->prepare('UPDATE password_resets SET used_at = NOW() WHERE id = ?')
        ->execute([$reset['reset_id']]);

    // Logout dari semua perangkat: hapus semua token lama
    $pdo->prepare('DELETE FROM api_tokens WHERE user_id = ?')
        ->execute([$reset['user_id']]);

    $pdo->commit();

    json_response(200, true, 'Password berhasil diubah, silakan login kembali');
} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log($e->getMessage());
    json_response(500, false, 'Terjadi kesalahan pada server');
}