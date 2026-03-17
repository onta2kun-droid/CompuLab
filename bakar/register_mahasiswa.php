<?php
require_once __DIR__ . '/../../sambung/compulab.php';

function normalizeWhatsAppNumber($number) {
    $number = trim($number);
    $number = preg_replace('/[^0-9]/', '', $number);
    if (substr($number, 0, 1) === "0") {
        $number = "62" . substr($number, 1);
    }
    return $number;
}

$nama      = trim($_POST['nama'] ?? '');
$nim       = trim($_POST['nim'] ?? '');
$angkatan  = trim($_POST['angkatan'] ?? '');
$email     = trim($_POST['email'] ?? '');
$whatsapp  = normalizeWhatsAppNumber($_POST['whatsapp'] ?? '');
$password  = $_POST['password'] ?? '';

$errors = [
    "nama" => "",
    "nim" => "",
    "angkatan" => "",
    "email" => "",
    "whatsapp" => "",
    "password" => "",
    "send" => ""
];

if ($nama == "") {
    $errors["nama"] = "Nama wajib diisi!";
}

if (!preg_match('/^[0-9]+$/', $nim)) {
    $errors["nim"] = "NIM hanya boleh angka!";
}

if (!in_array($angkatan, ['2024', '2025'])) {
    $errors["angkatan"] = "Angkatan wajib dipilih (2024 atau 2025)!";
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors["email"] = "Format email tidak valid!";
}

if (!preg_match('/^[0-9]{10,15}$/', $whatsapp)) {
    $errors["whatsapp"] = "Nomor WhatsApp tidak valid! Contoh: 08123456789";
}

if (strlen($password) < 6) {
    $errors["password"] = "Password minimal 6 karakter!";
}

if ($errors["email"] == "") {
    $stmt = $conn->prepare("SELECT id FROM akun WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) $errors["email"] = "Email sudah terdaftar!";
    $stmt->close();
}

if ($errors["nim"] == "") {
    $stmt = $conn->prepare("SELECT id FROM akun WHERE nim = ?");
    $stmt->bind_param("s", $nim);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) $errors["nim"] = "NIM sudah terdaftar!";
    $stmt->close();
}

if ($errors["whatsapp"] == "") {
    $stmt = $conn->prepare("SELECT id FROM akun WHERE whatsapp = ?");
    $stmt->bind_param("s", $whatsapp);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) $errors["whatsapp"] = "Nomor WhatsApp sudah terdaftar!";
    $stmt->close();
}

$hasError = false;
foreach ($errors as $value) {
    if ($value !== "") {
        $hasError = true;
        break;
    }
}

if ($hasError) {
    echo json_encode([
        "success" => false,
        "message" => "Validasi gagal",
        "errors" => $errors
    ]);
    exit;
}

$hashedPassword   = password_hash($password, PASSWORD_DEFAULT);
$verificationCode = str_pad((string) random_int(0, 9999), 4, "0", STR_PAD_LEFT);
$expiresAt        = date("Y-m-d H:i:s", strtotime("+10 minutes"));

$role = "mahasiswa";

$stmt = $conn->prepare("
    INSERT INTO akun (
        role, nim, angkatan, nama, email, whatsapp, password,
        is_verified, verification_code, verification_expires_at
    )
    VALUES (?, ?, ?, ?, ?, ?, ?, 0, ?, ?)
");

$stmt->bind_param(
    "ssissssss",
    $role,
    $nim,
    $angkatan,
    $nama,
    $email,
    $whatsapp,
    $hashedPassword,
    $verificationCode,
    $expiresAt
);

if ($stmt->execute()) {
    echo json_encode([
        "success" => true,
        "message" => "Registrasi berhasil",
        "data" => [
            "email" => $email,
            "nama" => $nama,
            "role" => "mahasiswa"
        ]
    ]);
} else {
    echo json_encode([
        "success" => false,
        "message" => "Registrasi gagal disimpan ke database.",
        "errors" => $errors
    ]);
}

$stmt->close();
?>