<?php
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
    SELECT id, nim, nama, email, angkatan, is_verified, role
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
$angkatan = $user['angkatan'];

$available = [];
$enrolled = [];
$enrolledNames = [];

$stmtAvailable = $conn->prepare("
    SELECT id, nama_matkul, angkatan, kelas
    FROM mata_kuliah
    WHERE angkatan = ?
    ORDER BY nama_matkul ASC, kelas ASC
");
$stmtAvailable->bind_param("s", $angkatan);
$stmtAvailable->execute();
$resultAvailable = $stmtAvailable->get_result();

while ($row = $resultAvailable->fetch_assoc()) {
    $available[] = $row;
}
$stmtAvailable->close();

$stmtEnrolled = $conn->prepare("
    SELECT 
        mm.id AS enroll_id,
        mk.id AS matkul_id,
        mk.nama_matkul,
        mk.angkatan,
        mk.kelas
    FROM mahasiswa_matkul mm
    JOIN mata_kuliah mk ON mm.matkul_id = mk.id
    WHERE mm.nim = ?
    ORDER BY mk.nama_matkul ASC, mk.kelas ASC
");
$stmtEnrolled->bind_param("s", $nim);
$stmtEnrolled->execute();
$resultEnrolled = $stmtEnrolled->get_result();

while ($row = $resultEnrolled->fetch_assoc()) {
    $enrolled[] = $row;
    $enrolledNames[] = $row['nama_matkul'];
}
$stmtEnrolled->close();

echo json_encode([
    "success" => true,
    "data" => [
        "user" => [
            "nim" => $user["nim"],
            "nama" => $user["nama"],
            "email" => $user["email"],
            "angkatan" => $user["angkatan"],
        ],
        "available_classes" => $available,
        "enrolled_classes" => $enrolled,
        "enrolled_names" => $enrolledNames,
    ]
]);
?>