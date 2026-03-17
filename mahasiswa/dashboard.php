<?php
session_start();
include "../idk/forsafety.php";

if (!isset($_SESSION['email'])) {
    header("Location: ../login/mahasiswa.php");
    exit;
}

$email = $_SESSION['email'];
$stmt = $conn->prepare("SELECT nim, nama, email, is_verified FROM akun WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows <= 0) {
    session_destroy();
    header("Location: ../login/mahasiswa.php");
    exit;
}

$user = $result->fetch_assoc();
$stmt->close();

if ((int)$user['is_verified'] !== 1) {
    session_destroy();
    header("Location: ../login/verify_mahasiswa.php?email=" . urlencode($user['email']));
    exit;
}
?>
<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard Mahasiswa | COMPULAB</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<script>
  const t = localStorage.getItem('compulab_theme');
  if (t) document.documentElement.setAttribute('data-theme', t);
</script>
<link rel="stylesheet" href="../css/global.css">
<link rel="stylesheet" href="../css/dashboard-mahasiswa.css">
</head>
<body>

<!-- HAMBURGER BUTTON -->
<!-- OVERLAY -->
<div class="mobile-overlay" id="mobileOverlay"></div>

<!-- MOBILE DRAWER -->
<div class="mobile-drawer" id="mobileDrawer">
  <div class="logo">COMPULAB</div>
  <nav>
    <a class="active" href="dashboard.php">🏠 Dashboard</a>
    <a href="enroll.php">📘 Enroll Class</a>
    <a href="materi.php">📚 Materi</a>
    <a href="kuis.php">📝 Kuis</a>
    <a href="scoreboard.php">🏆 Scoreboard</a>
    <a href="profil.php">👤 Profil</a>
    <a href="../login/logout.php" class="logout">🚪 Logout</a>
  </nav>
</div>

<div class="sidebar">
  <div class="logo">COMPULAB</div>
  <nav>
    <a class="active" href="dashboard.php">🏠 Dashboard</a>
    <a href="enroll.php">📘 Enroll Class</a>
    <a href="materi.php">📚 Materi</a>
    <a href="kuis.php">📝 Kuis</a>
    <a href="scoreboard.php">🏆 Scoreboard</a>
    <a href="profil.php">👤 Profil</a>
    <a href="../login/logout.php" class="logout">🚪 Logout</a>
  </nav>
</div>

<div class="main">
  <div class="topbar">
    <button class="hamburger" id="hamburgerBtn" aria-label="Menu">
      <span></span><span></span><span></span>
    </button>
    <h1>Halo, <?php echo htmlspecialchars($user['nama']); ?> 👋</h1>
    <div class="topbar-right">
      <a href="../mahasiswa/profil.php" class="profile-mini" title="Lihat Profil">🎓</a>
      <button class="dark-toggle-top" id="darkToggle" title="Toggle Dark Mode">🌙</button>
    </div>
  </div>

  <div class="stats">
    <div class="card">
      <h3>NIM</h3>
      <p><?php echo htmlspecialchars($user['nim']); ?></p>
    </div>
    <div class="card">
      <h3>Email</h3>
      <p><?php echo htmlspecialchars($user['email']); ?></p>
    </div>
    <div class="card">
      <h3>Status</h3>
      <p>Mahasiswa Aktif ✅</p>
    </div>
  </div>

  <div class="content-grid">
    <div class="card large">
      <h3>Selamat Datang di COMPULAB</h3>
      <p>Silakan mulai belajar dan mengerjakan kuis yang tersedia.</p>
      <button onclick="window.location.href='materi.php'">Mulai Belajar →</button>
    </div>
    <div class="card large">
      <h3>Informasi Akun</h3>
      <p>Nama: <?php echo htmlspecialchars($user['nama']); ?></p>
      <p>NIM: <?php echo htmlspecialchars($user['nim']); ?></p>
      <p>Email: <?php echo htmlspecialchars($user['email']); ?></p>
    </div>
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