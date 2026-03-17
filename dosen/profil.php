<?php
$success = "";
$error   = "";

function normalizeWhatsAppNumber($number) {
    $number = trim($number);
    $number = preg_replace('/[^0-9]/', '', $number);

    if (substr($number, 0, 1) === "0") {
        $number = "62" . substr($number, 1);
    }

    return $number;
}

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
include "../idk/forsafety.php";

/* =========================
   CEK LOGIN DOSEN
========================= */
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'dosen') {
    header("Location: ../login/dosen.php");
    exit();
}

$dosen_id   = $_SESSION['user_id'];
$nama_dosen = $_SESSION['user_nama'];

$success = "";
$error   = "";

/* =========================
   AMBIL DATA DOSEN
========================= */
$stmtUser = $conn->prepare("
    SELECT id, nama, email, whatsapp, role, password, is_verified, created_at
    FROM akun
    WHERE id = ? AND role = 'dosen'
    LIMIT 1
");

if (!$stmtUser) {
    die("Query data dosen gagal disiapkan.");
}

$stmtUser->bind_param("i", $dosen_id);
$stmtUser->execute();
$resultUser = $stmtUser->get_result();

if ($resultUser->num_rows <= 0) {
    session_destroy();
    header("Location: ../login/dosen.php");
    exit();
}

$user = $resultUser->fetch_assoc();
$stmtUser->close();

/* =========================
   TOTAL MATA KULIAH
========================= */
$totalMatkul = 0;
$stmtMatkul = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM dosen_matkul
    WHERE dosen_id = ?
");
if ($stmtMatkul) {
    $stmtMatkul->bind_param("i", $dosen_id);
    $stmtMatkul->execute();
    $resultMatkul = $stmtMatkul->get_result();
    $dataMatkul = $resultMatkul->fetch_assoc();
    $totalMatkul = (int)($dataMatkul['total'] ?? 0);
    $stmtMatkul->close();
}

/* =========================
   TOTAL MATERI
========================= */
$totalMateri = 0;
$stmtMateri = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM materi
    WHERE dosen_id = ?
");
if ($stmtMateri) {
    $stmtMateri->bind_param("i", $dosen_id);
    $stmtMateri->execute();
    $resultMateri = $stmtMateri->get_result();
    $dataMateri = $resultMateri->fetch_assoc();
    $totalMateri = (int)($dataMateri['total'] ?? 0);
    $stmtMateri->close();
}

/* =========================
   TOTAL KUIS
========================= */
$totalKuis = 0;
$stmtKuis = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM kuis
    WHERE dosen_id = ?
");
if ($stmtKuis) {
    $stmtKuis->bind_param("i", $dosen_id);
    $stmtKuis->execute();
    $resultKuis = $stmtKuis->get_result();
    $dataKuis = $resultKuis->fetch_assoc();
    $totalKuis = (int)($dataKuis['total'] ?? 0);
    $stmtKuis->close();
}

/* =========================
   DAFTAR MATA KULIAH DOSEN
========================= */
$stmtListMatkul = $conn->prepare("
    SELECT mk.nama_matkul, mk.angkatan, mk.kelas
    FROM mata_kuliah mk
    JOIN dosen_matkul dm ON mk.id = dm.matkul_id
    WHERE dm.dosen_id = ?
    ORDER BY mk.nama_matkul ASC, mk.kelas ASC
");
$listMatkul = null;
if ($stmtListMatkul) {
    $stmtListMatkul->bind_param("i", $dosen_id);
    $stmtListMatkul->execute();
    $listMatkul = $stmtListMatkul->get_result();
}

/* =========================
   PROSES UPDATE WHATSAPP
========================= */
if (isset($_POST['update_whatsapp'])) {
    $whatsapp = normalizeWhatsAppNumber($_POST['whatsapp'] ?? '');

    if ($whatsapp === '') {
        $error = "Nomor WhatsApp wajib diisi.";
    } elseif (!preg_match('/^[0-9]{10,15}$/', $whatsapp)) {
        $error = "Nomor WhatsApp tidak valid! Gunakan format seperti 628123456789.";
    } else {
        $stmtCekWa = $conn->prepare("
            SELECT id
            FROM akun
            WHERE whatsapp = ? AND id != ?
            LIMIT 1
        ");

        if ($stmtCekWa) {
            $stmtCekWa->bind_param("si", $whatsapp, $dosen_id);
            $stmtCekWa->execute();
            $resultCekWa = $stmtCekWa->get_result();

            if ($resultCekWa->num_rows > 0) {
                $error = "Nomor WhatsApp sudah digunakan akun lain.";
            } else {
                $stmtUpdateWa = $conn->prepare("
                    UPDATE akun
                    SET whatsapp = ?
                    WHERE id = ? AND role = 'dosen'
                ");

                if ($stmtUpdateWa) {
                    $stmtUpdateWa->bind_param("si", $whatsapp, $dosen_id);

                    if ($stmtUpdateWa->execute()) {
                        $success = "Nomor WhatsApp berhasil diperbarui.";
                        $user['whatsapp'] = $whatsapp;
                    } else {
                        $error = "Gagal memperbarui nomor WhatsApp.";
                    }

                    $stmtUpdateWa->close();
                } else {
                    $error = "Query update WhatsApp gagal disiapkan.";
                }
            }

            $stmtCekWa->close();
        } else {
            $error = "Query validasi WhatsApp gagal disiapkan.";
        }
    }
}

/* =========================
   PROSES GANTI PASSWORD
========================= */
if (isset($_POST['ganti_password'])) {
    $password_lama       = $_POST['password_lama'] ?? '';
    $password_baru       = $_POST['password_baru'] ?? '';
    $konfirmasi_password = $_POST['konfirmasi_password'] ?? '';

    if ($password_lama === '' || $password_baru === '' || $konfirmasi_password === '') {
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
            WHERE id = ? AND role = 'dosen'
        ");

        if ($stmtUpdate) {
            $stmtUpdate->bind_param("si", $hashPasswordBaru, $dosen_id);

            if ($stmtUpdate->execute()) {
                $success = "Password berhasil diganti.";
                $user['password'] = $hashPasswordBaru;
            } else {
                $error = "Gagal mengganti password.";
            }

            $stmtUpdate->close();
        } else {
            $error = "Query update password gagal disiapkan.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Profil Dosen | COMPULAB</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<script>
  const t = localStorage.getItem('compulab_theme');
  if (t) document.documentElement.setAttribute('data-theme', t);
</script>
<link rel="stylesheet" href="../css/dashboard-dosen.css">
<style>
  .content-stack {
    display: flex;
    flex-direction: column;
    gap: 24px;
  }

  .section-card {
    padding: 28px;
  }

  .profile-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
    gap: 16px;
    margin-top: 18px;
  }

  .profile-item {
    background: var(--bg-input, rgba(255,255,255,0.05));
    border: 1px solid var(--border, rgba(255,255,255,0.08));
    border-radius: 16px;
    padding: 16px;
  }

  .profile-item h4 {
    margin: 0 0 8px;
    font-size: 13px;
    opacity: 0.72;
    text-transform: uppercase;
    letter-spacing: 0.4px;
    color: var(--text-muted, #999);
  }

  .profile-item p {
    margin: 0;
    font-size: 18px;
    font-weight: 600;
    line-height: 1.5;
    word-break: break-word;
    color: var(--text-primary, #fff);
  }

  .profile-status {
    display: inline-block;
    padding: 7px 12px;
    border-radius: 999px;
    font-size: 13px;
    font-weight: 700;
    background: rgba(46, 204, 113, 0.18);
    color: #1e7e34;
  }

  .list-matkul {
    margin-top: 18px;
    display: grid;
    gap: 12px;
  }

  .matkul-item {
    background: var(--bg-input, rgba(255,255,255,0.05));
    border: 1px solid var(--border, rgba(255,255,255,0.08));
    border-radius: 14px;
    padding: 14px 16px;
  }

  .matkul-item strong {
    display: block;
    margin-bottom: 4px;
    color: var(--text-primary, #fff);
  }

  .matkul-item span {
    color: var(--text-muted, #999);
    font-size: 14px;
  }

  .form-password {
    max-width: 560px;
    display: flex;
    flex-direction: column;
    gap: 14px;
    margin-top: 18px;
  }

  .form-password input {
    width: 100%;
    box-sizing: border-box;
    padding: 14px 16px;
    border-radius: 12px;
    border: 1px solid var(--border, rgba(255,255,255,0.12));
    background: var(--bg-input, rgba(255,255,255,0.05));
    color: var(--text-primary, white);
    outline: none;
  }

  .form-password input::placeholder {
    color: rgba(255,255,255,0.55);
  }

  .form-password input:focus {
    border-color: rgba(73, 160, 120, 0.9);
    box-shadow: 0 0 0 3px rgba(73, 160, 120, 0.12);
  }

  .btn-submit {
    border: none;
    border-radius: 12px;
    padding: 12px 18px;
    background: linear-gradient(90deg, #49a078, #2d6a4f);
    color: white;
    font-weight: 700;
    cursor: pointer;
    width: fit-content;
  }

  .btn-submit:hover {
    opacity: 0.95;
    transform: translateY(-1px);
  }

  .alert-success {
    color: #1e7e34;
    font-weight: 600;
    margin-top: 10px;
  }

  .alert-error {
    color: #c0392b;
    font-weight: 600;
    margin-top: 10px;
  }

  .section-desc {
    opacity: 0.82;
    line-height: 1.6;
    margin-top: 6px;
    color: var(--text-muted, #999);
  }

  @media (max-width: 768px) {
    .section-card {
      padding: 18px;
    }

    .form-password {
      max-width: 100%;
    }

    .btn-submit {
      width: 100%;
    }
  }
</style>
</head>
<body>

<div class="mobile-overlay" id="mobileOverlay"></div>

<div class="mobile-drawer" id="mobileDrawer">
  <div class="logo">COMPULAB</div>
  <nav>
    <a href="dashboard.php">📊 Dashboard</a>
    <a href="upload_materi.php">📤 Upload Materi</a>
    <a href="kelola_materi.php">📚 Kelola Materi</a>
    <a href="tambah_kuis.php">📝 Tambah Kuis</a>
    <a href="kelola_kuis.php">📋 Kelola Kuis</a>
    <a href="scoreboard.php">📈 Scoreboard</a>
    <a class="active" href="profil.php">👨‍🏫 Profil</a>
  </nav>
  <div class="sidebar-bottom"><a href="logout.php">🚪 Logout</a></div>
</div>

<div class="sidebar">
  <div class="logo">COMPULAB</div>
  <nav>
    <a href="dashboard.php">📊 Dashboard</a>
    <a href="upload_materi.php">📤 Upload Materi</a>
    <a href="kelola_materi.php">📚 Kelola Materi</a>
    <a href="tambah_kuis.php">📝 Tambah Kuis</a>
    <a href="kelola_kuis.php">📋 Kelola Kuis</a>
    <a href="scoreboard.php">📈 Scoreboard</a>
    <a class="active" href="profil.php">👨‍🏫 Profil</a>
  </nav>
  <div class="sidebar-bottom"><a href="logout.php">🚪 Logout</a></div>
</div>

<div class="main">
  <div class="topbar">
    <button class="hamburger" id="hamburgerBtn" aria-label="Menu">
      <span></span><span></span><span></span>
    </button>
    <h1>Halo, <?php echo htmlspecialchars($nama_dosen); ?> 👨‍🏫</h1>
    <div class="topbar-right">
      <a href="profil.php" class="profile-mini" title="Profil Dosen">👨‍🏫</a>
      <button class="dark-toggle-top" id="darkToggle">🌙</button>
    </div>
  </div>

  <div class="stats">
    <div class="card">
      <h3>Total Mata Kuliah</h3>
      <p><?php echo $totalMatkul; ?></p>
    </div>
    <div class="card">
      <h3>Total Materi</h3>
      <p><?php echo $totalMateri; ?></p>
    </div>
    <div class="card">
      <h3>Total Kuis</h3>
      <p><?php echo $totalKuis; ?></p>
    </div>
  </div>

  <div class="content-stack">

    <div class="table-card section-card">
      <h3>Informasi Profil Dosen</h3>
      <p class="section-desc">Berikut adalah data akun dosen Anda.</p>

      <div class="profile-grid">
        <div class="profile-item">
          <h4>Nama Lengkap</h4>
          <p><?php echo htmlspecialchars($user['nama']); ?></p>
        </div>

        <div class="profile-item">
          <h4>Email</h4>
          <p><?php echo htmlspecialchars($user['email']); ?></p>
        </div>

        <div class="profile-item">
          <h4>WhatsApp</h4>
          <p><?php echo !empty($user['whatsapp']) ? htmlspecialchars($user['whatsapp']) : '-'; ?></p>
        </div>

        <div class="profile-item">
          <h4>Role</h4>
          <p><?php echo htmlspecialchars(ucfirst($user['role'])); ?></p>
        </div>

        <div class="profile-item">
          <h4>Status Verifikasi</h4>
          <p>
            <?php if ((int)$user['is_verified'] === 1): ?>
              <span class="profile-status">Terverifikasi</span>
            <?php else: ?>
              <span style="color:#c0392b;">Belum Terverifikasi</span>
            <?php endif; ?>
          </p>
        </div>

        <div class="profile-item">
          <h4>Tanggal Akun Dibuat</h4>
          <p><?php echo !empty($user['created_at']) ? htmlspecialchars($user['created_at']) : '-'; ?></p>
        </div>
      </div>
    </div>

    <div class="table-card section-card">
      <h3>Ubah Nomor WhatsApp</h3>
      <p class="section-desc">Perbarui nomor WhatsApp aktif Anda untuk keperluan akun dan komunikasi.</p>

      <?php if ($success): ?>
        <div class="alert-success"><?php echo htmlspecialchars($success); ?></div>
      <?php endif; ?>

      <?php if ($error): ?>
        <div class="alert-error"><?php echo htmlspecialchars($error); ?></div>
      <?php endif; ?>

      <form method="POST" class="form-password">
        <input
          type="text"
          name="whatsapp"
          placeholder="Nomor WhatsApp, contoh 628123456789"
          required
          value="<?php echo htmlspecialchars($user['whatsapp'] ?? ''); ?>"
        >

        <button type="submit" name="update_whatsapp" class="btn-submit">
          Simpan Nomor WhatsApp
        </button>
      </form>
    </div>

    <div class="table-card section-card">
      <h3>Mata Kuliah yang Diampu</h3>
      <p class="section-desc">Daftar mata kuliah yang saat ini Anda ajar.</p>

      <div class="list-matkul">
        <?php if ($listMatkul && $listMatkul->num_rows > 0): ?>
          <?php while ($mk = $listMatkul->fetch_assoc()): ?>
            <div class="matkul-item">
              <strong><?php echo htmlspecialchars($mk['nama_matkul']); ?></strong>
              <span>Angkatan <?php echo htmlspecialchars($mk['angkatan']); ?> • Kelas <?php echo htmlspecialchars($mk['kelas']); ?></span>
            </div>
          <?php endwhile; ?>
        <?php else: ?>
          <div class="matkul-item">
            <span>Belum ada mata kuliah yang diampu.</span>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <div class="table-card section-card">
      <h3>Ganti Password</h3>
      <p class="section-desc">Gunakan password yang kuat dan simpan dengan aman.</p>

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