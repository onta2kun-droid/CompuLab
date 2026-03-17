<?php
session_start();
require_once __DIR__ . '/../../sambung/forsafety.php';

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $email = trim($_POST['email']);
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT id, nama, password, role FROM akun WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {

        $user = $result->fetch_assoc();

        // cek password
        if (password_verify($password, $user['password'])) {

            // cek role dosen
            if ($user['role'] === 'dosen') {

                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_nama'] = $user['nama'];
                $_SESSION['role'] = $user['role'];

                header("Location: ../dosen/dashboard.php");
                exit();

            } else {
                $error = "Akun ini bukan akun dosen!";
            }

        } else {
            $error = "Password salah!";
        }

    } else {
        $error = "Email tidak ditemukan!";
    }

    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login Dosen | COMPULAB</title>

<script>
  const t = localStorage.getItem('compulab_theme');
  if (t) document.documentElement.setAttribute('data-theme', t);
</script>

<link rel="stylesheet" href="../css/global.css">
<link rel="stylesheet" href="../css/login-dosen.css">
</head>

<body>

<div class="background-blur"></div>

<div class="login-wrapper">
<div class="login-card">

<h2>Mode Dosen 👨‍🏫</h2>
<p>Kelola materi dan kuis mahasiswa</p>

<?php if (!empty($error)) : ?>
<div class="error-msg">
⚠️ <?php echo htmlspecialchars($error); ?>
</div>
<?php endif; ?>

<form method="POST" action="">
<input type="email" name="email" placeholder="Email Dosen" required>

<input type="password" name="password" placeholder="Password" required>

<button type="submit">Masuk</button>
</form>

<div class="back">
<a href="../index.html">← Kembali ke Beranda</a>
</div>

</div>
</div>

<script src="../js/darkmode.js"></script>
</body>
</html>