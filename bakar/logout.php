<?php
require_once __DIR__ . '/../../sambung/compulab.php';

$headers = getallheaders();
$authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';

if (!$authHeader) {
    echo json_encode([
        "success" => false,
        "message" => "Token tidak ditemukan"
    ]);
    exit;
}

$token = str_replace('Bearer ', '', $authHeader);

$stmt = $conn->prepare("UPDATE akun SET api_token = NULL WHERE api_token = ?");
$stmt->bind_param("s", $token);
$stmt->execute();

echo json_encode([
    "success" => true,
    "message" => "Logout berhasil"
]);
?>