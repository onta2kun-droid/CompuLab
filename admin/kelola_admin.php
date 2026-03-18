<?php
session_start();
require_once __DIR__ . '/../../sambung/forsafety.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login/admin.php"); exit();
}
$admin_id   = $_SESSION['user_id'];
$admin_nama = $_SESSION['user_nama'] ?? 'Admin';
$success = $error = "";

/* ── TAMBAH ADMIN ── */
if (isset($_POST['tambah_admin'])) {
    $nama  = trim($_POST['nama'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $pass  = trim($_POST['password'] ?? '');
    $wa    = trim($_POST['whatsapp'] ?? '');
    if ($nama && $email && $pass) {
        $hash = password_hash($pass, PASSWORD_DEFAULT);
        $st = $conn->prepare("INSERT INTO akun (nama, email, whatsapp, password, role, is_verified) VALUES (?,?,?,?,'admin',1)");
        $st->bind_param("ssss", $nama, $email, $wa, $hash);
        if ($st->execute()) $success = "Akun admin berhasil ditambahkan!";
        else $error = "Gagal: email mungkin sudah terdaftar.";
    } else $error = "Semua field wajib diisi!";
}

/* ── HAPUS ADMIN ── */
if (isset($_GET['hapus'])) {
    $hid = (int)$_GET['hapus'];
    if ($hid === $admin_id) $error = "Tidak bisa menghapus akun sendiri!";
    else {
        $st = $conn->prepare("DELETE FROM akun WHERE id=? AND role='admin'");
        $st->bind_param("i", $hid);
        if ($st->execute()) $success = "Admin berhasil dihapus.";
        else $error = "Gagal menghapus.";
    }
}

/* ── GANTI PASSWORD SENDIRI ── */
if (isset($_POST['ganti_pass'])) {
    $old_pass = $_POST['old_pass'] ?? '';
    $new_pass = $_POST['new_pass'] ?? '';
    $r = $conn->query("SELECT password FROM akun WHERE id=$admin_id LIMIT 1");
    $row = $r->fetch_assoc();
    if (!password_verify($old_pass, $row['password'])) $error = "Password lama salah!";
    elseif (strlen($new_pass) < 6) $error = "Password baru minimal 6 karakter!";
    else {
        $hash = password_hash($new_pass, PASSWORD_DEFAULT);
        $conn->query("UPDATE akun SET password='$hash' WHERE id=$admin_id");
        $success = "Password berhasil diubah!";
    }
}

$adminList = $conn->query("SELECT id, nama, email, created_at FROM akun WHERE role='admin' ORDER BY created_at ASC");
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Kelola Admin | COMPULAB Admin</title>
<link rel="stylesheet" href="../css/admin.css">
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
    <a href="kelola_matkul.php" class="nav-link"><span class="nav-icon">📚</span> Mata Kuliah</a>
    <a href="verifikasi_otp.php" class="nav-link"><span class="nav-icon">🔑</span> Verifikasi OTP</a>
    <a href="kelola_konten.php" class="nav-link"><span class="nav-icon">🗂️</span> Kuis & Materi</a>
    <div class="nav-label">Sistem</div>
    <a href="kelola_admin.php" class="nav-link active"><span class="nav-icon">🛡️</span> Kelola Admin</a>
  </nav>
  <div class="sidebar-bottom">
    <a href="../login/logout.php" class="nav-link logout"><span class="nav-icon">🚪</span> Logout</a>
  </div>
</aside>

<div class="main">
  <div class="topbar">
    <button class="topbar-hamburger" id="hamburgerBtn"><span></span><span></span><span></span></button>
    <h1>Kelola Admin 🛡️</h1>
    <div class="topbar-right">
      <div class="topbar-badge"><div class="topbar-avatar">A</div><?php echo htmlspecialchars($admin_nama); ?></div>
    </div>
  </div>

  <div class="page">
    <?php if ($success): ?><div class="alert alert-success">✅ <?php echo htmlspecialchars($success); ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-error">⚠️ <?php echo htmlspecialchars($error); ?></div><?php endif; ?>

    <div style="display:grid; grid-template-columns:1fr 320px; gap:24px; align-items:start;">

      <!-- Daftar Admin -->
      <div>
        <h3 style="font-family:'Syne',sans-serif; font-size:15px; font-weight:700; margin-bottom:14px;">Daftar Akun Admin</h3>
        <div class="table-wrap">
          <table class="admin-table">
            <thead><tr><th>Nama</th><th>Email</th><th>Terdaftar</th><th>Aksi</th></tr></thead>
            <tbody>
              <?php if ($adminList && $adminList->num_rows > 0):
                while ($row = $adminList->fetch_assoc()): ?>
              <tr>
                <td>
                  <strong><?php echo htmlspecialchars($row['nama']); ?></strong>
                  <?php if ($row['id'] == $admin_id): ?><span class="badge badge-red" style="margin-left:6px;">Anda</span><?php endif; ?>
                </td>
                <td style="color:var(--text-2);"><?php echo htmlspecialchars($row['email']); ?></td>
                <td style="color:var(--text-3); font-size:12px;"><?php echo date('d M Y', strtotime($row['created_at'])); ?></td>
                <td>
                  <?php if ($row['id'] != $admin_id): ?>
                    <a href="?hapus=<?php echo $row['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Hapus akun admin ini?')">🗑️</a>
                  <?php else: ?>
                    <span style="font-size:12px; color:var(--text-3);">—</span>
                  <?php endif; ?>
                </td>
              </tr>
              <?php endwhile; else: ?>
              <tr><td colspan="4"><div class="empty-state"><div class="icon">🛡️</div><p>Belum ada admin.</p></div></td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Sidebar Forms -->
      <div style="display:flex; flex-direction:column; gap:16px;">
        <!-- Tambah Admin -->
        <div class="card">
          <h3 style="font-family:'Syne',sans-serif; font-size:15px; font-weight:700; margin-bottom:16px;">+ Tambah Admin</h3>
          <form method="POST">
            <div class="form-group"><label>Nama</label><input type="text" name="nama" required placeholder="Nama admin"></div>
            <div class="form-group"><label>Email</label><input type="email" name="email" required placeholder="email@admin.com"></div>
            <div class="form-group"><label>No. WhatsApp</label><input type="text" name="whatsapp" placeholder="08123456789"></div>
            <div class="form-group"><label>Password</label><input type="password" name="password" required placeholder="Min. 6 karakter"></div>
            <button type="submit" name="tambah_admin" class="btn btn-red" style="width:100%;">Tambah Admin</button>
          </form>
        </div>

        <!-- Ganti Password -->
        <div class="card">
          <h3 style="font-family:'Syne',sans-serif; font-size:15px; font-weight:700; margin-bottom:16px;">🔑 Ganti Password Saya</h3>
          <form method="POST">
            <div class="form-group"><label>Password Lama</label><input type="password" name="old_pass" required placeholder="Password saat ini"></div>
            <div class="form-group"><label>Password Baru</label><input type="password" name="new_pass" required placeholder="Min. 6 karakter"></div>
            <button type="submit" name="ganti_pass" class="btn btn-ghost" style="width:100%;">Simpan Perubahan</button>
          </form>
        </div>
      </div>
    </div>
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