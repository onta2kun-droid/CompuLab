<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once __DIR__ . '/../../sambung/forsafety.php';

date_default_timezone_set('Asia/Makassar');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'dosen') {
    header("Location: ../login/dosen.php");
    exit();
}

$dosen_id   = $_SESSION['user_id'];
$nama_dosen = $_SESSION['user_nama'];
$success = "";
$error   = "";

/* =========================
   FILTER
========================= */
$filter_matkul = trim($_GET['matkul'] ?? '');
$filter_kelas  = trim($_GET['kelas'] ?? '');

/* =========================
   HAPUS KUIS
========================= */
if (isset($_GET['hapus'])) {
    $kuis_id = (int)($_GET['hapus'] ?? 0);

    if ($kuis_id > 0) {
        $stmtCek = $conn->prepare("
            SELECT id
            FROM kuis
            WHERE id = ? AND dosen_id = ?
            LIMIT 1
        ");

        if ($stmtCek) {
            $stmtCek->bind_param("ii", $kuis_id, $dosen_id);
            $stmtCek->execute();
            $resultCek = $stmtCek->get_result();

            if ($resultCek->num_rows <= 0) {
                $error = "Kuis tidak ditemukan atau bukan milik Anda.";
            } else {
                $conn->begin_transaction();

                try {
                    $stmtDeleteJawaban = $conn->prepare("
                        DELETE jm
                        FROM jawaban_mahasiswa jm
                        JOIN hasil_kuis hk ON jm.hasil_id = hk.id
                        WHERE hk.kuis_id = ?
                    ");
                    if ($stmtDeleteJawaban) {
                        $stmtDeleteJawaban->bind_param("i", $kuis_id);
                        $stmtDeleteJawaban->execute();
                        $stmtDeleteJawaban->close();
                    }

                    $stmtDeleteHasil = $conn->prepare("
                        DELETE FROM hasil_kuis
                        WHERE kuis_id = ?
                    ");
                    $stmtDeleteHasil->bind_param("i", $kuis_id);
                    $stmtDeleteHasil->execute();
                    $stmtDeleteHasil->close();

                    $stmtDeleteSoal = $conn->prepare("
                        DELETE FROM soal_kuis
                        WHERE kuis_id = ?
                    ");
                    $stmtDeleteSoal->bind_param("i", $kuis_id);
                    $stmtDeleteSoal->execute();
                    $stmtDeleteSoal->close();

                    $stmtDeleteKuis = $conn->prepare("
                        DELETE FROM kuis
                        WHERE id = ? AND dosen_id = ?
                    ");
                    $stmtDeleteKuis->bind_param("ii", $kuis_id, $dosen_id);
                    $stmtDeleteKuis->execute();
                    $stmtDeleteKuis->close();

                    $conn->commit();
                    $success = "Kuis berhasil dihapus.";
                } catch (Exception $e) {
                    $conn->rollback();
                    $error = "Gagal menghapus kuis.";
                }
            }

            $stmtCek->close();
        } else {
            $error = "Query cek kuis gagal.";
        }
    }
}

/* =========================
   AKTIFKAN / NONAKTIFKAN KUIS
========================= */
if (isset($_GET['toggle'])) {
    $kuis_id = (int)($_GET['toggle'] ?? 0);
    $mode    = trim($_GET['mode'] ?? '');

    if ($kuis_id > 0 && in_array($mode, ['aktif', 'nonaktif'])) {
        $stmtCekToggle = $conn->prepare("
            SELECT 
                k.id,
                k.is_active,
                k.waktu_mulai,
                k.waktu_selesai,
                k.durasi,
                (
                    SELECT COUNT(*)
                    FROM soal_kuis sq
                    WHERE sq.kuis_id = k.id
                ) AS total_soal
            FROM kuis k
            WHERE k.id = ? AND k.dosen_id = ?
            LIMIT 1
        ");

        if ($stmtCekToggle) {
            $stmtCekToggle->bind_param("ii", $kuis_id, $dosen_id);
            $stmtCekToggle->execute();
            $resultToggle = $stmtCekToggle->get_result();

            if ($resultToggle->num_rows <= 0) {
                $error = "Kuis tidak ditemukan atau bukan milik Anda.";
            } else {
                $dataKuis = $resultToggle->fetch_assoc();

                if ($mode === 'aktif') {
                    if ((int)$dataKuis['total_soal'] <= 0) {
                        $error = "Kuis tidak bisa diaktifkan karena belum memiliki soal.";
                    } elseif (empty($dataKuis['waktu_mulai']) || empty($dataKuis['waktu_selesai'])) {
                        $error = "Kuis tidak bisa diaktifkan karena waktu mulai / selesai belum valid.";
                    } elseif (strtotime($dataKuis['waktu_selesai']) <= strtotime($dataKuis['waktu_mulai'])) {
                        $error = "Kuis tidak bisa diaktifkan karena waktu selesai harus lebih besar dari waktu mulai.";
                    } elseif ((int)$dataKuis['durasi'] <= 0) {
                        $error = "Kuis tidak bisa diaktifkan karena durasi belum valid.";
                    } else {
                        $stmtAktif = $conn->prepare("
                            UPDATE kuis
                            SET is_active = 1
                            WHERE id = ? AND dosen_id = ?
                        ");
                        $stmtAktif->bind_param("ii", $kuis_id, $dosen_id);

                        if ($stmtAktif->execute()) {
                            $success = "Kuis berhasil diaktifkan. Mahasiswa sekarang bisa mengaksesnya.";
                        } else {
                            $error = "Gagal mengaktifkan kuis.";
                        }

                        $stmtAktif->close();
                    }
                }

                if ($mode === 'nonaktif') {
                    $stmtNonaktif = $conn->prepare("
                        UPDATE kuis
                        SET is_active = 0
                        WHERE id = ? AND dosen_id = ?
                    ");
                    $stmtNonaktif->bind_param("ii", $kuis_id, $dosen_id);

                    if ($stmtNonaktif->execute()) {
                        $success = "Kuis berhasil dinonaktifkan. Mahasiswa tidak bisa mengaksesnya.";
                    } else {
                        $error = "Gagal menonaktifkan kuis.";
                    }

                    $stmtNonaktif->close();
                }
            }

            $stmtCekToggle->close();
        } else {
            $error = "Query toggle kuis gagal.";
        }
    }
}

/* =========================
   AMBIL DATA FILTER MATKUL/KELAS
========================= */
$matkulList = [];
$stmtFilterMatkul = $conn->prepare("
    SELECT DISTINCT mk.nama_matkul
    FROM kuis k
    JOIN mata_kuliah mk ON k.matkul_id = mk.id
    WHERE k.dosen_id = ?
    ORDER BY mk.nama_matkul ASC
");
if ($stmtFilterMatkul) {
    $stmtFilterMatkul->bind_param("i", $dosen_id);
    $stmtFilterMatkul->execute();
    $resMatkul = $stmtFilterMatkul->get_result();
    while ($row = $resMatkul->fetch_assoc()) {
        $matkulList[] = $row['nama_matkul'];
    }
    $stmtFilterMatkul->close();
}

$kelasList = [];
$stmtFilterKelas = $conn->prepare("
    SELECT DISTINCT mk.kelas
    FROM kuis k
    JOIN mata_kuliah mk ON k.matkul_id = mk.id
    WHERE k.dosen_id = ?
    ORDER BY mk.kelas ASC
");
if ($stmtFilterKelas) {
    $stmtFilterKelas->bind_param("i", $dosen_id);
    $stmtFilterKelas->execute();
    $resKelas = $stmtFilterKelas->get_result();
    while ($row = $resKelas->fetch_assoc()) {
        $kelasList[] = $row['kelas'];
    }
    $stmtFilterKelas->close();
}

/* =========================
   AMBIL SEMUA KUIS + FILTER
========================= */
$sqlKuis = "
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
        mk.angkatan,
        mk.kelas,
        (
            SELECT COUNT(*)
            FROM soal_kuis sq
            WHERE sq.kuis_id = k.id
        ) AS total_soal,
        (
            SELECT COUNT(*)
            FROM hasil_kuis hk
            WHERE hk.kuis_id = k.id
        ) AS total_pengerjaan
    FROM kuis k
    JOIN mata_kuliah mk ON k.matkul_id = mk.id
    WHERE k.dosen_id = ?
";

$params = [$dosen_id];
$types  = "i";

if ($filter_matkul !== '') {
    $sqlKuis .= " AND mk.nama_matkul = ? ";
    $params[] = $filter_matkul;
    $types .= "s";
}

if ($filter_kelas !== '') {
    $sqlKuis .= " AND mk.kelas = ? ";
    $params[] = $filter_kelas;
    $types .= "s";
}

$sqlKuis .= " ORDER BY k.created_at DESC ";

$stmtKuis = $conn->prepare($sqlKuis);
$kuisQuery = null;

if ($stmtKuis) {
    $stmtKuis->bind_param($types, ...$params);
    $stmtKuis->execute();
    $kuisQuery = $stmtKuis->get_result();
} else {
    $error = "Query daftar kuis gagal.";
}

$sekarang = date('Y-m-d H:i:s');
?>
<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Kelola Kuis | COMPULAB</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<script>
  const t = localStorage.getItem('compulab_theme');
  if (t) document.documentElement.setAttribute('data-theme', t);
</script>
<link rel="stylesheet" href="../css/dashboard-dosen.css">
<style>
.kuis-list { display: flex; flex-direction: column; gap: 16px; }

.filter-wrap {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
  align-items: end;
  margin-bottom: 24px;
}

.filter-group {
  display: flex;
  flex-direction: column;
  gap: 8px;
  min-width: 220px;
}

.filter-group label {
  font-size: 13px;
  font-weight: 600;
  color: var(--text-muted);
}

.filter-group select {
  padding: 11px 14px;
  border-radius: 12px;
  border: 1.5px solid var(--border);
  background: var(--bg-input);
  color: var(--text-primary);
  font-size: 14px;
  outline: none;
}

.filter-actions {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
}

.kuis-card {
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 18px;
  padding: 22px 24px;
  display: grid;
  grid-template-columns: 1fr auto;
  gap: 16px;
  align-items: start;
  transition: box-shadow 0.2s, border-color 0.2s;
  position: relative;
  overflow: hidden;
}
.kuis-card:hover { box-shadow: 0 6px 24px rgba(15,31,61,0.09); border-color: var(--navy-light); }

.kuis-card::before {
  content: '';
  position: absolute;
  left: 0; top: 0; bottom: 0;
  width: 4px;
  background: var(--border);
  border-radius: 4px 0 0 4px;
}
.kuis-card.status-open::before { background: #22c55e; }
.kuis-card.status-wait::before { background: var(--gold); }
.kuis-card.status-close::before { background: var(--navy-light); }

.kuis-title {
  font-family: 'DM Serif Display', serif;
  font-size: 17px;
  font-weight: 400;
  color: var(--text-primary);
  margin-bottom: 4px;
}
.kuis-desc {
  font-size: 13px;
  color: var(--text-muted);
  margin-bottom: 12px;
  line-height: 1.5;
}

.kuis-meta {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  align-items: center;
}
.kuis-meta-chip {
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

.status-badge {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 5px 12px;
  border-radius: 20px;
  font-size: 12px;
  font-weight: 700;
}
.status-open  { background: rgba(34,197,94,0.12);  color: #16a34a; }
.status-wait  { background: rgba(245,166,35,0.12);  color: var(--gold-dark); }
.status-close { background: rgba(107,143,212,0.12); color: var(--navy-fresh); }

.status-access-active {
  background: rgba(34,197,94,0.12);
  color: #16a34a;
}
.status-access-inactive {
  background: rgba(231,76,60,0.12);
  color: #c0392b;
}

[data-theme="dark"] .status-open  { color: #4ade80; }
[data-theme="dark"] .status-wait  { color: var(--gold-light); }
[data-theme="dark"] .status-close { color: var(--navy-light); }
[data-theme="dark"] .status-access-active { color: #4ade80; }
[data-theme="dark"] .status-access-inactive { color: #ff9f9f; }

.aksi-col {
  display: flex;
  flex-direction: column;
  gap: 8px;
  align-items: flex-end;
  min-width: 165px;
}
.aksi-col .status-badge { align-self: flex-end; }

.waktu-block {
  font-size: 12px;
  color: var(--text-muted);
  line-height: 1.7;
}
.waktu-block strong { color: var(--text-mid); font-weight: 600; }

.stat-row {
  display: flex;
  gap: 32px;
  flex-wrap: wrap;
  margin: 12px 0 14px;
}
.stat-item {
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.stat-val {
  font-size: 20px;
  font-weight: 700;
  color: var(--text-primary);
  font-family: 'DM Serif Display', serif;
}
.stat-lbl {
  font-size: 11px;
  color: var(--text-muted);
  text-transform: uppercase;
  letter-spacing: 0.5px;
  font-weight: 600;
}

.btn-activate {
  background: linear-gradient(90deg, #16a085, #138d75);
  color: white;
}
.btn-disable {
  background: linear-gradient(90deg, #f39c12, #d68910);
  color: white;
}

.empty-state { text-align: center; padding: 48px 24px; color: var(--text-muted); }
.empty-state .empty-icon { font-size: 48px; margin-bottom: 12px; }

@media (max-width: 640px) {
  .kuis-card { grid-template-columns: 1fr; }
  .aksi-col { flex-direction: row; flex-wrap: wrap; align-items: flex-start; min-width: unset; }
  .aksi-col .status-badge { align-self: auto; }
  .filter-wrap { flex-direction: column; align-items: stretch; }
  .filter-group { min-width: 100%; }
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
    <h1>Kelola Kuis 📋</h1>
    <div class="topbar-right">
      <a href="profil.php" class="profile-mini" title="Profil Dosen">👨‍🏫</a>
      <button class="dark-toggle-top" id="darkToggle">🌙</button>
    </div>
  </div>

  <?php if ($success): ?>
    <div class="alert-success" style="margin-bottom:20px;">✅ <?php echo htmlspecialchars($success); ?></div>
  <?php endif; ?>

  <?php if ($error): ?>
    <div class="alert-error" style="margin-bottom:20px;">⚠️ <?php echo htmlspecialchars($error); ?></div>
  <?php endif; ?>

  <div class="table-card">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:24px; flex-wrap:wrap; gap:12px;">
      <h3 style="margin-bottom:0;">Daftar Kuis</h3>
      <a href="tambah_kuis.php" class="btn-gold" style="font-size:13px; padding:10px 18px;">+ Buat Kuis Baru</a>
    </div>

    <form method="GET" class="filter-wrap">
      <div class="filter-group">
        <label for="matkul">Filter Mata Kuliah</label>
        <select name="matkul" id="matkul">
          <option value="">Semua Mata Kuliah</option>
          <?php foreach ($matkulList as $matkul): ?>
            <option value="<?php echo htmlspecialchars($matkul); ?>" <?php echo ($filter_matkul === $matkul) ? 'selected' : ''; ?>>
              <?php echo htmlspecialchars($matkul); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="filter-group">
        <label for="kelas">Filter Kelas</label>
        <select name="kelas" id="kelas">
          <option value="">Semua Kelas</option>
          <?php foreach ($kelasList as $kelas): ?>
            <option value="<?php echo htmlspecialchars($kelas); ?>" <?php echo ($filter_kelas === $kelas) ? 'selected' : ''; ?>>
              <?php echo htmlspecialchars($kelas); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="filter-actions">
        <button type="submit" class="btn-gold" style="font-size:13px; padding:10px 18px;">Terapkan Filter</button>
        <a href="kelola_kuis.php" class="btn-secondary" style="font-size:13px; padding:10px 18px; text-decoration:none;">Reset</a>
      </div>
    </form>

    <?php if ($kuisQuery && $kuisQuery->num_rows > 0): ?>
      <div class="kuis-list">
        <?php while ($row = $kuisQuery->fetch_assoc()): ?>
          <?php
            $wMulai   = $row['waktu_mulai'];
            $wSelesai = $row['waktu_selesai'];

            $statusClass = 'status-close';
            $statusLabel = '⬛ Selesai';

            if (!empty($wMulai) && $sekarang < $wMulai) {
              $statusClass = 'status-wait';
              $statusLabel = '⏳ Belum Dimulai';
            } elseif (!empty($wSelesai) && $sekarang <= $wSelesai) {
              $statusClass = 'status-open';
              $statusLabel = '🟢 Berlangsung';
            }

            $accessClass = ((int)$row['is_active'] === 1) ? 'status-access-active' : 'status-access-inactive';
            $accessLabel = ((int)$row['is_active'] === 1) ? '🔓 Akses Aktif' : '🔒 Nonaktif';
          ?>

          <div class="kuis-card <?php echo $statusClass; ?>">
            <div>
              <div class="kuis-title"><?php echo htmlspecialchars($row['judul']); ?></div>

              <?php if (!empty($row['deskripsi'])): ?>
                <div class="kuis-desc">
                  <?php echo htmlspecialchars(mb_substr($row['deskripsi'], 0, 100)) . (mb_strlen($row['deskripsi']) > 100 ? '...' : ''); ?>
                </div>
              <?php endif; ?>

              <div class="kuis-meta" style="margin-bottom:14px;">
                <span class="kuis-meta-chip">📚 <?php echo htmlspecialchars($row['nama_matkul']); ?></span>
                <span class="kuis-meta-chip">👥 Angkatan <?php echo htmlspecialchars($row['angkatan']); ?> Kelas <?php echo htmlspecialchars($row['kelas']); ?></span>
                <span class="kuis-meta-chip">🕒 Upload: <?php echo date('d M Y, H:i', strtotime($row['created_at'])); ?></span>
              </div>

              <div class="stat-row">
                <div class="stat-item">
                  <div class="stat-val"><?php echo (int)$row['total_soal']; ?></div>
                  <div class="stat-lbl">Soal</div>
                </div>
                <div class="stat-item">
                  <div class="stat-val"><?php echo (int)$row['durasi']; ?></div>
                  <div class="stat-lbl">Menit</div>
                </div>
                <div class="stat-item">
                  <div class="stat-val"><?php echo (int)$row['total_pengerjaan']; ?></div>
                  <div class="stat-lbl">Dikerjakan</div>
                </div>
              </div>

              <div class="waktu-block">
                <strong>Mulai:</strong> <?php echo date('d M Y, H:i', strtotime($wMulai)); ?>
                &nbsp;|&nbsp;
                <strong>Selesai:</strong> <?php echo date('d M Y, H:i', strtotime($wSelesai)); ?>
              </div>
            </div>

            <div class="aksi-col">
              <span class="status-badge <?php echo $statusClass; ?>"><?php echo $statusLabel; ?></span>
              <span class="status-badge <?php echo $accessClass; ?>"><?php echo $accessLabel; ?></span>

              <a href="tambah_soal_kuis.php?kuis_id=<?php echo $row['id']; ?>" class="btn-main" style="font-size:12px; padding:8px 14px; width:100%; text-align:center;">
                📝 Tambah Soal
              </a>

              <a href="nilai_kuis.php?kuis_id=<?php echo $row['id']; ?>" class="btn-secondary" style="font-size:12px; padding:8px 14px; width:100%; text-align:center;">
                📊 Lihat Nilai
              </a>

              <?php if ((int)$row['is_active'] === 1): ?>
                <a
                  href="kelola_kuis.php?toggle=<?php echo $row['id']; ?>&mode=nonaktif"
                  class="btn-disable"
                  style="font-size:12px; padding:8px 14px; width:100%; text-align:center; text-decoration:none; border-radius:10px;"
                  onclick="return confirm('Yakin ingin menonaktifkan kuis ini? Mahasiswa tidak akan bisa mengaksesnya.')"
                >
                  🔒 Nonaktifkan
                </a>
              <?php else: ?>
                <a
                  href="kelola_kuis.php?toggle=<?php echo $row['id']; ?>&mode=aktif"
                  class="btn-activate"
                  style="font-size:12px; padding:8px 14px; width:100%; text-align:center; text-decoration:none; border-radius:10px;"
                  onclick="return confirm('Yakin ingin mengaktifkan kuis ini? Mahasiswa akan bisa mengaksesnya.')"
                >
                  🚀 Aktifkan
                </a>
              <?php endif; ?>

              <a
                href="kelola_kuis.php?hapus=<?php echo $row['id']; ?>"
                class="btn-danger"
                style="font-size:12px; padding:8px 14px; width:100%; text-align:center;"
                onclick="return confirm('Yakin hapus kuis ini beserta semua soal dan nilainya?')"
              >
                🗑️ Hapus
              </a>
            </div>
          </div>
        <?php endwhile; ?>
      </div>
    <?php else: ?>
      <div class="empty-state">
        <div class="empty-icon">📭</div>
        <p>Tidak ada kuis yang cocok dengan filter.</p>
        <a href="kelola_kuis.php" class="btn-secondary" style="text-decoration:none;">Reset Filter</a>
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