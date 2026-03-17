<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
include "../idk/forsafety.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'dosen') {
    header("Location: ../login/dosen.php");
    exit();
}

$dosen_id   = $_SESSION['user_id'];
$nama_dosen = $_SESSION['user_nama'];
$success = "";
$error   = "";

$kuis_id = (int)($_GET['kuis_id'] ?? 0);
if ($kuis_id <= 0) { header("Location: kelola_kuis.php"); exit(); }

$stmtKuis = $conn->prepare("SELECT k.id, k.judul, k.deskripsi, k.durasi, k.waktu_mulai, k.waktu_selesai, mk.nama_matkul, mk.angkatan, mk.kelas FROM kuis k JOIN mata_kuliah mk ON k.matkul_id = mk.id WHERE k.id = ? AND k.dosen_id = ? LIMIT 1");
if (!$stmtKuis) { die("Query kuis gagal."); }
$stmtKuis->bind_param("ii", $kuis_id, $dosen_id);
$stmtKuis->execute();
$resultKuis = $stmtKuis->get_result();
if ($resultKuis->num_rows <= 0) { header("Location: kelola_kuis.php"); exit(); }
$kuis = $resultKuis->fetch_assoc();
$stmtKuis->close();

/* ── HAPUS SOAL ── */
if (isset($_GET['hapus'])) {
    $soal_id = (int)$_GET['hapus'];
    $stmtH = $conn->prepare("DELETE sq FROM soal_kuis sq JOIN kuis k ON sq.kuis_id = k.id WHERE sq.id = ? AND sq.kuis_id = ? AND k.dosen_id = ?");
    if ($stmtH) {
        $stmtH->bind_param("iii", $soal_id, $kuis_id, $dosen_id);
        if ($stmtH->execute()) { header("Location: tambah_soal_kuis.php?kuis_id=$kuis_id&hapus_sukses=1"); exit(); }
        else { $error = "Gagal menghapus soal."; }
        $stmtH->close();
    }
}
if (isset($_GET['hapus_sukses'])) $success = "Soal berhasil dihapus!";

/* ── TAMBAH SOAL ── */
if (isset($_POST['simpan_soal'])) {
    $pertanyaan    = trim($_POST['pertanyaan'] ?? '');
    $opsi_a        = trim($_POST['opsi_a'] ?? '');
    $opsi_b        = trim($_POST['opsi_b'] ?? '');
    $opsi_c        = trim($_POST['opsi_c'] ?? '');
    $opsi_d        = trim($_POST['opsi_d'] ?? '');
    $jawaban_benar = trim($_POST['jawaban_benar'] ?? '');

    if ($pertanyaan === '' || $opsi_a === '' || $opsi_b === '' || $opsi_c === '' || $opsi_d === '' || !in_array($jawaban_benar, ['A','B','C','D'])) {
        $error = "Semua field soal wajib diisi dengan benar!";
    } else {
        $stmtI = $conn->prepare("INSERT INTO soal_kuis (kuis_id, pertanyaan, opsi_a, opsi_b, opsi_c, opsi_d, jawaban_benar) VALUES (?, ?, ?, ?, ?, ?, ?)");
        if ($stmtI) {
            $stmtI->bind_param("issssss", $kuis_id, $pertanyaan, $opsi_a, $opsi_b, $opsi_c, $opsi_d, $jawaban_benar);
            if ($stmtI->execute()) { header("Location: tambah_soal_kuis.php?kuis_id=$kuis_id&sukses=1"); exit(); }
            else { $error = "Gagal menyimpan soal: " . $stmtI->error; }
            $stmtI->close();
        }
    }
}
if (isset($_GET['sukses'])) $success = "Soal berhasil ditambahkan!";

/* ── DAFTAR SOAL ── */
$soalQuery = null;
$stmtS = $conn->prepare("SELECT id, pertanyaan, opsi_a, opsi_b, opsi_c, opsi_d, jawaban_benar FROM soal_kuis WHERE kuis_id = ? ORDER BY id ASC");
if ($stmtS) { $stmtS->bind_param("i", $kuis_id); $stmtS->execute(); $soalQuery = $stmtS->get_result(); }

$totalSoal = $soalQuery ? $soalQuery->num_rows : 0;
?>
<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Tambah Soal Kuis | COMPULAB</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<script>
  const t = localStorage.getItem('compulab_theme');
  if (t) document.documentElement.setAttribute('data-theme', t);
</script>
<link rel="stylesheet" href="../css/dashboard-dosen.css">
<style>
/* Kuis info banner */
.kuis-banner {
  background: linear-gradient(135deg, var(--navy-deep), var(--navy-mid));
  border-radius: 16px;
  padding: 20px 24px;
  margin-bottom: 28px;
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px 24px;
  position: relative;
  overflow: hidden;
}
.kuis-banner::before {
  content: '';
  position: absolute;
  top: 0; left: 0; right: 0;
  height: 3px;
  background: linear-gradient(90deg, var(--gold), var(--gold-light));
}
.kuis-banner-item { display: flex; flex-direction: column; gap: 2px; }
.kuis-banner-label { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.6px; color: rgba(255,255,255,0.45); }
.kuis-banner-value { font-size: 14px; font-weight: 600; color: #fff; }
.kuis-banner-title { grid-column: 1 / -1; }
.kuis-banner-title .kuis-banner-value { font-family: 'DM Serif Display', serif; font-size: 18px; }

/* Steps */
.steps { display: flex; align-items: center; margin-bottom: 28px; }
.step { display: flex; align-items: center; gap: 8px; flex: 1; }
.step-num {
  width: 30px; height: 30px; border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  font-size: 12px; font-weight: 700;
  background: var(--bg-input); color: var(--text-muted);
  border: 2px solid var(--border); flex-shrink: 0;
}
.step.done .step-num { background: var(--navy-fresh); color: #fff; border-color: var(--navy-fresh); }
.step.active .step-num { background: var(--gold); color: #fff; border-color: var(--gold); box-shadow: 0 4px 12px rgba(245,166,35,0.35); }
.step-label { font-size: 12px; font-weight: 600; color: var(--text-muted); }
.step.done .step-label, .step.active .step-label { color: var(--text-primary); }
.step-line { flex: 1; height: 2px; background: var(--border); margin: 0 6px; border-radius: 2px; }
.step.done + .step-line { background: var(--navy-fresh); }

/* Form soal grid */
.soal-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0 20px;
}
.soal-grid .full { grid-column: 1 / -1; }

/* Opsi pills */
.opsi-group { display: flex; flex-direction: column; gap: 10px; margin-bottom: 18px; }
.opsi-row { display: flex; align-items: center; gap: 10px; }
.opsi-label {
  width: 32px; height: 32px; border-radius: 50%;
  background: var(--navy-deep); color: #fff;
  display: flex; align-items: center; justify-content: center;
  font-size: 13px; font-weight: 700; flex-shrink: 0;
}
.opsi-row input { flex: 1; }

/* Soal card list */
.soal-list { display: flex; flex-direction: column; gap: 14px; margin-top: 20px; }
.soal-card {
  background: var(--bg-input);
  border: 1px solid var(--border);
  border-radius: 16px;
  padding: 20px;
  position: relative;
}
.soal-card-header {
  display: flex; justify-content: space-between; align-items: flex-start;
  margin-bottom: 14px; gap: 12px;
}
.soal-num {
  width: 28px; height: 28px; border-radius: 50%;
  background: var(--gold); color: #fff;
  display: flex; align-items: center; justify-content: center;
  font-size: 12px; font-weight: 700; flex-shrink: 0;
}
.soal-pertanyaan { font-weight: 600; font-size: 14px; color: var(--text-primary); flex: 1; line-height: 1.5; }
.soal-opsi-grid {
  display: grid; grid-template-columns: 1fr 1fr; gap: 6px 12px;
  margin-top: 10px;
}
.soal-opsi-item {
  display: flex; align-items: center; gap: 8px;
  font-size: 13px; color: var(--text-mid);
  padding: 8px 12px; border-radius: 10px;
  background: var(--bg-card); border: 1px solid var(--border);
}
.soal-opsi-item.correct {
  background: rgba(34,197,94,0.08);
  border-color: rgba(34,197,94,0.25);
  color: #16a34a;
  font-weight: 600;
}
[data-theme="dark"] .soal-opsi-item.correct { color: #4ade80; }
.soal-opsi-badge {
  width: 22px; height: 22px; border-radius: 50%;
  background: var(--bg-input); border: 1px solid var(--border);
  display: flex; align-items: center; justify-content: center;
  font-size: 11px; font-weight: 700; flex-shrink: 0;
  color: var(--text-muted);
}
.soal-opsi-item.correct .soal-opsi-badge {
  background: #16a34a; color: #fff; border-color: #16a34a;
}
[data-theme="dark"] .soal-opsi-item.correct .soal-opsi-badge { background: #4ade80; color: #052e16; }

/* Divider */
.section-divider {
  border: none; border-top: 1px solid var(--border);
  margin: 28px 0;
}

/* Counter badge */
.soal-counter {
  display: inline-flex; align-items: center; gap: 6px;
  background: rgba(245,166,35,0.10);
  border: 1px solid rgba(245,166,35,0.25);
  color: var(--gold-dark);
  border-radius: 20px; padding: 4px 12px;
  font-size: 13px; font-weight: 700;
}
[data-theme="dark"] .soal-counter { color: var(--gold-light); }

.form-actions { display: flex; gap: 12px; align-items: center; margin-top: 8px; grid-column: 1 / -1; }

@media (max-width: 640px) {
  .kuis-banner { grid-template-columns: 1fr; }
  .kuis-banner-title { grid-column: 1; }
  .steps .step-label { display: none; }
  .soal-grid { grid-template-columns: 1fr; }
  .soal-grid .full { grid-column: 1; }
  .soal-opsi-grid { grid-template-columns: 1fr; }
  .form-actions { flex-direction: column; }
  .form-actions button, .form-actions a { width: 100%; justify-content: center; }
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
    <h1>Tambah Soal 📝</h1>
    <div class="topbar-right">
      <a href="profil.php" class="profile-mini" title="Profil Dosen">👨‍🏫</a>
      <button class="dark-toggle-top" id="darkToggle">🌙</button>
    </div>
  </div>

  <!-- Steps -->
  <div class="steps">
    <div class="step done">
      <div class="step-num">✓</div>
      <div class="step-label">Info Kuis</div>
    </div>
    <div class="step-line"></div>
    <div class="step active">
      <div class="step-num">2</div>
      <div class="step-label">Tambah Soal</div>
    </div>
    <div class="step-line"></div>
    <div class="step">
      <div class="step-num">3</div>
      <div class="step-label">Selesai</div>
    </div>
  </div>

  <!-- Kuis Info Banner -->
  <div class="kuis-banner">
    <div class="kuis-banner-item kuis-banner-title">
      <div class="kuis-banner-label">Judul Kuis</div>
      <div class="kuis-banner-value"><?php echo htmlspecialchars($kuis['judul']); ?></div>
    </div>
    <div class="kuis-banner-item">
      <div class="kuis-banner-label">Mata Kuliah</div>
      <div class="kuis-banner-value"><?php echo htmlspecialchars($kuis['nama_matkul'] . " — " . $kuis['angkatan'] . " Kelas " . $kuis['kelas']); ?></div>
    </div>
    <div class="kuis-banner-item">
      <div class="kuis-banner-label">Durasi</div>
      <div class="kuis-banner-value"><?php echo $kuis['durasi']; ?> menit</div>
    </div>
    <div class="kuis-banner-item">
      <div class="kuis-banner-label">Waktu Mulai</div>
      <div class="kuis-banner-value"><?php echo date('d M Y, H:i', strtotime($kuis['waktu_mulai'])); ?></div>
    </div>
    <div class="kuis-banner-item">
      <div class="kuis-banner-label">Waktu Selesai</div>
      <div class="kuis-banner-value"><?php echo date('d M Y, H:i', strtotime($kuis['waktu_selesai'])); ?></div>
    </div>
  </div>

  <?php if ($success): ?>
    <div class="alert-success" style="margin-bottom:20px;">✅ <?php echo htmlspecialchars($success); ?></div>
  <?php endif; ?>
  <?php if ($error): ?>
    <div class="alert-error" style="margin-bottom:20px;">⚠️ <?php echo htmlspecialchars($error); ?></div>
  <?php endif; ?>

  <!-- Form Tambah Soal -->
  <div class="table-card">
    <h3>Tambah Soal Baru</h3>

    <form method="POST">
      <div class="soal-grid">

        <div class="form-group full">
          <label>Pertanyaan</label>
          <textarea name="pertanyaan" required placeholder="Tulis pertanyaan di sini..." style="min-height:100px; resize:vertical;"><?php echo isset($_POST['pertanyaan']) ? htmlspecialchars($_POST['pertanyaan']) : ''; ?></textarea>
        </div>

        <div class="form-group full">
          <label>Pilihan Jawaban</label>
          <div class="opsi-group">
            <?php foreach (['a','b','c','d'] as $opt): ?>
            <div class="opsi-row">
              <div class="opsi-label"><?php echo strtoupper($opt); ?></div>
              <input type="text" name="opsi_<?php echo $opt; ?>" required placeholder="Opsi <?php echo strtoupper($opt); ?>"
                value="<?php echo isset($_POST['opsi_'.$opt]) ? htmlspecialchars($_POST['opsi_'.$opt]) : ''; ?>">
            </div>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="form-group full">
          <label>Jawaban Benar</label>
          <select name="jawaban_benar" required>
            <option value="">-- Pilih Jawaban Benar --</option>
            <?php foreach (['A','B','C','D'] as $opt): ?>
              <option value="<?php echo $opt; ?>" <?php echo (isset($_POST['jawaban_benar']) && $_POST['jawaban_benar'] == $opt) ? 'selected' : ''; ?>>
                Opsi <?php echo $opt; ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-actions">
          <button type="submit" name="simpan_soal" class="btn-gold">+ Simpan Soal</button>
          <a href="kelola_kuis.php" class="btn-secondary">Selesai & Kelola Kuis</a>
        </div>

      </div>
    </form>
  </div>

  <!-- Daftar Soal -->
  <div class="table-card" style="margin-top:24px;">
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom:20px;">
      <h3 style="margin-bottom:0;">Daftar Soal</h3>
      <span class="soal-counter">📋 <?php echo $totalSoal; ?> Soal</span>
    </div>

    <?php if ($soalQuery && $totalSoal > 0):
      $soalQuery->data_seek(0);
      $no = 1;
      echo '<div class="soal-list">';
      while ($soal = $soalQuery->fetch_assoc()):
        $jawaban = $soal['jawaban_benar'];
    ?>
      <div class="soal-card">
        <div class="soal-card-header">
          <div class="soal-num"><?php echo $no++; ?></div>
          <div class="soal-pertanyaan"><?php echo htmlspecialchars($soal['pertanyaan']); ?></div>
          <a href="tambah_soal_kuis.php?kuis_id=<?php echo $kuis_id; ?>&hapus=<?php echo $soal['id']; ?>"
            class="btn-danger" style="font-size:12px; padding:7px 12px; white-space:nowrap;"
            onclick="return confirm('Yakin hapus soal ini?')">🗑️ Hapus</a>
        </div>
        <div class="soal-opsi-grid">
          <?php foreach (['A','B','C','D'] as $opt):
            $key = 'opsi_' . strtolower($opt);
            $isCorrect = ($jawaban === $opt);
          ?>
          <div class="soal-opsi-item <?php echo $isCorrect ? 'correct' : ''; ?>">
            <div class="soal-opsi-badge"><?php echo $opt; ?></div>
            <?php echo htmlspecialchars($soal[$key]); ?>
            <?php if ($isCorrect): ?> ✓<?php endif; ?>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endwhile;
    echo '</div>';
    else: ?>
      <div style="text-align:center; padding:40px; color:var(--text-muted);">
        <div style="font-size:40px; margin-bottom:10px;">📭</div>
        <p>Belum ada soal. Tambahkan soal pertama di atas.</p>
      </div>
    <?php endif; ?>
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