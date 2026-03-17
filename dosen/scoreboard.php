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

$stmtScoreboard = $conn->prepare("
    SELECT 
        mk.id AS matkul_id,
        mk.nama_matkul,
        mk.angkatan,
        mk.kelas,
        COUNT(DISTINCT mm.nim) AS total_mahasiswa_enroll,
        COUNT(DISTINCT CASE WHEN hk.nilai IS NOT NULL THEN hk.nim END) AS mahasiswa_sudah_dinilai,
        COUNT(DISTINCT k.id) AS total_kuis_matkul,
        ROUND(
            COALESCE(SUM(hk.nilai), 0) / NULLIF(COUNT(DISTINCT mm.nim) * COUNT(DISTINCT k.id), 0),
            2
        ) AS rata_nilai_matkul,
        MAX(hk.nilai) AS nilai_tertinggi,
        MIN(hk.nilai) AS nilai_terendah
    FROM dosen_matkul dm
    JOIN mata_kuliah mk ON dm.matkul_id = mk.id
    LEFT JOIN mahasiswa_matkul mm ON mk.id = mm.matkul_id
    LEFT JOIN kuis k ON mk.id = k.matkul_id
    LEFT JOIN hasil_kuis hk ON k.id = hk.kuis_id AND hk.nim = mm.nim
    WHERE dm.dosen_id = ?
    GROUP BY mk.id, mk.nama_matkul, mk.angkatan, mk.kelas
    ORDER BY mk.nama_matkul ASC, mk.kelas ASC
");

$scoreboardQuery = null;
if ($stmtScoreboard) {
    $stmtScoreboard->bind_param("i", $dosen_id);
    $stmtScoreboard->execute();
    $scoreboardQuery = $stmtScoreboard->get_result();
}
?>
<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Scoreboard | COMPULAB</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<script>
  const t = localStorage.getItem('compulab_theme');
  if (t) document.documentElement.setAttribute('data-theme', t);
</script>
<link rel="stylesheet" href="../css/dashboard-dosen.css">
<style>
/* Scoreboard cards */
.sb-list { display: flex; flex-direction: column; gap: 16px; }

.sb-card {
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 18px;
  padding: 22px 24px;
  display: grid;
  grid-template-columns: 1fr auto;
  gap: 16px;
  align-items: center;
  transition: box-shadow 0.2s, border-color 0.2s;
  position: relative;
  overflow: hidden;
}
.sb-card:hover { box-shadow: 0 6px 24px rgba(15,31,61,0.09); border-color: var(--navy-light); }
.sb-card::before {
  content: '';
  position: absolute;
  left: 0; top: 0; bottom: 0;
  width: 4px;
  background: linear-gradient(180deg, var(--gold), var(--gold-light));
  border-radius: 4px 0 0 4px;
}

.sb-matkul-name {
  font-family: 'DM Serif Display', serif;
  font-size: 17px;
  color: var(--text-primary);
  margin-bottom: 6px;
}

/* Progress bar */
.progress-wrap { margin: 14px 0 6px; }
.progress-label {
  display: flex; justify-content: space-between;
  font-size: 12px; color: var(--text-muted); margin-bottom: 6px; font-weight: 600;
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
  transition: width 0.8s ease;
  min-width: 4px;
}

/* Stats row */
.sb-stats {
  display: flex;
  gap: 24px;
  flex-wrap: wrap;
  margin-top: 14px;
}
.sb-stat { display: flex; flex-direction: column; gap: 2px; }
.sb-stat-val {
  font-size: 22px; font-weight: 700;
  font-family: 'DM Serif Display', serif;
  color: var(--text-primary);
}
.sb-stat-val.gold { color: var(--gold-dark); }
.sb-stat-val.green { color: #16a34a; }
.sb-stat-val.red { color: #dc2626; }
[data-theme="dark"] .sb-stat-val.gold { color: var(--gold-light); }
[data-theme="dark"] .sb-stat-val.green { color: #4ade80; }
[data-theme="dark"] .sb-stat-val.red { color: #f87171; }
.sb-stat-lbl { font-size: 11px; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600; }

/* Divider between stat groups */
.sb-stat-sep { width: 1px; background: var(--border); align-self: stretch; margin: 2px 0; }

/* Empty */
.empty-state { text-align: center; padding: 56px 24px; color: var(--text-muted); }
.empty-state .empty-icon { font-size: 48px; margin-bottom: 12px; }

@media (max-width: 640px) {
  .sb-card { grid-template-columns: 1fr; }
  .sb-stats { gap: 16px; }
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
    <h1>Scoreboard 📈</h1>
    <div class="topbar-right">
      <a href="profil.php" class="profile-mini" title="Profil Dosen">👨‍🏫</a>
      <button class="dark-toggle-top" id="darkToggle">🌙</button>
    </div>
  </div>

  <div class="table-card">
    <div style="margin-bottom:24px;">
      <h3>Ringkasan per Mata Kuliah</h3>
      <p style="font-size:13px; color:var(--text-muted); margin-top:4px;">Nilai mahasiswa pada setiap mata kuliah yang Anda ajar.</p>
    </div>

    <?php if ($scoreboardQuery && $scoreboardQuery->num_rows > 0): ?>
    <div class="sb-list">
      <?php while ($row = $scoreboardQuery->fetch_assoc()):
        $total      = (int)$row['total_mahasiswa_enroll'];
        $sudah      = (int)$row['mahasiswa_sudah_dinilai'];
        $pct        = $total > 0 ? round(($sudah / $total) * 100) : 0;
        $rata       = $row['rata_nilai_matkul'];
        $tertinggi  = $row['nilai_tertinggi'];
        $terendah   = $row['nilai_terendah'];
        $totalKuis  = (int)$row['total_kuis_matkul'];
      ?>
      <div class="sb-card">
        <div>
          <div class="sb-matkul-name"><?php echo htmlspecialchars($row['nama_matkul']); ?></div>
          <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
            <span class="badge badge-navy">Angkatan <?php echo $row['angkatan']; ?> Kelas <?php echo $row['kelas']; ?></span>
            <span class="badge badge-soft"><?php echo $totalKuis; ?> kuis</span>
          </div>

          <div class="progress-wrap">
            <div class="progress-label">
              <span>Partisipasi Mahasiswa</span>
              <span><?php echo $sudah; ?>/<?php echo $total; ?> (<?php echo $pct; ?>%)</span>
            </div>
            <div class="progress-bar-bg">
              <div class="progress-bar-fill" style="width:<?php echo $pct; ?>%"></div>
            </div>
          </div>

          <div class="sb-stats">
            <div class="sb-stat">
              <div class="sb-stat-val"><?php echo $total; ?></div>
              <div class="sb-stat-lbl">Total Mahasiswa</div>
            </div>
            <div class="sb-stat-sep"></div>
            <div class="sb-stat">
              <div class="sb-stat-val gold"><?php echo $rata !== null ? $rata : '—'; ?></div>
              <div class="sb-stat-lbl">Rata-rata Akhir</div>
            </div>
            <div class="sb-stat">
              <div class="sb-stat-val green"><?php echo $tertinggi !== null ? $tertinggi : '—'; ?></div>
              <div class="sb-stat-lbl">Tertinggi</div>
            </div>
            <div class="sb-stat">
              <div class="sb-stat-val red"><?php echo $terendah !== null ? $terendah : '—'; ?></div>
              <div class="sb-stat-lbl">Terendah</div>
            </div>
          </div>
        </div>

        <div style="display:flex; flex-direction:column; gap:8px; align-items:flex-end;">
          <a href="scoreboard_detail.php?matkul_id=<?php echo $row['matkul_id']; ?>" class="btn-gold" style="font-size:12px; padding:10px 16px; white-space:nowrap;">
            📊 Lihat Detail
          </a>
        </div>
      </div>
      <?php endwhile; ?>
    </div>

    <?php else: ?>
    <div class="empty-state">
      <div class="empty-icon">📭</div>
      <p>Belum ada data scoreboard.</p>
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