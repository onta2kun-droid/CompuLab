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

$matkul_id = (int)($_GET['matkul_id'] ?? 0);
$nim       = trim($_GET['nim'] ?? '');

if ($matkul_id <= 0 || $nim === '') {
    header("Location: scoreboard.php");
    exit();
}

/* =========================
   VALIDASI MATKUL MILIK DOSEN
========================= */
$stmtMatkul = $conn->prepare("
    SELECT mk.id, mk.nama_matkul, mk.angkatan, mk.kelas
    FROM mata_kuliah mk
    JOIN dosen_matkul dm ON mk.id = dm.matkul_id
    WHERE mk.id = ? AND dm.dosen_id = ?
    LIMIT 1
");
if (!$stmtMatkul) {
    die("Query matkul gagal.");
}
$stmtMatkul->bind_param("ii", $matkul_id, $dosen_id);
$stmtMatkul->execute();
$resultMatkul = $stmtMatkul->get_result();

if ($resultMatkul->num_rows <= 0) {
    header("Location: scoreboard.php");
    exit();
}

$matkul = $resultMatkul->fetch_assoc();
$stmtMatkul->close();

/* =========================
   VALIDASI MAHASISWA ENROLL
========================= */
$stmtMahasiswa = $conn->prepare("
    SELECT a.nim, a.nama, a.email
    FROM akun a
    JOIN mahasiswa_matkul mm ON a.nim = mm.nim
    WHERE a.nim = ? AND mm.matkul_id = ?
    LIMIT 1
");
if (!$stmtMahasiswa) {
    die("Query mahasiswa gagal.");
}
$stmtMahasiswa->bind_param("si", $nim, $matkul_id);
$stmtMahasiswa->execute();
$resultMahasiswa = $stmtMahasiswa->get_result();

if ($resultMahasiswa->num_rows <= 0) {
    header("Location: scoreboard_detail.php?matkul_id=" . $matkul_id);
    exit();
}

$mahasiswa = $resultMahasiswa->fetch_assoc();
$stmtMahasiswa->close();

/* =========================
   AMBIL SEMUA KUIS DI MATKUL
   + NILAI MAHASISWA INI
========================= */
$stmtDetail = $conn->prepare("
    SELECT 
        k.id,
        k.judul,
        k.deskripsi,
        k.durasi,
        k.waktu_mulai,
        k.waktu_selesai,
        hk.nilai,
        hk.tanggal_submit
    FROM kuis k
    LEFT JOIN hasil_kuis hk 
        ON hk.kuis_id = k.id AND hk.nim = ?
    WHERE k.matkul_id = ?
    ORDER BY k.created_at DESC, k.id DESC
");
if (!$stmtDetail) {
    die("Query detail kuis mahasiswa gagal.");
}
$stmtDetail->bind_param("si", $nim, $matkul_id);
$stmtDetail->execute();
$detailQuery = $stmtDetail->get_result();

/* simpan ke array supaya bisa dipakai berkali-kali */
$detailRows = [];
while ($row = $detailQuery->fetch_assoc()) {
    $detailRows[] = $row;
}
$stmtDetail->close();

/* =========================
   RINGKASAN NILAI MAHASISWA
========================= */
$totalKuis       = count($detailRows);
$kuisDikerjakan  = 0;
$totalNilai      = 0;
$nilaiTertinggi  = null;
$nilaiTerendah   = null;

foreach ($detailRows as $row) {
    if ($row['nilai'] !== null) {
        $nilai = (float)$row['nilai'];
        $kuisDikerjakan++;
        $totalNilai += $nilai;

        if ($nilaiTertinggi === null || $nilai > $nilaiTertinggi) {
            $nilaiTertinggi = $nilai;
        }

        if ($nilaiTerendah === null || $nilai < $nilaiTerendah) {
            $nilaiTerendah = $nilai;
        }
    }
}

/* nilai akhir = total nilai / total kuis yang ada */
$nilaiAkhir = $totalKuis > 0 ? round($totalNilai / $totalKuis, 2) : 0;
$progress = $totalKuis > 0 ? round(($kuisDikerjakan / $totalKuis) * 100) : 0;

date_default_timezone_set('Asia/Makassar');
?>
<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Detail Nilai Mahasiswa | COMPULAB</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<script>
  const t = localStorage.getItem('compulab_theme');
  if (t) document.documentElement.setAttribute('data-theme', t);
</script>
<link rel="stylesheet" href="../css/dashboard-dosen.css">
<style>
.stats-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 14px;
  margin-bottom: 24px;
}

.stat-card {
  background: var(--bg-input);
  border: 1px solid var(--border);
  border-radius: 16px;
  padding: 18px 20px;
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.stat-card-lbl {
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  color: var(--text-muted);
}

.stat-card-val {
  font-size: 28px;
  font-weight: 700;
  font-family: 'DM Serif Display', serif;
  color: var(--text-primary);
}

.stat-card-val.gold  { color: var(--gold-dark); }
.stat-card-val.green { color: #16a34a; }
.stat-card-val.red   { color: #dc2626; }

[data-theme="dark"] .stat-card-val.gold  { color: var(--gold-light); }
[data-theme="dark"] .stat-card-val.green { color: #4ade80; }
[data-theme="dark"] .stat-card-val.red   { color: #f87171; }

.banner-box {
  background: linear-gradient(135deg, var(--navy-deep), var(--navy-mid));
  border-radius: 16px;
  padding: 20px 22px;
  margin-bottom: 22px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 16px;
  flex-wrap: wrap;
  position: relative;
  overflow: hidden;
}

.banner-box::before {
  content: '';
  position: absolute;
  top: 0; left: 0; right: 0;
  height: 3px;
  background: linear-gradient(90deg, var(--gold), var(--gold-light));
}

.banner-title {
  font-family: 'DM Serif Display', serif;
  font-size: 20px;
  color: #fff;
}

.banner-sub {
  font-size: 13px;
  color: rgba(255,255,255,0.7);
  margin-top: 4px;
  line-height: 1.6;
}

.progress-wrap { margin-bottom: 8px; }

.progress-label {
  display: flex;
  justify-content: space-between;
  font-size: 12px;
  color: var(--text-muted);
  margin-bottom: 6px;
  font-weight: 600;
}

.progress-bar-bg {
  background: var(--bg-input);
  border-radius: 99px;
  height: 8px;
  overflow: hidden;
  border: 1px solid var(--border);
}

.progress-bar-fill {
  height: 100%;
  border-radius: 99px;
  background: linear-gradient(90deg, var(--gold), var(--gold-light));
}

.quiz-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.quiz-card {
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 16px;
  padding: 18px 20px;
  display: grid;
  grid-template-columns: 1fr auto;
  gap: 16px;
  align-items: center;
}

.quiz-title {
  font-weight: 700;
  font-size: 15px;
  color: var(--text-primary);
  margin-bottom: 4px;
}

.quiz-desc {
  font-size: 13px;
  color: var(--text-muted);
  line-height: 1.6;
  margin-bottom: 10px;
}

.quiz-meta {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.quiz-chip {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  background: var(--bg-input);
  border: 1px solid var(--border);
  border-radius: 20px;
  padding: 4px 10px;
  font-size: 12px;
  color: var(--text-mid);
  font-weight: 500;
}

.quiz-score {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
  justify-content: flex-end;
}

.score-badge {
  min-width: 90px;
  text-align: center;
  padding: 10px 14px;
  border-radius: 14px;
  font-size: 18px;
  font-weight: 700;
  font-family: 'DM Serif Display', serif;
  border: 1px solid var(--border);
  background: var(--bg-input);
  color: var(--text-primary);
}

.status-badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border-radius: 999px;
  padding: 6px 12px;
  font-size: 12px;
  font-weight: 700;
}

.status-done {
  background: rgba(34,197,94,0.10);
  color: #16a34a;
}

.status-notyet {
  background: rgba(220,38,38,0.08);
  color: #dc2626;
}

[data-theme="dark"] .status-done {
  color: #4ade80;
}

[data-theme="dark"] .status-notyet {
  color: #f87171;
}

.empty-state {
  text-align: center;
  padding: 48px 24px;
  color: var(--text-muted);
}

.empty-state .empty-icon {
  font-size: 48px;
  margin-bottom: 12px;
}

@media (max-width: 900px) {
  .stats-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (max-width: 640px) {
  .quiz-card {
    grid-template-columns: 1fr;
  }

  .quiz-score {
    justify-content: flex-start;
  }
}

@media (max-width: 480px) {
  .stats-grid {
    grid-template-columns: repeat(2, 1fr);
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
    <a href="kelola_kuis.php">📋 Kelola Kuis</a>
    <a class="active" href="scoreboard.php">📈 Scoreboard</a>
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
    <a href="kelola_kuis.php">📋 Kelola Kuis</a>
    <a class="active" href="scoreboard.php">📈 Scoreboard</a>
    <a href="profil.php">👨‍🏫 Profil</a>
  </nav>
  <div class="sidebar-bottom"><a href="logout.php">🚪 Logout</a></div>
</div>

<div class="main">

  <div class="topbar">
    <button class="hamburger" id="hamburgerBtn" aria-label="Menu">
      <span></span><span></span><span></span>
    </button>
    <h1>Detail Nilai Mahasiswa 📘</h1>
    <div class="topbar-right">
      <a href="profil.php" class="profile-mini" title="Profil Dosen">👨‍🏫</a>
      <button class="dark-toggle-top" id="darkToggle">🌙</button>
    </div>
  </div>

  <div style="margin-bottom:20px;">
    <a href="scoreboard_detail.php?matkul_id=<?php echo $matkul_id; ?>" class="btn-secondary" style="font-size:13px; padding:8px 16px; display:inline-flex; align-items:center; gap:6px; margin-bottom:16px;">
      ← Kembali ke Ranking Mahasiswa
    </a>

    <div class="banner-box">
      <div>
        <div class="banner-title"><?php echo htmlspecialchars($mahasiswa['nama']); ?></div>
        <div class="banner-sub">
          NIM: <?php echo htmlspecialchars($mahasiswa['nim']); ?><br>
          Email: <?php echo htmlspecialchars($mahasiswa['email']); ?><br>
          Mata Kuliah: <?php echo htmlspecialchars($matkul['nama_matkul']); ?> · Angkatan <?php echo htmlspecialchars($matkul['angkatan']); ?> · Kelas <?php echo htmlspecialchars($matkul['kelas']); ?>
        </div>
      </div>
    </div>
  </div>

    <a href="export_pdf_mahasiswa.php?matkul_id=<?php echo $matkul_id; ?>&nim=<?php echo urlencode($mahasiswa['nim']); ?>"
    class="btn-gold"
    style="font-size:13px; padding:10px 16px; margin-bottom:16px; display:inline-block;">
    ⬇️ Download PDF
    </a>

  <div class="stats-grid">
    <div class="stat-card">
      <div class="stat-card-lbl">Total Kuis</div>
      <div class="stat-card-val"><?php echo $totalKuis; ?></div>
    </div>
    <div class="stat-card">
      <div class="stat-card-lbl">Sudah Dikerjakan</div>
      <div class="stat-card-val"><?php echo $kuisDikerjakan; ?></div>
    </div>
    <div class="stat-card">
      <div class="stat-card-lbl">Total Nilai</div>
      <div class="stat-card-val gold"><?php echo $totalNilai; ?></div>
    </div>
    <div class="stat-card">
      <div class="stat-card-lbl">Nilai Akhir</div>
      <div class="stat-card-val green"><?php echo $nilaiAkhir; ?></div>
    </div>
  </div>

  <div class="table-card" style="margin-bottom:20px; padding:20px 24px;">
    <div class="progress-wrap">
      <div class="progress-label">
        <span>Progress Pengerjaan Kuis</span>
        <span><?php echo $kuisDikerjakan; ?>/<?php echo $totalKuis; ?> (<?php echo $progress; ?>%)</span>
      </div>
      <div class="progress-bar-bg">
        <div class="progress-bar-fill" style="width:<?php echo $progress; ?>%"></div>
      </div>
    </div>
  </div>

  <div class="table-card">
    <h3 style="margin-bottom:20px;">Nilai per Kuis</h3>

    <?php if (count($detailRows) > 0): ?>
      <div class="quiz-list">
        <?php foreach ($detailRows as $row): ?>
          <?php $sudah = $row['nilai'] !== null; ?>
          <div class="quiz-card">
            <div>
              <div class="quiz-title"><?php echo htmlspecialchars($row['judul']); ?></div>

              <?php if (!empty($row['deskripsi'])): ?>
                <div class="quiz-desc">
                  <?php echo nl2br(htmlspecialchars($row['deskripsi'])); ?>
                </div>
              <?php endif; ?>

              <div class="quiz-meta">
                <span class="quiz-chip">⏱️ <?php echo (int)$row['durasi']; ?> menit</span>
                <span class="quiz-chip">🕒 Mulai: <?php echo !empty($row['waktu_mulai']) ? date('d M Y H:i', strtotime($row['waktu_mulai'])) : '-'; ?></span>
                <span class="quiz-chip">🛑 Selesai: <?php echo !empty($row['waktu_selesai']) ? date('d M Y H:i', strtotime($row['waktu_selesai'])) : '-'; ?></span>
                <span class="quiz-chip">📅 Submit: <?php echo !empty($row['tanggal_submit']) ? date('d M Y H:i', strtotime($row['tanggal_submit'])) : '-'; ?></span>
              </div>
            </div>

            <div class="quiz-score">
              <?php if ($sudah): ?>
                <span class="status-badge status-done">Sudah Dikerjakan</span>
                <div class="score-badge"><?php echo htmlspecialchars($row['nilai']); ?></div>
              <?php else: ?>
                <span class="status-badge status-notyet">Belum Dikerjakan</span>
                <div class="score-badge">0</div>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="empty-state">
        <div class="empty-icon">📭</div>
        <p>Belum ada kuis pada mata kuliah ini.</p>
      </div>
    <?php endif; ?>
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