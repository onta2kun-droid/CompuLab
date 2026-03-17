<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../../sambung/compulab.php';

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

$stmtUser = $conn->prepare("
    SELECT id, nim, nama, angkatan, email, whatsapp, is_verified, created_at
    FROM akun
    WHERE api_token = ? AND role = 'mahasiswa'
    LIMIT 1
");
$stmtUser->bind_param("s", $token);
$stmtUser->execute();
$resultUser = $stmtUser->get_result();

if ($resultUser->num_rows <= 0) {
    echo json_encode([
        "success" => false,
        "message" => "User tidak valid"
    ]);
    exit;
}

$user = $resultUser->fetch_assoc();
$stmtUser->close();

if ((int)$user['is_verified'] !== 1) {
    echo json_encode([
        "success" => false,
        "message" => "Akun belum diverifikasi"
    ]);
    exit;
}

echo json_encode([
    "success" => true,
    "data" => [
        "id" => $user["id"],
        "nim" => $user["nim"],
        "nama" => $user["nama"],
        "angkatan" => $user["angkatan"],
        "email" => $user["email"],
        "whatsapp" => $user["whatsapp"],
        "is_verified" => $user["is_verified"],
        "created_at" => $user["created_at"]
    ]
]);
?>