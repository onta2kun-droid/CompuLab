<?php
session_start();
require_once __DIR__ . '/../../sambung/forsafety.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login/admin.php");
    exit();
}

$admin_nama = $_SESSION['user_nama'] ?? 'Admin';
$success = "";
$error   = "";
$tab = $_GET['tab'] ?? 'list';

/* =========================
   HAPUS MATA KULIAH
========================= */
if (isset($_GET['hapus'])) {
    $mid = (int)($_GET['hapus'] ?? 0);

    if ($mid > 0) {
        $conn->begin_transaction();

        try {
            /* hapus relasi dosen */
            $st1 = $conn->prepare("DELETE FROM dosen_matkul WHERE matkul_id = ?");
            if ($st1) {
                $st1->bind_param("i", $mid);
                $st1->execute();
                $st1->close();
            }

            /* hapus enroll mahasiswa */
            $st2 = $conn->prepare("DELETE FROM mahasiswa_matkul WHERE matkul_id = ?");
            if ($st2) {
                $st2->bind_param("i", $mid);
                $st2->execute();
                $st2->close();
            }

            /* hapus mata kuliah */
            $st3 = $conn->prepare("DELETE FROM mata_kuliah WHERE id = ?");
            if (!$st3) {
                throw new Exception("Query hapus mata kuliah gagal.");
            }

            $st3->bind_param("i", $mid);
            $st3->execute();

            if ($st3->affected_rows <= 0) {
                throw new Exception("Mata kuliah tidak ditemukan.");
            }

            $st3->close();

            $conn->commit();
            $success = "Mata kuliah berhasil dihapus.";
        } catch (Exception $e) {
            $conn->rollback();
            $error = "Gagal menghapus mata kuliah: " . $e->getMessage();
        }
    }
}

/* =========================
   TAMBAH MATKUL
========================= */
if (isset($_POST['tambah_matkul'])) {
    $nama      = trim($_POST['nama_matkul'] ?? '');
    $angkatan  = trim($_POST['angkatan'] ?? '');
    $kelas     = trim($_POST['kelas'] ?? '');
    $dosen_id  = (int)($_POST['dosen_id'] ?? 0);

    if ($nama && $angkatan && $kelas && $dosen_id) {
        $conn->begin_transaction();

        try {
            $st = $conn->prepare("
                INSERT INTO mata_kuliah (nama_matkul, angkatan, kelas)
                VALUES (?, ?, ?)
            ");

            if (!$st) {
                throw new Exception("Query tambah mata kuliah gagal.");
            }

            $st->bind_param("sss", $nama, $angkatan, $kelas);
            $st->execute();
            $matkul_id = $conn->insert_id;
            $st->close();

            $st2 = $conn->prepare("
                INSERT INTO dosen_matkul (dosen_id, matkul_id)
                VALUES (?, ?)
            ");

            if (!$st2) {
                throw new Exception("Query relasi dosen_matkul gagal.");
            }

            $st2->bind_param("ii", $dosen_id, $matkul_id);
            $st2->execute();
            $st2->close();

            $conn->commit();
            $success = "Mata kuliah berhasil ditambahkan!";
            $tab = 'list';
        } catch (Exception $e) {
            $conn->rollback();
            $error = "Gagal menyimpan mata kuliah: " . $e->getMessage();
        }
    } else {
        $error = "Semua field wajib diisi!";
    }
}

/* =========================
   ENROLL MAHASISWA
========================= */
if (isset($_POST['enroll_mhs'])) {
    $matkul_id = (int)($_POST['matkul_id'] ?? 0);
    $nim       = trim($_POST['nim'] ?? '');

    if ($matkul_id > 0 && $nim !== '') {
        $cek = $conn->prepare("
            SELECT 1
            FROM mahasiswa_matkul
            WHERE nim = ? AND matkul_id = ?
            LIMIT 1
        ");

        if ($cek) {
            $cek->bind_param("si", $nim, $matkul_id);
            $cek->execute();

            if ($cek->get_result()->num_rows > 0) {
                $error = "Mahasiswa sudah terdaftar di mata kuliah ini!";
            } else {
                $cekMhs = $conn->prepare("
                    SELECT nim
                    FROM akun
                    WHERE nim = ? AND role = 'mahasiswa'
                    LIMIT 1
                ");

                if ($cekMhs) {
                    $cekMhs->bind_param("s", $nim);
                    $cekMhs->execute();

                    if ($cekMhs->get_result()->num_rows === 0) {
                        $error = "NIM tidak ditemukan!";
                    } else {
                        $st = $conn->prepare("
                            INSERT INTO mahasiswa_matkul (nim, matkul_id)
                            VALUES (?, ?)
                        ");

                        if ($st) {
                            $st->bind_param("si", $nim, $matkul_id);
                            if ($st->execute()) {
                                $success = "Mahasiswa berhasil dienroll!";
                            } else {
                                $error = "Gagal enroll mahasiswa.";
                            }
                            $st->close();
                        } else {
                            $error = "Query enroll mahasiswa gagal.";
                        }
                    }
                    $cekMhs->close();
                }
            }
            $cek->close();
        }
    } else {
        $error = "Matkul dan NIM wajib diisi.";
    }
}

/* =========================
   HAPUS ENROLL
========================= */
if (isset($_GET['hapus_enroll'])) {
    $nim = trim($_GET['hapus_enroll'] ?? '');
    $mid = (int)($_GET['mid'] ?? 0);

    if ($nim !== '' && $mid > 0) {
        $st = $conn->prepare("
            DELETE FROM mahasiswa_matkul
            WHERE nim = ? AND matkul_id = ?
        ");

        if ($st) {
            $st->bind_param("si", $nim, $mid);
            if ($st->execute()) {
                $success = "Enroll mahasiswa berhasil dihapus.";
            } else {
                $error = "Gagal menghapus enroll.";
            }
            $st->close();
        }
    }
}

/* =========================
   DATA LIST MATKUL
========================= */
$matkulList = $conn->query("
    SELECT 
        mk.id,
        mk.nama_matkul,
        mk.angkatan,
        mk.kelas,
        GROUP_CONCAT(DISTINCT a.nama ORDER BY a.nama SEPARATOR '||') AS nama_dosen,
        COUNT(DISTINCT mm.nim) AS total_mhs
    FROM mata_kuliah mk
    LEFT JOIN dosen_matkul dm ON mk.id = dm.matkul_id
    LEFT JOIN akun a ON dm.dosen_id = a.id
    LEFT JOIN mahasiswa_matkul mm ON mk.id = mm.matkul_id
    GROUP BY mk.id, mk.nama_matkul, mk.angkatan, mk.kelas
    ORDER BY mk.nama_matkul ASC, mk.angkatan ASC, mk.kelas ASC
");

/* =========================
   DATA DOSEN UNTUK FORM
========================= */
$dosenList = $conn->query("
    SELECT id, nama
    FROM akun
    WHERE role = 'dosen'
    ORDER BY nama ASC
");

/* =========================
   DETAIL MATKUL
========================= */
$detailMatkul = null;
$enrollList   = null;
$totalMateri  = 0;
$totalKuis    = 0;

if (isset($_GET['detail'])) {
    $mid = (int)($_GET['detail'] ?? 0);

    if ($mid > 0) {
        $stmtDetail = $conn->prepare("
            SELECT 
                mk.*,
                GROUP_CONCAT(DISTINCT a.nama ORDER BY a.nama SEPARATOR ', ') AS nama_dosen,
                COUNT(DISTINCT mm.nim) AS total_mahasiswa
            FROM mata_kuliah mk
            LEFT JOIN dosen_matkul dm ON mk.id = dm.matkul_id
            LEFT JOIN akun a ON dm.dosen_id = a.id
            LEFT JOIN mahasiswa_matkul mm ON mk.id = mm.matkul_id
            WHERE mk.id = ?
            GROUP BY mk.id, mk.nama_matkul, mk.angkatan, mk.kelas
            LIMIT 1
        ");

        if ($stmtDetail) {
            $stmtDetail->bind_param("i", $mid);
            $stmtDetail->execute();
            $resDetail = $stmtDetail->get_result();
            $detailMatkul = $resDetail ? $resDetail->fetch_assoc() : null;
            $stmtDetail->close();
        }

        $stmtEnroll = $conn->prepare("
            SELECT a.nim, a.nama, a.email, a.is_verified
            FROM mahasiswa_matkul mm
            JOIN akun a ON mm.nim = a.nim
            WHERE mm.matkul_id = ?
            ORDER BY a.nama ASC
        ");

        if ($stmtEnroll) {
            $stmtEnroll->bind_param("i", $mid);
            $stmtEnroll->execute();
            $enrollList = $stmtEnroll->get_result();
        }

        $stmtMateri = $conn->prepare("
            SELECT COUNT(*) AS total
            FROM materi
            WHERE matkul_id = ?
        ");
        if ($stmtMateri) {
            $stmtMateri->bind_param("i", $mid);
            $stmtMateri->execute();
            $rMateri = $stmtMateri->get_result()->fetch_assoc();
            $totalMateri = (int)($rMateri['total'] ?? 0);
            $stmtMateri->close();
        }

        $stmtKuis = $conn->prepare("
            SELECT COUNT(*) AS total
            FROM kuis
            WHERE matkul_id = ?
        ");
        if ($stmtKuis) {
            $stmtKuis->bind_param("i", $mid);
            $stmtKuis->execute();
            $rKuis = $stmtKuis->get_result()->fetch_assoc();
            $totalKuis = (int)($rKuis['total'] ?? 0);
            $stmtKuis->close();
        }

        $tab = 'detail';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Mata Kuliah | COMPULAB Admin</title>
<link rel="stylesheet" href="../css/admin.css">
<style>
.detail-grid{
  display:grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap:14px;
  margin-bottom:20px;
}
.detail-card-mini{
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: 18px;
  padding: 16px;
}
.detail-card-mini h4{
  margin:0 0 6px;
  font-size:12px;
  color:var(--text-3);
  text-transform:uppercase;
  letter-spacing:.4px;
}
.detail-card-mini p{
  margin:0;
  font-size:18px;
  font-weight:700;
  color:var(--text-1);
}
.info-banner{
  background: linear-gradient(135deg, rgba(220,38,38,.10), rgba(220,38,38,.03));
  border:1px solid rgba(220,38,38,.18);
  border-radius:20px;
  padding:20px;
  margin-bottom:20px;
}
.info-banner h3{
  margin:0 0 8px;
  font-size:20px;
}
.info-meta{
  display:flex;
  flex-wrap:wrap;
  gap:8px;
  margin-top:10px;
}
.info-chip{
  display:inline-flex;
  align-items:center;
  gap:6px;
  padding:8px 12px;
  border-radius:999px;
  font-size:12px;
  font-weight:600;
  background:rgba(255,255,255,.6);
  border:1px solid var(--border);
}
.action-row{
  display:flex;
  justify-content:space-between;
  align-items:center;
  gap:12px;
  margin-bottom:16px;
  flex-wrap:wrap;
}
@media (max-width: 900px){
  .detail-layout{
    display:block !important;
  }
  .detail-side{
    margin-top:20px;
  }
}
</style>
</head>
<body>
<div class="mobile-overlay" id="mobileOverlay"></div>

<aside class="sidebar" id="sidebar">
  <div class="sidebar-logo">
    <div class="sidebar-logo-mark">
      <span class="sidebar-logo-mark-text">
        <span class="cl-c">C</span><span class="cl-l">L</span>
      </span>
    </div>
    <div class="sidebar-logo-text">COMPU<span>LAB</span></div>
  </div>

  <nav class="sidebar-nav">
    <div class="nav-label">Menu</div>
    <a href="dashboard.php" class="nav-link"><span class="nav-icon">📊</span> Overview</a>
    <a href="kelola_akun.php" class="nav-link"><span class="nav-icon">👥</span> Kelola Akun</a>
    <a href="kelola_matkul.php" class="nav-link active"><span class="nav-icon">📚</span> Mata Kuliah</a>
    <a href="verifikasi_otp.php" class="nav-link"><span class="nav-icon">🔑</span> Verifikasi OTP</a>
    <a href="kelola_konten.php" class="nav-link"><span class="nav-icon">🗂️</span> Kuis & Materi</a>
    <div class="nav-label">Sistem</div>
    <a href="kelola_admin.php" class="nav-link"><span class="nav-icon">🛡️</span> Kelola Admin</a>
  </nav>

  <div class="sidebar-bottom">
    <a href="../login/logout.php" class="nav-link logout"><span class="nav-icon">🚪</span> Logout</a>
  </div>
</aside>

<div class="main">
  <div class="topbar">
    <button class="topbar-hamburger" id="hamburgerBtn"><span></span><span></span><span></span></button>
    <h1>Mata Kuliah & Enroll</h1>
    <div class="topbar-right">
      <div class="topbar-badge">
        <div class="topbar-avatar">A</div>
        <?php echo htmlspecialchars($admin_nama); ?>
      </div>
    </div>
  </div>

  <div class="page">
    <?php if ($success): ?>
      <div class="alert alert-success">✅ <?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
      <div class="alert alert-error">⚠️ <?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if ($tab === 'detail' && $detailMatkul): ?>

      <div style="margin-bottom:16px;">
        <a href="kelola_matkul.php" class="btn btn-ghost btn-sm">← Kembali</a>
      </div>

      <div class="info-banner">
        <h3><?php echo htmlspecialchars($detailMatkul['nama_matkul']); ?></h3>
        <div style="font-size:14px; color:var(--text-2);">
          Detail lengkap mata kuliah, dosen pengampu, konten, dan mahasiswa terdaftar.
        </div>

        <div class="info-meta">
          <span class="info-chip">🎓 Angkatan <?php echo htmlspecialchars($detailMatkul['angkatan']); ?></span>
          <span class="info-chip">🏷️ Kelas <?php echo htmlspecialchars($detailMatkul['kelas']); ?></span>
          <span class="info-chip">👨‍🏫 <?php echo htmlspecialchars($detailMatkul['nama_dosen'] ?: '-'); ?></span>
        </div>
      </div>

      <div class="detail-grid">
        <div class="detail-card-mini">
          <h4>Total Mahasiswa</h4>
          <p><?php echo (int)($detailMatkul['total_mahasiswa'] ?? 0); ?></p>
        </div>
        <div class="detail-card-mini">
          <h4>Total Materi</h4>
          <p><?php echo $totalMateri; ?></p>
        </div>
        <div class="detail-card-mini">
          <h4>Total Kuis</h4>
          <p><?php echo $totalKuis; ?></p>
        </div>
        <div class="detail-card-mini">
          <h4>ID Mata Kuliah</h4>
          <p>#<?php echo (int)$detailMatkul['id']; ?></p>
        </div>
      </div>

      <div class="detail-layout" style="display:grid; grid-template-columns:1fr 320px; gap:20px; align-items:start;">
        <div>
          <div class="action-row">
            <h3 style="font-family:'Syne',sans-serif; font-size:16px; font-weight:700; margin:0;">Daftar Mahasiswa di Mata Kuliah Ini</h3>
            <span class="badge badge-gray"><?php echo (int)($detailMatkul['total_mahasiswa'] ?? 0); ?> mahasiswa</span>
          </div>

          <div class="table-wrap">
            <table class="admin-table">
              <thead>
                <tr>
                  <th>NIM</th>
                  <th>Nama</th>
                  <th>Email</th>
                  <th>Status</th>
                  <th>Aksi</th>
                </tr>
              </thead>
              <tbody>
                <?php if ($enrollList && $enrollList->num_rows > 0): ?>
                  <?php while ($r = $enrollList->fetch_assoc()): ?>
                    <tr>
                      <td style="font-family:'Syne',sans-serif; font-weight:600;"><?php echo htmlspecialchars($r['nim']); ?></td>
                      <td><?php echo htmlspecialchars($r['nama']); ?></td>
                      <td style="color:var(--text-2);"><?php echo htmlspecialchars($r['email']); ?></td>
                      <td>
                        <?php if ((int)$r['is_verified'] === 1): ?>
                          <span class="badge badge-green">✓ Terverifikasi</span>
                        <?php else: ?>
                          <span class="badge badge-red">Belum Verif</span>
                        <?php endif; ?>
                      </td>
                      <td>
                        <a href="?detail=<?php echo (int)$detailMatkul['id']; ?>&hapus_enroll=<?php echo urlencode($r['nim']); ?>&mid=<?php echo (int)$detailMatkul['id']; ?>"
                           class="btn btn-danger btn-sm"
                           onclick="return confirm('Hapus mahasiswa ini dari mata kuliah?')">
                          🗑️ Hapus
                        </a>
                      </td>
                    </tr>
                  <?php endwhile; ?>
                <?php else: ?>
                  <tr>
                    <td colspan="5">
                      <div class="empty-state">
                        <div class="icon">🎓</div>
                        <p>Belum ada mahasiswa terdaftar.</p>
                      </div>
                    </td>
                  </tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

        <div class="detail-side">
          <div class="card">
            <h3 style="font-family:'Syne',sans-serif; font-size:15px; font-weight:700; margin-bottom:16px;">+ Enroll Mahasiswa</h3>
            <form method="POST">
              <input type="hidden" name="matkul_id" value="<?php echo (int)$detailMatkul['id']; ?>">

              <div class="form-group">
                <label>NIM Mahasiswa</label>
                <input type="text" name="nim" required placeholder="Masukkan NIM mahasiswa">
              </div>

              <button type="submit" name="enroll_mhs" class="btn btn-red" style="width:100%;">Enroll Mahasiswa</button>
            </form>
          </div>
        </div>
      </div>

    <?php elseif ($tab === 'tambah'): ?>

      <div style="margin-bottom:16px;">
        <a href="kelola_matkul.php" class="btn btn-ghost btn-sm">← Kembali</a>
      </div>

      <div class="card" style="max-width:520px;">
        <h3 style="font-family:'Syne',sans-serif; font-size:16px; font-weight:700; margin-bottom:20px;">Tambah Mata Kuliah</h3>

        <form method="POST">
          <div class="form-group">
            <label>Nama Mata Kuliah</label>
            <input type="text" name="nama_matkul" required placeholder="Contoh: Algoritma dan Pemrograman">
          </div>

          <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
            <div class="form-group">
              <label>Angkatan</label>
              <input type="text" name="angkatan" required placeholder="2025">
            </div>

            <div class="form-group">
              <label>Kelas</label>
              <input type="text" name="kelas" required placeholder="A">
            </div>
          </div>

          <div class="form-group">
            <label>Dosen Pengampu</label>
            <select name="dosen_id" required>
              <option value="">-- Pilih Dosen --</option>
              <?php if ($dosenList): ?>
                <?php while ($d = $dosenList->fetch_assoc()): ?>
                  <option value="<?php echo (int)$d['id']; ?>"><?php echo htmlspecialchars($d['nama']); ?></option>
                <?php endwhile; ?>
              <?php endif; ?>
            </select>
          </div>

          <button type="submit" name="tambah_matkul" class="btn btn-red">📚 Simpan Mata Kuliah</button>
        </form>
      </div>

    <?php else: ?>

      <div class="action-row">
        <div>
          <h3 style="margin:0 0 4px;">Daftar Mata Kuliah</h3>
          <p style="margin:0; font-size:13px; color:var(--text-2);">Kelola mata kuliah, dosen pengampu, dan mahasiswa yang terdaftar.</p>
        </div>
        <a href="?tab=tambah" class="btn btn-red">+ Tambah Mata Kuliah</a>
      </div>

      <div class="table-wrap">
        <table class="admin-table">
          <thead>
            <tr>
              <th>Mata Kuliah</th>
              <th>Angkatan</th>
              <th>Kelas</th>
              <th>Dosen</th>
              <th>Mahasiswa</th>
              <th>Aksi</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($matkulList && $matkulList->num_rows > 0): ?>
              <?php while ($row = $matkulList->fetch_assoc()): ?>
                <tr>
                  <td><strong><?php echo htmlspecialchars($row['nama_matkul']); ?></strong></td>
                  <td><?php echo htmlspecialchars($row['angkatan']); ?></td>
                  <td><span class="badge badge-blue"><?php echo htmlspecialchars($row['kelas']); ?></span></td>
                  

                  <td>
                    <?php 
                      $dosens = array_filter(explode('||', $row['nama_dosen'] ?? ''));
                      if ($dosens):
                        foreach ($dosens as $d): ?>
                          <div style="font-size:13px; color:var(--text-2); padding:2px 0;"><?php echo htmlspecialchars(trim($d)); ?></div>
                        <?php endforeach;
                      else: ?>
                        <span style="color:var(--text-3);">-</span>
                      <?php endif; ?>
                  </td>

                  <td><span class="badge badge-gray"><?php echo (int)$row['total_mhs']; ?> mhs</span></td>
                  <td>
                    <div style="display:flex; flex-direction:column; gap:6px; min-width:110px;">
                      <a href="?detail=<?php echo (int)$row['id']; ?>" class="btn btn-ghost btn-sm" style="justify-content:flex-start;">ℹ️ Detail & Enroll</a>
                      <a href="?hapus=<?php echo (int)$row['id']; ?>" class="btn btn-danger btn-sm" style="justify-content:flex-start;"
                        onclick="return confirm('Hapus mata kuliah ini? Relasi dosen dan enroll mahasiswa juga akan dihapus.')">🗑️ Hapus</a>
                    </div>
                  </td>
                </tr>
              <?php endwhile; ?>
            <?php else: ?>
              <tr>
                <td colspan="6">
                  <div class="empty-state">
                    <div class="icon">📚</div>
                    <p>Belum ada mata kuliah.</p>
                  </div>
                </td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

    <?php endif; ?>
  </div>
</div>

<script>
const btn = document.getElementById('hamburgerBtn');
const sidebar = document.getElementById('sidebar');
const overlay = document.getElementById('mobileOverlay');

btn.addEventListener('click', () => {
  sidebar.classList.toggle('open');
  overlay.classList.toggle('show');
});

overlay.addEventListener('click', () => {
  sidebar.classList.remove('open');
  overlay.classList.remove('show');
});
</script>
</body>
</html>