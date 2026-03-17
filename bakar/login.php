<?php
require_once __DIR__ . '/../../sambung/compulab.php';

$login = $_POST['login'] ?? '';
$password = $_POST['password'] ?? '';
$role = $_POST['role'] ?? '';

if (empty($login) || empty($password) || empty($role)) {
    echo json_encode([
        "success" => false,
        "message" => "Login, password, dan role wajib diisi"
    ]);
    exit;
}

$stmt = $conn->prepare("
    SELECT id, role, nim, nama, email, password, angkatan, whatsapp, is_verified
    FROM akun
    WHERE email = ? AND role = ?
    LIMIT 1
");
$stmt->bind_param("ss", $login, $role);
$stmt->execute();
$result = $stmt->get_result();

if ($user = $result->fetch_assoc()) {
    if (password_verify($password, $user['password'])) {
        if ((int)$user['is_verified'] !== 1) {
            echo json_encode([
                "success" => false,
                "need_verification" => true,
                "message" => "Akun belum diverifikasi",
                "data" => [
                    "email" => $user["email"],
                    "role" => $user["role"]
                ]
            ]);
            exit;
        }

        $token = bin2hex(random_bytes(32));

        $update = $conn->prepare("UPDATE akun SET api_token = ? WHERE id = ?");
        $update->bind_param("si", $token, $user['id']);
        $update->execute();

        echo json_encode([
            "success" => true,
            "message" => "Login berhasil",
            "data" => [
                "id" => $user["id"],
                "role" => $user["role"],
                "nim" => $user["nim"],
                "angkatan" => $user["angkatan"],
                "nama" => $user["nama"],
                "email" => $user["email"],
                "whatsapp" => $user["whatsapp"],
                "token" => $token
            ]
        ]);
    } else {
        echo json_encode([
            "success" => false,
            "message" => "Password salah!"
        ]);
    }
} else {
    echo json_encode([
        "success" => false,
        "message" => "Email tidak ditemukan!"
    ]);
}
?>