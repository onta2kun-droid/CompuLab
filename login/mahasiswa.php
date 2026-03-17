<?php
session_start();
include "../idk/forsafety.php";

$emailError = "";
$passwordError = "";
$verifyError = "";

if (isset($_POST['login'])) {

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === "" || $password === "") {
        $emailError = "Email dan password wajib diisi!";
    } else {

        $stmt = $conn->prepare("
            SELECT id, nim, nama, email, password, angkatan, is_verified, role
            FROM akun
            WHERE email = ? AND role = 'mahasiswa'
            LIMIT 1
        ");

        if ($stmt) {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();

                /* cek password dulu */
                if (password_verify($password, $row['password'])) {

                    /* kalau password benar tapi belum verifikasi */
                    if ((int)$row['is_verified'] !== 1) {
                        header("Location: verify_mahasiswa.php?email=" . urlencode($row['email']));
                        exit;
                    }

                    /* login sukses */
                    $_SESSION['user_id']       = $row['id'];
                    $_SESSION['nim']           = $row['nim'];
                    $_SESSION['user_nama']     = $row['nama'];
                    $_SESSION['nama']          = $row['nama'];
                    $_SESSION['email']         = $row['email'];
                    $_SESSION['user_angkatan'] = $row['angkatan'];
                    $_SESSION['role']          = $row['role'];

                    header("Location: ../mahasiswa/dashboard.php");
                    exit;
                } else {
                    $passwordError = "Password salah!";
                }
            } else {
                $emailError = "Email mahasiswa tidak ditemukan!";
            }

            $stmt->close();
        } else {
            $emailError = "Terjadi kesalahan pada sistem.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login Mahasiswa</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">

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
        <h2>Selamat Datang 🎓</h2>
        <p>Masuk untuk melanjutkan belajar</p>

        <form method="POST">
            <?php if ($emailError != ""): ?>
                <div class="error-message"><?php echo htmlspecialchars($emailError); ?></div>
            <?php endif; ?>

            <?php if ($verifyError != ""): ?>
                <div class="error-message"><?php echo htmlspecialchars($verifyError); ?></div>
            <?php endif; ?>

            <input 
                type="email" 
                name="email" 
                placeholder="Email" 
                required
                value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
            >

            <?php if ($passwordError != ""): ?>
                <div class="error-message"><?php echo htmlspecialchars($passwordError); ?></div>
            <?php endif; ?>

            <input type="password" name="password" placeholder="Password" required>
            <button type="submit" name="login">Login</button>
        </form>

        <div class="register-link">
            <p>Belum punya akun?</p>
            <a href="mahasiswa_register.php">Daftar Akun Mahasiswa</a>
        </div>

        <div class="back">
            <p>Atau</p>
            <a href="../index.html">← Kembali</a>
        </div>   
    </div>
</div>

<script src="../js/darkmode.js"></script>
</body>
</html>