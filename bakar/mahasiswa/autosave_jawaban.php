<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../../sambung/compulab.php';

date_default_timezone_set('Asia/Makassar');

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

$kuis_id = (int)($_POST['kuis_id'] ?? 0);
$soal_id = (int)($_POST['soal_id'] ?? 0);
$jawaban = trim($_POST['jawaban'] ?? '');

if ($kuis_id <= 0 || $soal_id <= 0 || !in_array($jawaban, ['A', 'B', 'C', 'D'])) {
    echo json_encode([
        "success" => false,
        "message" => "Data autosave tidak valid"
    ]);
    exit;
}

/* cek sesi kuis */
$stmtHasil = $conn->prepare("
    SELECT id, deadline, status
    FROM hasil_kuis
    WHERE kuis_id = ? AND nim = ?
    LIMIT 1
");
$stmtHasil->bind_param("is", $kuis_id, $nim);
$stmtHasil->execute();
$resultHasil = $stmtHasil->get_result();

if ($resultHasil->num_rows <= 0) {
    echo json_encode([
        "success" => false,
        "message" => "Sesi kuis tidak ditemukan"
    ]);
    exit;
}

$hasil = $resultHasil->fetch_assoc();
$stmtHasil->close();

if ($hasil['status'] === 'selesai') {
    echo json_encode([
        "success" => false,
        "message" => "Kuis sudah selesai"
    ]);
    exit;
}

if (strtotime($hasil['deadline']) <= time()) {
    echo json_encode([
        "success" => false,
        "message" => "Waktu kuis sudah habis"
    ]);
    exit;
}

$hasil_id = (int)$hasil['id'];

/* cek jawaban sudah ada atau belum */
$stmtCheck = $conn->prepare("
    SELECT id
    FROM jawaban_mahasiswa
    WHERE hasil_id = ? AND soal_id = ?
    LIMIT 1
");
$stmtCheck->bind_param("ii", $hasil_id, $soal_id);
$stmtCheck->execute();
$resultCheck = $stmtCheck->get_result();

if ($resultCheck->num_rows > 0) {
    $row = $resultCheck->fetch_assoc();
    $jawaban_id = (int)$row['id'];

    $stmtUpdate = $conn->prepare("
        UPDATE jawaban_mahasiswa
        SET jawaban = ?, updated_at = CURRENT_TIMESTAMP
        WHERE id = ?
    ");
    $stmtUpdate->bind_param("si", $jawaban, $jawaban_id);
    $ok = $stmtUpdate->execute();

    if (!$ok) {
        echo json_encode([
            "success" => false,
            "message" => "Gagal update jawaban: " . $stmtUpdate->error
        ]);
        $stmtUpdate->close();
        exit;
    }

    $stmtUpdate->close();
} else {
    $stmtInsert = $conn->prepare("
        INSERT INTO jawaban_mahasiswa (hasil_id, kuis_id, soal_id, nim, jawaban)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmtInsert->bind_param("iiiss", $hasil_id, $kuis_id, $soal_id, $nim, $jawaban);
    $ok = $stmtInsert->execute();

    if (!$ok) {
        echo json_encode([
            "success" => false,
            "message" => "Gagal insert jawaban: " . $stmtInsert->error
        ]);
        $stmtInsert->close();
        exit;
    }

    $stmtInsert->close();
}

$stmtCheck->close();

echo json_encode([
    "success" => true,
    "message" => "Jawaban tersimpan"
]);
?>