<?php
session_start();
include "../idk/forsafety.php";

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

/* =========================
   FILTER
========================= */
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
   AMBIL DAFTAR MATKUL
   UNTUK FILTER
========================= */
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

/* =========================
   VALIDASI FILTER
   pastikan matkul_id yang dipilih memang milik mahasiswa
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
   AMBIL MATERI SESUAI ENROLL
   + FILTER MATKUL
   + TERBARU DULU
========================= */
if ($filter_matkul > 0) {
    $stmtMateri = $conn->prepare("
        SELECT 
            m.id,
            m.judul,
            m.deskripsi,
            m.file_materi,
            m.created_at,
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
?>
<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Materi | COMPULAB</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<script>
  const t = localStorage.getItem('compulab_theme');
  if (t) document.documentElement.setAttribute('data-theme', t);
</script>
<link rel="stylesheet" href="../css/global.css">
<link rel="stylesheet" href="../css/dashboard-mahasiswa.css">

<!-- <style>
  .section-card {
    padding: 28px;
  }

  .section-card h3 {
    margin-bottom: 8px;
  }

  .section-card p.desc {
    margin-bottom: 18px;
    opacity: 0.82;
    line-height: 1.6;
  }

  .content-stack {
    display: flex;
    flex-direction: column;
    gap: 24px;
  }

  .custom-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 10px;
    overflow: hidden;
    border-radius: 16px;
  }

  .custom-table th,
  .custom-table td {
    padding: 14px 16px;
    text-align: left;
    border-bottom: 1px solid rgba(255,255,255,0.08);
    vertical-align: top;
  }

  .custom-table th {
    font-size: 14px;
    opacity: 0.75;
    background: rgba(255,255,255,0.03);
  }

  .custom-table tr:hover td {
    background: rgba(255,255,255,0.025);
  }

  .materi-title {
    font-weight: 700;
    margin-bottom: 6px;
  }

  .materi-sub {
    font-size: 13px;
    opacity: 0.75;
    line-height: 1.5;
  }

  .desc-box {
    max-width: 320px;
    line-height: 1.6;
    opacity: 0.9;
  }

  .btn-file,
  .btn-enroll,
  .btn-filter,
  .btn-reset {
    border: none;
    border-radius: 12px;
    padding: 10px 16px;
    font-weight: 600;
    cursor: pointer;
    transition: 0.2s ease;
    text-decoration: none;
    display: inline-block;
  }

  .btn-file,
  .btn-enroll,
  .btn-filter {
    background: linear-gradient(90deg, #49a078, #2d6a4f);
    color: white;
  }

  .btn-reset {
    background: rgba(255,255,255,0.08);
    color: white;
  }

  .btn-file:hover,
  .btn-enroll:hover,
  .btn-filter:hover,
  .btn-reset:hover {
    transform: translateY(-1px);
    opacity: 0.95;
  }

  .empty-text {
    opacity: 0.8;
    padding: 14px 0;
  }

  .empty-box {
    padding: 18px 0 6px;
  }

  .filter-box {
    display: flex;
    gap: 12px;
    align-items: end;
    flex-wrap: wrap;
    margin-bottom: 16px;
  }

  .filter-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
    min-width: 280px;
  }

  .filter-group label {
    font-size: 14px;
    opacity: 0.8;
    font-weight: 600;
  }

  .filter-group select {
    padding: 12px 14px;
    border-radius: 12px;
    border: 1px solid rgba(255,255,255,0.12);
    background: rgba(255,255,255,0.05);
    color: white;
    outline: none;
  }

  .filter-group select option {
    color: black;
  }

  .result-info {
    margin-bottom: 10px;
    font-size: 14px;
    opacity: 0.8;
  }
</style> -->

<style>
  .section-card { padding: 28px; }
  .section-card h3 { margin-bottom: 8px; }
  .section-card p.desc {
    margin-bottom: 18px;
    color: var(--text-muted);
    line-height: 1.6;
    font-size: 14px;
  }

  .content-stack { display: flex; flex-direction: column; gap: 24px; }
  .empty-text { color: var(--text-muted); padding: 14px 0; font-size: 14px; }
  .empty-box { padding: 18px 0 6px; }

  /* ── FILTER ── */
  .filter-box {
    display: flex;
    gap: 12px;
    align-items: end;
    flex-wrap: wrap;
    margin-bottom: 16px;
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
  }

  .filter-group select:focus { border-color: var(--border-focus); }

  .btn-filter, .btn-enroll {
    border: none;
    border-radius: 12px;
    padding: 12px 18px;
    font-weight: 600;
    font-family: 'DM Sans', sans-serif;
    font-size: 14px;
    cursor: pointer;
    background: var(--btn-gradient);
    color: white;
    box-shadow: 0 4px 12px var(--btn-shadow);
    text-decoration: none;
    display: inline-block;
  }

  .btn-reset {
    border: 1.5px solid var(--border);
    border-radius: 12px;
    padding: 12px 18px;
    font-weight: 600;
    font-family: 'DM Sans', sans-serif;
    font-size: 14px;
    cursor: pointer;
    background: var(--bg-input);
    color: var(--text-primary);
    text-decoration: none;
    display: inline-block;
  }

  .btn-file {
    border: none;
    border-radius: 10px;
    padding: 8px 14px;
    font-weight: 600;
    font-family: 'DM Sans', sans-serif;
    font-size: 13px;
    cursor: pointer;
    background: var(--btn-gradient);
    color: white;
    text-decoration: none;
    display: inline-block;
    white-space: nowrap;
  }

  .result-info {
    margin-bottom: 10px;
    font-size: 13px;
    color: var(--text-muted);
  }

  /* ── TABLE ── */
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
    text-align: left;
    border-bottom: 1px solid var(--border);
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

  .materi-title { font-weight: 700; margin-bottom: 4px; color: var(--text-primary); }
  .materi-sub { font-size: 13px; color: var(--text-muted); line-height: 1.5; }
  .desc-box { max-width: 280px; line-height: 1.6; color: var(--text-mid); font-size: 13px; }

  /* ── RESPONSIVE TABLE MOBILE ── */
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

    .desc-box { max-width: 100%; }
  }
</style>

</head>
<body>

<!-- HAMBURGER BUTTON -->
<!-- OVERLAY -->
<div class="mobile-overlay" id="mobileOverlay"></div>

<!-- MOBILE DRAWER -->
<div class="mobile-drawer" id="mobileDrawer">
  <div class="logo">COMPULAB</div>
  <nav>
    <a href="dashboard.php">🏠 Dashboard</a>
    <a href="enroll.php">📘 Enroll Class</a>
    <a class="active" href="materi.php">📚 Materi</a>
    <a href="kuis.php">📝 Kuis</a>
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
    <a class="active" href="materi.php">📚 Materi</a>
    <a href="kuis.php">📝 Kuis</a>
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
      <h3>Materi Pembelajaran</h3>
      <p class="desc">
        Halaman ini menampilkan materi sesuai kelas yang sudah Anda enroll. Materi diurutkan dari yang terbaru.
      </p>

      <?php if ($totalEnroll <= 0): ?>
        <div class="empty-box">
          <p class="empty-text">Anda belum enroll kelas. Silakan pilih kelas terlebih dahulu untuk mengakses materi.</p>
          <a href="enroll.php" class="btn-enroll">Ke Halaman Enroll</a>
        </div>
      <?php else: ?>

        <form method="GET" class="filter-box">
          <div class="filter-group">
            <label for="matkul_id">Filter Mata Kuliah</label>
            <select name="matkul_id" id="matkul_id">
              <option value="0">Semua Mata Kuliah</option>
              <?php while ($filterRow = $filterMatkulQuery->fetch_assoc()): ?>
                <option 
                  value="<?php echo $filterRow['id']; ?>"
                  <?php echo ($filter_matkul == $filterRow['id']) ? 'selected' : ''; ?>
                >
                  <?php echo htmlspecialchars($filterRow['nama_matkul'] . ' | ' . $filterRow['angkatan'] . ' ' . $filterRow['kelas']); ?>
                </option>
              <?php endwhile; ?>
            </select>
          </div>

          <button type="submit" class="btn-filter">Terapkan Filter</button>
          <a href="materi.php" class="btn-reset">Reset</a>
        </form>

        <div class="result-info">
          Menampilkan materi
          <?php echo ($filter_matkul > 0) ? 'sesuai mata kuliah yang dipilih.' : 'untuk semua mata kuliah yang Anda enroll.'; ?>
        </div>

        <?php if ($materiQuery && $materiQuery->num_rows > 0): ?>
          <table class="custom-table">
            <thead>
              <tr>
                <th>No</th>
                <th>Materi</th>
                <th>Mata Kuliah</th>
                <th>Deskripsi</th>
                <th>Dosen</th>
                <th>Tanggal Upload</th>
                <th>File</th>
              </tr>
            </thead>
            <tbody>
              <?php $no = 1; ?>
              <?php while ($row = $materiQuery->fetch_assoc()): ?>
                <tr>
                  <td data-label="No"><?php echo $no++; ?></td>

                  <td data-label="Materi">
                    <div class="materi-title">
                      <?php echo htmlspecialchars($row['judul']); ?>
                    </div>
                    <div class="materi-sub">
                      File: <?php echo htmlspecialchars($row['file_materi']); ?>
                    </div>
                  </td>

                  <td data-label="Mata Kuliah">
                    <div class="materi-title">
                      <?php echo htmlspecialchars($row['nama_matkul']); ?>
                    </div>
                    <div class="materi-sub">
                      Angkatan <?php echo htmlspecialchars($row['angkatan']); ?> | Kelas <?php echo htmlspecialchars($row['kelas']); ?>
                    </div>
                  </td>

                  <td data-label="Deskripsi" class="desc-box">
                    <?php echo !empty($row['deskripsi']) ? nl2br(htmlspecialchars($row['deskripsi'])) : '-'; ?>
                  </td>

                  <td data-label="Dosen">
                    <?php echo htmlspecialchars($row['nama_dosen']); ?>
                  </td>

                  <td data-label="Tanggal">
                    <?php echo htmlspecialchars($row['created_at']); ?>
                  </td>

                  <td data-label="File">
                    <?php if (!empty($row['file_materi'])): ?>
                      <a 
                        href="../uploads/materi/<?php echo urlencode($row['file_materi']); ?>" 
                        target="_blank" 
                        class="btn-file"
                      >
                        Lihat File
                      </a>
                    <?php else: ?>
                      -
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        <?php else: ?>
          <p class="empty-text">Belum ada materi yang tersedia untuk filter yang dipilih.</p>
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