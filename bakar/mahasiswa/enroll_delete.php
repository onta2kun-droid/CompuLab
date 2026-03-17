<?php
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
    SELECT nim, is_verified
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

$nim = $user['nim'];
$enroll_id = (int) ($_POST['enroll_id'] ?? 0);

if ($enroll_id <= 0) {
    echo json_encode([
        "success" => false,
        "message" => "ID enroll tidak valid."
    ]);
    exit;
}

$stmtDelete = $conn->prepare("
    DELETE FROM mahasiswa_matkul
    WHERE id = ? AND nim = ?
");
$stmtDelete->bind_param("is", $enroll_id, $nim);

if ($stmtDelete->execute()) {
    echo json_encode([
        "success" => true,
        "message" => "Kelas berhasil dihapus dari enroll."
    ]);
} else {
    echo json_encode([
        "success" => false,
        "message" => "Gagal menghapus enroll."
    ]);
}
$stmtDelete->close();
?>