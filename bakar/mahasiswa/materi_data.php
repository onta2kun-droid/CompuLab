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
$filter_matkul = isset($_GET['matkul_id']) ? (int) $_GET['matkul_id'] : 0;

/* =========================
   CEK JUMLAH ENROLL
========================= */
$stmtCekEnroll = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM mahasiswa_matkul
    WHERE nim = ?
");
$stmtCekEnroll->bind_param("s", $nim);
$stmtCekEnroll->execute();
$resultCekEnroll = $stmtCekEnroll->get_result();
$dataCekEnroll = $resultCekEnroll->fetch_assoc();
$totalEnroll = (int) ($dataCekEnroll['total'] ?? 0);
$stmtCekEnroll->close();

/* =========================
   AMBIL DAFTAR MATKUL UNTUK FILTER
========================= */
$filterMatkul = [];
$stmtFilter = $conn->prepare("
    SELECT 
        mk.id,
        mk.nama_matkul,
        mk.kelas,
        mk.angkatan
    FROM mahasiswa_matkul mm
    JOIN mata_kuliah mk ON mm.matkul_id = mk.id
    WHERE mm.nim = ?
    ORDER BY mk.nama_matkul ASC, mk.kelas ASC
");
$stmtFilter->bind_param("s", $nim);
$stmtFilter->execute();
$filterMatkulQuery = $stmtFilter->get_result();

while ($row = $filterMatkulQuery->fetch_assoc()) {
    $filterMatkul[] = $row;
}
$stmtFilter->close();

/* =========================
   VALIDASI FILTER
========================= */
if ($filter_matkul > 0) {
    $stmtValidasiFilter = $conn->prepare("
        SELECT 1
        FROM mahasiswa_matkul
        WHERE nim = ? AND matkul_id = ?
        LIMIT 1
    ");
    $stmtValidasiFilter->bind_param("si", $nim, $filter_matkul);
    $stmtValidasiFilter->execute();
    $resultValidasiFilter = $stmtValidasiFilter->get_result();

    if ($resultValidasiFilter->num_rows <= 0) {
        $filter_matkul = 0;
    }

    $stmtValidasiFilter->close();
}

/* =========================
   AMBIL MATERI
========================= */
$materi = [];

if ($filter_matkul > 0) {
    $stmtMateri = $conn->prepare("
        SELECT 
            m.id,
            m.judul,
            m.deskripsi,
            m.file_materi,
            m.created_at,
            mk.id AS matkul_id,
            mk.nama_matkul,
            mk.kelas,
            mk.angkatan,
            d.nama AS nama_dosen
        FROM materi m
        JOIN mata_kuliah mk ON m.matkul_id = mk.id
        JOIN mahasiswa_matkul mm ON m.matkul_id = mm.matkul_id
        JOIN akun d ON m.dosen_id = d.id
        WHERE mm.nim = ? AND m.matkul_id = ?
        ORDER BY m.created_at DESC
    ");
    $stmtMateri->bind_param("si", $nim, $filter_matkul);
} else {
    $stmtMateri = $conn->prepare("
        SELECT 
            m.id,
            m.judul,
            m.deskripsi,
            m.file_materi,
            m.created_at,
            mk.id AS matkul_id,
            mk.nama_matkul,
            mk.kelas,
            mk.angkatan,
            d.nama AS nama_dosen
        FROM materi m
        JOIN mata_kuliah mk ON m.matkul_id = mk.id
        JOIN mahasiswa_matkul mm ON m.matkul_id = mm.matkul_id
        JOIN akun d ON m.dosen_id = d.id
        WHERE mm.nim = ?
        ORDER BY m.created_at DESC
    ");
    $stmtMateri->bind_param("s", $nim);
}

$stmtMateri->execute();
$materiQuery = $stmtMateri->get_result();

while ($row = $materiQuery->fetch_assoc()) {
    $materi[] = $row;
}
$stmtMateri->close();

echo json_encode([
    "success" => true,
    "data" => [
        "user" => [
            "nim" => $user["nim"],
            "nama" => $user["nama"],
            "email" => $user["email"],
            "angkatan" => $user["angkatan"],
        ],
        "total_enroll" => $totalEnroll,
        "filter_matkul" => $filter_matkul,
        "filter_options" => $filterMatkul,
        "materi" => $materi
    ]
]);
?>