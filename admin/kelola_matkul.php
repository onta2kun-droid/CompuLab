<?php
session_start();
include "../idk/forsafety.php";
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login/admin.php"); exit();
}
$admin_nama = $_SESSION['user_nama'] ?? 'Admin';
$success = $error = "";
$tab = $_GET['tab'] ?? 'list';

/* ── HAPUS MATKUL ── */
if (isset($_GET['hapus'])) {
    $mid = (int)$_GET['hapus'];
    $st = $conn->prepare("DELETE FROM mata_kuliah WHERE id=?");
    $st->bind_param("i", $mid);
    if ($st->execute()) $success = "Mata kuliah berhasil dihapus.";
    else $error = "Gagal menghapus.";
}

/* ── TAMBAH MATKUL ── */
if (isset($_POST['tambah_matkul'])) {
    $nama    = trim($_POST['nama_matkul'] ?? '');
    $angkatan = trim($_POST['angkatan'] ?? '');
    $kelas   = trim($_POST['kelas'] ?? '');
    $dosen_id = (int)($_POST['dosen_id'] ?? 0);
    if ($nama && $angkatan && $kelas && $dosen_id) {
        $st = $conn->prepare("INSERT INTO mata_kuliah (nama_matkul, angkatan, kelas) VALUES (?,?,?)");
        $st->bind_param("sss", $nama, $angkatan, $kelas);
        if ($st->execute()) {
            $matkul_id = $conn->insert_id;
            $st2 = $conn->prepare("INSERT INTO dosen_matkul (dosen_id, matkul_id) VALUES (?,?)");
            $st2->bind_param("ii", $dosen_id, $matkul_id);
            $st2->execute();
            $success = "Mata kuliah berhasil ditambahkan!"; $tab = 'list';
        } else $error = "Gagal menyimpan mata kuliah.";
    } else $error = "Semua field wajib diisi!";
}

/* ── ENROLL MAHASISWA ── */
if (isset($_POST['enroll_mhs'])) {
    $matkul_id = (int)$_POST['matkul_id'];
    $nim = trim($_POST['nim'] ?? '');
    if ($matkul_id && $nim) {
        $cek = $conn->prepare("SELECT 1 FROM mahasiswa_matkul WHERE nim=? AND matkul_id=?");
        $cek->bind_param("si", $nim, $matkul_id);
        $cek->execute();
        if ($cek->get_result()->num_rows > 0) $error = "Mahasiswa sudah terdaftar di matkul ini!";
        else {
            $cekMhs = $conn->prepare("SELECT nim FROM akun WHERE nim=? AND role='mahasiswa'");
            $cekMhs->bind_param("s", $nim);
            $cekMhs->execute();
            if ($cekMhs->get_result()->num_rows === 0) $error = "NIM tidak ditemukan!";
            else {
                $st = $conn->prepare("INSERT INTO mahasiswa_matkul (nim, matkul_id) VALUES (?,?)");
                $st->bind_param("si", $nim, $matkul_id);
                if ($st->execute()) $success = "Mahasiswa berhasil dienroll!";
                else $error = "Gagal enroll.";
            }
        }
    }
}

/* ── HAPUS ENROLL ── */
if (isset($_GET['hapus_enroll'])) {
    $nim = $_GET['hapus_enroll'];
    $mid = (int)($_GET['mid'] ?? 0);
    $st = $conn->prepare("DELETE FROM mahasiswa_matkul WHERE nim=? AND matkul_id=?");
    $st->bind_param("si", $nim, $mid);
    if ($st->execute()) $success = "Enroll berhasil dihapus.";
}

/* ── DATA ── */
$matkulList = $conn->query("SELECT mk.id, mk.nama_matkul, mk.angkatan, mk.kelas, a.nama AS nama_dosen, COUNT(DISTINCT mm.nim) AS total_mhs FROM mata_kuliah mk LEFT JOIN dosen_matkul dm ON mk.id=dm.matkul_id LEFT JOIN akun a ON dm.dosen_id=a.id LEFT JOIN mahasiswa_matkul mm ON mk.id=mm.matkul_id GROUP BY mk.id ORDER BY mk.nama_matkul, mk.kelas");
$dosenList = $conn->query("SELECT id, nama FROM akun WHERE role='dosen' ORDER BY nama");

$detailMatkul = null;
$enrollList = null;
if (isset($_GET['detail'])) {
    $mid = (int)$_GET['detail'];
    $r = $conn->query("SELECT mk.*, a.nama AS nama_dosen FROM mata_kuliah mk LEFT JOIN dosen_matkul dm ON mk.id=dm.matkul_id LEFT JOIN akun a ON dm.dosen_id=a.id WHERE mk.id=$mid LIMIT 1");
    $detailMatkul = $r ? $r->fetch_assoc() : null;
    $enrollList = $conn->query("SELECT a.nim, a.nama, a.email FROM mahasiswa_matkul mm JOIN akun a ON mm.nim=a.nim WHERE mm.matkul_id=$mid ORDER BY a.nama");
    $tab = 'detail';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Mata Kuliah | COMPULAB Admin</title>
<link rel="stylesheet" href="../css/admin.css">
</head>
<body>
<div class="mobile-overlay" id="mobileOverlay"></div>
<aside class="sidebar" id="sidebar">
  <div class="sidebar-logo">
    <div class="sidebar-logo-mark">C</div>
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
      <div class="topbar-badge"><div class="topbar-avatar">A</div><?php echo htmlspecialchars($admin_nama); ?></div>
    </div>
  </div>

  <div class="page">
    <?php if ($success): ?><div class="alert alert-success">✅ <?php echo htmlspecialchars($success); ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-error">⚠️ <?php echo htmlspecialchars($error); ?></div><?php endif; ?>

    <?php if ($tab === 'detail' && $detailMatkul): ?>
    <!-- Detail / Enroll -->
    <div style="margin-bottom:16px;">
      <a href="kelola_matkul.php" class="btn btn-ghost btn-sm">← Kembali</a>
    </div>
    <div class="card" style="margin-bottom:20px;">
      <h3 style="font-family:'Syne',sans-serif; font-size:17px; font-weight:700; margin-bottom:6px;"><?php echo htmlspecialchars($detailMatkul['nama_matkul']); ?></h3>
      <div style="font-size:13px; color:var(--text-2);">Angkatan <?php echo $detailMatkul['angkatan']; ?> · Kelas <?php echo $detailMatkul['kelas']; ?> · Dosen: <strong style="color:var(--text-1);"><?php echo htmlspecialchars($detailMatkul['nama_dosen'] ?? '-'); ?></strong></div>
    </div>

    <div style="display:grid; grid-template-columns:1fr 300px; gap:20px; align-items:start;">
      <div>
        <h3 style="font-family:'Syne',sans-serif; font-size:15px; font-weight:700; margin-bottom:14px;">Mahasiswa Terdaftar</h3>
        <div class="table-wrap">
          <table class="admin-table">
            <thead><tr><th>NIM</th><th>Nama</th><th>Email</th><th>Aksi</th></tr></thead>
            <tbody>
              <?php if ($enrollList && $enrollList->num_rows > 0):
                while ($r = $enrollList->fetch_assoc()): ?>
              <tr>
                <td style="font-family:'Syne',sans-serif; font-weight:600;"><?php echo htmlspecialchars($r['nim']); ?></td>
                <td><?php echo htmlspecialchars($r['nama']); ?></td>
                <td style="color:var(--text-2);"><?php echo htmlspecialchars($r['email']); ?></td>
                <td><a href="?detail=<?php echo $detailMatkul['id']; ?>&hapus_enroll=<?php echo urlencode($r['nim']); ?>&mid=<?php echo $detailMatkul['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Hapus enroll?')">🗑️</a></td>
              </tr>
              <?php endwhile; else: ?>
              <tr><td colspan="4"><div class="empty-state"><div class="icon">🎓</div><p>Belum ada mahasiswa.</p></div></td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <div class="card">
        <h3 style="font-family:'Syne',sans-serif; font-size:15px; font-weight:700; margin-bottom:16px;">+ Enroll Mahasiswa</h3>
        <form method="POST">
          <input type="hidden" name="matkul_id" value="<?php echo $detailMatkul['id']; ?>">
          <div class="form-group"><label>NIM Mahasiswa</label><input type="text" name="nim" required placeholder="Masukkan NIM"></div>
          <button type="submit" name="enroll_mhs" class="btn btn-red" style="width:100%;">Enroll</button>
        </form>
      </div>
    </div>

    <?php elseif ($tab === 'tambah'): ?>
    <!-- Tambah Matkul -->
    <div style="margin-bottom:16px;"><a href="kelola_matkul.php" class="btn btn-ghost btn-sm">← Kembali</a></div>
    <div class="card" style="max-width:480px;">
      <h3 style="font-family:'Syne',sans-serif; font-size:16px; font-weight:700; margin-bottom:20px;">Tambah Mata Kuliah</h3>
      <form method="POST">
        <div class="form-group"><label>Nama Mata Kuliah</label><input type="text" name="nama_matkul" required placeholder="Contoh: Algoritma dan Pemrograman"></div>
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
          <div class="form-group"><label>Angkatan</label><input type="text" name="angkatan" required placeholder="2025"></div>
          <div class="form-group"><label>Kelas</label><input type="text" name="kelas" required placeholder="A"></div>
        </div>
        <div class="form-group">
          <label>Dosen Pengampu</label>
          <select name="dosen_id" required>
            <option value="">-- Pilih Dosen --</option>
            <?php if ($dosenList): while ($d = $dosenList->fetch_assoc()): ?>
              <option value="<?php echo $d['id']; ?>"><?php echo htmlspecialchars($d['nama']); ?></option>
            <?php endwhile; endif; ?>
          </select>
        </div>
        <button type="submit" name="tambah_matkul" class="btn btn-red">📚 Simpan</button>
      </form>
    </div>

    <?php else: ?>
    <!-- Daftar Matkul -->
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
      <div></div>
      <a href="?tab=tambah" class="btn btn-red">+ Tambah Mata Kuliah</a>
    </div>
    <div class="table-wrap">
      <table class="admin-table">
        <thead><tr><th>Mata Kuliah</th><th>Angkatan</th><th>Kelas</th><th>Dosen</th><th>Mahasiswa</th><th>Aksi</th></tr></thead>
        <tbody>
          <?php if ($matkulList && $matkulList->num_rows > 0):
            while ($row = $matkulList->fetch_assoc()): ?>
          <tr>
            <td><strong><?php echo htmlspecialchars($row['nama_matkul']); ?></strong></td>
            <td><?php echo $row['angkatan']; ?></td>
            <td><span class="badge badge-blue"><?php echo $row['kelas']; ?></span></td>
            <td style="color:var(--text-2);"><?php echo htmlspecialchars($row['nama_dosen'] ?? '-'); ?></td>
            <td><span class="badge badge-gray"><?php echo $row['total_mhs']; ?> mhs</span></td>
            <td>
              <div style="display:flex; gap:6px;">
                <a href="?detail=<?php echo $row['id']; ?>" class="btn btn-ghost btn-sm">👥 Enroll</a>
                <a href="?hapus=<?php echo $row['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Hapus mata kuliah ini?')">🗑️</a>
              </div>
            </td>
          </tr>
          <?php endwhile; else: ?>
          <tr><td colspan="6"><div class="empty-state"><div class="icon">📚</div><p>Belum ada mata kuliah.</p></div></td></tr>
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
btn.addEventListener('click', () => { sidebar.classList.toggle('open'); overlay.classList.toggle('show'); });
overlay.addEventListener('click', () => { sidebar.classList.remove('open'); overlay.classList.remove('show'); });
</script>
</body>
</html>