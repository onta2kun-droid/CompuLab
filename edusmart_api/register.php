<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// CORS
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../../sambung/edusmart.php';

// Ambil data JSON / POST
$data = json_decode(file_get_contents("php://input"), true);
if (!$data) {
    $data = $_POST;
}

$name = trim($data['name'] ?? '');
$email = trim($data['email'] ?? '');
$password = trim($data['password'] ?? '');

if (!$name || !$email || !$password) {
    echo json_encode([
        "success" => false,
        "message" => "Semua field wajib diisi"
    ]);
    exit;
}

// Cek email
$stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
$stmt->execute([$email]);

if ($stmt->fetch()) {
    echo json_encode([
        "success" => false,
        "message" => "Email sudah digunakan"
    ]);
    exit;
}

// Hash password
$hashedPassword = password_hash($password, PASSWORD_BCRYPT);

// Insert
$stmt = $pdo->prepare("INSERT INTO users (name, email, password, role, acc_mode, keep_signed_in) VALUES (?, ?, ?, 'siswa', 'visual', 1)");
$stmt->execute([$name, $email, $hashedPassword]);

echo json_encode([
    "success" => true,
    "message" => "Registrasi berhasil"
]);