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
$matkul_id  = (int)($_GET['matkul_id'] ?? 0);

if ($matkul_id <= 0) {
    header("Location: scoreboard.php");
    exit();
}

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
   TOTAL KUIS DI MATA KULIAH
========================= */
$totalKuisMatkul = 0;
$stmtTotalKuis = $conn->prepare("
    SELECT COUNT(*) AS total_kuis
    FROM kuis
    WHERE matkul_id = ?
");
if ($stmtTotalKuis) {
    $stmtTotalKuis->bind_param("i", $matkul_id);
    $stmtTotalKuis->execute();
    $resTotalKuis = $stmtTotalKuis->get_result()->fetch_assoc();
    $totalKuisMatkul = (int)($resTotalKuis['total_kuis'] ?? 0);
    $stmtTotalKuis->close();
}

/* =========================
   RANKING MAHASISWA
========================= */
$stmtRanking = $conn->prepare("
    SELECT 
        a.nim,
        a.nama,
        a.email,
        COUNT(DISTINCT hk.id) AS jumlah_kuis_dikerjakan,
        COALESCE(SUM(hk.nilai), 0) AS total_nilai,
        ROUND(
            COALESCE(SUM(hk.nilai), 0) / NULLIF(COUNT(DISTINCT k.id), 0),
            2
        ) AS rata_nilai,
        MAX(hk.nilai) AS nilai_tertinggi,
        MIN(hk.nilai) AS nilai_terendah,
        COUNT(DISTINCT k.id) AS total_kuis_matkul
    FROM mahasiswa_matkul mm
    JOIN akun a ON mm.nim = a.nim
    LEFT JOIN kuis k ON mm.matkul_id = k.matkul_id
    LEFT JOIN hasil_kuis hk ON hk.kuis_id = k.id AND hk.nim = mm.nim
    WHERE mm.matkul_id = ?
    GROUP BY a.nim, a.nama, a.email
    ORDER BY 
        rata_nilai DESC,
        jumlah_kuis_dikerjakan DESC,
        a.nama ASC
");
if (!$stmtRanking) {
    die("Query ranking gagal.");
}
$stmtRanking->bind_param("i", $matkul_id);
$stmtRanking->execute();
$rankingQuery = $stmtRanking->get_result();

/* =========================
   RINGKASAN
========================= */
$totalMahasiswa = 0;
$totalSudahDinilai = 0;
$rataMatkul = null;
$nilaiTertinggi = null;
$nilaiTerendah = null;

$stmtRingkas = $conn->prepare("
    SELECT 
        COUNT(DISTINCT mm.nim) AS total_mahasiswa,
        COUNT(DISTINCT CASE WHEN hk.nilai IS NOT NULL THEN hk.nim END) AS total_dinilai,
        ROUND(
            COALESCE(SUM(hk.nilai), 0) / NULLIF(COUNT(DISTINCT mm.nim) * COUNT(DISTINCT k.id), 0),
            2
        ) AS rata_matkul,
        MAX(hk.nilai) AS tertinggi,
        MIN(hk.nilai) AS terendah
    FROM mahasiswa_matkul mm
    LEFT JOIN kuis k ON mm.matkul_id = k.matkul_id
    LEFT JOIN hasil_kuis hk ON hk.kuis_id = k.id AND hk.nim = mm.nim
    WHERE mm.matkul_id = ?
");
if ($stmtRingkas) {
    $stmtRingkas->bind_param("i", $matkul_id);
    $stmtRingkas->execute();
    $r = $stmtRingkas->get_result()->fetch_assoc();

    $totalMahasiswa    = (int)($r['total_mahasiswa'] ?? 0);
    $totalSudahDinilai = (int)($r['total_dinilai'] ?? 0);
    $rataMatkul        = $r['rata_matkul'];
    $nilaiTertinggi    = $r['tertinggi'];
    $nilaiTerendah     = $r['terendah'];

    $stmtRingkas->close();
}

$pct = $totalMahasiswa > 0 ? round(($totalSudahDinilai / $totalMahasiswa) * 100) : 0;
?>
<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Scoreboard Detail | COMPULAB</title>
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

.matkul-banner {
  background: linear-gradient(135deg, var(--navy-deep), var(--navy-mid));
  border-radius: 16px;
  padding: 18px 22px;
  margin-bottom: 22px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 12px;
  position: relative;
  overflow: hidden;
}
.matkul-banner::before {
  content: '';
  position: absolute;
  top: 0; left: 0; right: 0;
  height: 3px;
  background: linear-gradient(90deg, var(--gold), var(--gold-light));
}
.matkul-banner-name {
  font-family: 'DM Serif Display', serif;
  font-size: 18px;
  color: #fff;
}
.matkul-banner-sub {
  font-size: 13px;
  color: rgba(255,255,255,0.55);
  margin-top: 3px;
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

.rank-badge {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 13px;
  font-weight: 700;
  background: var(--bg-input);
  color: var(--text-muted);
  border: 2px solid var(--border);
}
.rank-badge.gold-rank  { background: #fef3c7; color: #92400e; border-color: #fcd34d; }
.rank-badge.silver-rank{ background: #f1f5f9; color: #475569; border-color: #cbd5e1; }
.rank-badge.bronze-rank{ background: #fef3c7; color: #78350f; border-color: #d97706; }
[data-theme="dark"] .rank-badge.gold-rank   { background: rgba(253,211,77,0.15); color: var(--gold-light); }
[data-theme="dark"] .rank-badge.silver-rank { background: rgba(203,213,225,0.10); color: #94a3b8; }
[data-theme="dark"] .rank-badge.bronze-rank { background: rgba(217,119,6,0.15); color: #fbbf24; }

.student-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.student-card {
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 14px;
  padding: 16px 20px;
  display: grid;
  grid-template-columns: 40px 1fr auto auto;
  align-items: center;
  gap: 16px;
  transition: box-shadow 0.2s;
}
.student-card:hover {
  box-shadow: 0 4px 16px rgba(15,31,61,0.08);
}
.student-info-name {
  font-weight: 600;
  font-size: 14px;
  color: var(--text-primary);
}
.student-info-sub {
  font-size: 12px;
  color: var(--text-muted);
  margin-top: 2px;
}
.student-scores {
  display: flex;
  gap: 20px;
  align-items: center;
}
.score-item {
  display: flex;
  flex-direction: column;
  gap: 2px;
  text-align: center;
}
.score-val {
  font-size: 18px;
  font-weight: 700;
  font-family: 'DM Serif Display', serif;
  color: var(--text-primary);
}
.score-val.gold  { color: var(--gold-dark); }
.score-val.green { color: #16a34a; }
.score-val.red   { color: #dc2626; }
[data-theme="dark"] .score-val.gold  { color: var(--gold-light); }
[data-theme="dark"] .score-val.green { color: #4ade80; }
[data-theme="dark"] .score-val.red   { color: #f87171; }
.score-lbl {
  font-size: 10px;
  color: var(--text-muted);
  text-transform: uppercase;
  letter-spacing: 0.4px;
  font-weight: 600;
}

.status-sudah {
  background: rgba(34,197,94,0.10);
  color: #16a34a;
  border-radius: 20px;
  padding: 3px 10px;
  font-size: 11px;
  font-weight: 700;
}
.status-belum {
  background: rgba(220,38,38,0.08);
  color: #dc2626;
  border-radius: 20px;
  padding: 3px 10px;
  font-size: 11px;
  font-weight: 700;
}
[data-theme="dark"] .status-sudah { color: #4ade80; }
[data-theme="dark"] .status-belum { color: #f87171; }

.detail-btn-wrap {
  display: flex;
  align-items: center;
  justify-content: flex-end;
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

@media (max-width: 768px) {
  .stats-grid { grid-template-columns: repeat(2, 1fr); }
}

@media (max-width: 640px) {
  .student-card {
    grid-template-columns: 36px 1fr;
  }

  .student-scores {
    grid-column: 2 / 3;
    justify-content: flex-start;
    flex-wrap: wrap;
  }

  .detail-btn-wrap {
    grid-column: 2 / 3;
    justify-content: flex-start;
  }
}

@media (max-width: 480px) {
  .stats-grid { grid-template-columns: repeat(2, 1fr); }
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
    <h1>Scoreboard Detail 📊</h1>
    <div class="topbar-right">
      <a href="profil.php" class="profile-mini" title="Profil Dosen">👨‍🏫</a>
      <button class="dark-toggle-top" id="darkToggle">🌙</button>
    </div>
  </div>

  <div style="margin-bottom:20px;">
    <a href="scoreboard.php" class="btn-secondary" style="font-size:13px; padding:8px 16px; display:inline-flex; align-items:center; gap:6px; margin-bottom:16px;">
      ← Kembali ke Scoreboard
    </a>
    <div class="matkul-banner">
      <div>
        <div class="matkul-banner-name"><?php echo htmlspecialchars($matkul['nama_matkul']); ?></div>
        <div class="matkul-banner-sub">Angkatan <?php echo $matkul['angkatan']; ?> &nbsp;·&nbsp; Kelas <?php echo $matkul['kelas']; ?></div>
      </div>
    </div>
  </div>

  <div class="stats-grid">
    <div class="stat-card">
      <div class="stat-card-lbl">Total Mahasiswa</div>
      <div class="stat-card-val"><?php echo $totalMahasiswa; ?></div>
    </div>
    <div class="stat-card">
      <div class="stat-card-lbl">Sudah Nilai</div>
      <div class="stat-card-val"><?php echo $totalSudahDinilai; ?></div>
    </div>
    <div class="stat-card">
      <div class="stat-card-lbl">Rata-rata Akhir</div>
      <div class="stat-card-val gold"><?php echo $rataMatkul !== null ? $rataMatkul : '—'; ?></div>
    </div>
    <div class="stat-card">
      <div class="stat-card-lbl">Tertinggi / Terendah</div>
      <div class="stat-card-val" style="font-size:20px;">
        <span class="score-val green" style="font-size:20px;"><?php echo $nilaiTertinggi ?? '—'; ?></span>
        <span style="color:var(--text-muted); font-size:16px; font-family:'DM Sans',sans-serif;"> / </span>
        <span class="score-val red" style="font-size:20px;"><?php echo $nilaiTerendah ?? '—'; ?></span>
      </div>
    </div>
  </div>

  <div class="table-card" style="margin-bottom:20px; padding:20px 24px;">
    <div class="progress-wrap">
      <div class="progress-label">
        <span>Partisipasi Mahasiswa</span>
        <span><?php echo $totalSudahDinilai; ?>/<?php echo $totalMahasiswa; ?> (<?php echo $pct; ?>%)</span>
      </div>
      <div class="progress-bar-bg">
        <div class="progress-bar-fill" style="width:<?php echo $pct; ?>%"></div>
      </div>
    </div>
  </div>

  <div class="table-card">
    <h3 style="margin-bottom:20px;">Ranking Mahasiswa</h3>

    <?php if ($rankingQuery && $rankingQuery->num_rows > 0): ?>
    <div class="student-list">
      <?php $rank = 1; while ($row = $rankingQuery->fetch_assoc()):
        $hasNilai = $row['jumlah_kuis_dikerjakan'] > 0;
        $rankClass = '';
        $rankLabel = '—';

        if ($hasNilai) {
          $rankLabel = $rank;
          if ($rank === 1) $rankClass = 'gold-rank';
          elseif ($rank === 2) $rankClass = 'silver-rank';
          elseif ($rank === 3) $rankClass = 'bronze-rank';
          $rank++;
        }
      ?>
      <div class="student-card">
        <div class="rank-badge <?php echo $rankClass; ?>"><?php echo $rankLabel; ?></div>

        <div>
          <div class="student-info-name"><?php echo htmlspecialchars($row['nama']); ?></div>
          <div class="student-info-sub">
            <?php echo htmlspecialchars($row['nim']); ?> &nbsp;·&nbsp;
            <?php echo htmlspecialchars($row['email']); ?> &nbsp;·&nbsp;
            <?php echo $row['jumlah_kuis_dikerjakan']; ?>/<?php echo $row['total_kuis_matkul']; ?> kuis dikerjakan &nbsp;·&nbsp;
            <?php if ($hasNilai): ?>
              <span class="status-sudah">✓ Sudah Dinilai</span>
            <?php else: ?>
              <span class="status-belum">Belum Ada Nilai</span>
            <?php endif; ?>
          </div>
        </div>

        <div class="student-scores">
          <div class="score-item">
            <div class="score-val gold"><?php echo $hasNilai ? $row['rata_nilai'] : '0'; ?></div>
            <div class="score-lbl">Nilai Akhir</div>
          </div>
          <div class="score-item">
            <div class="score-val green"><?php echo $row['nilai_tertinggi'] ?? '—'; ?></div>
            <div class="score-lbl">Tertinggi</div>
          </div>
          <div class="score-item">
            <div class="score-val red"><?php echo $row['nilai_terendah'] ?? '—'; ?></div>
            <div class="score-lbl">Terendah</div>
          </div>
        </div>

        <div class="detail-btn-wrap">
          <a href="scoreboard_detail_mahasiswa.php?matkul_id=<?php echo $matkul_id; ?>&nim=<?php echo urlencode($row['nim']); ?>"
             class="btn-secondary"
             style="font-size:12px; padding:8px 14px; text-decoration:none; white-space:nowrap;">
            Detail
          </a>
        </div>
      </div>
      <?php endwhile; ?>
    </div>

    <?php else: ?>
    <div class="empty-state">
      <div class="empty-icon">📭</div>
      <p>Belum ada data mahasiswa untuk mata kuliah ini.</p>
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