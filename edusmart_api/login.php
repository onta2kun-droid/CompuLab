<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// CORS
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

// Preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../../sambung/edusmart.php';

// Ambil data
$data = json_decode(file_get_contents("php://input"), true);
if (!$data) {
    $data = $_POST;
}

$email = trim($data['email'] ?? '');
$password = trim($data['password'] ?? '');

if (!$email || !$password) {
    echo json_encode([
        "success" => false,
        "message" => "Email dan password wajib diisi"
    ]);
    exit;
}

// Ambil user
$stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user) {
    echo json_encode([
        "success" => false,
        "message" => "Email tidak ditemukan"
    ]);
    exit;
}

// Verifikasi password
if (!password_verify($password, $user['password'])) {
    echo json_encode([
        "success" => false,
        "message" => "Password salah"
    ]);
    exit;
}

// SUCCESS
echo json_encode([
    "success" => true,
    "message" => "Login berhasil",
    "data" => [
        "id" => (int)$user['id'],
        "name" => $user['name'],
        "email" => $user['email'],
        "role" => $user['role'] ?? 'siswa',
        "accMode" => $user['acc_mode'] ?? 'visual',
        "keepSignedIn" => (bool)($user['keep_signed_in'] ?? 1)
    ]
]);