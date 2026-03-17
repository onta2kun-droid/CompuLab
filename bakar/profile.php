<?php
require_once 'config.php';

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

$stmt = $conn->prepare("
    SELECT id, role, nim, angkatan, nama, email, whatsapp, is_verified, created_at
    FROM akun
    WHERE api_token = ?
    LIMIT 1
");
$stmt->bind_param("s", $token);
$stmt->execute();
$result = $stmt->get_result();

if ($user = $result->fetch_assoc()) {
    echo json_encode([
        "success" => true,
        "data" => $user
    ]);
} else {
    echo json_encode([
        "success" => false,
        "message" => "Token tidak valid"
    ]);
}
?>