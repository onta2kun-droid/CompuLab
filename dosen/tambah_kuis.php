<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once __DIR__ . '/../../sambung/forsafety.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'dosen') {
    header("Location: ../login/dosen.php");
    exit();
}

$dosen_id   = $_SESSION['user_id'];
$nama_dosen = $_SESSION['user_nama'];
$success = "";
$error   = "";

$stmtMatkul = $conn->prepare("
    SELECT mk.id, mk.nama_matkul, mk.angkatan, mk.kelas
    FROM mata_kuliah mk
    JOIN dosen_matkul dm ON mk.id = dm.matkul_id
    WHERE dm.dosen_id = ?
    ORDER BY mk.nama_matkul ASC, mk.kelas ASC
");
$matkulQuery = null;
if ($stmtMatkul) {
    $stmtMatkul->bind_param("i", $dosen_id);
    $stmtMatkul->execute();
    $matkulQuery = $stmtMatkul->get_result();
} else {
    $error = "Query mata kuliah gagal disiapkan.";
}

if (isset($_POST['simpan'])) {
    $matkul_id     = (int)($_POST['matkul_id'] ?? 0);
    $judul         = trim($_POST['judul'] ?? '');
    $deskripsi     = trim($_POST['deskripsi'] ?? '');
    $waktu_mulai   = trim($_POST['waktu_mulai'] ?? '');
    $waktu_selesai = trim($_POST['waktu_selesai'] ?? '');
    $durasi        = (int)($_POST['durasi'] ?? 0);

    if ($matkul_id <= 0 || $judul === "" || $waktu_mulai === "" || $waktu_selesai === "" || $durasi <= 0) {
        $error = "Semua field wajib diisi dengan benar!";
    } elseif (strtotime($waktu_selesai) <= strtotime($waktu_mulai)) {
        $error = "Waktu selesai harus lebih besar dari waktu mulai!";
    } else {
        $stmtCek = $conn->prepare("SELECT 1 FROM dosen_matkul WHERE dosen_id = ? AND matkul_id = ? LIMIT 1");
        if ($stmtCek) {
            $stmtCek->bind_param("ii", $dosen_id, $matkul_id);
            $stmtCek->execute();
            if ($stmtCek->get_result()->num_rows <= 0) {
                $error = "Mata kuliah tidak valid atau bukan milik Anda!";
            } else {
                $stmtInsert = $conn->prepare("
                    INSERT INTO kuis (
                        matkul_id, dosen_id, judul, deskripsi, waktu_mulai, waktu_selesai, durasi, is_active
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, 0)
                ");
                if ($stmtInsert) {
                    $stmtInsert->bind_param("iissssi", $matkul_id, $dosen_id, $judul, $deskripsi, $waktu_mulai, $waktu_selesai, $durasi);
                    if ($stmtInsert->execute()) {
                        header("Location: tambah_soal_kuis.php?kuis_id=" . $conn->insert_id);
                        exit();
                    } else {
                        $error = "Gagal menyimpan kuis: " . $stmtInsert->error;
                    }
                    $stmtInsert->close();
                }
            }
            $stmtCek->close();
        }
    }

    $stmtMatkul = $conn->prepare("SELECT mk.id, mk.nama_matkul, mk.angkatan, mk.kelas FROM mata_kuliah mk JOIN dosen_matkul dm ON mk.id = dm.matkul_id WHERE dm.dosen_id = ? ORDER BY mk.nama_matkul ASC, mk.kelas ASC");
    $stmtMatkul->bind_param("i", $dosen_id);
    $stmtMatkul->execute();
    $matkulQuery = $stmtMatkul->get_result();
}
?>
<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Tambah Kuis | COMPULAB</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<script>
  const t = localStorage.getItem('compulab_theme');
  if (t) document.documentElement.setAttribute('data-theme', t);
</script>
<link rel="stylesheet" href="../css/dashboard-dosen.css">
<style>
.form-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0 20px;
}
.form-grid .full { grid-column: 1 / -1; }

.form-actions {
  display: flex;
  gap: 12px;
  align-items: center;
  margin-top: 8px;
  grid-column: 1 / -1;
}

.info-hint {
  font-size: 12px;
  color: var(--text-muted);
  margin-top: -10px;
  margin-bottom: 6px;
}

/* Steps indicator */
.steps {
  display: flex;
  align-items: center;
  gap: 0;
  margin-bottom: 32px;
}
.step {
  display: flex;
  align-items: center;
  gap: 10px;
  flex: 1;
}
.step-num {
  width: 32px; height: 32px;
  border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  font-size: 13px; font-weight: 700;
  background: var(--bg-input);
  color: var(--text-muted);
  border: 2px solid var(--border);
  flex-shrink: 0;
  transition: all 0.3s;
}
.step.active .step-num {
  background: var(--gold);
  color: #fff;
  border-color: var(--gold);
  box-shadow: 0 4px 12px rgba(245,166,35,0.35);
}
.step.done .step-num {
  background: var(--navy-fresh);
  color: #fff;
  border-color: var(--navy-fresh);
}
.step-label { font-size: 13px; font-weight: 600; color: var(--text-muted); }
.step.active .step-label { color: var(--text-primary); }
.step-line {
  flex: 1;
  height: 2px;
  background: var(--border);
  margin: 0 8px;
  border-radius: 2px;
}

@media (max-width: 640px) {
  .form-grid { grid-template-columns: 1fr; }
  .form-grid .full { grid-column: 1; }
  .form-actions { flex-direction: column; }
  .form-actions .btn-gold,
  .form-actions .btn-secondary { width: 100%; justify-content: center; }
  .steps { gap: 0; }
  .step-label { display: none; }
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
    <a class="active" href="tambah_kuis.php">📝 Tambah Kuis</a>
    <a href="kelola_kuis.php">📋 Kelola Kuis</a>
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
    <a class="active" href="tambah_kuis.php">📝 Tambah Kuis</a>
    <a href="kelola_kuis.php">📋 Kelola Kuis</a>
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
    <h1>Tambah Kuis 📝</h1>
    <div class="topbar-right">
      <a href="profil.php" class="profile-mini" title="Profil Dosen">👨‍🏫</a>
      <button class="dark-toggle-top" id="darkToggle">🌙</button>
    </div>
  </div>

  <?php if ($error): ?>
    <div class="alert-error" style="margin-bottom:20px;">⚠️ <?php echo htmlspecialchars($error); ?></div>
  <?php endif; ?>

  <div class="table-card">
    <h3>Informasi Kuis</h3>

    <!-- Steps -->
    <div class="steps">
      <div class="step active">
        <div class="step-num">1</div>
        <div class="step-label">Info Kuis</div>
      </div>
      <div class="step-line"></div>
      <div class="step">
        <div class="step-num">2</div>
        <div class="step-label">Tambah Soal</div>
      </div>
      <div class="step-line"></div>
      <div class="step">
        <div class="step-num">3</div>
        <div class="step-label">Selesai</div>
      </div>
    </div>

    <form method="POST">
      <div class="form-grid">

        <div class="form-group full">
          <label>Mata Kuliah / Kelas</label>
          <select name="matkul_id" required>
            <option value="">-- Pilih Mata Kuliah --</option>
            <?php if ($matkulQuery && $matkulQuery->num_rows > 0): ?>
              <?php while ($matkul = $matkulQuery->fetch_assoc()): ?>
                <option value="<?php echo $matkul['id']; ?>"
                  <?php echo (isset($_POST['matkul_id']) && $_POST['matkul_id'] == $matkul['id']) ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars($matkul['nama_matkul'] . " — Angkatan " . $matkul['angkatan'] . " Kelas " . $matkul['kelas']); ?>
                </option>
              <?php endwhile; ?>
            <?php endif; ?>
          </select>
        </div>

        <div class="form-group full">
          <label>Judul Kuis</label>
          <input type="text" name="judul" required placeholder="Contoh: Kuis Pertemuan 1 — Pengantar"
            value="<?php echo isset($_POST['judul']) ? htmlspecialchars($_POST['judul']) : ''; ?>">
        </div>

        <div class="form-group full">
          <label>Deskripsi <span style="color:var(--text-muted); font-weight:400;">(opsional)</span></label>
          <textarea name="deskripsi" placeholder="Masukkan petunjuk atau deskripsi kuis..." style="min-height:100px; resize:vertical;"><?php echo isset($_POST['deskripsi']) ? htmlspecialchars($_POST['deskripsi']) : ''; ?></textarea>
        </div>

        <div class="form-group">
          <label>Waktu Mulai</label>
          <input type="datetime-local" name="waktu_mulai" required
            value="<?php echo isset($_POST['waktu_mulai']) ? htmlspecialchars($_POST['waktu_mulai']) : ''; ?>">
        </div>

        <div class="form-group">
          <label>Waktu Selesai</label>
          <input type="datetime-local" name="waktu_selesai" required
            value="<?php echo isset($_POST['waktu_selesai']) ? htmlspecialchars($_POST['waktu_selesai']) : ''; ?>">
        </div>

        <div class="form-group">
          <label>Durasi Kuis (menit)</label>
          <input type="number" name="durasi" min="1" required placeholder="Contoh: 30"
            value="<?php echo isset($_POST['durasi']) ? htmlspecialchars($_POST['durasi']) : ''; ?>">
          <div class="info-hint">Waktu pengerjaan per mahasiswa</div>
        </div>

        <div class="form-actions">
          <button type="submit" name="simpan" class="btn-gold">💾 Simpan & Tambah Soal →</button>
          <a href="kelola_kuis.php" class="btn-secondary">Lihat Daftar Kuis</a>
        </div>

      </div>
    </form>
  </div>

</div>

<script src="../js/darkmode.js"></script>
<script>
  const btn = document.getElementById('hamburgerBtn');
  const drawer = document.getElementById('mobileDrawer');
  const overlay = document.getElementById('mobileOverlay');
  btn.addEventListener('click', () => { btn.classList.toggle('open'); drawer.classList.toggle('open'); overlay.classList.toggle('show'); });
  overlay.addEventListener('click', () => { btn.classList.remove('open'); drawer.classList.remove('open'); overlay.classList.remove('show'); });
</script>
</body>
</html>