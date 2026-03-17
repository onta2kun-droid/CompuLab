<?php
session_start();
include "../idk/forsafety.php";

if (!isset($_SESSION['email'])) {
    header("Location: ../login/mahasiswa.php");
    exit;
}

$email = $_SESSION['email'];

$stmtUser = $conn->prepare("
    SELECT nim, nama, email, angkatan, is_verified
    FROM akun
    WHERE email = ? AND role = 'mahasiswa'
    LIMIT 1
");
$stmtUser->bind_param("s", $email);
$stmtUser->execute();
$resUser = $stmtUser->get_result();

if ($resUser->num_rows <= 0) {
    session_destroy();
    header("Location: ../login/mahasiswa.php");
    exit;
}

$user = $resUser->fetch_assoc();
$stmtUser->close();

if ((int)$user['is_verified'] !== 1) {
    session_destroy();
    header("Location: ../login/verify_mahasiswa.php?email=" . urlencode($user['email']));
    exit;
}

$nim = $user['nim'];
$nama = $user['nama'];
$angkatan = $user['angkatan'];

$filter_matkul = isset($_GET['matkul_id']) ? (int)$_GET['matkul_id'] : 0;

/* daftar matkul yang di-enroll mahasiswa */
$stmtFilter = $conn->prepare("
    SELECT mk.id, mk.nama_matkul, mk.kelas, mk.angkatan
    FROM mahasiswa_matkul mm
    JOIN mata_kuliah mk ON mm.matkul_id = mk.id
    WHERE mm.nim = ?
    ORDER BY mk.nama_matkul, mk.kelas
");
$stmtFilter->bind_param("s", $nim);
$stmtFilter->execute();
$filterQuery = $stmtFilter->get_result();

/* scoreboard */
$scoreRows = [];
if ($filter_matkul > 0) {
    $stmtBoard = $conn->prepare("
        SELECT 
            hk.nim,
            a.nama,
            mk.nama_matkul,
            mk.kelas,
            ROUND(AVG(hk.nilai), 2) AS rata_nilai,
            COUNT(hk.id) AS jumlah_kuis
        FROM hasil_kuis hk
        JOIN kuis k ON hk.kuis_id = k.id
        JOIN mata_kuliah mk ON k.matkul_id = mk.id
        JOIN akun a ON hk.nim = a.nim
        WHERE k.matkul_id = ?
        GROUP BY hk.nim, a.nama, mk.nama_matkul, mk.kelas
        ORDER BY jumlah_kuis DESC, rata_nilai DESC, a.nama ASC
    ");
    $stmtBoard->bind_param("i", $filter_matkul);
    $stmtBoard->execute();
    $scoreQuery = $stmtBoard->get_result();

    while ($row = $scoreQuery->fetch_assoc()) {
        $scoreRows[] = $row;
    }
}

$top1 = $scoreRows[0] ?? null;
$top2 = $scoreRows[1] ?? null;
$top3 = $scoreRows[2] ?? null;
$others = array_slice($scoreRows, 3);
?>
<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Scoreboard | COMPULAB</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../css/global.css">
<link rel="stylesheet" href="../css/dashboard-mahasiswa.css">
<style>
  .section-card { padding: 28px; }

  .filter-box {
    display: flex;
    gap: 12px;
    align-items: end;
    flex-wrap: wrap;
    margin-bottom: 24px;
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
    font-weight: 500;
  }

  .filter-group select {
    padding: 12px 14px;
    border-radius: 12px;
    background: var(--bg-input);
    color: var(--text-primary);
    border: 1.5px solid var(--border);
    font-family: 'DM Sans', sans-serif;
    font-size: 14px;
    outline: none;
  }

  .filter-group select:focus {
    border-color: var(--border-focus);
  }

  .btn-main {
    border: none;
    border-radius: 12px;
    padding: 12px 20px;
    font-weight: 600;
    font-family: 'DM Sans', sans-serif;
    font-size: 14px;
    cursor: pointer;
    background: var(--btn-gradient);
    color: white;
    box-shadow: 0 4px 14px var(--btn-shadow);
  }

  .btn-main:hover {
    opacity: 0.9;
    transform: translateY(-1px);
  }

  .btn-reset {
    border: 1.5px solid var(--border);
    border-radius: 12px;
    padding: 12px 20px;
    font-weight: 600;
    font-family: 'DM Sans', sans-serif;
    font-size: 14px;
    cursor: pointer;
    background: var(--bg-input);
    color: var(--text-primary);
    text-decoration: none;
    display: inline-block;
  }

  .btn-reset:hover {
    background: var(--bg-card);
    border-color: var(--green-fresh);
    color: var(--green-fresh);
  }

  /* ── PODIUM ── */
  .podium-wrap { margin: 24px 0 28px; }

  .podium-grid {
    display: grid;
    grid-template-columns: 1fr 1.2fr 1fr;
    gap: 18px;
    align-items: end;
  }

  .podium-card {
    border-radius: 18px;
    border: 1px solid var(--border);
    background: var(--bg-card);
    padding: 28px 18px 18px;
    text-align: center;
    position: relative;
    box-shadow: 0 8px 24px var(--shadow);
  }

  .podium-name {
    font-weight: 700;
    font-size: 16px;
    color: var(--text-primary);
    margin-bottom: 6px;
    line-height: 1.4;
  }

  .podium-nim {
    font-size: 13px;
    color: var(--text-muted);
    margin-bottom: 10px;
  }

  .podium-score {
    font-size: 28px;
    font-weight: 700;
    color: var(--text-primary);
    margin-bottom: 4px;
  }

  .podium-rank {
    position: absolute;
    top: -16px;
    left: 50%;
    transform: translateX(-50%);
    width: 42px;
    height: 42px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 16px;
  }

  .rank-1 { min-height: 250px; border-top: 3px solid #d4af37; }
  .rank-2 { min-height: 210px; border-top: 3px solid #c0c0c0; }
  .rank-3 { min-height: 190px; border-top: 3px solid #cd7f32; }

  .rank-1 .podium-rank { background: #d4af37; color: #111; }
  .rank-2 .podium-rank { background: #c0c0c0; color: #111; }
  .rank-3 .podium-rank { background: #cd7f32; color: #111; }

  .medal { font-size: 26px; margin-bottom: 10px; }

  .muted { font-size: 13px; color: var(--text-muted); }

  /* ── TABLE ── */
  .table-title {
    font-family: 'DM Serif Display', serif;
    font-size: 18px;
    font-weight: 400;
    color: var(--text-primary);
    margin-bottom: 14px;
  }

  .table-ranking {
    width: 100%;
    border-collapse: collapse;
    border-radius: 16px;
    overflow: hidden;
  }

  .table-ranking th,
  .table-ranking td {
    padding: 14px 16px;
    border-bottom: 1px solid var(--border);
    text-align: left;
    vertical-align: middle;
    color: var(--text-primary);
  }

  .table-ranking th {
    background: var(--bg-input);
    font-size: 13px;
    color: var(--text-muted);
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
  }

  .table-ranking tbody tr:hover td {
    background: var(--bg-input);
  }

  .rank-badge {
    display: inline-flex;
    min-width: 34px;
    height: 34px;
    align-items: center;
    justify-content: center;
    border-radius: 10px;
    background: var(--bg-input);
    color: var(--text-primary);
    font-weight: 700;
    border: 1px solid var(--border);
  }

  .score-text { font-weight: 700; color: var(--green-fresh); }

  .empty-box {
    padding: 24px 0;
    color: var(--text-muted);
    font-size: 14px;
  }

  @media (max-width: 900px) {
    .podium-grid { grid-template-columns: 1fr; }
    .rank-1, .rank-2, .rank-3 { min-height: auto; }
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
    <a href="materi.php">📚 Materi</a>
    <a href="kuis.php">📝 Kuis</a>
    <a class="active" href="scoreboard.php">🏆 Scoreboard</a>
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
    <a href="kuis.php">📝 Kuis</a>
    <a class="active" href="scoreboard.php">🏆 Scoreboard</a>
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

  <div class="card section-card">
    <h3>Scoreboard</h3>
    <p>Pilih mata kuliah untuk melihat ranking mahasiswa.</p>

    <form method="GET" class="filter-box">
      <div class="filter-group">
        <label for="matkul_id">Mata Kuliah</label>
        <select name="matkul_id" id="matkul_id" required>
          <option value="">-- Pilih Mata Kuliah --</option>
          <?php while ($f = $filterQuery->fetch_assoc()): ?>
            <option value="<?php echo $f['id']; ?>" <?php echo ($filter_matkul == $f['id']) ? 'selected' : ''; ?>>
              <?php echo htmlspecialchars($f['nama_matkul'] . ' | ' . $f['angkatan'] . ' ' . $f['kelas']); ?>
            </option>
          <?php endwhile; ?>
        </select>
      </div>
      <button type="submit" class="btn-main">Lihat Scoreboard</button>
      <a href="scoreboard.php" class="btn-reset">Reset</a>
    </form>

    <?php if ($filter_matkul > 0): ?>
      <?php if (count($scoreRows) > 0): ?>

        <div class="podium-wrap">
          <div class="podium-grid">
            
            <div>
              <?php if ($top2): ?>
                <div class="podium-card rank-2">
                  <div class="podium-rank">2</div>
                  <div class="medal">🥈</div>
                  <div class="podium-name"><?php echo htmlspecialchars($top2['nama']); ?></div>
                  <div class="podium-nim"><?php echo htmlspecialchars($top2['nim']); ?></div>
                  <div class="podium-score"><?php echo htmlspecialchars($top2['rata_nilai']); ?></div>
                  <div class="muted"><?php echo htmlspecialchars($top2['jumlah_kuis']); ?> kuis</div>
                </div>
              <?php endif; ?>
            </div>

            <div>
              <?php if ($top1): ?>
                <div class="podium-card rank-1">
                  <div class="podium-rank">1</div>
                  <div class="medal">🥇</div>
                  <div class="podium-name"><?php echo htmlspecialchars($top1['nama']); ?></div>
                  <div class="podium-nim"><?php echo htmlspecialchars($top1['nim']); ?></div>
                  <div class="podium-score"><?php echo htmlspecialchars($top1['rata_nilai']); ?></div>
                  <div class="muted"><?php echo htmlspecialchars($top1['jumlah_kuis']); ?> kuis</div>
                </div>
              <?php endif; ?>
            </div>

            <div>
              <?php if ($top3): ?>
                <div class="podium-card rank-3">
                  <div class="podium-rank">3</div>
                  <div class="medal">🥉</div>
                  <div class="podium-name"><?php echo htmlspecialchars($top3['nama']); ?></div>
                  <div class="podium-nim"><?php echo htmlspecialchars($top3['nim']); ?></div>
                  <div class="podium-score"><?php echo htmlspecialchars($top3['rata_nilai']); ?></div>
                  <div class="muted"><?php echo htmlspecialchars($top3['jumlah_kuis']); ?> kuis</div>
                </div>
              <?php endif; ?>
            </div>

          </div>
        </div>

        <?php if (count($others) > 0): ?>
          <h4 class="table-title">Peringkat Lainnya</h4>
          <table class="table-ranking">
            <thead>
              <tr>
                <th style="width:90px;">Rank</th>
                <th style="width:170px;">NIM</th>
                <th>Nama</th>
                <th style="width:180px;">Rata-rata Nilai</th>
                <th style="width:150px;">Jumlah Kuis</th>
              </tr>
            </thead>
            <tbody>
              <?php $rank = 4; ?>
              <?php foreach ($others as $row): ?>
                <tr>
                  <td><span class="rank-badge"><?php echo $rank++; ?></span></td>
                  <td><?php echo htmlspecialchars($row['nim']); ?></td>
                  <td><?php echo htmlspecialchars($row['nama']); ?></td>
                  <td class="score-text"><?php echo htmlspecialchars($row['rata_nilai']); ?></td>
                  <td><?php echo htmlspecialchars($row['jumlah_kuis']); ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>

      <?php else: ?>
        <div class="empty-box">Belum ada hasil kuis untuk mata kuliah ini.</div>
      <?php endif; ?>
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