<?php
header('Content-Type: text/html; charset=utf-8');
include "../idk/forsafety.php";

$email = isset($_GET['email']) ? trim($_GET['email']) : "";
$codeError = "";
$success = "";
$generalError = "";

if (isset($_POST['verify'])) {
    $email = trim($_POST['email']);
    $kode = trim($_POST['kode']);

    if (!preg_match('/^[0-9]{4}$/', $kode)) {
        $codeError = "Kode verifikasi harus 4 digit angka!";
    } else {
        $stmt = $conn->prepare("SELECT id, verification_code, verification_expires_at, is_verified FROM akun WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            if ((int)$row['is_verified'] === 1) {
                $generalError = "Akun ini sudah terverifikasi. Silakan login.";
            } elseif ($row['verification_code'] !== $kode) {
                $codeError = "Kode verifikasi salah!";
            } elseif (strtotime($row['verification_expires_at']) < time()) {
                $generalError = "Kode verifikasi sudah expired.";
            } else {
                $update = $conn->prepare("UPDATE akun SET is_verified = 1, verification_code = NULL, verification_expires_at = NULL WHERE email = ?");
                $update->bind_param("s", $email);

                if ($update->execute()) {
                    $success = "Verifikasi berhasil! Silakan login.";
                } else {
                    $generalError = "Verifikasi gagal.";
                }

                $update->close();
            }
        } else {
            $generalError = "Email tidak ditemukan.";
        }

        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Verifikasi Akun</title>
<!-- <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet"> -->
<link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">

<script>
  const t = localStorage.getItem('compulab_theme');
  if (t) document.documentElement.setAttribute('data-theme', t);
</script>
<link rel="stylesheet" href="../css/global.css">
<link rel="stylesheet" href="../css/login-mahasiswa.css">
</head>
<body>

<div class="login-container">
  <div class="login-card">
    <h2>Verifikasi WhatsApp 📲</h2>
    <p>Masukkan kode 4 digit yang dikirim ke WhatsApp kamu</p>

    <?php if($generalError != ""): ?>
      <div class="error-message">⚠️ <?php echo htmlspecialchars($generalError); ?></div>
    <?php endif; ?>

    <?php if($success != ""): ?>
      <div class="success-message">✅ <?php echo htmlspecialchars($success); ?></div>
      <div style="margin-top:16px; text-align:center;">
        <a href="mahasiswa.php" style="color:var(--green-fresh); font-weight:600; text-decoration:none;">
          Login sekarang →
        </a>
      </div>

    <?php else: ?>

      <?php 
        $isManual = isset($_GET['manual']) && $_GET['manual'] == '1';
        $namaMhs = "";
        if ($email != "") {
            $stmtNama = $conn->prepare("SELECT nama FROM akun WHERE email = ? LIMIT 1");
            $stmtNama->bind_param("s", $email);
            $stmtNama->execute();
            $resNama = $stmtNama->get_result();
            if ($rowNama = $resNama->fetch_assoc()) {
                $namaMhs = $rowNama['nama'];
            }
            $stmtNama->close();
        }
        $waAdmin  = "6282255304228";
        $pesanWA  = urlencode("Halo Admin COMPULAB\n\nSaya *$namaMhs* baru saja mendaftar akun dengan email *$email*.\n\nMohon kirimkan kode verifikasi saya. Terima kasih");
        $linkWA   = "https://wa.me/$waAdmin?text=$pesanWA";
      ?>

      <?php if($isManual): ?>
        <div style="
          background: rgba(245,158,11,0.10);
          border: 1px solid rgba(245,158,11,0.30);
          border-radius: 12px;
          padding: 14px 16px;
          margin-bottom: 20px;
          font-size: 13px;
          color: #f59e0b;
          font-weight: 500;
          line-height: 1.6;
        ">
          ⚠️ Pengiriman OTP otomatis gagal. Silakan hubungi admin untuk mendapatkan kode verifikasi.
        </div>

        <a href="<?php echo $linkWA; ?>" target="_blank" style="
          display: block;
          width: 100%;
          padding: 14px;
          margin-bottom: 20px;
          border-radius: 14px;
          background: linear-gradient(135deg, #25d366, #128c7e);
          color: white;
          font-weight: 700;
          font-size: 15px;
          text-align: center;
          text-decoration: none;
          box-shadow: 0 6px 18px rgba(37,211,102,0.3);
        ">
          Chat Admin via WhatsApp
        </a>

      <?php else: ?>
        <div style="
          background: rgba(64,145,108,0.08);
          border: 1px solid rgba(64,145,108,0.20);
          border-radius: 12px;
          padding: 14px 16px;
          margin-bottom: 20px;
          font-size: 13px;
          color: var(--green-fresh);
          font-weight: 500;
          line-height: 1.6;
        ">
          ✅ Kode verifikasi sudah dikirim ke WhatsApp kamu. Jika tidak terima dalam 1 menit,
          <a href="<?php echo $linkWA; ?>" target="_blank" style="color:var(--green-fresh); font-weight:700;">
            hubungi admin
          </a>.
        </div>
      <?php endif; ?>

      <form method="POST">
        <input type="email" name="email" placeholder="Email" required
          value="<?php echo htmlspecialchars($email); ?>">

        <?php if($codeError != ""): ?>
          <div class="error-message">⚠️ <?php echo htmlspecialchars($codeError); ?></div>
        <?php endif; ?>

        <input type="text" name="kode" maxlength="4" placeholder="Kode 4 Digit"
          required autocomplete="one-time-code" inputmode="numeric"
          style="letter-spacing: 8px; font-size: 22px; text-align: center; font-weight: 700;">

        <button type="submit" name="verify">Verifikasi</button>
      </form>

      <div class="login-link" style="margin-top:20px;">
        <p>Belum terima kode?</p>
        <a href="<?php echo $linkWA; ?>" target="_blank">Chat Admin WhatsApp</a>
      </div>

    <?php endif; ?>
  </div>
</div>

<script src="../js/darkmode.js"></script>
</body>
</html>