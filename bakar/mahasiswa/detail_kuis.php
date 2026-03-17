<?php
require_once '../config.php';

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
$kuis_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($kuis_id <= 0) {
    echo json_encode([
        "success" => false,
        "message" => "ID kuis tidak valid"
    ]);
    exit;
}

/* =========================
   CEK KUIS VALID + SUDAH DIKERJAKAN
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
        mk.kelas,
        hk.id AS hasil_id,
        hk.nilai,
        hk.tanggal_submit,
        hk.status
    FROM kuis k
    JOIN mata_kuliah mk ON k.matkul_id = mk.id
    JOIN mahasiswa_matkul mm ON k.matkul_id = mm.matkul_id
    JOIN hasil_kuis hk ON hk.kuis_id = k.id AND hk.nim = mm.nim
    WHERE k.id = ? AND mm.nim = ? AND hk.status = 'selesai'
    LIMIT 1
");
$stmtKuis->bind_param("is", $kuis_id, $nim);
$stmtKuis->execute();
$resultKuis = $stmtKuis->get_result();

if ($resultKuis->num_rows <= 0) {
    echo json_encode([
        "success" => false,
        "message" => "Detail kuis tidak ditemukan"
    ]);
    exit;
}

$kuis = $resultKuis->fetch_assoc();
$stmtKuis->close();

$hasil_id = (int) $kuis['hasil_id'];

/* =========================
   AMBIL SOAL + JAWABAN USER
========================= */
$stmtDetail = $conn->prepare("
    SELECT
        sq.id,
        sq.pertanyaan,
        sq.opsi_a,
        sq.opsi_b,
        sq.opsi_c,
        sq.opsi_d,
        sq.jawaban_benar,
        jm.jawaban AS jawaban_user
    FROM soal_kuis sq
    LEFT JOIN jawaban_mahasiswa jm
        ON jm.soal_id = sq.id AND jm.hasil_id = ?
    WHERE sq.kuis_id = ?
    ORDER BY sq.id ASC
");
$stmtDetail->bind_param("ii", $hasil_id, $kuis_id);
$stmtDetail->execute();
$detailQuery = $stmtDetail->get_result();

$detailSoal = [];
$jumlahBenar = 0;
$jumlahSalah = 0;
$jumlahKosong = 0;

while ($row = $detailQuery->fetch_assoc()) {
    $jawabanUser = $row['jawaban_user'] ?? '';
    $jawabanBenar = $row['jawaban_benar'];

    if ($jawabanUser === '') {
        $jumlahKosong++;
        $statusText = 'Tidak Dijawab';
        $statusType = 'kosong';
    } elseif ($jawabanUser === $jawabanBenar) {
        $jumlahBenar++;
        $statusText = 'Jawaban Benar';
        $statusType = 'benar';
    } else {
        $jumlahSalah++;
        $statusText = 'Jawaban Salah';
        $statusType = 'salah';
    }

    $row['status_text'] = $statusText;
    $row['status_type'] = $statusType;
    $row['opsi'] = [
        ["kode" => "A", "teks" => $row['opsi_a']],
        ["kode" => "B", "teks" => $row['opsi_b']],
        ["kode" => "C", "teks" => $row['opsi_c']],
        ["kode" => "D", "teks" => $row['opsi_d']]
    ];

    $detailSoal[] = $row;
}
$stmtDetail->close();

$totalSoal = count($detailSoal);

echo json_encode([
    "success" => true,
    "data" => [
        "user" => [
            "nim" => $user["nim"],
            "nama" => $user["nama"],
            "email" => $user["email"],
            "angkatan" => $user["angkatan"]
        ],
        "kuis" => $kuis,
        "summary" => [
            "total_soal" => $totalSoal,
            "jumlah_benar" => $jumlahBenar,
            "jumlah_salah" => $jumlahSalah,
            "jumlah_kosong" => $jumlahKosong
        ],
        "soal" => $detailSoal
    ]
]);
?>