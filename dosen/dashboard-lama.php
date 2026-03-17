<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
include "../idk/forsafety.php";

/* ===============================
   CEK LOGIN
================================ */

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'dosen') {
    header("Location: ../login/dosen.php");
    exit();
}

$dosen_id   = $_SESSION['user_id'];
$nama_dosen = $_SESSION['user_nama'];


/* ===============================
   TOTAL MATERI
================================ */

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
    $result1 = $stmt1->get_result();
    $data1 = $result1->fetch_assoc();
    $totalMateri = $data1['total'] ?? 0;
    $stmt1->close();
}


/* ===============================
   TOTAL KUIS
================================ */

$totalKuis = 0;

$stmt2 = $conn->prepare("
SELECT COUNT(DISTINCT k.id) as total 
FROM kuis k
JOIN dosen_matkul dm ON k.matkul_id = dm.matkul_id
WHERE dm.dosen_id = ?
");

if($stmt2){
    $stmt2->bind_param("i", $dosen_id);
    $stmt2->execute();
    $result2 = $stmt2->get_result();
    $data2 = $result2->fetch_assoc();
    $totalKuis = $data2['total'] ?? 0;
    $stmt2->close();
}


/* ===============================
   TOTAL MAHASISWA
================================ */

$totalMahasiswa = 0;

$stmt3 = $conn->prepare("
SELECT COUNT(DISTINCT mm.nim) as total
FROM mahasiswa_matkul mm
JOIN dosen_matkul dm ON mm.matkul_id = dm.matkul_id
WHERE dm.dosen_id = ?
");

if($stmt3){
    $stmt3->bind_param("i", $dosen_id);
    $stmt3->execute();
    $result3 = $stmt3->get_result();
    $data3 = $result3->fetch_assoc();
    $totalMahasiswa = $data3['total'] ?? 0;
    $stmt3->close();
}


/* ===============================
   MATA KULIAH DOSEN
================================ */

$matkul = null;

$stmt4 = $conn->prepare("
SELECT mk.nama_matkul, mk.angkatan, mk.kelas
FROM mata_kuliah mk
JOIN dosen_matkul dm ON mk.id = dm.matkul_id
WHERE dm.dosen_id = ?
ORDER BY mk.angkatan
");

if($stmt4){
    $stmt4->bind_param("i", $dosen_id);
    $stmt4->execute();
    $matkul = $stmt4->get_result();
}

?>

<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>

<meta charset="UTF-8">
<title>Dashboard Dosen | COMPULAB</title>

<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">

<!-- <script>
  const t = localStorage.getItem('compulab_theme');
  if (t) document.documentElement.setAttribute('data-theme', t);
</script> -->

<!-- <link rel="stylesheet" href="../css/global.css"> -->
<link rel="stylesheet" href="../css/dashboard-dosen.css">

</head>

<body>

<div class="sidebar">

<div class="logo">COMPULAB</div>

<nav>
<a href="dashboard.php">📊 Dashboard</a>
<a href="upload_materi.php">📤 Upload Materi</a>
<a href="kelola_materi.php">📚 Kelola Materi</a>
<a href="tambah_kuis.php">📝 Tambah Kuis</a>
<a href="kelola_kuis.php">📋 Kelola Kuis</a>
<a href="scoreboard.php">📈 Scoreboard</a>
<a href="logout.php">🚪 Logout</a>
</nav>

</div>


<div class="main">

<div class="topbar">

<h1>Halo, <?php echo htmlspecialchars($nama_dosen); ?> 👨‍🏫</h1>

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

<table border="1" cellpadding="8">

<tr>
<th>Mata Kuliah</th>
<th>Angkatan</th>
<th>Kelas</th>
</tr>

<?php if($matkul): ?>
<?php while($row = $matkul->fetch_assoc()): ?>

<tr>
<td><?php echo htmlspecialchars($row['nama_matkul']); ?></td>
<td><?php echo htmlspecialchars($row['angkatan']); ?></td>
<td><?php echo htmlspecialchars($row['kelas']); ?></td>
</tr>

<?php endwhile; ?>
<?php endif; ?>

</table>

</div>

</div>

<!-- <script src="../js/darkmode.js"></script> -->
</body>
</html>