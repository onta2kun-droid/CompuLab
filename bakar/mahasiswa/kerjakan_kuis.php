<?php
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
    SELECT nim, nama, email, is_verified
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

$nim  = $user['nim'];
$nama = $user['nama'];

$kuis_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($kuis_id <= 0) {
    echo json_encode([
        "success" => false,
        "message" => "ID kuis tidak valid"
    ]);
    exit;
}

/* =========================
   CEK KUIS VALID & SESUAI ENROLL
========================= */
$stmtKuis = $conn->prepare("
    SELECT 
        k.id,
        k.judul,
        k.deskripsi,
        k.durasi,
        k.waktu_mulai,
        k.waktu_selesai,
        mk.nama_matkul,
        mk.kelas
    FROM kuis k
    JOIN mata_kuliah mk ON k.matkul_id = mk.id
    JOIN mahasiswa_matkul mm ON k.matkul_id = mm.matkul_id
    WHERE k.id = ? AND mm.nim = ?
    LIMIT 1
");
$stmtKuis->bind_param("is", $kuis_id, $nim);
$stmtKuis->execute();
$resultKuis = $stmtKuis->get_result();

if ($resultKuis->num_rows <= 0) {
    echo json_encode([
        "success" => false,
        "message" => "Kuis tidak ditemukan"
    ]);
    exit;
}

$kuis = $resultKuis->fetch_assoc();
$stmtKuis->close();

/* =========================
   CEK JADWAL GLOBAL KUIS
========================= */
$sekarang = date('Y-m-d H:i:s');

if (!empty($kuis['waktu_mulai']) && $sekarang < $kuis['waktu_mulai']) {
    echo json_encode([
        "success" => false,
        "message" => "Kuis belum dimulai."
    ]);
    exit;
}

if (!empty($kuis['waktu_selesai']) && $sekarang > $kuis['waktu_selesai']) {
    echo json_encode([
        "success" => false,
        "message" => "Kuis sudah ditutup."
    ]);
    exit;
}

/* =========================
   AMBIL SOAL
========================= */
$stmtSoal = $conn->prepare("
    SELECT id, pertanyaan, opsi_a, opsi_b, opsi_c, opsi_d, jawaban_benar
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

if (count($soalList) <= 0) {
    echo json_encode([
        "success" => false,
        "message" => "Soal kuis belum tersedia."
    ]);
    exit;
}

/* =========================
   CEK / BUAT SESI PENGERJAAN
========================= */
$stmtHasil = $conn->prepare("
    SELECT id, nilai, waktu_mulai, deadline, status
    FROM hasil_kuis
    WHERE kuis_id = ? AND nim = ?
    LIMIT 1
");
$stmtHasil->bind_param("is", $kuis_id, $nim);
$stmtHasil->execute();
$resultHasil = $stmtHasil->get_result();

$hasil_id = 0;
$deadline = null;

if ($resultHasil->num_rows > 0) {
    $hasil = $resultHasil->fetch_assoc();

    if ($hasil['status'] === 'selesai') {
        echo json_encode([
            "success" => false,
            "already_finished" => true,
            "message" => "Kuis sudah dikerjakan."
        ]);
        exit;
    }

    $hasil_id = (int)$hasil['id'];
    $deadline = $hasil['deadline'];
} else {
    $waktuMulaiKerja = date('Y-m-d H:i:s');
    $deadlineBaru = date('Y-m-d H:i:s', strtotime("+{$kuis['durasi']} minutes"));

    $stmtInsertMulai = $conn->prepare("
        INSERT INTO hasil_kuis (kuis_id, nim, nilai, waktu_mulai, deadline, status, tanggal_submit)
        VALUES (?, ?, 0, ?, ?, 'sedang_mengerjakan', NOW())
    ");
    $stmtInsertMulai->bind_param("isss", $kuis_id, $nim, $waktuMulaiKerja, $deadlineBaru);

    if (!$stmtInsertMulai->execute()) {
        echo json_encode([
            "success" => false,
            "message" => "Gagal memulai sesi kuis."
        ]);
        exit;
    }

    $hasil_id = $conn->insert_id;
    $deadline = $deadlineBaru;
    $stmtInsertMulai->close();
}
$stmtHasil->close();

/* =========================
   AMBIL JAWABAN TERSIMPAN
========================= */
$jawabanTersimpan = [];

$stmtSaved = $conn->prepare("
    SELECT soal_id, jawaban
    FROM jawaban_mahasiswa
    WHERE hasil_id = ?
");
$stmtSaved->bind_param("i", $hasil_id);
$stmtSaved->execute();
$resultSaved = $stmtSaved->get_result();

while ($rowSaved = $resultSaved->fetch_assoc()) {
    $jawabanTersimpan[$rowSaved['soal_id']] = $rowSaved['jawaban'];
}
$stmtSaved->close();

/* =========================
   CEK WAKTU SUDAH HABIS
========================= */
$nowTs = time();
$deadlineTs = strtotime($deadline);

if ($deadlineTs <= $nowTs) {
    echo json_encode([
        "success" => false,
        "time_up" => true,
        "message" => "Waktu kuis sudah habis."
    ]);
    exit;
}

$sisaDetik = $deadlineTs - time();
if ($sisaDetik < 0) {
    $sisaDetik = 0;
}

/* =========================
   FORMAT SOAL UNTUK FLUTTER
========================= */
$soalFormatted = [];
foreach ($soalList as $soal) {
    $soalId = (int)$soal['id'];
    $soalFormatted[] = [
        "id" => $soalId,
        "pertanyaan" => $soal['pertanyaan'],
        "opsi" => [
            ["kode" => "A", "teks" => $soal['opsi_a']],
            ["kode" => "B", "teks" => $soal['opsi_b']],
            ["kode" => "C", "teks" => $soal['opsi_c']],
            ["kode" => "D", "teks" => $soal['opsi_d']]
        ],
        "jawaban_tersimpan" => $jawabanTersimpan[$soalId] ?? ''
    ];
}

echo json_encode([
    "success" => true,
    "data" => [
        "user" => [
            "nim" => $nim,
            "nama" => $nama
        ],
        "kuis" => [
            "id" => $kuis['id'],
            "judul" => $kuis['judul'],
            "deskripsi" => $kuis['deskripsi'],
            "durasi" => $kuis['durasi'],
            "waktu_mulai" => $kuis['waktu_mulai'],
            "waktu_selesai" => $kuis['waktu_selesai'],
            "deadline" => $deadline,
            "nama_matkul" => $kuis['nama_matkul'],
            "kelas" => $kuis['kelas']
        ],
        "hasil_id" => $hasil_id,
        "deadline" => $deadline,
        "sisa_detik" => $sisaDetik,
        "soal" => $soalFormatted
    ]
]);
?>