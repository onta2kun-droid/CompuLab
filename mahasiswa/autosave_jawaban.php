<?php
session_start();
include "../idk/forsafety.php";

date_default_timezone_set('Asia/Makassar');
header('Content-Type: application/json');

if (!isset($_SESSION['email'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$email = $_SESSION['email'];

/* =========================
   AMBIL DATA MAHASISWA
========================= */
$stmtUser = $conn->prepare("
    SELECT nim, email, is_verified
    FROM akun
    WHERE email = ? AND role = 'mahasiswa'
    LIMIT 1
");
$stmtUser->bind_param("s", $email);
$stmtUser->execute();
$resultUser = $stmtUser->get_result();

if ($resultUser->num_rows <= 0) {
    echo json_encode(['success' => false, 'message' => 'User tidak ditemukan']);
    exit;
}

$user = $resultUser->fetch_assoc();
$stmtUser->close();

if ((int)$user['is_verified'] !== 1) {
    echo json_encode(['success' => false, 'message' => 'User belum verifikasi']);
    exit;
}

$nim = $user['nim'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method tidak valid']);
    exit;
}

$kuis_id = (int) ($_POST['kuis_id'] ?? 0);
$soal_id = (int) ($_POST['soal_id'] ?? 0);
$jawaban = trim($_POST['jawaban'] ?? '');

if ($kuis_id <= 0 || $soal_id <= 0 || !in_array($jawaban, ['A', 'B', 'C', 'D'])) {
    echo json_encode(['success' => false, 'message' => 'Data tidak valid']);
    exit;
}

/* =========================
   CEK SESI KUIS AKTIF
========================= */
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
    echo json_encode(['success' => false, 'message' => 'Sesi kuis tidak ditemukan']);
    exit;
}

$hasil = $resultHasil->fetch_assoc();
$stmtHasil->close();

$hasil_id = (int)$hasil['id'];
$deadline = $hasil['deadline'];
$status   = $hasil['status'];

if ($status !== 'sedang_mengerjakan') {
    echo json_encode(['success' => false, 'message' => 'Kuis sudah selesai']);
    exit;
}

if (strtotime($deadline) <= time()) {
    echo json_encode(['success' => false, 'message' => 'Waktu kuis habis']);
    exit;
}

/* =========================
   CEK SOAL VALID
========================= */
$stmtSoal = $conn->prepare("
    SELECT sq.id
    FROM soal_kuis sq
    JOIN kuis k ON sq.kuis_id = k.id
    JOIN mahasiswa_matkul mm ON k.matkul_id = mm.matkul_id
    WHERE sq.id = ? AND sq.kuis_id = ? AND mm.nim = ?
    LIMIT 1
");
$stmtSoal->bind_param("iis", $soal_id, $kuis_id, $nim);
$stmtSoal->execute();
$resultSoal = $stmtSoal->get_result();

if ($resultSoal->num_rows <= 0) {
    echo json_encode(['success' => false, 'message' => 'Soal tidak valid']);
    exit;
}
$stmtSoal->close();

/* =========================
   UPSERT JAWABAN
========================= */
$stmtCekJawaban = $conn->prepare("
    SELECT id
    FROM jawaban_mahasiswa
    WHERE hasil_id = ? AND soal_id = ?
    LIMIT 1
");
$stmtCekJawaban->bind_param("ii", $hasil_id, $soal_id);
$stmtCekJawaban->execute();
$resultCekJawaban = $stmtCekJawaban->get_result();

if ($resultCekJawaban->num_rows > 0) {
    $stmtUpdate = $conn->prepare("
        UPDATE jawaban_mahasiswa
        SET jawaban = ?
        WHERE hasil_id = ? AND soal_id = ?
    ");
    $stmtUpdate->bind_param("sii", $jawaban, $hasil_id, $soal_id);

    if ($stmtUpdate->execute()) {
        echo json_encode(['success' => true, 'message' => 'Jawaban diperbarui']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal update jawaban']);
    }

    $stmtUpdate->close();
} else {
    $stmtInsert = $conn->prepare("
        INSERT INTO jawaban_mahasiswa (hasil_id, kuis_id, soal_id, nim, jawaban)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmtInsert->bind_param("iiiss", $hasil_id, $kuis_id, $soal_id, $nim, $jawaban);

    if ($stmtInsert->execute()) {
        echo json_encode(['success' => true, 'message' => 'Jawaban tersimpan']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal simpan jawaban']);
    }

    $stmtInsert->close();
}