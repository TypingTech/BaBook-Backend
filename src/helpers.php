<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailException;

require_once __DIR__ . '/vendor/autoload.php';

header('Content-Type: application/json; charset=utf-8');

// Kirim respons JSON dengan format yang konsisten, lalu hentikan script
function json_response(int $status, bool $success, string $message, array $data = null): void
{
    http_response_code($status);
    $body = ['success' => $success, 'message' => $message];
    if ($data !== null) {
        $body['data'] = $data;
    }
    echo json_encode($body, JSON_UNESCAPED_UNICODE);
    exit;
}

// Endpoint hanya boleh menerima method tertentu (misalnya POST)
function require_method(string $method): void
{
    if ($_SERVER['REQUEST_METHOD'] !== $method) {
        json_response(405, false, 'Method tidak diizinkan');
    }
}

// Baca body JSON yang dikirim Flutter
function get_json_input(): array
{
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        json_response(400, false, 'Body harus berupa JSON yang valid');
    }
    return $input;
}

// Buat token acak, simpan hash-nya, kembalikan token asli ke client
function create_token(PDO $pdo, int $userId): string
{
    $ttlDays   = (int) (getenv('TOKEN_TTL_DAYS') ?: 7);
    $token     = bin2hex(random_bytes(32));          // 64 karakter acak
    $tokenHash = hash('sha256', $token);

    $stmt = $pdo->prepare(
        'INSERT INTO api_tokens (user_id, token_hash, expires_at)
         VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? DAY))'
    );
    $stmt->execute([$userId, $tokenHash, $ttlDays]);

    return $token;
}

// Ambil user dari header Authorization. Kalau token salah/kedaluwarsa -> 401
function require_auth(PDO $pdo): array
{
    $header = $_SERVER['HTTP_AUTHORIZATION']
        ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
        ?? '';

    if (!preg_match('/^Bearer\s+(\S+)$/', $header, $m)) {
        json_response(401, false, 'Token tidak ditemukan');
    }

    $stmt = $pdo->prepare(
        'SELECT u.id, u.name, u.email
         FROM api_tokens t
         JOIN users u ON u.id = t.user_id
         WHERE t.token_hash = ? AND t.expires_at > NOW()'
    );
    $stmt->execute([hash('sha256', $m[1])]);
    $user = $stmt->fetch();

    if (!$user) {
        json_response(401, false, 'Token tidak valid atau sudah kedaluwarsa');
    }
    return $user;
}

// Kirim email lewat SMTP (Mailpit saat development). Return true jika sukses
function send_mail(string $to, string $subject, string $body): bool
{
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = getenv('MAIL_HOST') ?: 'mailpit';
        $mail->Port       = (int) (getenv('MAIL_PORT') ?: 1025);
        $mail->SMTPAuth   = false;
        $mail->SMTPSecure = false;
        $mail->SMTPAutoTLS = false;
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom(getenv('MAIL_FROM') ?: 'noreply@babook.local', 'BaBook');
        $mail->addAddress($to);
        $mail->Subject = $subject;
        $mail->Body    = $body;

        $mail->send();
        return true;
    } catch (MailException $e) {
        error_log('Gagal kirim email: ' . $mail->ErrorInfo);
        return false;
    }
}