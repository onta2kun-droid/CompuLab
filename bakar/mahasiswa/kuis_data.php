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
    SELECT id, nim, nama, email, angkatan, is_verified
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
   CEK ENROLL
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
$totalEnroll = (int)($dataCekEnroll['total'] ?? 0);
$stmtCekEnroll->close();

/* =========================
   FILTER MATKUL
========================= */
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
$filterMatkulQuery = $stmtFilter->get_result();

while ($row = $filterMatkulQuery->fetch_assoc()) {
    $filterOptions[] = $row;
}
$stmtFilter->close();

/* =========================
   VALIDASI FILTER
========================= */
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

/* =========================
   AMBIL KUIS
========================= */
$kuisList = [];
$sekarang = date('Y-m-d H:i:s');

if ($filter_matkul > 0) {
    $stmtKuis = $conn->prepare("
        SELECT 
            k.id,
            k.judul,
            k.deskripsi,
            k.waktu_mulai,
            k.waktu_selesai,
            k.durasi,
            k.is_active,
            k.created_at,
            mk.nama_matkul,
            mk.kelas,
            a.nama AS nama_dosen,
            hk.id AS hasil_id,
            hk.nilai,
            hk.status AS status_pengerjaan
        FROM kuis k
        JOIN mata_kuliah mk ON k.matkul_id = mk.id
        JOIN mahasiswa_matkul mm ON k.matkul_id = mm.matkul_id
        JOIN akun a ON k.dosen_id = a.id
        LEFT JOIN hasil_kuis hk ON hk.kuis_id = k.id AND hk.nim = ?
        WHERE mm.nim = ? AND k.matkul_id = ?
        ORDER BY k.created_at DESC
    ");
    $stmtKuis->bind_param("ssi", $nim, $nim, $filter_matkul);
} else {
    $stmtKuis = $conn->prepare("
        SELECT 
            k.id,
            k.judul,
            k.deskripsi,
            k.waktu_mulai,
            k.waktu_selesai,
            k.durasi,
            k.is_active,
            k.created_at,
            mk.nama_matkul,
            mk.kelas,
            a.nama AS nama_dosen,
            hk.id AS hasil_id,
            hk.nilai,
            hk.status AS status_pengerjaan
        FROM kuis k
        JOIN mata_kuliah mk ON k.matkul_id = mk.id
        JOIN mahasiswa_matkul mm ON k.matkul_id = mm.matkul_id
        JOIN akun a ON k.dosen_id = a.id
        LEFT JOIN hasil_kuis hk ON hk.kuis_id = k.id AND hk.nim = ?
        WHERE mm.nim = ?
        ORDER BY k.created_at DESC
    ");
    $stmtKuis->bind_param("ss", $nim, $nim);
}

$stmtKuis->execute();
$kuisQuery = $stmtKuis->get_result();

while ($row = $kuisQuery->fetch_assoc()) {
    $sudahDikerjakan = !is_null($row['nilai']);
    $isActive = (int)($row['is_active'] ?? 0) === 1;

    $statusLabel = '';
    $statusType = '';
    $canDo = false;
    $canDetail = false;
    $actionLabel = '';

    if (!$isActive) {
        $statusLabel = 'Belum Diaktifkan';
        $statusType = 'inactive';

        if ($sudahDikerjakan) {
            $canDetail = true;
            $actionLabel = 'Detail';
        } else {
            $actionLabel = 'Menunggu Aktivasi';
        }
    } elseif (!empty($row['waktu_mulai']) && $sekarang < $row['waktu_mulai']) {
        $statusLabel = 'Belum Dimulai';
        $statusType = 'wait';

        if ($sudahDikerjakan) {
            $canDetail = true;
            $actionLabel = 'Detail';
        } else {
            $actionLabel = 'Menunggu';
        }
    } elseif (!empty($row['waktu_selesai']) && $sekarang > $row['waktu_selesai']) {
        $statusLabel = 'Selesai';
        $statusType = 'close';

        if ($sudahDikerjakan) {
            $canDetail = true;
            $actionLabel = 'Detail';
        } else {
            $actionLabel = 'Ditutup';
        }
    } else {
        $statusLabel = 'Tersedia';
        $statusType = 'open';

        if ($sudahDikerjakan) {
            $canDetail = true;
            $actionLabel = 'Sudah Dikerjakan';
        } else {
            $canDo = true;
            $actionLabel = 'Kerjakan';
        }
    }

    $row['status_label'] = $statusLabel;
    $row['status_type'] = $statusType;
    $row['can_do'] = $canDo;
    $row['can_detail'] = $canDetail;
    $row['action_label'] = $actionLabel;

    $kuisList[] = $row;
}
$stmtKuis->close();

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
        "filter_options" => $filterOptions,
        "kuis" => $kuisList
    ]
]);
?>