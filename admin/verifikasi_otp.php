<?php
session_start();
include "../idk/forsafety.php";
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login/admin.php"); exit();
}
$admin_nama = $_SESSION['user_nama'] ?? 'Admin';
$success = $error = "";

/* ── APPROVE OTP ── */
if (isset($_GET['approve'])) {
    $nim = $_GET['approve'];
    $st = $conn->prepare("UPDATE akun SET is_verified=1, verification_code=NULL, verification_expires_at=NULL WHERE nim=? AND role='mahasiswa'");
    $st->bind_param("s", $nim);
    if ($st->execute()) $success = "Mahasiswa berhasil diverifikasi!";
    else $error = "Gagal memverifikasi.";
}

/* ── REJECT OTP ── */
if (isset($_GET['reject'])) {
    $nim = $_GET['reject'];
    $st = $conn->prepare("UPDATE akun SET verification_code=NULL, verification_expires_at=NULL WHERE nim=? AND role='mahasiswa'");
    $st->bind_param("s", $nim);
    if ($st->execute()) $success = "OTP berhasil ditolak/dihapus.";
    else $error = "Gagal menolak OTP.";
}

/* ── AMBIL DATA ── */
$pending = $conn->query("SELECT nim, nama, email, verification_code, verification_expires_at FROM akun WHERE role='mahasiswa' AND is_verified=0 AND verification_code IS NOT NULL ORDER BY verification_expires_at ASC");
$verified = $conn->query("SELECT nim, nama, email, created_at FROM akun WHERE role='mahasiswa' AND is_verified=1 ORDER BY created_at DESC LIMIT 20");
$now = time();
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Verifikasi OTP | COMPULAB Admin</title>
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
    <a href="kelola_matkul.php" class="nav-link"><span class="nav-icon">📚</span> Mata Kuliah</a>
    <a href="verifikasi_otp.php" class="nav-link active"><span class="nav-icon">🔑</span> Verifikasi OTP</a>
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
    <h1>Verifikasi OTP 🔑</h1>
    <div class="topbar-right">
      <div class="topbar-badge"><div class="topbar-avatar">A</div><?php echo htmlspecialchars($admin_nama); ?></div>
    </div>
  </div>

  <div class="page">
    <?php if ($success): ?><div class="alert alert-success">✅ <?php echo htmlspecialchars($success); ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-error">⚠️ <?php echo htmlspecialchars($error); ?></div><?php endif; ?>

    <div class="page-header">
      <h2>OTP Pending</h2>
      <p>Mahasiswa berikut menunggu verifikasi. Kirimkan kode OTP ke WhatsApp mereka, lalu approve.</p>
    </div>

    <?php if ($pending && $pending->num_rows > 0): ?>
    <div style="display:flex; flex-direction:column; gap:12px; margin-bottom:32px;">
      <?php while ($row = $pending->fetch_assoc()):
        $expires = strtotime($row['verification_expires_at']);
        $isExpired = $expires < $now;
        $minsLeft = max(0, floor(($expires - $now) / 60));
      ?>
      <div class="otp-card" style="<?php echo $isExpired ? 'opacity:0.6;' : ''; ?>">
        <div>
          <div class="otp-name"><?php echo htmlspecialchars($row['nama']); ?></div>
          <div class="otp-meta">
            NIM: <?php echo htmlspecialchars($row['nim']); ?> &nbsp;·&nbsp;
            <?php echo htmlspecialchars($row['email']); ?> &nbsp;·&nbsp;
            <?php if ($isExpired): ?>
              <span style="color:var(--crimson-light);">⚠️ Expired</span>
            <?php else: ?>
              <span style="color:var(--green);">⏱ <?php echo $minsLeft; ?> menit lagi</span>
            <?php endif; ?>
          </div>
          <div class="otp-code"><?php echo htmlspecialchars($row['verification_code']); ?></div>
        </div>
        <div style="display:flex; flex-direction:column; gap:8px; align-items:flex-end;">
          <?php
            $wa_num = preg_replace('/^0/', '62', preg_replace('/[^0-9]/', '', $row['email'] ?? ''));
            $wa_msg = rawurlencode("Halo " . $row['nama'] . "! Kode OTP COMPULAB Anda: *" . $row['verification_code'] . "*\nBerlaku " . $minsLeft . " menit lagi.");
          ?>
          <a href="https://wa.me/?text=<?php echo $wa_msg; ?>" target="_blank" class="btn btn-green btn-sm">📱 Kirim WA</a>
          <a href="?approve=<?php echo urlencode($row['nim']); ?>" class="btn btn-red btn-sm" onclick="return confirm('Approve verifikasi <?php echo htmlspecialchars($row['nama']); ?>?')">✅ Approve</a>
          <a href="?reject=<?php echo urlencode($row['nim']); ?>" class="btn btn-ghost btn-sm" onclick="return confirm('Tolak/hapus OTP ini?')">❌ Tolak</a>
        </div>
      </div>
      <?php endwhile; ?>
    </div>
    <?php else: ?>
    <div class="card" style="margin-bottom:32px;">
      <div class="empty-state"><div class="icon">🎉</div><p>Tidak ada OTP yang menunggu verifikasi.</p></div>
    </div>
    <?php endif; ?>

    <!-- Recently Verified -->
    <h3 style="font-family:'Syne',sans-serif; font-size:15px; font-weight:700; margin-bottom:14px;">Terverifikasi Terbaru</h3>
    <div class="table-wrap">
      <table class="admin-table">
        <thead><tr><th>NIM</th><th>Nama</th><th>Email</th><th>Terdaftar</th></tr></thead>
        <tbody>
          <?php if ($verified && $verified->num_rows > 0):
            while ($row = $verified->fetch_assoc()): ?>
          <tr>
            <td style="font-family:'Syne',sans-serif; font-weight:600;"><?php echo htmlspecialchars($row['nim']); ?></td>
            <td><?php echo htmlspecialchars($row['nama']); ?></td>
            <td style="color:var(--text-2);"><?php echo htmlspecialchars($row['email']); ?></td>
            <td style="color:var(--text-3); font-size:12px;"><?php echo date('d M Y', strtotime($row['created_at'])); ?></td>
          </tr>
          <?php endwhile; else: ?>
          <tr><td colspan="4"><div class="empty-state"><div class="icon">📭</div><p>Belum ada.</p></div></td></tr>
          <?php endif; ?>
        </tbody>
      </table>
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