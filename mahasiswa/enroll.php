<?php
session_start();
require_once __DIR__ . '/../../sambung/forsafety.php';

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

$success = "";
$error   = "";

/* =========================
   HAPUS ENROLL
========================= */
if (isset($_GET['hapus'])) {
    $enroll_id = (int) $_GET['hapus'];

    $stmtHapus = $conn->prepare("
        DELETE FROM mahasiswa_matkul
        WHERE id = ? AND nim = ?
    ");
    $stmtHapus->bind_param("is", $enroll_id, $nim);

    if ($stmtHapus->execute()) {
        $success = "Kelas berhasil dihapus dari enroll.";
    } else {
        $error = "Gagal menghapus enroll.";
    }

    $stmtHapus->close();
}

/* =========================
   PROSES ENROLL
========================= */
if (isset($_POST['enroll'])) {
    $matkul_id = (int) ($_POST['matkul_id'] ?? 0);

    if ($matkul_id <= 0) {
        $error = "Pilih kelas terlebih dahulu.";
    } else {
        $stmtMatkul = $conn->prepare("
            SELECT id, nama_matkul, kelas, angkatan
            FROM mata_kuliah
            WHERE id = ? AND angkatan = ?
            LIMIT 1
        ");
        $stmtMatkul->bind_param("is", $matkul_id, $angkatan);
        $stmtMatkul->execute();
        $resultMatkul = $stmtMatkul->get_result();

        if ($resultMatkul->num_rows <= 0) {
            $error = "Kelas tidak valid untuk angkatan Anda.";
        } else {
            $matkulDipilih = $resultMatkul->fetch_assoc();
            $nama_matkul_dipilih = $matkulDipilih['nama_matkul'];

            $stmtCekNama = $conn->prepare("
                SELECT mm.id, mk.nama_matkul, mk.kelas
                FROM mahasiswa_matkul mm
                JOIN mata_kuliah mk ON mm.matkul_id = mk.id
                WHERE mm.nim = ? AND mk.nama_matkul = ?
                LIMIT 1
            ");
            $stmtCekNama->bind_param("ss", $nim, $nama_matkul_dipilih);
            $stmtCekNama->execute();
            $resultCekNama = $stmtCekNama->get_result();

            if ($resultCekNama->num_rows > 0) {
                $dataSudah = $resultCekNama->fetch_assoc();
                $error = "Anda sudah enroll mata kuliah " . $dataSudah['nama_matkul'] . " kelas " . $dataSudah['kelas'] . ". Hanya boleh pilih 1 kelas.";
            } else {
                $stmtCekSama = $conn->prepare("
                    SELECT id
                    FROM mahasiswa_matkul
                    WHERE nim = ? AND matkul_id = ?
                    LIMIT 1
                ");
                $stmtCekSama->bind_param("si", $nim, $matkul_id);
                $stmtCekSama->execute();
                $resultCekSama = $stmtCekSama->get_result();

                if ($resultCekSama->num_rows > 0) {
                    $error = "Kelas ini sudah Anda enroll.";
                } else {
                    $stmtInsert = $conn->prepare("
                        INSERT INTO mahasiswa_matkul (nim, matkul_id)
                        VALUES (?, ?)
                    ");
                    $stmtInsert->bind_param("si", $nim, $matkul_id);

                    if ($stmtInsert->execute()) {
                        $success = "Berhasil enroll kelas " . $matkulDipilih['nama_matkul'] . " - " . $matkulDipilih['kelas'] . ".";
                    } else {
                        $error = "Gagal menyimpan enroll.";
                    }

                    $stmtInsert->close();
                }

                $stmtCekSama->close();
            }

            $stmtCekNama->close();
        }

        $stmtMatkul->close();
    }
}

/* =========================
   AMBIL KELAS SESUAI ANGKATAN
========================= */
$stmtAvailable = $conn->prepare("
    SELECT id, nama_matkul, angkatan, kelas
    FROM mata_kuliah
    WHERE angkatan = ?
    ORDER BY nama_matkul ASC, kelas ASC
");
$stmtAvailable->bind_param("s", $angkatan);
$stmtAvailable->execute();
$availableClasses = $stmtAvailable->get_result();

/* =========================
   AMBIL ENROLL MAHASISWA
========================= */
$stmtEnrolled = $conn->prepare("
    SELECT 
        mm.id AS enroll_id,
        mk.id AS matkul_id,
        mk.nama_matkul,
        mk.angkatan,
        mk.kelas
    FROM mahasiswa_matkul mm
    JOIN mata_kuliah mk ON mm.matkul_id = mk.id
    WHERE mm.nim = ?
    ORDER BY mk.nama_matkul ASC, mk.kelas ASC
");
$stmtEnrolled->bind_param("s", $nim);
$stmtEnrolled->execute();
$enrolledClasses = $stmtEnrolled->get_result();

/* =========================
   AMBIL DAFTAR MATKUL YANG SUDAH DIAMBIL
========================= */
$enrolledNames = [];
$stmtEnrolledNames = $conn->prepare("
    SELECT mk.nama_matkul
    FROM mahasiswa_matkul mm
    JOIN mata_kuliah mk ON mm.matkul_id = mk.id
    WHERE mm.nim = ?
");
$stmtEnrolledNames->bind_param("s", $nim);
$stmtEnrolledNames->execute();
$resultEnrolledNames = $stmtEnrolledNames->get_result();

while ($rowName = $resultEnrolledNames->fetch_assoc()) {
    $enrolledNames[] = $rowName['nama_matkul'];
}
$stmtEnrolledNames->close();
?>
<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Enroll Class | COMPULAB</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<script>
  const t = localStorage.getItem('compulab_theme');
  if (t) document.documentElement.setAttribute('data-theme', t);
</script>
<link rel="stylesheet" href="../css/global.css">
<link rel="stylesheet" href="../css/dashboard-mahasiswa.css">

<!-- <style>
  .alert-success,
  .alert-error {
    margin-bottom: 16px;
    font-weight: 600;
  }

  .alert-success {
    color: #6ee7a8;
  }

  .alert-error {
    color: #ff8f8f;
  }

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
    vertical-align: middle;
  }

  .custom-table th {
    font-size: 14px;
    opacity: 0.75;
    background: rgba(255,255,255,0.03);
  }

  .custom-table tr:hover td {
    background: rgba(255,255,255,0.025);
  }

  .badge-disabled {
    display: inline-block;
    padding: 8px 12px;
    border-radius: 999px;
    font-size: 13px;
    background: rgba(255,255,255,0.08);
    color: rgba(255,255,255,0.75);
  }

  .btn-enroll,
  .btn-hapus {
    border: none;
    border-radius: 12px;
    padding: 10px 16px;
    font-weight: 600;
    cursor: pointer;
    transition: 0.2s ease;
  }

  .btn-enroll {
    background: linear-gradient(90deg, #49a078, #2d6a4f);
    color: white;
  }

  .btn-enroll:hover {
    transform: translateY(-1px);
    opacity: 0.95;
  }

  .btn-hapus {
    background: #c84e4e;
    color: white;
    text-decoration: none;
    display: inline-block;
  }

  .btn-hapus:hover {
    opacity: 0.92;
  }

  .info-inline {
    display: flex;
    gap: 18px;
    flex-wrap: wrap;
    margin-top: 8px;
    margin-bottom: 2px;
  }

  .info-chip {
    background: rgba(255,255,255,0.05);
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 12px;
    padding: 10px 14px;
    min-width: 160px;
  }

  .info-chip span {
    display: block;
    font-size: 12px;
    opacity: 0.7;
    margin-bottom: 4px;
  }

  .empty-text {
    opacity: 0.8;
    padding: 14px 0;
  }

  .content-stack {
    display: flex;
    flex-direction: column;
    gap: 24px;
  }
</style> -->

<style>
  .alert-success {
    background: rgba(64,145,108,0.10);
    border: 1px solid rgba(64,145,108,0.25);
    color: var(--green-fresh);
    border-radius: 10px;
    padding: 10px 14px;
    font-size: 13px;
    font-weight: 600;
    margin-bottom: 16px;
  }

  .alert-error {
    background: var(--error-bg);
    border: 1px solid var(--error-border);
    color: var(--error-text);
    border-radius: 10px;
    padding: 10px 14px;
    font-size: 13px;
    font-weight: 600;
    margin-bottom: 16px;
  }

  .section-card { padding: 28px; }
  .section-card h3 { margin-bottom: 8px; }
  .section-card p.desc {
    margin-bottom: 18px;
    color: var(--text-muted);
    line-height: 1.6;
    font-size: 14px;
  }

  .content-stack {
    display: flex;
    flex-direction: column;
    gap: 24px;
  }

  .empty-text { color: var(--text-muted); padding: 14px 0; font-size: 14px; }

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
    vertical-align: middle;
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

  /* ── BADGE & BUTTONS ── */
  .badge-disabled {
    display: inline-block;
    padding: 7px 12px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 600;
    background: var(--bg-input);
    color: var(--text-muted);
    border: 1px solid var(--border);
  }

  .btn-enroll {
    border: none;
    border-radius: 12px;
    padding: 10px 16px;
    font-weight: 600;
    font-family: 'DM Sans', sans-serif;
    font-size: 13px;
    cursor: pointer;
    background: var(--btn-gradient);
    color: white;
    box-shadow: 0 4px 12px var(--btn-shadow);
  }

  .btn-enroll:hover { transform: translateY(-1px); opacity: 0.95; }

  .btn-hapus {
    border: none;
    border-radius: 12px;
    padding: 10px 16px;
    font-weight: 600;
    font-family: 'DM Sans', sans-serif;
    font-size: 13px;
    cursor: pointer;
    background: rgba(220,38,38,0.12);
    color: #ef4444;
    border: 1px solid rgba(220,38,38,0.2);
    text-decoration: none;
    display: inline-block;
  }

  .btn-hapus:hover { background: rgba(220,38,38,0.2); }

  /* ── RESPONSIVE TABLE ── */
  @media (max-width: 640px) {
    .section-card { padding: 20px 16px; }

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
      margin-bottom: 12px;
      padding: 4px 0;
      background: var(--bg-card);
    }

    .custom-table td {
      border-bottom: 1px solid var(--border);
      padding: 10px 16px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 8px;
    }

    .custom-table td:last-child { border-bottom: none; }

    .custom-table td::before {
      content: attr(data-label);
      font-size: 11px;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      color: var(--text-muted);
      flex-shrink: 0;
      min-width: 80px;
    }

    .btn-enroll, .btn-hapus { padding: 8px 14px; font-size: 13px; }
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
    <a class="active" href="enroll.php">📘 Enroll Class</a>
    <a href="materi.php">📚 Materi</a>
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
    <a class="active" href="enroll.php">📘 Enroll Class</a>
    <a href="materi.php">📚 Materi</a>
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
      <h3>Pilih Kelas Anda</h3>
      <p class="desc">
        Anda hanya bisa mengambil kelas yang tersedia untuk angkatan
        <strong><?php echo htmlspecialchars($angkatan); ?></strong>.
        Untuk mata kuliah yang sama, Anda hanya boleh memilih <strong>1 kelas</strong> saja.
      </p>

      <?php if ($success): ?>
        <div class="alert-success"><?php echo htmlspecialchars($success); ?></div>
      <?php endif; ?>

      <?php if ($error): ?>
        <div class="alert-error"><?php echo htmlspecialchars($error); ?></div>
      <?php endif; ?>

      <?php if ($availableClasses && $availableClasses->num_rows > 0): ?>
        <table class="custom-table">
          <thead>
            <tr>
              <th>No</th>
              <th>Mata Kuliah</th>
              <th>Angkatan</th>
              <th>Kelas</th>
              <th>Aksi</th>
            </tr>
          </thead>
          <tbody>
            <?php $no = 1; ?>
            <?php while ($kelas = $availableClasses->fetch_assoc()): ?>
              <?php $sudahAmbilNama = in_array($kelas['nama_matkul'], $enrolledNames); ?>
              <tr>
                <td data-label="No"><?php echo $no++; ?></td>
                <td data-label="Mata Kuliah"><?php echo htmlspecialchars($kelas['nama_matkul']); ?></td>
                <td data-label="Angkatan"><?php echo htmlspecialchars($kelas['angkatan']); ?></td>
                <td data-label="Kelas"><?php echo htmlspecialchars($kelas['kelas']); ?></td>
                <td data-label="Aksi">
                  <?php if ($sudahAmbilNama): ?>
                    <span class="badge-disabled">Sudah pilih kelas</span>
                  <?php else: ?>
                    <form method="POST" style="margin:0;">
                      <input type="hidden" name="matkul_id" value="<?php echo $kelas['id']; ?>">
                      <button type="submit" name="enroll" class="btn-enroll">Enroll</button>
                    </form>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      <?php else: ?>
        <p class="empty-text">Tidak ada kelas yang tersedia untuk angkatan Anda.</p>
      <?php endif; ?>
    </div>

    <div class="card section-card">
      <h3>Kelas yang Sudah Anda Enroll</h3>

      <?php if ($enrolledClasses && $enrolledClasses->num_rows > 0): ?>
        <table class="custom-table">
          <thead>
            <tr>
              <th>No</th>
              <th>Mata Kuliah</th>
              <th>Angkatan</th>
              <th>Kelas</th>
              <th>Aksi</th>
            </tr>
          </thead>
          <tbody>
            <?php $no = 1; ?>
            <?php while ($row = $enrolledClasses->fetch_assoc()): ?>
              <tr>
                <td data-label="No"><?php echo $no++; ?></td>
                <td data-label="Mata Kuliah"><?php echo htmlspecialchars($row['nama_matkul']); ?></td>
                <td data-label="Angkatan"><?php echo htmlspecialchars($row['angkatan']); ?></td>
                <td data-label="Kelas"><?php echo htmlspecialchars($row['kelas']); ?></td>
                <td data-label="Aksi">
                  <a
                    href="enroll.php?hapus=<?php echo $row['enroll_id']; ?>"
                    class="btn-hapus"
                    onclick="return confirm('Yakin ingin menghapus enroll kelas ini?')"
                  >
                    Hapus
                  </a>
                </td>
              </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      <?php else: ?>
        <p class="empty-text">Anda belum enroll kelas apa pun.</p>
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