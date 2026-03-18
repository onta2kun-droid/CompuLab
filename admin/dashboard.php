<?php
session_start();
require_once __DIR__ . '/../../sambung/forsafety.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login/admin.php"); exit();
}
$admin_nama = $_SESSION['user_nama'] ?? 'Admin';

// Stats
$stats = [];
$queries = [
    'total_dosen'     => "SELECT COUNT(*) c FROM akun WHERE role='dosen'",
    'total_mahasiswa' => "SELECT COUNT(*) c FROM akun WHERE role='mahasiswa'",
    'total_kuis'      => "SELECT COUNT(*) c FROM kuis",
    'total_materi'    => "SELECT COUNT(*) c FROM materi",
    'total_matkul'    => "SELECT COUNT(*) c FROM mata_kuliah",
    'otp_pending'     => "SELECT COUNT(*) c FROM akun WHERE role='mahasiswa' AND is_verified=0 AND verification_code IS NOT NULL",
];
foreach ($queries as $key => $sql) {
    $r = $conn->query($sql);
    $stats[$key] = $r ? (int)$r->fetch_assoc()['c'] : 0;
}

// Recent activity — latest akun registered
$recentQuery = $conn->query("SELECT nama, role, created_at FROM akun ORDER BY created_at DESC LIMIT 5");
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard | COMPULAB Admin</title>
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
    <a href="dashboard.php" class="nav-link active"><span class="nav-icon">📊</span> Overview</a>
    <a href="kelola_akun.php" class="nav-link"><span class="nav-icon">👥</span> Kelola Akun</a>
    <a href="kelola_matkul.php" class="nav-link"><span class="nav-icon">📚</span> Mata Kuliah</a>
    <a href="verifikasi_otp.php" class="nav-link">
      <span class="nav-icon">🔑</span> Verifikasi OTP
      <?php if ($stats['otp_pending'] > 0): ?>
        <span class="badge badge-red" style="margin-left:auto;"><?php echo $stats['otp_pending']; ?></span>
      <?php endif; ?>
    </a>
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
    <h1>Admin Control Panel</h1>
    <div class="topbar-right">
      <div class="topbar-badge" id="topbarBadge" onclick="this.classList.toggle('show')">
        <div class="topbar-avatar">A</div>
        <span class="topbar-badge-text"><?php echo htmlspecialchars($admin_nama); ?></span>
      </div>
    </div>
  </div>

  <div class="page">
    <div class="page-header">
      <h2>Overview</h2>
      <p>Selamat datang kembali, <?php echo htmlspecialchars($admin_nama); ?>. Berikut ringkasan sistem hari ini.</p>
    </div>

    <div class="stats-grid">
      <div class="stat-card" data-icon="👨‍🏫">
        <div class="stat-label">Total Dosen</div>
        <div class="stat-value"><?php echo $stats['total_dosen']; ?></div>
        <div class="stat-sub">Akun dosen aktif</div>
      </div>
      <div class="stat-card" data-icon="🎓">
        <div class="stat-label">Total Mahasiswa</div>
        <div class="stat-value"><?php echo $stats['total_mahasiswa']; ?></div>
        <div class="stat-sub">Terdaftar di sistem</div>
      </div>
      <div class="stat-card" data-icon="📝">
        <div class="stat-label">Total Kuis</div>
        <div class="stat-value"><?php echo $stats['total_kuis']; ?></div>
        <div class="stat-sub">Dari semua dosen</div>
      </div>
      <div class="stat-card accent" data-icon="📤">
        <div class="stat-label">Total Materi</div>
        <div class="stat-value"><?php echo $stats['total_materi']; ?></div>
        <div class="stat-sub">File terupload</div>
      </div>
    </div>

    <div class="dashboard-grid">

      <!-- Recent Registrations -->
      <div class="card">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
          <h3 style="font-family:'Syne',sans-serif; font-size:15px; font-weight:700;">Registrasi Terbaru</h3>
          <a href="kelola_akun.php" class="btn btn-ghost btn-sm">Lihat semua →</a>
        </div>
        <div class="table-wrap">
          <table class="admin-table">
            <thead><tr><th>Nama</th><th>Role</th><th>Tanggal</th></tr></thead>
            <tbody>
              <?php if ($recentQuery && $recentQuery->num_rows > 0):
                while ($row = $recentQuery->fetch_assoc()): ?>
              <tr>
                <td><?php echo htmlspecialchars($row['nama']); ?></td>
                <td>
                  <?php if ($row['role'] === 'dosen'): ?>
                    <span class="badge badge-blue">Dosen</span>
                  <?php elseif ($row['role'] === 'mahasiswa'): ?>
                    <span class="badge badge-green">Mahasiswa</span>
                  <?php else: ?>
                    <span class="badge badge-red">Admin</span>
                  <?php endif; ?>
                </td>
                <td style="color:var(--text-2); font-size:12px;"><?php echo date('d M Y', strtotime($row['created_at'])); ?></td>
              </tr>
              <?php endwhile; else: ?>
              <tr><td colspan="3"><div class="empty-state"><div class="icon">📭</div><p>Belum ada akun.</p></div></td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- System Status + Quick Actions -->
      <div style="display:flex; flex-direction:column; gap:16px;">
        <div class="card">
          <h3 style="font-family:'Syne',sans-serif; font-size:15px; font-weight:700; margin-bottom:14px;">Status Sistem</h3>
          <div style="display:flex; flex-direction:column; gap:10px; font-size:13px;">
            <div style="display:flex; justify-content:space-between; align-items:center;">
              <span style="color:var(--text-2);">Server</span>
              <span class="badge badge-green">🟢 Online</span>
            </div>
            <div style="display:flex; justify-content:space-between; align-items:center;">
              <span style="color:var(--text-2);">Database</span>
              <span class="badge badge-green">🟢 Connected</span>
            </div>
            <div style="display:flex; justify-content:space-between; align-items:center;">
              <span style="color:var(--text-2);">OTP Pending</span>
              <span class="badge <?php echo $stats['otp_pending'] > 0 ? 'badge-red' : 'badge-gray'; ?>">
                <?php echo $stats['otp_pending'] > 0 ? '⚠️ '.$stats['otp_pending'].' pending' : '✓ Bersih'; ?>
              </span>
            </div>
            <div style="display:flex; justify-content:space-between; align-items:center;">
              <span style="color:var(--text-2);">Mata Kuliah</span>
              <span class="badge badge-blue"><?php echo $stats['total_matkul']; ?> aktif</span>
            </div>
          </div>
        </div>

        <div class="card">
          <h3 style="font-family:'Syne',sans-serif; font-size:15px; font-weight:700; margin-bottom:14px;">Aksi Cepat</h3>
          <div style="display:flex; flex-direction:column; gap:8px;">
            <a href="kelola_akun.php?tab=tambah_dosen" class="btn btn-ghost" style="width:100%; justify-content:flex-start;">👨‍🏫 Tambah Dosen</a>
            <a href="kelola_matkul.php?tab=tambah" class="btn btn-ghost" style="width:100%; justify-content:flex-start;">📚 Tambah Mata Kuliah</a>
            <a href="verifikasi_otp.php" class="btn btn-red" style="width:100%; justify-content:flex-start;">🔑 Verifikasi OTP <?php if ($stats['otp_pending'] > 0) echo "(".$stats['otp_pending'].")"; ?></a>
          </div>
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