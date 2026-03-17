<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../../sambung/compulab.php';

date_default_timezone_set('Asia/Makassar');

function finalisasiKuisDariDatabase($conn, $hasil_id, $kuis_id, $nim, $soalList)
{
    $stmtJawaban = $conn->prepare("
        SELECT soal_id, jawaban
        FROM jawaban_mahasiswa
        WHERE hasil_id = ?
    ");
    $stmtJawaban->bind_param("i", $hasil_id);
    $stmtJawaban->execute();
    $resultJawaban = $stmtJawaban->get_result();

    $jawabanMap = [];
    while ($row = $resultJawaban->fetch_assoc()) {
        $jawabanMap[$row['soal_id']] = $row['jawaban'];
    }
    $stmtJawaban->close();

    $jumlah_soal = count($soalList);
    $jumlah_benar = 0;

    foreach ($soalList as $soal) {
        $soal_id = $soal['id'];
        $jawaban = $jawabanMap[$soal_id] ?? '';

        if ($jawaban === $soal['jawaban_benar']) {
            $jumlah_benar++;
        }
    }

    $nilai = $jumlah_soal > 0 ? round(($jumlah_benar / $jumlah_soal) * 100) : 0;

    $stmtUpdate = $conn->prepare("
        UPDATE hasil_kuis
        SET nilai = ?, status = 'selesai', tanggal_submit = NOW()
        WHERE id = ? AND kuis_id = ? AND nim = ?
    ");
    $stmtUpdate->bind_param("iiis", $nilai, $hasil_id, $kuis_id, $nim);
    $ok = $stmtUpdate->execute();

    if (!$ok) {
        $error = $stmtUpdate->error;
        $stmtUpdate->close();
        return [false, $nilai, $error];
    }

    $stmtUpdate->close();
    return [true, $nilai, ""];
}

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

if ($kuis_id <= 0) {
    echo json_encode([
        "success" => false,
        "message" => "ID kuis tidak valid"
    ]);
    exit;
}

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
        "message" => "Kuis sudah disubmit"
    ]);
    exit;
}

$hasil_id = (int)$hasil['id'];

$stmtSoal = $conn->prepare("
    SELECT id, jawaban_benar
    FROM soal_kuis
    WHERE kuis_id = ?
    ORDER BY id ASC
");
$stmtSoal->bind_param("i", $kuis_id);
$stmtSoal->execute();
$resultSoal = $stmtSoal->get_result();

$soalList = [];
while ($row = $resultSoal->fetch_assoc()) {
    $soalList[] = $row;
}
$stmtSoal->close();

list($ok, $nilai, $errorDb) = finalisasiKuisDariDatabase($conn, $hasil_id, $kuis_id, $nim, $soalList);

echo json_encode([
    "success" => $ok ? true : false,
    "message" => $ok ? "Kuis berhasil dikumpulkan" : "Gagal menyimpan hasil kuis: " . $errorDb,
    "nilai" => $nilai
]);
?>