<?php
require_once __DIR__ . '/../../sambung/forsafety.php';
// require "../vendor/autoload.php";

// use Twilio\Rest\Client;

$namaError      = "";
$nimError       = "";
$angkatanError  = "";
$emailError     = "";
$waError        = "";
$passwordError  = "";
$sendError      = "";
$success        = "";

function normalizeWhatsAppNumber($number) {
    $number = trim($number);
    $number = preg_replace('/[^0-9]/', '', $number);
    if (substr($number, 0, 1) === "0") {
        $number = "62" . substr($number, 1);
    }
    return $number;
}

if (isset($_POST['register'])) {

    $nama      = trim($_POST['nama'] ?? '');
    $nim       = trim($_POST['nim'] ?? '');
    $angkatan  = trim($_POST['angkatan'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $whatsapp  = normalizeWhatsAppNumber($_POST['whatsapp'] ?? '');
    $password  = $_POST['password'] ?? '';

    if ($nama == "") {
        $namaError = "Nama wajib diisi!";
    }

    if (!preg_match('/^[0-9]+$/', $nim)) {
        $nimError = "NIM hanya boleh angka!";
    }

    if (!in_array($angkatan, ['2024', '2025'])) {
        $angkatanError = "Angkatan wajib dipilih (2024 atau 2025)!";
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $emailError = "Format email tidak valid!";
    }

    if (!preg_match('/^[0-9]{10,15}$/', $whatsapp)) {
        $waError = "Nomor WhatsApp tidak valid! Contoh: 08123456789";
    }

    if (strlen($password) < 6) {
        $passwordError = "Password minimal 6 karakter!";
    }

    if ($emailError == "") {
        $stmt = $conn->prepare("SELECT id FROM akun WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) $emailError = "Email sudah terdaftar!";
        $stmt->close();
    }

    if ($nimError == "") {
        $stmt = $conn->prepare("SELECT id FROM akun WHERE nim = ?");
        $stmt->bind_param("s", $nim);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) $nimError = "NIM sudah terdaftar!";
        $stmt->close();
    }

    if ($waError == "") {
        $stmt = $conn->prepare("SELECT id FROM akun WHERE whatsapp = ?");
        $stmt->bind_param("s", $whatsapp);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) $waError = "Nomor WhatsApp sudah terdaftar!";
        $stmt->close();
    }

    if (
        $namaError == "" &&
        $nimError == "" &&
        $angkatanError == "" &&
        $emailError == "" &&
        $waError == "" &&
        $passwordError == ""
    ) {
        $hashedPassword   = password_hash($password, PASSWORD_DEFAULT);
        $verificationCode = str_pad((string) random_int(0, 9999), 4, "0", STR_PAD_LEFT);
        $expiresAt        = date("Y-m-d H:i:s", strtotime("+10 minutes"));

        $stmt = $conn->prepare("
            INSERT INTO akun (
                role, nim, angkatan, nama, email, whatsapp, password, 
                is_verified, verification_code, verification_expires_at
            ) 
            VALUES (?, ?, ?, ?, ?, ?, ?, 0, ?, ?)
        ");

        $role = "mahasiswa";

        $stmt->bind_param(
            "ssissssss",
            $role,
            $nim,
            $angkatan,
            $nama,
            $email,
            $whatsapp,
            $hashedPassword,
            $verificationCode,
            $expiresAt
        );

        if ($stmt->execute()) {
            // TWILIO TEST (Gagal juga)
            // $accountSid = "AC0d8b3a10b19a596d8da796621ee725a2";
            // $authToken  = "3ff08f783de28fff0272b72b0e68ebfc";
            // $twilioFrom = "whatsapp:+14155238886";

            // try {
            //     $client = new Client($accountSid, $authToken);
            //     $client->messages->create("whatsapp:+" . $whatsapp, [
            //         "from" => $twilioFrom,
            //         "body" => "Halo $nama, kode verifikasi CompuLab kamu adalah: $verificationCode. Kode ini berlaku 10 menit."
            //     ]);

            //     header("Location: verify_mahasiswa.php?email=" . urlencode($email));
            //     exit;
            // } catch (Exception $e) {
            //     $sendError = "Registrasi berhasil, tapi WhatsApp gagal dikirim. Error: " . $e->getMessage();
            // }


            // FONNTE TEST (tapi gagal)
            // $fonnte_token = "G1udVC3PxwTrKtVYrMRw";

            // $curl = curl_init();
            // curl_setopt_array($curl, [
            //     CURLOPT_URL => "https://api.fonnte.com/send",
            //     CURLOPT_RETURNTRANSFER => true,
            //     CURLOPT_POST => true,
            //     CURLOPT_HTTPHEADER => [
            //         "Authorization: " . $fonnte_token
            //     ],
            //     CURLOPT_POSTFIELDS => [
            //         "target"      => $whatsapp,
            //         "message"     => "Halo $nama, kode verifikasi COMPULAB kamu adalah: *$verificationCode*\n\nKode ini berlaku 10 menit. Jangan bagikan ke siapapun.",
            //         "countryCode" => "62"
            //     ]
            // ]);

            // $response = curl_exec($curl);
            // curl_close($curl);

            // $result = json_decode($response, true);

            // if (isset($result['status']) && $result['status'] === true) {
            //     header("Location: verify_mahasiswa.php?email=" . urlencode($email));
            //     exit;
            // } else {
            //     $sendError = "Registrasi berhasil, tapi WhatsApp gagal dikirim. " . ($result['reason'] ?? '');
            // }

          // Langsung redirect ke halaman verifikasi
          header("Location: verify_mahasiswa.php?email=" . urlencode($email) . "&nama=" . urlencode($nama));
          exit;

        } else {
            $sendError = "Registrasi gagal disimpan ke database.";
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
<title>Daftar Akun | COMPULAB</title>

<script>
  const t = localStorage.getItem('compulab_theme');
  if (t) document.documentElement.setAttribute('data-theme', t);
</script>

<link rel="stylesheet" href="../css/global.css">
<link rel="stylesheet" href="../css/register-mahasiswa.css">
</head>
<body>

<div class="login-container">
  <div class="login-card">

    <h2>Daftar Akun 🎓</h2>
    <p>Buat akun untuk mulai belajar</p>

    <form method="POST">

      <?php if($namaError != ""): ?>
        <div class="error-message">⚠️ <?php echo $namaError; ?></div>
      <?php endif; ?>
      <input type="text" name="nama" placeholder="Nama Lengkap" required
        value="<?php echo isset($_POST['nama']) ? htmlspecialchars($_POST['nama']) : ''; ?>">

      <?php if($nimError != ""): ?>
        <div class="error-message">⚠️ <?php echo $nimError; ?></div>
      <?php endif; ?>
      <input type="text" name="nim" placeholder="NIM" required
        value="<?php echo isset($_POST['nim']) ? htmlspecialchars($_POST['nim']) : ''; ?>">

      <?php if($angkatanError != ""): ?>
        <div class="error-message">⚠️ <?php echo $angkatanError; ?></div>
      <?php endif; ?>
      <select name="angkatan" class="input-field" required>
        <option value="">Pilih Angkatan</option>
        <option value="2024" <?php echo (isset($_POST['angkatan']) && $_POST['angkatan'] == '2024') ? 'selected' : ''; ?>>2024</option>
        <option value="2025" <?php echo (isset($_POST['angkatan']) && $_POST['angkatan'] == '2025') ? 'selected' : ''; ?>>2025</option>
      </select>

      <?php if($emailError != ""): ?>
        <div class="error-message">⚠️ <?php echo $emailError; ?></div>
      <?php endif; ?>
      <input type="email" name="email" placeholder="Email" required
        value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">

      <?php if($waError != ""): ?>
        <div class="error-message">⚠️ <?php echo $waError; ?></div>
      <?php endif; ?>
      <input type="text" name="whatsapp" placeholder="Nomor WhatsApp, contoh 628123456789" required
        value="<?php echo isset($_POST['whatsapp']) ? htmlspecialchars($_POST['whatsapp']) : ''; ?>">

      <?php if($passwordError != ""): ?>
        <div class="error-message">⚠️ <?php echo $passwordError; ?></div>
      <?php endif; ?>
      <input type="password" name="password" placeholder="Password" required>

      <button type="submit" name="register">Register</button>

    </form>

    <?php if($sendError != ""): ?>
      <div class="error-message" style="margin-top:16px;">⚠️ <?php echo $sendError; ?></div>
    <?php endif; ?>

    <?php if($success != ""): ?>
      <div class="success-message"><?php echo $success; ?></div>
    <?php endif; ?>

    <div class="login-link">
      <p>Sudah punya akun?</p>
      <a href="mahasiswa.php">Login di sini</a>
    </div>

  </div>
</div>

<script src="../js/darkmode.js"></script>
</body>
</html>