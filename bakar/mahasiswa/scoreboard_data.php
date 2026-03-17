<?php
header('Content-Type: application/json; charset=utf-8');
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
$resUser = $stmtUser->get_result();

if ($resUser->num_rows <= 0) {
    echo json_encode([
        "success" => false,
        "message" => "User tidak valid"
    ]);
    exit;
}

$user = $resUser->fetch_assoc();
$stmtUser->close();

if ((int)$user['is_verified'] !== 1) {
    echo json_encode([
        "success" => false,
        "message" => "Akun belum diverifikasi"
    ]);
    exit;
}

$nim = $user['nim'];
$nama = $user['nama'];
$angkatan = $user['angkatan'];

$filter_matkul = isset($_GET['matkul_id']) ? (int)$_GET['matkul_id'] : 0;

/* daftar matkul yang di-enroll mahasiswa */
$filterOptions = [];
$stmtFilter = $conn->prepare("
    SELECT mk.id, mk.nama_matkul, mk.kelas, mk.angkatan
    FROM mahasiswa_matkul mm
    JOIN mata_kuliah mk ON mm.matkul_id = mk.id
    WHERE mm.nim = ?
    ORDER BY mk.nama_matkul, mk.kelas
");
$stmtFilter->bind_param("s", $nim);
$stmtFilter->execute();
$filterQuery = $stmtFilter->get_result();

while ($row = $filterQuery->fetch_assoc()) {
    $filterOptions[] = $row;
}
$stmtFilter->close();

/* validasi filter */
if ($filter_matkul > 0) {
    $stmtValidasi = $conn->prepare("
        SELECT 1
        FROM mahasiswa_matkul
        WHERE nim = ? AND matkul_id = ?
        LIMIT 1
    ");
    $stmtValidasi->bind_param("si", $nim, $filter_matkul);
    $stmtValidasi->execute();
    $resValidasi = $stmtValidasi->get_result();

    if ($resValidasi->num_rows <= 0) {
        $filter_matkul = 0;
    }

    $stmtValidasi->close();
}

/* scoreboard versi baru */
$scoreRows = [];
if ($filter_matkul > 0) {
    $stmtBoard = $conn->prepare("
        SELECT 
            a.nim,
            a.nama,
            mk.nama_matkul,
            mk.kelas,
            COUNT(DISTINCT k.id) AS total_kuis_matkul,
            COUNT(DISTINCT hk.id) AS jumlah_kuis,
            COALESCE(SUM(hk.nilai), 0) AS total_nilai,
            ROUND(
                COALESCE(SUM(hk.nilai), 0) / NULLIF(COUNT(DISTINCT k.id), 0),
                2
            ) AS rata_nilai
        FROM mahasiswa_matkul mm
        JOIN akun a ON mm.nim = a.nim
        JOIN mata_kuliah mk ON mm.matkul_id = mk.id
        LEFT JOIN kuis k ON k.matkul_id = mk.id
        LEFT JOIN hasil_kuis hk ON hk.kuis_id = k.id AND hk.nim = mm.nim
        WHERE mm.matkul_id = ?
        GROUP BY a.nim, a.nama, mk.nama_matkul, mk.kelas
        HAVING COUNT(DISTINCT k.id) > 0
        ORDER BY rata_nilai DESC, jumlah_kuis DESC, a.nama ASC
    ");
    $stmtBoard->bind_param("i", $filter_matkul);
    $stmtBoard->execute();
    $scoreQuery = $stmtBoard->get_result();

    while ($row = $scoreQuery->fetch_assoc()) {
        $scoreRows[] = $row;
    }

    $stmtBoard->close();
}

$top1 = $scoreRows[0] ?? null;
$top2 = $scoreRows[1] ?? null;
$top3 = $scoreRows[2] ?? null;
$others = array_slice($scoreRows, 3);

echo json_encode([
    "success" => true,
    "data" => [
        "user" => [
            "nim" => $nim,
            "nama" => $nama,
            "angkatan" => $angkatan
        ],
        "filter_matkul" => $filter_matkul,
        "filter_options" => $filterOptions,
        "top1" => $top1,
        "top2" => $top2,
        "top3" => $top3,
        "others" => $others,
        "has_scoreboard" => count($scoreRows) > 0
    ]
]);
?>