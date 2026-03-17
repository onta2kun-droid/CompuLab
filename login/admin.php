<?php
error_reporting(E_ALL);
ini_set('display_errors',1);

session_start();
include "../idk/forsafety.php";

$error = "";

if(isset($_POST['login'])){

    $email    = trim($_POST['email']);
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT id, nama, password, role FROM akun WHERE email = ? AND role = 'admin'");
    $stmt->bind_param("s",$email);
    $stmt->execute();
    $result = $stmt->get_result();

    if($result->num_rows === 1){

        $admin = $result->fetch_assoc();

        if(password_verify($password,$admin['password'])){

            $_SESSION['user_id'] = $admin['id'];
            $_SESSION['user_nama'] = $admin['nama'];
            $_SESSION['role'] = $admin['role'];

            header("Location: ../admin/dashboard.php");
            exit;

        }else{
            $error = "Password salah!";
        }

    }else{
        $error = "Admin tidak ditemukan!";
    }

    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Login Admin | COMPULAB</title>

<script>
  const t = localStorage.getItem('compulab_theme');
  if (t) document.documentElement.setAttribute('data-theme', t);
</script>

<link rel="stylesheet" href="../css/global.css">
<link rel="stylesheet" href="../css/login-admin.css">
</head>

<body>

<div class="bg-gradient"></div>

<div class="admin-wrapper">

<div class="admin-card">

<h2>Admin Control 👑</h2>
<p>Akses sistem COMPULAB</p>

<?php if($error != ""): ?>
<div class="error-msg">
⚠️ <?php echo htmlspecialchars($error); ?>
</div>
<?php endif; ?>

<form method="POST">

<input 
type="email" 
name="email" 
placeholder="Email Admin" 
required
value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
>

<input 
type="password" 
name="password" 
placeholder="Password"
required
>

<button type="submit" name="login">
Masuk Sistem
</button>

</form>

<div class="back">
<a href="../index.html">← Kembali</a>
</div>

</div>

</div>

<script src="../js/darkmode.js"></script>
</body>
</lang=>