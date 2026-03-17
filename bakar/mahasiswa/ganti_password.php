<?php
header('Content-Type: application/json; charset=utf-8');
require_once '../config.php';

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
    SELECT id, password, is_verified
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

$password_lama = $_POST['password_lama'] ?? '';
$password_baru = $_POST['password_baru'] ?? '';
$konfirmasi_password = $_POST['konfirmasi_password'] ?? '';

if ($password_lama === "" || $password_baru === "" || $konfirmasi_password === "") {
    echo json_encode([
        "success" => false,
        "message" => "Semua field password wajib diisi."
    ]);
    exit;
}

if (!password_verify($password_lama, $user['password'])) {
    echo json_encode([
        "success" => false,
        "message" => "Password lama tidak benar."
    ]);
    exit;
}

if (strlen($password_baru) < 6) {
    echo json_encode([
        "success" => false,
        "message" => "Password baru minimal 6 karakter."
    ]);
    exit;
}

if ($password_baru !== $konfirmasi_password) {
    echo json_encode([
        "success" => false,
        "message" => "Konfirmasi password baru tidak cocok."
    ]);
    exit;
}

if ($password_lama === $password_baru) {
    echo json_encode([
        "success" => false,
        "message" => "Password baru tidak boleh sama dengan password lama."
    ]);
    exit;
}

$hashPasswordBaru = password_hash($password_baru, PASSWORD_DEFAULT);

$stmtUpdate = $conn->prepare("
    UPDATE akun
    SET password = ?
    WHERE id = ? AND role = 'mahasiswa'
");
$stmtUpdate->bind_param("si", $hashPasswordBaru, $user['id']);

if ($stmtUpdate->execute()) {
    echo json_encode([
        "success" => true,
        "message" => "Password berhasil diganti."
    ]);
} else {
    echo json_encode([
        "success" => false,
        "message" => "Gagal mengganti password."
    ]);
}

$stmtUpdate->close();
?>