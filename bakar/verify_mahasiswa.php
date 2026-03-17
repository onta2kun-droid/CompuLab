<?php
require_once __DIR__ . '/../../sambung/compulab.php';

$email = trim($_POST['email'] ?? '');
$kode  = trim($_POST['kode'] ?? '');

if ($email === '' || $kode === '') {
    echo json_encode([
        "success" => false,
        "message" => "Email dan kode wajib diisi"
    ]);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode([
        "success" => false,
        "field" => "email",
        "message" => "Format email tidak valid!"
    ]);
    exit;
}

if (!preg_match('/^[0-9]{4}$/', $kode)) {
    echo json_encode([
        "success" => false,
        "field" => "kode",
        "message" => "Kode verifikasi harus 4 digit angka!"
    ]);
    exit;
}

$stmt = $conn->prepare("
    SELECT id, nama, verification_code, verification_expires_at, is_verified
    FROM akun
    WHERE email = ?
    LIMIT 1
");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    if ((int)$row['is_verified'] === 1) {
        echo json_encode([
            "success" => false,
            "already_verified" => true,
            "message" => "Akun ini sudah terverifikasi. Silakan login."
        ]);
        exit;
    }

    if ($row['verification_code'] !== $kode) {
        echo json_encode([
            "success" => false,
            "field" => "kode",
            "message" => "Kode verifikasi salah!"
        ]);
        exit;
    }

    if (strtotime($row['verification_expires_at']) < time()) {
        echo json_encode([
            "success" => false,
            "expired" => true,
            "message" => "Kode verifikasi sudah expired."
        ]);
        exit;
    }

    $update = $conn->prepare("
        UPDATE akun
        SET is_verified = 1,
            verification_code = NULL,
            verification_expires_at = NULL
        WHERE email = ?
    ");
    $update->bind_param("s", $email);

    if ($update->execute()) {
        echo json_encode([
            "success" => true,
            "message" => "Verifikasi berhasil! Silakan login.",
            "data" => [
                "email" => $email
            ]
        ]);
    } else {
        echo json_encode([
            "success" => false,
            "message" => "Verifikasi gagal."
        ]);
    }

    $update->close();
} else {
    echo json_encode([
        "success" => false,
        "field" => "email",
        "message" => "Email tidak ditemukan."
    ]);
}

$stmt->close();
?>