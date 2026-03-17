<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
include "../idk/forsafety.php";

/* =========================
   CEK LOGIN
========================= */
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'dosen') {
    header("Location: ../login/dosen.php");
    exit();
}

$dosen_id   = $_SESSION['user_id'];
$nama_dosen = $_SESSION['user_nama'];

$kuis_id = (int) ($_GET['kuis_id'] ?? 0);

if ($kuis_id <= 0) {
    header("Location: kelola_kuis.php");
    exit();
}

/* =========================
   AMBIL DETAIL KUIS
   pastikan milik dosen login
========================= */
$stmtKuis = $conn->prepare("
    SELECT 
        k.id,
        k.judul,
        k.deskripsi,
        k.waktu_mulai,
        k.waktu_selesai,
        k.durasi,
        k.created_at,
        k.matkul_id,
        mk.nama_matkul,
        mk.angkatan,
        mk.kelas
    FROM kuis k
    JOIN mata_kuliah mk ON k.matkul_id = mk.id
    WHERE k.id = ? AND k.dosen_id = ?
    LIMIT 1
");

if (!$stmtKuis) {
    die("Query detail kuis gagal disiapkan.");
}

$stmtKuis->bind_param("ii", $kuis_id, $dosen_id);
$stmtKuis->execute();
$resultKuis = $stmtKuis->get_result();

if ($resultKuis->num_rows <= 0) {
    header("Location: kelola_kuis.php");
    exit();
}

$kuis = $resultKuis->fetch_assoc();
$stmtKuis->close();

$matkul_id = $kuis['matkul_id'];

/* =========================
   TOTAL SOAL
========================= */
$totalSoal = 0;
$stmtSoal = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM soal_kuis
    WHERE kuis_id = ?
");
if ($stmtSoal) {
    $stmtSoal->bind_param("i", $kuis_id);
    $stmtSoal->execute();
    $resultSoal = $stmtSoal->get_result();
    $dataSoal = $resultSoal->fetch_assoc();
    $totalSoal = (int)($dataSoal['total'] ?? 0);
    $stmtSoal->close();
}

/* =========================
   AMBIL SEMUA MAHASISWA
   YANG ENROLL DI MATKUL INI
   + NILAI JIKA SUDAH MENGERJAKAN
========================= */
$stmtNilai = $conn->prepare("
    SELECT 
        a.nim,
        a.nama,
        a.email,
        hk.nilai,
        hk.tanggal_submit
    FROM mahasiswa_matkul mm
    JOIN akun a ON mm.nim = a.nim
    LEFT JOIN hasil_kuis hk 
        ON hk.kuis_id = ? AND hk.nim = mm.nim
    WHERE mm.matkul_id = ?
    ORDER BY 
        CASE WHEN hk.nilai IS NULL THEN 1 ELSE 0 END ASC,
        hk.nilai DESC,
        a.nama ASC
");

if (!$stmtNilai) {
    die("Query nilai mahasiswa gagal disiapkan.");
}

$stmtNilai->bind_param("ii", $kuis_id, $matkul_id);
$stmtNilai->execute();
$nilaiQuery = $stmtNilai->get_result();

/* =========================
   RINGKASAN
========================= */
$totalMahasiswa = 0;
$sudahMengerjakan = 0;
$belumMengerjakan = 0;
$rataNilai = 0;

$stmtRingkas = $conn->prepare("
    SELECT 
        COUNT(mm.nim) AS total_mahasiswa,
        SUM(CASE WHEN hk.nilai IS NOT NULL THEN 1 ELSE 0 END) AS sudah_mengerjakan,
        SUM(CASE WHEN hk.nilai IS NULL THEN 1 ELSE 0 END) AS belum_mengerjakan,
        AVG(hk.nilai) AS rata_nilai
    FROM mahasiswa_matkul mm
    LEFT JOIN hasil_kuis hk 
        ON hk.kuis_id = ? AND hk.nim = mm.nim
    WHERE mm.matkul_id = ?
");

if ($stmtRingkas) {
    $stmtRingkas->bind_param("ii", $kuis_id, $matkul_id);
    $stmtRingkas->execute();
    $resultRingkas = $stmtRingkas->get_result();
    $ringkas = $resultRingkas->fetch_assoc();

    $totalMahasiswa   = (int)($ringkas['total_mahasiswa'] ?? 0);
    $sudahMengerjakan = (int)($ringkas['sudah_mengerjakan'] ?? 0);
    $belumMengerjakan = (int)($ringkas['belum_mengerjakan'] ?? 0);
    $rataNilai        = $ringkas['rata_nilai'] !== null ? round($ringkas['rata_nilai'], 2) : 0;

    $stmtRingkas->close();
}

date_default_timezone_set('Asia/Makassar');
$sekarang = date('Y-m-d H:i:s');

$statusKuis = '';
if (!empty($kuis['waktu_mulai']) && $sekarang < $kuis['waktu_mulai']) {
    $statusKuis = 'Belum Dimulai';
} elseif (!empty($kuis['waktu_selesai']) && $sekarang > $kuis['waktu_selesai']) {
    $statusKuis = 'Selesai';
} else {
    $statusKuis = 'Berlangsung';
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Nilai Kuis | COMPULAB</title>
    <link rel="stylesheet" href="../css/dashboard-dosen.css">
    <style>
        .btn-back {
            display: inline-block;
            margin-bottom: 16px;
            padding: 10px 14px;
            background: #3498db;
            color: white;
            text-decoration: none;
            border-radius: 8px;
        }

        .info-box {
            background: #f7f7f7;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 20px;
            line-height: 1.8;
        }

        .stats-mini {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 14px;
            margin-bottom: 20px;
        }

        .card-mini {
            background: #ffffff;
            border: 1px solid #e5e5e5;
            border-radius: 10px;
            padding: 14px;
        }

        .card-mini h4 {
            margin: 0 0 8px;
            font-size: 14px;
            color: #666;
        }

        .card-mini p {
            margin: 0;
            font-size: 20px;
            font-weight: bold;
        }

        .table-nilai {
            width: 100%;
            border-collapse: collapse;
            margin-top: 12px;
        }

        .table-nilai th,
        .table-nilai td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: left;
            vertical-align: middle;
        }

        .table-nilai th {
            background: #f1f1f1;
        }

        .status-submit {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: bold;
        }

        .status-sudah {
            background: #d4edda;
            color: #155724;
        }

        .status-belum {
            background: #f8d7da;
            color: #721c24;
        }

        .nilai-bagus {
            font-weight: bold;
            color: #1e7e34;
        }

        .nilai-kosong {
            color: #999;
            font-style: italic;
        }

        .small-text {
            color: #666;
            font-size: 13px;
            line-height: 1.5;
        }
    </style>
</head>
<body>

<div class="sidebar">
    <div class="logo">COMPULAB</div>

    <nav>
        <a href="dashboard.php">📊 Dashboard</a>
        <a href="upload_materi.php">📤 Upload Materi</a>
        <a href="kelola_materi.php">📚 Kelola Materi</a>
        <a href="tambah_kuis.php">📝 Tambah Kuis</a>
        <a class="active" href="kelola_kuis.php">📋 Kelola Kuis</a>
        <a href="scoreboard.php">📈 Scoreboard</a>
        <a href="profil.php">👨‍🏫 Profil</a>
        <a href="logout.php">🚪 Logout</a>
    </nav>
</div>

<div class="main">
    <div class="topbar">
        <h1>Halo, <?php echo htmlspecialchars($nama_dosen); ?> 👨‍🏫</h1>
    </div>

    <div class="table-card">
        <a href="kelola_kuis.php" class="btn-back">← Kembali ke Kelola Kuis</a>

        <h3>Nilai Kuis Mahasiswa</h3>

        <div class="info-box">
            <strong>Judul Kuis:</strong> <?php echo htmlspecialchars($kuis['judul']); ?><br>
            <strong>Mata Kuliah:</strong> <?php echo htmlspecialchars($kuis['nama_matkul'] . ' | ' . $kuis['angkatan'] . ' ' . $kuis['kelas']); ?><br>
            <strong>Durasi:</strong> <?php echo htmlspecialchars($kuis['durasi']); ?> menit<br>
            <strong>Total Soal:</strong> <?php echo $totalSoal; ?> soal<br>
            <strong>Status:</strong> <?php echo htmlspecialchars($statusKuis); ?><br>
            <strong>Waktu:</strong>
            <span class="small-text">
                <?php echo htmlspecialchars($kuis['waktu_mulai']); ?> s/d
                <?php echo htmlspecialchars($kuis['waktu_selesai']); ?>
            </span>
            <br>
            <strong>Deskripsi:</strong><br>
            <span class="small-text">
                <?php echo !empty($kuis['deskripsi']) ? nl2br(htmlspecialchars($kuis['deskripsi'])) : '-'; ?>
            </span>
        </div>

        <div class="stats-mini">
            <div class="card-mini">
                <h4>Total Mahasiswa Enroll</h4>
                <p><?php echo $totalMahasiswa; ?></p>
            </div>

            <div class="card-mini">
                <h4>Sudah Mengerjakan</h4>
                <p><?php echo $sudahMengerjakan; ?></p>
            </div>

            <div class="card-mini">
                <h4>Belum Mengerjakan</h4>
                <p><?php echo $belumMengerjakan; ?></p>
            </div>

            <div class="card-mini">
                <h4>Rata-rata Nilai</h4>
                <p><?php echo $rataNilai; ?></p>
            </div>
        </div>

        <table class="table-nilai">
            <tr>
                <th>Rank</th>
                <th>NIM</th>
                <th>Nama</th>
                <th>Email</th>
                <th>Status Pengerjaan</th>
                <th>Nilai</th>
                <th>Tanggal Submit</th>
            </tr>

            <?php if ($nilaiQuery && $nilaiQuery->num_rows > 0): ?>
                <?php $rank = 1; ?>
                <?php while ($row = $nilaiQuery->fetch_assoc()): ?>
                    <tr>
                        <td>
                            <?php echo !is_null($row['nilai']) ? $rank++ : '-'; ?>
                        </td>
                        <td><?php echo htmlspecialchars($row['nim']); ?></td>
                        <td><?php echo htmlspecialchars($row['nama']); ?></td>
                        <td><?php echo htmlspecialchars($row['email']); ?></td>
                        <td>
                            <?php if (!is_null($row['nilai'])): ?>
                                <span class="status-submit status-sudah">Sudah Mengerjakan</span>
                            <?php else: ?>
                                <span class="status-submit status-belum">Belum Mengerjakan</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!is_null($row['nilai'])): ?>
                                <span class="nilai-bagus"><?php echo htmlspecialchars($row['nilai']); ?></span>
                            <?php else: ?>
                                <span class="nilai-kosong">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php echo !empty($row['tanggal_submit']) ? htmlspecialchars($row['tanggal_submit']) : '-'; ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="7">Belum ada mahasiswa yang enroll pada kelas kuis ini.</td>
                </tr>
            <?php endif; ?>
        </table>
    </div>
</div>

</body>
</html>