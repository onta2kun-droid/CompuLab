<?php
session_start();
include "../idk/forsafety.php";
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login/admin.php"); exit();
}
$admin_nama = $_SESSION['user_nama'] ?? 'Admin';
$success = $error = "";
$tab = $_GET['tab'] ?? 'dosen';

/* ── HAPUS AKUN ── */
if (isset($_GET['hapus']) && isset($_GET['role_hapus'])) {
    $nim_or_id = $_GET['hapus'];
    $role_hapus = $_GET['role_hapus'];
    if ($role_hapus === 'mahasiswa') {
        $st = $conn->prepare("DELETE FROM akun WHERE nim=? AND role='mahasiswa'");
        $st->bind_param("s", $nim_or_id);
    } else {
        $st = $conn->prepare("DELETE FROM akun WHERE id=? AND role='dosen'");
        $st->bind_param("i", $nim_or_id);
    }
    if ($st && $st->execute()) $success = "Akun berhasil dihapus.";
    else $error = "Gagal menghapus akun.";
}

/* ── TAMBAH DOSEN ── */
if (isset($_POST['tambah_dosen'])) {
    $nama  = trim($_POST['nama'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $pass  = trim($_POST['password'] ?? '');
    if ($nama && $email && $pass) {
        $hash = password_hash($pass, PASSWORD_DEFAULT);
        $wa = trim($_POST['whatsapp'] ?? '');
        $st = $conn->prepare("INSERT INTO akun (nama, email, whatsapp, password, role, is_verified) VALUES (?,?,?,?,'dosen',1)");
        $st->bind_param("ssss", $nama, $email, $wa, $hash);
        if ($st->execute()) { $success = "Dosen berhasil ditambahkan!"; $tab = 'dosen'; }
        else $error = "Gagal: email mungkin sudah terdaftar.";
    } else $error = "Semua field wajib diisi!";
}

/* ── RESET PASSWORD ── */
if (isset($_POST['reset_pass'])) {
    $akun_id  = (int)$_POST['akun_id'];
    $new_pass = trim($_POST['new_pass'] ?? '');
    if ($new_pass) {
        $hash = password_hash($new_pass, PASSWORD_DEFAULT);
        $st = $conn->prepare("UPDATE akun SET password=? WHERE id=?");
        $st->bind_param("si", $hash, $akun_id);
        if ($st->execute()) $success = "Password berhasil direset!";
        else $error = "Gagal reset password.";
    }
}

/* ── DATA ── */
$search = trim($_GET['q'] ?? '');
$dosen_list = [];
$mhs_list = [];

$sqlD = "SELECT id, nama, email, created_at FROM akun WHERE role='dosen'" . ($search ? " AND (nama LIKE ? OR email LIKE ?)" : "") . " ORDER BY created_at DESC";
$stD = $conn->prepare($sqlD);
if ($search) { $like = "%$search%"; $stD->bind_param("ss", $like, $like); }
$stD->execute(); $dosen_list = $stD->get_result()->fetch_all(MYSQLI_ASSOC);

$sqlM = "SELECT nim, nama, email, is_verified, created_at FROM akun WHERE role='mahasiswa'" . ($search ? " AND (nama LIKE ? OR email LIKE ? OR nim LIKE ?)" : "") . " ORDER BY created_at DESC";
$stM = $conn->prepare($sqlM);
if ($search) { $like = "%$search%"; $stM->bind_param("sss", $like, $like, $like); }
$stM->execute(); $mhs_list = $stM->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Kelola Akun | COMPULAB Admin</title>
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
    <a href="kelola_akun.php" class="nav-link active"><span class="nav-icon">👥</span> Kelola Akun</a>
    <a href="kelola_matkul.php" class="nav-link"><span class="nav-icon">📚</span> Mata Kuliah</a>
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
    <h1>Kelola Akun</h1>
    <div class="topbar-right">
      <div class="topbar-badge"><div class="topbar-avatar">A</div><?php echo htmlspecialchars($admin_nama); ?></div>
    </div>
  </div>

  <div class="page">
    <?php if ($success): ?><div class="alert alert-success">✅ <?php echo htmlspecialchars($success); ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-error">⚠️ <?php echo htmlspecialchars($error); ?></div><?php endif; ?>

    <!-- Tabs -->
    <div style="display:flex; gap:6px; margin-bottom:20px; border-bottom:1px solid var(--border); padding-bottom:0;">
      <?php foreach (['dosen'=>'👨‍🏫 Dosen','mahasiswa'=>'🎓 Mahasiswa','tambah_dosen'=>'+ Tambah Dosen'] as $k => $label): ?>
        <a href="?tab=<?php echo $k; ?>" style="padding:10px 16px; font-size:13px; font-weight:600; text-decoration:none; border-bottom:2px solid <?php echo $tab===$k ? 'var(--crimson)' : 'transparent'; ?>; color:<?php echo $tab===$k ? 'var(--crimson-light)' : 'var(--text-2)'; ?>; margin-bottom:-1px; transition:color 0.2s;">
          <?php echo $label; ?>
        </a>
      <?php endforeach; ?>
    </div>

    <?php if ($tab === 'tambah_dosen'): ?>
    <!-- Form Tambah Dosen -->
    <div class="card" style="max-width:480px;">
      <h3 style="font-family:'Syne',sans-serif; font-size:16px; font-weight:700; margin-bottom:20px;">Tambah Akun Dosen</h3>
      <form method="POST">
        <div class="form-group"><label>Nama Lengkap</label><input type="text" name="nama" required placeholder="Nama dosen"></div>
        <div class="form-group"><label>Email</label><input type="email" name="email" required placeholder="email@kampus.ac.id"></div>
        <div class="form-group"><label>No. WhatsApp</label><input type="text" name="whatsapp" required placeholder="08123456789"></div>
        <div class="form-group"><label>Password</label><input type="password" name="password" required placeholder="Password awal"></div>
        <button type="submit" name="tambah_dosen" class="btn btn-red">👨‍🏫 Tambah Dosen</button>
      </form>
    </div>

    <?php elseif ($tab === 'dosen'): ?>
    <!-- Daftar Dosen -->
    <div class="search-bar">
      <div class="search-input-wrap">
        <input type="text" id="searchInput" placeholder="Cari nama atau email dosen..." value="<?php echo htmlspecialchars($search); ?>" onkeydown="if(event.key==='Enter')window.location='?tab=dosen&q='+encodeURIComponent(this.value)">
      </div>
      <button class="btn btn-ghost" onclick="window.location='?tab=dosen&q='+document.getElementById('searchInput').value">Cari</button>
      <a href="?tab=tambah_dosen" class="btn btn-red">+ Tambah Dosen</a>
    </div>
    <div class="table-wrap">
      <table class="admin-table">
        <thead><tr><th>No</th><th>Nama</th><th>Email</th><th>Terdaftar</th><th>Aksi</th></tr></thead>
        <tbody>
          <?php if ($dosen_list): $no=1; foreach ($dosen_list as $row): ?>
          <tr>
            <td style="color:var(--text-3);"><?php echo $no++; ?></td>
            <td><strong><?php echo htmlspecialchars($row['nama']); ?></strong></td>
            <td style="color:var(--text-2);"><?php echo htmlspecialchars($row['email']); ?></td>
            <td style="color:var(--text-3); font-size:12px;"><?php echo date('d M Y', strtotime($row['created_at'])); ?></td>
            <td>
              <div style="display:flex; gap:6px;">
                <button class="btn btn-ghost btn-sm" onclick="openReset(<?php echo $row['id']; ?>,'<?php echo htmlspecialchars($row['nama']); ?>')">🔑 Reset</button>
                <a href="?tab=dosen&hapus=<?php echo $row['id']; ?>&role_hapus=dosen" class="btn btn-danger btn-sm" onclick="return confirm('Hapus akun <?php echo htmlspecialchars($row['nama']); ?>?')">🗑️</a>
              </div>
            </td>
          </tr>
          <?php endforeach; else: ?>
          <tr><td colspan="5"><div class="empty-state"><div class="icon">👤</div><p>Tidak ada dosen.</p></div></td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <?php elseif ($tab === 'mahasiswa'): ?>
    <!-- Daftar Mahasiswa -->
    <div class="search-bar">
      <div class="search-input-wrap">
        <input type="text" id="searchInput" placeholder="Cari nama, NIM, atau email..." value="<?php echo htmlspecialchars($search); ?>" onkeydown="if(event.key==='Enter')window.location='?tab=mahasiswa&q='+encodeURIComponent(this.value)">
      </div>
      <button class="btn btn-ghost" onclick="window.location='?tab=mahasiswa&q='+document.getElementById('searchInput').value">Cari</button>
    </div>
    <div class="table-wrap">
      <table class="admin-table">
        <thead><tr><th>NIM</th><th>Nama</th><th>Email</th><th>Status</th><th>Terdaftar</th><th>Aksi</th></tr></thead>
        <tbody>
          <?php if ($mhs_list): foreach ($mhs_list as $row): ?>
          <tr>
            <td style="font-family:'Syne',sans-serif; font-weight:600; font-size:13px;"><?php echo htmlspecialchars($row['nim']); ?></td>
            <td><?php echo htmlspecialchars($row['nama']); ?></td>
            <td style="color:var(--text-2);"><?php echo htmlspecialchars($row['email']); ?></td>
            <td>
              <?php if ($row['is_verified']): ?>
                <span class="badge badge-green">✓ Terverifikasi</span>
              <?php else: ?>
                <span class="badge badge-red">⏳ Belum Verif</span>
              <?php endif; ?>
            </td>
            <td style="color:var(--text-3); font-size:12px;"><?php echo date('d M Y', strtotime($row['created_at'])); ?></td>
            <td>
              <a href="?tab=mahasiswa&hapus=<?php echo urlencode($row['nim']); ?>&role_hapus=mahasiswa" class="btn btn-danger btn-sm" onclick="return confirm('Hapus akun <?php echo htmlspecialchars($row['nama']); ?>?')">🗑️ Hapus</a>
            </td>
          </tr>
          <?php endforeach; else: ?>
          <tr><td colspan="6"><div class="empty-state"><div class="icon">🎓</div><p>Tidak ada mahasiswa.</p></div></td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- Modal Reset Password -->
<div class="modal-overlay" id="resetModal">
  <div class="modal-box">
    <div class="modal-header">
      <h3>🔑 Reset Password</h3>
      <button class="modal-close" onclick="closeReset()">✕</button>
    </div>
    <p style="font-size:13px; color:var(--text-2); margin-bottom:16px;">Reset password untuk: <strong id="resetNama"></strong></p>
    <form method="POST">
      <input type="hidden" name="akun_id" id="resetId">
      <div class="form-group"><label>Password Baru</label><input type="password" name="new_pass" required placeholder="Masukkan password baru"></div>
      <div style="display:flex; gap:10px; margin-top:8px;">
        <button type="submit" name="reset_pass" class="btn btn-red">Simpan</button>
        <button type="button" class="btn btn-ghost" onclick="closeReset()">Batal</button>
      </div>
    </form>
  </div>
</div>

<script>
const btn = document.getElementById('hamburgerBtn');
const sidebar = document.getElementById('sidebar');
const overlay = document.getElementById('mobileOverlay');
btn.addEventListener('click', () => { sidebar.classList.toggle('open'); overlay.classList.toggle('show'); });
overlay.addEventListener('click', () => { sidebar.classList.remove('open'); overlay.classList.remove('show'); });

function openReset(id, nama) {
  document.getElementById('resetId').value = id;
  document.getElementById('resetNama').textContent = nama;
  document.getElementById('resetModal').classList.add('show');
}
function closeReset() { document.getElementById('resetModal').classList.remove('show'); }
document.getElementById('resetModal').addEventListener('click', e => { if (e.target === document.getElementById('resetModal')) closeReset(); });
</script>
</body>
</html>