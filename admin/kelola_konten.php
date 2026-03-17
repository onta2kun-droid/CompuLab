<?php
session_start();
require_once __DIR__ . '/../../sambung/forsafety.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login/admin.php"); exit();
}
$admin_nama = $_SESSION['user_nama'] ?? 'Admin';
$success = $error = "";
$tab = $_GET['tab'] ?? 'kuis';

/* ── HAPUS KUIS ── */
if (isset($_GET['hapus_kuis'])) {
    $id = (int)$_GET['hapus_kuis'];
    $conn->query("DELETE FROM hasil_kuis WHERE kuis_id=$id");
    $conn->query("DELETE FROM soal_kuis WHERE kuis_id=$id");
    $st = $conn->prepare("DELETE FROM kuis WHERE id=?");
    $st->bind_param("i", $id);
    if ($st->execute()) $success = "Kuis berhasil dihapus.";
    else $error = "Gagal menghapus kuis.";
}

/* ── HAPUS MATERI ── */
if (isset($_GET['hapus_materi'])) {
    $id = (int)$_GET['hapus_materi'];
    $r = $conn->query("SELECT file_materi FROM materi WHERE id=$id");
    if ($r) {
        $row = $r->fetch_assoc();
        if ($row['file_materi'] && file_exists("../uploads/materi/".$row['file_materi'])) unlink("../uploads/materi/".$row['file_materi']);
    }
    $st = $conn->prepare("DELETE FROM materi WHERE id=?");
    $st->bind_param("i", $id);
    if ($st->execute()) $success = "Materi berhasil dihapus.";
    else $error = "Gagal menghapus materi.";
}

/* ── DATA ── */
$kuisList = $conn->query("SELECT k.id, k.judul, k.durasi, k.waktu_mulai, k.waktu_selesai, mk.nama_matkul, mk.kelas, a.nama AS nama_dosen, (SELECT COUNT(*) FROM soal_kuis sq WHERE sq.kuis_id=k.id) AS total_soal, (SELECT COUNT(*) FROM hasil_kuis hk WHERE hk.kuis_id=k.id) AS total_pengerjaan FROM kuis k JOIN mata_kuliah mk ON k.matkul_id=mk.id JOIN akun a ON k.dosen_id=a.id ORDER BY k.created_at DESC");
$materiList = $conn->query("SELECT m.id, m.judul, m.deskripsi, m.file_materi, m.created_at, mk.nama_matkul, mk.kelas, a.nama AS nama_dosen FROM materi m JOIN mata_kuliah mk ON m.matkul_id=mk.id JOIN akun a ON m.dosen_id=a.id ORDER BY m.created_at DESC");
$now = gmdate('Y-m-d H:i:s');
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Kuis & Materi | COMPULAB Admin</title>
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
    <a href="verifikasi_otp.php" class="nav-link"><span class="nav-icon">🔑</span> Verifikasi OTP</a>
    <a href="kelola_konten.php" class="nav-link active"><span class="nav-icon">🗂️</span> Kuis & Materi</a>
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
    <h1>Kuis & Materi 🗂️</h1>
    <div class="topbar-right">
      <div class="topbar-badge"><div class="topbar-avatar">A</div><?php echo htmlspecialchars($admin_nama); ?></div>
    </div>
  </div>

  <div class="page">
    <?php if ($success): ?><div class="alert alert-success">✅ <?php echo htmlspecialchars($success); ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-error">⚠️ <?php echo htmlspecialchars($error); ?></div><?php endif; ?>

    <!-- Tabs -->
    <div style="display:flex; gap:6px; margin-bottom:20px; border-bottom:1px solid var(--border);">
      <?php foreach (['kuis'=>'📝 Semua Kuis','materi'=>'📤 Semua Materi'] as $k=>$label): ?>
        <a href="?tab=<?php echo $k; ?>" style="padding:10px 16px; font-size:13px; font-weight:600; text-decoration:none; border-bottom:2px solid <?php echo $tab===$k ? 'var(--crimson)' : 'transparent'; ?>; color:<?php echo $tab===$k ? 'var(--crimson-light)' : 'var(--text-2)'; ?>; margin-bottom:-1px;"><?php echo $label; ?></a>
      <?php endforeach; ?>
    </div>

    <?php if ($tab === 'kuis'): ?>
    <div class="table-wrap">
      <table class="admin-table">
        <thead><tr><th>Judul</th><th>Dosen</th><th>Matkul</th><th>Soal</th><th>Dikerjakan</th><th>Status</th><th>Aksi</th></tr></thead>
        <tbody>
          <?php if ($kuisList && $kuisList->num_rows > 0):
            while ($row = $kuisList->fetch_assoc()):
              $st_kuis = 'Selesai'; $st_class = 'badge-gray';
              if ($now < $row['waktu_mulai']) { $st_kuis = 'Belum Mulai'; $st_class = 'badge-gold'; }
              elseif ($now <= $row['waktu_selesai']) { $st_kuis = 'Berlangsung'; $st_class = 'badge-green'; }
          ?>
          <tr>
            <td><strong><?php echo htmlspecialchars($row['judul']); ?></strong><br><span style="font-size:12px;color:var(--text-3);"><?php echo $row['durasi']; ?> menit</span></td>
            <td style="color:var(--text-2);"><?php echo htmlspecialchars($row['nama_dosen']); ?></td>
            <td><?php echo htmlspecialchars($row['nama_matkul']); ?> <span class="badge badge-blue"><?php echo $row['kelas']; ?></span></td>
            <td><span class="badge badge-gray"><?php echo $row['total_soal']; ?> soal</span></td>
            <td style="color:var(--text-2);"><?php echo $row['total_pengerjaan']; ?> mhs</td>
            <td><span class="badge <?php echo $st_class; ?>"><?php echo $st_kuis; ?></span></td>
            <td><a href="?tab=kuis&hapus_kuis=<?php echo $row['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Hapus kuis ini beserta semua soal dan hasilnya?')">🗑️</a></td>
          </tr>
          <?php endwhile; else: ?>
          <tr><td colspan="7"><div class="empty-state"><div class="icon">📝</div><p>Belum ada kuis.</p></div></td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <?php else: ?>
    <div class="table-wrap">
      <table class="admin-table">
        <thead><tr><th>Judul</th><th>Dosen</th><th>Matkul</th><th>Deskripsi</th><th>Tanggal</th><th>Aksi</th></tr></thead>
        <tbody>
          <?php if ($materiList && $materiList->num_rows > 0):
            while ($row = $materiList->fetch_assoc()): ?>
          <tr>
            <td>
              <strong><?php echo htmlspecialchars($row['judul']); ?></strong>
              <?php if ($row['file_materi']): ?>
                <br><a href="../uploads/materi/<?php echo urlencode($row['file_materi']); ?>" target="_blank" style="font-size:11px; color:var(--crimson-light);">📄 Lihat file</a>
              <?php endif; ?>
            </td>
            <td style="color:var(--text-2);"><?php echo htmlspecialchars($row['nama_dosen']); ?></td>
            <td><?php echo htmlspecialchars($row['nama_matkul']); ?> <span class="badge badge-blue"><?php echo $row['kelas']; ?></span></td>
            <td style="color:var(--text-2); font-size:12px; max-width:160px;"><?php echo htmlspecialchars(mb_substr($row['deskripsi'] ?? '-', 0, 60)); ?></td>
            <td style="color:var(--text-3); font-size:12px;"><?php echo date('d M Y', strtotime($row['created_at'])); ?></td>
            <td><a href="?tab=materi&hapus_materi=<?php echo $row['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Hapus materi ini?')">🗑️</a></td>
          </tr>
          <?php endwhile; else: ?>
          <tr><td colspan="6"><div class="empty-state"><div class="icon">📤</div><p>Belum ada materi.</p></div></td></tr>
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