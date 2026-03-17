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
 
/* TOTAL MATERI */
$totalMateri = 0;
$stmt1 = $conn->prepare("
SELECT COUNT(DISTINCT m.id) as total 
FROM materi m 
JOIN dosen_matkul dm ON m.matkul_id = dm.matkul_id 
WHERE dm.dosen_id = ?
");

if($stmt1){ 
    $stmt1->bind_param("i", $dosen_id); 
    $stmt1->execute(); 
    $totalMateri = $stmt1->get_result()->fetch_assoc()['total'] ?? 0; 
    $stmt1->close(); }
 
/* TOTAL KUIS */
$totalKuis = 0;
$stmt2 = $conn->prepare("
SELECT COUNT(DISTINCT k.id) as total 
FROM kuis k 
JOIN dosen_matkul dm ON k.matkul_id = dm.matkul_id 
WHERE dm.dosen_id = ?");

if($stmt2){ 
    $stmt2->bind_param("i", $dosen_id); 
    $stmt2->execute(); 
    $totalKuis = $stmt2->get_result()->fetch_assoc()['total'] ?? 0; 
    $stmt2->close(); }
 
/* TOTAL MAHASISWA */
$totalMahasiswa = 0;
$stmt3 = $conn->prepare("
SELECT COUNT(DISTINCT mm.nim) as total 
FROM mahasiswa_matkul mm 
JOIN dosen_matkul dm ON mm.matkul_id = dm.matkul_id 
WHERE dm.dosen_id = ?");

if($stmt3){ 
    $stmt3->bind_param("i", $dosen_id); 
    $stmt3->execute(); 
    $totalMahasiswa = $stmt3->get_result()->fetch_assoc()['total'] ?? 0; 
    $stmt3->close(); }
 
/* MATA KULIAH */
$matkul = null;
$stmt4 = $conn->prepare("
SELECT mk.nama_matkul, mk.angkatan, mk.kelas 
FROM mata_kuliah mk 
JOIN dosen_matkul dm ON mk.id = dm.matkul_id 
WHERE dm.dosen_id = ? ORDER BY mk.angkatan");

if($stmt4){ 
    $stmt4->bind_param("i", $dosen_id); 
    $stmt4->execute(); 
    $matkul = $stmt4->get_result(); }
?>

<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard Dosen | COMPULAB</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<script>
  const t = localStorage.getItem('compulab_theme');
  if (t) document.documentElement.setAttribute('data-theme', t);
</script>
<!-- <link rel="stylesheet" href="../css/global.css"> -->
<link rel="stylesheet" href="../css/dashboard-dosen.css">
</head>
<body>
 
<div class="mobile-overlay" id="mobileOverlay"></div>
<div class="mobile-drawer" id="mobileDrawer">
  <div class="logo">COMPULAB</div>
  <nav>
    <a class="active" href="dashboard.php">📊 Dashboard</a>
    <a href="upload_materi.php">📤 Upload Materi</a>
    <a href="kelola_materi.php">📚 Kelola Materi</a>
    <a href="tambah_kuis.php">📝 Tambah Kuis</a>
    <a href="kelola_kuis.php">📋 Kelola Kuis</a>
    <a href="scoreboard.php">📈 Scoreboard</a>
    <a href="profil.php">👨‍🏫 Profil</a>
  </nav>
  <div class="sidebar-bottom">
    <a href="logout.php">🚪 Logout</a>
  </div>
</div>
 
<div class="sidebar">
  <div class="logo">COMPULAB</div>
  <nav>
    <a class="active" href="dashboard.php">📊 Dashboard</a>
    <a href="upload_materi.php">📤 Upload Materi</a>
    <a href="kelola_materi.php">📚 Kelola Materi</a>
    <a href="tambah_kuis.php">📝 Tambah Kuis</a>
    <a href="kelola_kuis.php">📋 Kelola Kuis</a>
    <a href="scoreboard.php">📈 Scoreboard</a>
    <a href="profil.php">👨‍🏫 Profil</a>
  </nav>
  <div class="sidebar-bottom">
    <a href="logout.php" class="logout">🚪 Logout</a>
  </div>
</div>
 
<div class="main">
 
  <div class="topbar">
    <button class="hamburger" id="hamburgerBtn" aria-label="Menu">
      <span></span><span></span><span></span>
    </button>
    <h1>Halo, <?php echo htmlspecialchars($nama_dosen); ?></h1>
    <div class="topbar-right">
        <a href="profil.php" class="profile-mini" title="Profil Dosen">👨‍🏫</a>
        <button class="dark-toggle-top" id="darkToggle" title="Toggle Dark Mode">🌙</button>
    </div>
  </div>
 
  <div class="stats">
    <div class="card">
      <h3>Total Materi</h3>
      <p><?php echo $totalMateri; ?></p>
    </div>
    <div class="card">
      <h3>Total Kuis</h3>
      <p><?php echo $totalKuis; ?></p>
    </div>
    <div class="card">
      <h3>Total Mahasiswa</h3>
      <p><?php echo $totalMahasiswa; ?></p>
    </div>
  </div>
 
  <div class="table-card">
    <h3>Mata Kuliah Anda</h3>
    <table class="custom-table">
      <thead>
        <tr>
          <th>Mata Kuliah</th>
          <th>Angkatan</th>
          <th>Kelas</th>
        </tr>
      </thead>
      <tbody>
        <?php if($matkul && $matkul->num_rows > 0): ?>
          <?php while($row = $matkul->fetch_assoc()): ?>
            <tr>
              <td data-label="Mata Kuliah"><?php echo htmlspecialchars($row['nama_matkul']); ?></td>
              <td data-label="Angkatan"><?php echo htmlspecialchars($row['angkatan']); ?></td>
              <td data-label="Kelas"><?php echo htmlspecialchars($row['kelas']); ?></td>
            </tr>
          <?php endwhile; ?>
        <?php else: ?>
          <tr><td colspan="3" style="color:var(--text-muted); text-align:center; padding:24px;">Belum ada mata kuliah yang ditugaskan.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
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