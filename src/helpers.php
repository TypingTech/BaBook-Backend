<?php
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