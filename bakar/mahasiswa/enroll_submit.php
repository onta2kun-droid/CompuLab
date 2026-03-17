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
    SELECT nim, nama, email, angkatan, is_verified
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
$matkul_id = (int) ($_POST['matkul_id'] ?? 0);

if ($matkul_id <= 0) {
    echo json_encode([
        "success" => false,
        "message" => "Pilih kelas terlebih dahulu."
    ]);
    exit;
}

$stmtMatkul = $conn->prepare("
    SELECT id, nama_matkul, kelas, angkatan
    FROM mata_kuliah
    WHERE id = ? AND angkatan = ?
    LIMIT 1
");
$stmtMatkul->bind_param("is", $matkul_id, $angkatan);
$stmtMatkul->execute();
$resultMatkul = $stmtMatkul->get_result();

if ($resultMatkul->num_rows <= 0) {
    echo json_encode([
        "success" => false,
        "message" => "Kelas tidak valid untuk angkatan Anda."
    ]);
    exit;
}

$matkulDipilih = $resultMatkul->fetch_assoc();
$nama_matkul_dipilih = $matkulDipilih['nama_matkul'];
$stmtMatkul->close();

$stmtCekNama = $conn->prepare("
    SELECT mm.id, mk.nama_matkul, mk.kelas
    FROM mahasiswa_matkul mm
    JOIN mata_kuliah mk ON mm.matkul_id = mk.id
    WHERE mm.nim = ? AND mk.nama_matkul = ?
    LIMIT 1
");
$stmtCekNama->bind_param("ss", $nim, $nama_matkul_dipilih);
$stmtCekNama->execute();
$resultCekNama = $stmtCekNama->get_result();

if ($resultCekNama->num_rows > 0) {
    $dataSudah = $resultCekNama->fetch_assoc();
    echo json_encode([
        "success" => false,
        "message" => "Anda sudah enroll mata kuliah " . $dataSudah['nama_matkul'] . " kelas " . $dataSudah['kelas'] . ". Hanya boleh pilih 1 kelas."
    ]);
    exit;
}
$stmtCekNama->close();

$stmtCekSama = $conn->prepare("
    SELECT id
    FROM mahasiswa_matkul
    WHERE nim = ? AND matkul_id = ?
    LIMIT 1
");
$stmtCekSama->bind_param("si", $nim, $matkul_id);
$stmtCekSama->execute();
$resultCekSama = $stmtCekSama->get_result();

if ($resultCekSama->num_rows > 0) {
    echo json_encode([
        "success" => false,
        "message" => "Kelas ini sudah Anda enroll."
    ]);
    exit;
}
$stmtCekSama->close();

$stmtInsert = $conn->prepare("
    INSERT INTO mahasiswa_matkul (nim, matkul_id)
    VALUES (?, ?)
");
$stmtInsert->bind_param("si", $nim, $matkul_id);

if ($stmtInsert->execute()) {
    echo json_encode([
        "success" => true,
        "message" => "Berhasil enroll kelas " . $matkulDipilih['nama_matkul'] . " - " . $matkulDipilih['kelas'] . "."
    ]);
} else {
    echo json_encode([
        "success" => false,
        "message" => "Gagal menyimpan enroll."
    ]);
}
$stmtInsert->close();
?>