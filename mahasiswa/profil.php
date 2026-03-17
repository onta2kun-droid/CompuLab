<?php
session_start();
include "../idk/forsafety.php";

if (!isset($_SESSION['email'])) {
    header("Location: ../login/mahasiswa.php");
    exit;
}

$emailSession = $_SESSION['email'];

$success = "";
$error = "";

/* =========================
   AMBIL DATA MAHASISWA
========================= */
$stmtUser = $conn->prepare("
    SELECT id, nim, nama, angkatan, email, whatsapp, password, is_verified, created_at
    FROM akun
    WHERE email = ? AND role = 'mahasiswa'
    LIMIT 1
");
$stmtUser->bind_param("s", $emailSession);
$stmtUser->execute();
$resultUser = $stmtUser->get_result();

if ($resultUser->num_rows <= 0) {
    session_destroy();
    header("Location: ../login/mahasiswa.php");
    exit;
}

$user = $resultUser->fetch_assoc();
$stmtUser->close();

if ((int)$user['is_verified'] !== 1) {
    session_destroy();
    header("Location: ../login/verify_mahasiswa.php?email=" . urlencode($user['email']));
    exit;
}

/* =========================
   PROSES GANTI PASSWORD
========================= */
if (isset($_POST['ganti_password'])) {
    $password_lama = $_POST['password_lama'] ?? '';
    $password_baru = $_POST['password_baru'] ?? '';
    $konfirmasi_password = $_POST['konfirmasi_password'] ?? '';

    if ($password_lama === "" || $password_baru === "" || $konfirmasi_password === "") {
        $error = "Semua field password wajib diisi.";
    } elseif (!password_verify($password_lama, $user['password'])) {
        $error = "Password lama tidak benar.";
    } elseif (strlen($password_baru) < 6) {
        $error = "Password baru minimal 6 karakter.";
    } elseif ($password_baru !== $konfirmasi_password) {
        $error = "Konfirmasi password baru tidak cocok.";
    } elseif ($password_lama === $password_baru) {
        $error = "Password baru tidak boleh sama dengan password lama.";
    } else {
        $hashPasswordBaru = password_hash($password_baru, PASSWORD_DEFAULT);

        $stmtUpdate = $conn->prepare("
            UPDATE akun
            SET password = ?
            WHERE id = ? AND role = 'mahasiswa'
        ");
        $stmtUpdate->bind_param("si", $hashPasswordBaru, $user['id']);

        if ($stmtUpdate->execute()) {
            $success = "Password berhasil diganti.";
            $user['password'] = $hashPasswordBaru;
        } else {
            $error = "Gagal mengganti password.";
        }

        $stmtUpdate->close();
    }
}
?>
<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Profil | COMPULAB</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../css/global.css">
<link rel="stylesheet" href="../css/dashboard-mahasiswa.css">
<style>
  .content-stack {
    display: flex;
    flex-direction: column;
    gap: 24px;
  }

  .section-card { padding: 28px; }

  .section-desc {
    font-size: 14px;
    color: var(--text-muted);
    line-height: 1.6;
    margin-top: 6px;
  }

  /* ── PROFILE GRID ── */
  .profile-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 14px;
    margin-top: 18px;
  }

  .profile-item {
    background: var(--bg-input);
    border: 1px solid var(--border);
    border-radius: 14px;
    padding: 16px;
  }

  .profile-item h4 {
    margin: 0 0 8px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.6px;
    color: var(--text-muted);
  }

  .profile-item p {
    margin: 0;
    font-size: 15px;
    font-weight: 600;
    color: var(--text-primary);
    line-height: 1.5;
    word-break: break-word;
  }

  .profile-status {
    display: inline-block;
    padding: 5px 12px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 700;
    background: rgba(64, 145, 108, 0.15);
    color: var(--green-fresh);
    border: 1px solid rgba(64, 145, 108, 0.25);
  }

  /* ── FORM PASSWORD ── */
  .form-password {
    max-width: 480px;
    display: flex;
    flex-direction: column;
    gap: 14px;
    margin-top: 18px;
  }

  .form-password input {
    width: 100%;
    padding: 14px 16px;
    border-radius: 12px;
    border: 1.5px solid var(--border);
    background: var(--bg-input);
    color: var(--text-primary);
    font-family: 'DM Sans', sans-serif;
    font-size: 14px;
    outline: none;
  }

  .form-password input::placeholder { color: var(--text-muted); }

  .form-password input:focus {
    border-color: var(--border-focus);
    background: var(--bg-card);
    box-shadow: 0 0 0 3px rgba(64, 145, 108, 0.10);
  }

  .btn-submit {
    border: none;
    border-radius: 12px;
    padding: 13px 20px;
    background: var(--btn-gradient);
    color: white;
    font-family: 'DM Sans', sans-serif;
    font-weight: 600;
    font-size: 14px;
    cursor: pointer;
    width: fit-content;
    box-shadow: 0 4px 14px var(--btn-shadow);
  }

  .btn-submit:hover {
    opacity: 0.95;
    transform: translateY(-1px);
    box-shadow: 0 6px 18px var(--btn-shadow-h);
  }

  .alert-success {
    background: rgba(64, 145, 108, 0.10);
    border: 1px solid rgba(64, 145, 108, 0.25);
    color: var(--green-fresh);
    border-radius: 10px;
    padding: 10px 14px;
    font-size: 13px;
    font-weight: 600;
    margin-top: 10px;
  }

  .alert-error {
    background: var(--error-bg);
    border: 1px solid var(--error-border);
    color: var(--error-text);
    border-radius: 10px;
    padding: 10px 14px;
    font-size: 13px;
    font-weight: 600;
    margin-top: 10px;
  }
</style>
</head>
<body>

<!-- HAMBURGER BUTTON -->
<!-- OVERLAY -->
<div class="mobile-overlay" id="mobileOverlay"></div>

<!-- MOBILE DRAWER -->
<div class="mobile-drawer" id="mobileDrawer">
  <div class="logo">COMPULAB</div>
  <nav>
    <a href="dashboard.php">🏠 Dashboard</a>
    <a href="enroll.php">📘 Enroll Class</a>
    <a href="materi.php">📚 Materi</a>
    <a href="kuis.php">📝 Kuis</a>
    <a href="scoreboard.php">🏆 Scoreboard</a>
    <a class="active" href="profil.php">👤 Profil</a>
    <a href="../login/logout.php" class="logout">🚪 Logout</a>
  </nav>
</div>

<div class="sidebar">
  <div class="logo">COMPULAB</div>
  <nav>
    <a href="dashboard.php">🏠 Dashboard</a>
    <a href="enroll.php">📘 Enroll Class</a>
    <a href="materi.php">📚 Materi</a>
    <a href="kuis.php">📝 Kuis</a>
    <a href="scoreboard.php">🏆 Scoreboard</a>
    <a class="active" href="profil.php">👤 Profil</a>
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
      <h3>Angkatan</h3>
      <p><?php echo htmlspecialchars($user['angkatan']); ?></p>
    </div>
    <div class="card">
      <h3>Status</h3>
      <p>Mahasiswa Aktif ✅</p>
    </div>
  </div>

  <div class="content-stack">

    <div class="card section-card">
      <h3>Informasi Profil</h3>
      <p class="section-desc">Berikut adalah data akun mahasiswa Anda.</p>

      <div class="profile-grid">
        <div class="profile-item">
          <h4>Nama Lengkap</h4>
          <p><?php echo htmlspecialchars($user['nama']); ?></p>
        </div>

        <div class="profile-item">
          <h4>NIM</h4>
          <p><?php echo htmlspecialchars($user['nim']); ?></p>
        </div>

        <div class="profile-item">
          <h4>Angkatan</h4>
          <p><?php echo htmlspecialchars($user['angkatan']); ?></p>
        </div>

        <div class="profile-item">
          <h4>Email</h4>
          <p><?php echo htmlspecialchars($user['email']); ?></p>
        </div>

        <div class="profile-item">
          <h4>WhatsApp</h4>
          <p><?php echo htmlspecialchars($user['whatsapp'] ?? '-'); ?></p>
        </div>

        <div class="profile-item">
          <h4>Status Verifikasi</h4>
          <p>
            <?php if ((int)$user['is_verified'] === 1): ?>
              <span class="profile-status">Terverifikasi</span>
            <?php else: ?>
              Belum Terverifikasi
            <?php endif; ?>
          </p>
        </div>

        <div class="profile-item">
          <h4>Tanggal Dibuat</h4>
          <p><?php echo !empty($user['created_at']) ? htmlspecialchars($user['created_at']) : '-'; ?></p>
        </div>
      </div>
    </div>

    <div class="card section-card">
      <h3>Ganti Password</h3>
      <p class="section-desc">Gunakan password yang kuat dan jangan bagikan kepada orang lain.</p>

      <?php if ($success): ?>
        <div class="alert-success"><?php echo htmlspecialchars($success); ?></div>
      <?php endif; ?>

      <?php if ($error): ?>
        <div class="alert-error"><?php echo htmlspecialchars($error); ?></div>
      <?php endif; ?>

      <form method="POST" class="form-password">
        <input type="password" name="password_lama" placeholder="Password Lama" required>
        <input type="password" name="password_baru" placeholder="Password Baru" required>
        <input type="password" name="konfirmasi_password" placeholder="Konfirmasi Password Baru" required>

        <button type="submit" name="ganti_password" class="btn-submit">
          Simpan Password Baru
        </button>
      </form>
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