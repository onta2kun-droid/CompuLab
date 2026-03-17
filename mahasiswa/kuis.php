<?php
session_start();
include "../idk/forsafety.php";

date_default_timezone_set('Asia/Makassar');

if (!isset($_SESSION['email'])) {
    header("Location: ../login/mahasiswa.php");
    exit;
}

$email = $_SESSION['email'];

/* =========================
   AMBIL DATA MAHASISWA
========================= */
$stmt = $conn->prepare("
    SELECT id, nim, nama, email, angkatan, is_verified
    FROM akun
    WHERE email = ? AND role = 'mahasiswa'
    LIMIT 1
");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows <= 0) {
    session_destroy();
    header("Location: ../login/mahasiswa.php");
    exit;
}

$user = $result->fetch_assoc();
$stmt->close();

if ((int)$user['is_verified'] !== 1) {
    session_destroy();
    header("Location: ../login/verify_mahasiswa.php?email=" . urlencode($user['email']));
    exit;
}

$nim      = $user['nim'];
$nama     = $user['nama'];
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
   AMBIL KUIS SESUAI ENROLL
   NOTE: TIDAK DIFILTER is_active
   supaya kuis nonaktif tetap terlihat
========================= */
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

$sekarang = date('Y-m-d H:i:s');
?>
<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Kuis | COMPULAB</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../css/global.css">
<link rel="stylesheet" href="../css/dashboard-mahasiswa.css">

<style>
  .section-card { padding: 28px; }
  .content-stack { display: flex; flex-direction: column; gap: 24px; }

  .filter-box {
    display: flex;
    gap: 12px;
    align-items: end;
    flex-wrap: wrap;
    margin-bottom: 18px;
  }

  .filter-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
    min-width: 280px;
  }

  .filter-group label {
    font-size: 13px;
    color: var(--text-muted);
    font-weight: 600;
  }

  .filter-group select {
    padding: 12px 14px;
    border-radius: 12px;
    border: 1.5px solid var(--border);
    background: var(--bg-input);
    color: var(--text-primary);
    font-family: 'DM Sans', sans-serif;
    font-size: 14px;
    outline: none;
    appearance: none;
  }

  .filter-group select:focus { border-color: var(--border-focus); }

  .btn-main, .btn-kerjakan {
    border: none;
    border-radius: 12px;
    padding: 11px 18px;
    font-weight: 600;
    font-family: 'DM Sans', sans-serif;
    font-size: 14px;
    cursor: pointer;
    background: var(--btn-gradient);
    color: white;
    text-decoration: none;
    display: inline-block;
    box-shadow: 0 4px 12px var(--btn-shadow);
  }

  .btn-main:hover, .btn-kerjakan:hover { opacity: 0.9; transform: translateY(-1px); }

  .btn-reset {
    border: 1.5px solid var(--border);
    border-radius: 12px;
    padding: 11px 18px;
    font-weight: 600;
    font-family: 'DM Sans', sans-serif;
    font-size: 14px;
    cursor: pointer;
    background: var(--bg-input);
    color: var(--text-primary);
    text-decoration: none;
    display: inline-block;
  }

  .btn-disabled {
    border: 1.5px solid var(--border);
    border-radius: 12px;
    padding: 10px 14px;
    font-weight: 600;
    font-size: 13px;
    background: var(--bg-input);
    color: var(--text-muted);
    cursor: not-allowed;
    display: inline-block;
  }

  .btn-detail {
    border: none;
    border-radius: 12px;
    padding: 10px 14px;
    font-weight: 600;
    font-size: 13px;
    text-decoration: none;
    display: inline-block;
    background: rgba(52,152,219,0.15);
    color: #3498db;
    border: 1px solid rgba(52,152,219,0.25);
    margin-left: 6px;
  }

  .btn-detail:hover { background: rgba(52,152,219,0.25); }

  .status-open  { color: var(--green-fresh); font-weight: 700; }
  .status-close { color: #ef4444; font-weight: 700; }
  .status-wait  { color: #f59e0b; font-weight: 700; }
  .status-inactive { color: #9ca3af; font-weight: 700; }

  .custom-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 10px;
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

  .custom-table tbody tr:hover td { background: var(--bg-input); }

  .custom-table td strong { color: var(--text-primary); }
  .custom-table td small { color: var(--text-muted); font-size: 12px; }

  .empty-text { color: var(--text-muted); padding: 14px 0; font-size: 14px; }

  @media (max-width: 640px) {
    .section-card { padding: 16px; }
    .filter-group { min-width: 100%; }
    .filter-box { flex-direction: column; }

    .custom-table thead { display: none; }

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

    .custom-table td:last-child { border-bottom: none; }

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
    <a href="dashboard.php">🏠 Dashboard</a>
    <a href="enroll.php">📘 Enroll Class</a>
    <a href="materi.php">📚 Materi</a>
    <a class="active" href="kuis.php">📝 Kuis</a>
    <a href="scoreboard.php">🏆 Scoreboard</a>
    <a href="profil.php">👤 Profil</a>
    <a href="../login/logout.php" class="logout">🚪 Logout</a>
  </nav>
</div>

<div class="sidebar">
  <div class="logo">COMPULAB</div>
  <nav>
    <a href="dashboard.php">🏠 Dashboard</a>
    <a href="enroll.php">📘 Enroll Class</a>
    <a href="materi.php">📚 Materi</a>
    <a class="active" href="kuis.php">📝 Kuis</a>
    <a href="scoreboard.php">🏆 Scoreboard</a>
    <a href="profil.php">👤 Profil</a>
    <a href="../login/logout.php" class="logout">🚪 Logout</a>
  </nav>
</div>

<div class="main">
  <div class="topbar">
    <button class="hamburger" id="hamburgerBtn" aria-label="Menu">
      <span></span><span></span><span></span>
    </button>
    <h1>Halo, <?php echo htmlspecialchars($nama); ?> 👋</h1>
    <div class="topbar-right">
      <a href="../mahasiswa/profil.php" class="profile-mini" title="Lihat Profil">🎓</a>
      <button class="dark-toggle-top" id="darkToggle" title="Toggle Dark Mode">🌙</button>
    </div>
  </div>

  <div class="stats">
    <div class="card">
      <h3>NIM</h3>
      <p><?php echo htmlspecialchars($nim); ?></p>
    </div>
    <div class="card">
      <h3>Angkatan</h3>
      <p><?php echo htmlspecialchars($angkatan); ?></p>
    </div>
    <div class="card">
      <h3>Status</h3>
      <p>Mahasiswa Aktif ✅</p>
    </div>
  </div>

  <div class="content-stack">
    <div class="card section-card">
      <h3>Daftar Kuis</h3>
      <p>Menampilkan kuis sesuai kelas yang sudah Anda enroll.</p>

      <?php if ($totalEnroll <= 0): ?>
        <p class="empty-text">Anda belum enroll kelas. Silakan enroll terlebih dahulu.</p>
        <a href="enroll.php" class="btn-main">Ke Halaman Enroll</a>
      <?php else: ?>

        <form method="GET" class="filter-box">
          <div class="filter-group">
            <label for="matkul_id">Filter Mata Kuliah</label>
            <select name="matkul_id" id="matkul_id">
              <option value="0">Semua Mata Kuliah</option>
              <?php while ($f = $filterMatkulQuery->fetch_assoc()): ?>
                <option value="<?php echo $f['id']; ?>" <?php echo ($filter_matkul == $f['id']) ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars($f['nama_matkul'] . ' | ' . $f['angkatan'] . ' ' . $f['kelas']); ?>
                </option>
              <?php endwhile; ?>
            </select>
          </div>
          <button type="submit" class="btn-main">Terapkan Filter</button>
          <a href="kuis.php" class="btn-reset">Reset</a>
        </form>

        <?php if ($kuisQuery && $kuisQuery->num_rows > 0): ?>
          <table class="custom-table">
            <thead>
              <tr>
                <th>No</th>
                <th>Kuis</th>
                <th>Mata Kuliah</th>
                <th>Dosen</th>
                <th>Durasi</th>
                <th>Status</th>
                <th>Nilai</th>
                <th>Aksi</th>
              </tr>
            </thead>
            <tbody>
              <?php $no = 1; ?>
              <?php while ($row = $kuisQuery->fetch_assoc()): ?>
                <?php
                  $status = '';
                  $aksi = '';
                  $sudahDikerjakan = !is_null($row['nilai']);
                  $isActive = (int)($row['is_active'] ?? 0) === 1;

                  if (!$isActive) {
                      $status = '<span class="status-inactive">Belum Diaktifkan</span>';

                      if ($sudahDikerjakan) {
                          $aksi = '<a href="detail_kuis.php?id=' . $row['id'] . '" class="btn-detail">Detail</a>';
                      } else {
                          $aksi = '<span class="btn-disabled">Menunggu Aktivasi</span>';
                      }
                  } elseif (!empty($row['waktu_mulai']) && $sekarang < $row['waktu_mulai']) {
                      $status = '<span class="status-wait">Belum Dimulai</span>';

                      if ($sudahDikerjakan) {
                          $aksi = '<a href="detail_kuis.php?id=' . $row['id'] . '" class="btn-detail">Detail</a>';
                      } else {
                          $aksi = '<span class="btn-disabled">Menunggu</span>';
                      }
                  } elseif (!empty($row['waktu_selesai']) && $sekarang > $row['waktu_selesai']) {
                      $status = '<span class="status-close">Selesai</span>';

                      if ($sudahDikerjakan) {
                          $aksi = '<a href="detail_kuis.php?id=' . $row['id'] . '" class="btn-detail">Detail</a>';
                      } else {
                          $aksi = '<span class="btn-disabled">Ditutup</span>';
                      }
                  } else {
                      $status = '<span class="status-open">Tersedia</span>';

                      if ($sudahDikerjakan) {
                          $aksi = '
                              <span class="btn-disabled">Sudah Dikerjakan</span>
                              <a href="detail_kuis.php?id=' . $row['id'] . '" class="btn-detail">Detail</a>
                          ';
                      } else {
                          $aksi = '<a href="kerjakan_kuis.php?id=' . $row['id'] . '" class="btn-kerjakan">Kerjakan</a>';
                      }
                  }
                ?>
                <tr>
                  <td data-label="No"><?php echo $no++; ?></td>
                  <td data-label="Kuis">
                    <strong><?php echo htmlspecialchars($row['judul']); ?></strong><br>
                    <small><?php echo !empty($row['deskripsi']) ? htmlspecialchars($row['deskripsi']) : '-'; ?></small>
                  </td>
                  <td data-label="Mata Kuliah">
                    <?php echo htmlspecialchars($row['nama_matkul']); ?><br>
                    <small>Kelas <?php echo htmlspecialchars($row['kelas']); ?></small>
                  </td>
                  <td data-label="Dosen"><?php echo htmlspecialchars($row['nama_dosen']); ?></td>
                  <td data-label="Durasi"><?php echo (int)$row['durasi']; ?> menit</td>
                  <td data-label="Status"><?php echo $status; ?></td>
                  <td data-label="Nilai"><?php echo is_null($row['nilai']) ? '-' : htmlspecialchars($row['nilai']); ?></td>
                  <td data-label="Aksi"><?php echo $aksi; ?></td>
                </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        <?php else: ?>
          <p class="empty-text">Belum ada kuis untuk kelas yang Anda enroll.</p>
        <?php endif; ?>

      <?php endif; ?>
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