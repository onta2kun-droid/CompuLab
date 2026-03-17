<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once __DIR__ . '/../../sambung/forsafety.php';

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
$statusClass = 'status-wait';

if (!empty($kuis['waktu_mulai']) && $sekarang < $kuis['waktu_mulai']) {
    $statusKuis = 'Belum Dimulai';
    $statusClass = 'status-wait';
} elseif (!empty($kuis['waktu_selesai']) && $sekarang > $kuis['waktu_selesai']) {
    $statusKuis = 'Selesai';
    $statusClass = 'status-close';
} else {
    $statusKuis = 'Berlangsung';
    $statusClass = 'status-open';
}
?>

<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Nilai Kuis | COMPULAB</title>

<link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">

<script>
  const t = localStorage.getItem('compulab_theme');
  if (t) document.documentElement.setAttribute('data-theme', t);
</script>

<link rel="stylesheet" href="../css/dashboard-dosen.css">

<style>
  .content-stack {
    display: flex;
    flex-direction: column;
    gap: 24px;
  }

  .section-card {
    padding: 28px;
  }

  .btn-back {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 18px;
    padding: 10px 14px;
    background: var(--bg-input);
    color: var(--text-primary);
    text-decoration: none;
    border-radius: 12px;
    border: 1px solid var(--border);
    font-size: 14px;
  }

  .info-box {
    background: var(--bg-input);
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 18px;
    margin-bottom: 20px;
    line-height: 1.8;
    color: var(--text-primary);
  }

  .small-text {
    color: var(--text-muted);
    font-size: 13px;
    line-height: 1.6;
  }

  .stats-mini {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 14px;
    margin-bottom: 20px;
  }

  .card-mini {
    background: var(--bg-input);
    border: 1px solid var(--border);
    border-radius: 14px;
    padding: 16px;
  }

  .card-mini h4 {
    margin: 0 0 8px;
    font-size: 13px;
    color: var(--text-muted);
    font-weight: 600;
  }

  .card-mini p {
    margin: 0;
    font-size: 24px;
    font-weight: 700;
    color: var(--text-primary);
    font-family: 'DM Serif Display', serif;
  }

  .status-chip {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 700;
  }

  .status-open {
    background: rgba(34,197,94,0.12);
    color: #16a34a;
  }

  .status-wait {
    background: rgba(245,166,35,0.12);
    color: var(--gold-dark);
  }

  .status-close {
    background: rgba(107,143,212,0.12);
    color: var(--navy-fresh);
  }

  .status-submit {
    display: inline-block;
    padding: 6px 10px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 700;
  }

  .status-sudah {
    background: rgba(34,197,94,0.12);
    color: #16a34a;
  }

  .status-belum {
    background: rgba(239,68,68,0.12);
    color: #b91c1c;
  }

  .nilai-bagus {
    font-weight: 700;
    color: #1e7e34;
  }

  .nilai-kosong {
    color: var(--text-muted);
    font-style: italic;
  }

  .custom-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 12px;
    border-radius: 16px;
    overflow: hidden;
  }

  .custom-table th,
  .custom-table td {
    padding: 14px 16px;
    border-bottom: 1px solid var(--border);
    text-align: left;
    vertical-align: top;
    color: var(--text-primary);
  }

  .custom-table th {
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--text-muted);
    background: var(--bg-input);
  }

  .custom-table tbody tr:hover td {
    background: var(--bg-input);
  }

  .empty-row {
    text-align: center;
    color: var(--text-muted);
  }

  @media (max-width: 768px) {
    .section-card {
      padding: 18px;
    }
  }

  @media (max-width: 640px) {
    .custom-table thead {
      display: none;
    }

    .custom-table,
    .custom-table tbody,
    .custom-table tr,
    .custom-table td {
      display: block;
      width: 100%;
    }

    .custom-table tr {
      border: 1px solid var(--border);
      border-radius: 14px;
      margin-bottom: 14px;
      background: var(--bg-card);
      overflow: hidden;
    }

    .custom-table td {
      border-bottom: 1px solid var(--border);
      padding: 12px 16px;
    }

    .custom-table td:last-child {
      border-bottom: none;
    }

    .custom-table td::before {
      content: attr(data-label);
      display: block;
      font-size: 11px;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      color: var(--text-muted);
      margin-bottom: 4px;
    }
  }
</style>
</head>
<body>

<div class="mobile-overlay" id="mobileOverlay"></div>

<div class="mobile-drawer" id="mobileDrawer">
  <div class="logo">COMPULAB</div>
  <nav>
    <a href="dashboard.php">📊 Dashboard</a>
    <a href="upload_materi.php">📤 Upload Materi</a>
    <a href="kelola_materi.php">📚 Kelola Materi</a>
    <a href="tambah_kuis.php">📝 Tambah Kuis</a>
    <a class="active" href="kelola_kuis.php">📋 Kelola Kuis</a>
    <a href="scoreboard.php">📈 Scoreboard</a>
    <a href="profil.php">👨‍🏫 Profil</a>
  </nav>
  <div class="sidebar-bottom"><a href="logout.php">🚪 Logout</a></div>
</div>

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
  </nav>
  <div class="sidebar-bottom"><a href="logout.php">🚪 Logout</a></div>
</div>

<div class="main">
  <div class="topbar">
    <button class="hamburger" id="hamburgerBtn" aria-label="Menu">
      <span></span><span></span><span></span>
    </button>
    <h1>Nilai Kuis 📊</h1>
    <div class="topbar-right">
      <a href="profil.php" class="profile-mini" title="Profil Dosen">👨‍🏫</a>
      <button class="dark-toggle-top" id="darkToggle">🌙</button>
    </div>
  </div>

  <div class="content-stack">

    <div class="table-card section-card">
      <a href="kelola_kuis.php" class="btn-back">← Kembali ke Kelola Kuis</a>

      <h3 style="margin-bottom:16px;">Nilai Kuis Mahasiswa</h3>

      <div class="info-box">
        <strong>Judul Kuis:</strong> <?php echo htmlspecialchars($kuis['judul']); ?><br>
        <strong>Mata Kuliah:</strong> <?php echo htmlspecialchars($kuis['nama_matkul'] . ' | ' . $kuis['angkatan'] . ' ' . $kuis['kelas']); ?><br>
        <strong>Durasi:</strong> <?php echo htmlspecialchars($kuis['durasi']); ?> menit<br>
        <strong>Total Soal:</strong> <?php echo $totalSoal; ?> soal<br>
        <strong>Status:</strong> <span class="status-chip <?php echo $statusClass; ?>"><?php echo htmlspecialchars($statusKuis); ?></span><br>
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

      <table class="custom-table">
        <thead>
          <tr>
            <th>Rank</th>
            <th>NIM</th>
            <th>Nama</th>
            <th>Email</th>
            <th>Status Pengerjaan</th>
            <th>Nilai</th>
            <th>Tanggal Submit</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($nilaiQuery && $nilaiQuery->num_rows > 0): ?>
            <?php $rank = 1; ?>
            <?php while ($row = $nilaiQuery->fetch_assoc()): ?>
              <tr>
                <td data-label="Rank">
                  <?php echo !is_null($row['nilai']) ? $rank++ : '-'; ?>
                </td>
                <td data-label="NIM"><?php echo htmlspecialchars($row['nim']); ?></td>
                <td data-label="Nama"><?php echo htmlspecialchars($row['nama']); ?></td>
                <td data-label="Email"><?php echo htmlspecialchars($row['email']); ?></td>
                <td data-label="Status Pengerjaan">
                  <?php if (!is_null($row['nilai'])): ?>
                    <span class="status-submit status-sudah">Sudah Mengerjakan</span>
                  <?php else: ?>
                    <span class="status-submit status-belum">Belum Mengerjakan</span>
                  <?php endif; ?>
                </td>
                <td data-label="Nilai">
                  <?php if (!is_null($row['nilai'])): ?>
                    <span class="nilai-bagus"><?php echo htmlspecialchars($row['nilai']); ?></span>
                  <?php else: ?>
                    <span class="nilai-kosong">-</span>
                  <?php endif; ?>
                </td>
                <td data-label="Tanggal Submit">
                  <?php echo !empty($row['tanggal_submit']) ? htmlspecialchars($row['tanggal_submit']) : '-'; ?>
                </td>
              </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr>
              <td colspan="7" class="empty-row">Belum ada mahasiswa yang enroll pada kelas kuis ini.</td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

  </div>
</div>

<script src="../js/darkmode.js"></script>
<script>
  const btn = document.getElementById('hamburgerBtn');
  const drawer = document.getElementById('mobileDrawer');
  const overlay = document.getElementById('mobileOverlay');

  btn.addEventListener('click', () => {
    btn.classList.toggle('open');
    drawer.classList.toggle('open');
    overlay.classList.toggle('show');
  });

  overlay.addEventListener('click', () => {
    btn.classList.remove('open');
    drawer.classList.remove('open');
    overlay.classList.remove('show');
  });
</script>
</body>
</html>