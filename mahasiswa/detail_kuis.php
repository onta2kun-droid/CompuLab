<?php
session_start();
include "../idk/forsafety.php";

date_default_timezone_set('Asia/Makassar');

if (!isset($_SESSION['email'])) {
    header("Location: ../login/mahasiswa.php");
    exit;
}

$email = $_SESSION['email'];

/* =========================
   AMBIL DATA MAHASISWA
========================= */
$stmtUser = $conn->prepare("
    SELECT nim, nama, email, angkatan, is_verified
    FROM akun
    WHERE email = ? AND role = 'mahasiswa'
    LIMIT 1
");
$stmtUser->bind_param("s", $email);
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

$nim      = $user['nim'];
$nama     = $user['nama'];
$angkatan = $user['angkatan'];

$kuis_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($kuis_id <= 0) {
    header("Location: kuis.php");
    exit;
}

/* =========================
   CEK KUIS VALID
   + SUDAH DIKERJAKAN
========================= */
$stmtKuis = $conn->prepare("
    SELECT 
        k.id,
        k.judul,
        k.deskripsi,
        k.durasi,
        k.waktu_mulai,
        k.waktu_selesai,
        mk.nama_matkul,
        mk.kelas,
        hk.id AS hasil_id,
        hk.nilai,
        hk.tanggal_submit,
        hk.status
    FROM kuis k
    JOIN mata_kuliah mk ON k.matkul_id = mk.id
    JOIN mahasiswa_matkul mm ON k.matkul_id = mm.matkul_id
    JOIN hasil_kuis hk ON hk.kuis_id = k.id AND hk.nim = mm.nim
    WHERE k.id = ? AND mm.nim = ? AND hk.status = 'selesai'
    LIMIT 1
");
$stmtKuis->bind_param("is", $kuis_id, $nim);
$stmtKuis->execute();
$resultKuis = $stmtKuis->get_result();

if ($resultKuis->num_rows <= 0) {
    header("Location: kuis.php");
    exit;
}

$kuis = $resultKuis->fetch_assoc();
$stmtKuis->close();

$hasil_id = (int) $kuis['hasil_id'];

/* =========================
   AMBIL SOAL + JAWABAN USER
========================= */
$stmtDetail = $conn->prepare("
    SELECT
        sq.id,
        sq.pertanyaan,
        sq.opsi_a,
        sq.opsi_b,
        sq.opsi_c,
        sq.opsi_d,
        sq.jawaban_benar,
        jm.jawaban AS jawaban_user
    FROM soal_kuis sq
    LEFT JOIN jawaban_mahasiswa jm
        ON jm.soal_id = sq.id AND jm.hasil_id = ?
    WHERE sq.kuis_id = ?
    ORDER BY sq.id ASC
");
$stmtDetail->bind_param("ii", $hasil_id, $kuis_id);
$stmtDetail->execute();
$detailQuery = $stmtDetail->get_result();

$detailSoal = [];
$jumlahBenar = 0;
$jumlahSalah = 0;
$jumlahKosong = 0;

while ($row = $detailQuery->fetch_assoc()) {
    $jawabanUser = $row['jawaban_user'] ?? '';
    $jawabanBenar = $row['jawaban_benar'];

    if ($jawabanUser === '') {
        $jumlahKosong++;
    } elseif ($jawabanUser === $jawabanBenar) {
        $jumlahBenar++;
    } else {
        $jumlahSalah++;
    }

    $detailSoal[] = $row;
}
$stmtDetail->close();

$totalSoal = count($detailSoal);
?>
<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Detail Kuis | COMPULAB</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../css/global.css">
<link rel="stylesheet" href="../css/dashboard-mahasiswa.css">
<style>
  .section-card { padding: 28px; }
  .content-stack { display:flex; flex-direction:column; gap:24px; }

  .btn-back {
    display: inline-block;
    margin-bottom: 16px;
    padding: 10px 14px;
    background: #3498db;
    color: white;
    text-decoration: none;
    border-radius: 10px;
  }

  .summary-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 14px;
    margin-top: 18px;
    margin-bottom: 24px;
  }

  .summary-card {
    background: rgba(255,255,255,0.05);
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 14px;
    padding: 16px;
  }

  .summary-card h4 {
    margin: 0 0 8px;
    font-size: 14px;
    opacity: 0.75;
  }

  .summary-card p {
    margin: 0;
    font-size: 24px;
    font-weight: 700;
  }

  .question-card {
    background: rgba(255,255,255,0.04);
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 16px;
    padding: 20px;
    margin-bottom: 18px;
  }

  .question-card h4 {
    margin-bottom: 14px;
  }

  .option-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
    margin-top: 12px;
  }

  .option-item {
    padding: 10px 12px;
    border-radius: 10px;
    background: rgba(255,255,255,0.03);
    border: 1px solid transparent;
  }

  .option-correct {
    border-color: #2ecc71;
    background: rgba(46, 204, 113, 0.15);
  }

  .option-user-wrong {
    border-color: #e74c3c;
    background: rgba(231, 76, 60, 0.15);
  }

  .tag-row {
    margin-top: 16px;
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
  }

  .tag {
    display: inline-block;
    padding: 6px 10px;
    border-radius: 999px;
    font-size: 13px;
    font-weight: 700;
  }

  .tag-benar {
    background: rgba(46, 204, 113, 0.18);
    color: #7dffb3;
  }

  .tag-salah {
    background: rgba(231, 76, 60, 0.18);
    color: #ff9f9f;
  }

  .tag-kosong {
    background: rgba(255,255,255,0.08);
    color: rgba(255,255,255,0.8);
  }

  .meta-text {
    opacity: 0.82;
    line-height: 1.6;
  }
</style>
</head>
<body>

<div class="sidebar">
  <div class="logo">COMPULAB</div>
  <nav>
    <a href="dashboard.php">🏠 Dashboard</a>
    <a href="enroll.php">📘 Enroll Class</a>
    <a href="materi.php">📚 Materi</a>
    <a class="active" href="kuis.php">📝 Kuis</a>
    <a href="scoreboard.php">🏆 Scoreboard</a>
    <a href="profil.php">👤 Profil</a>
    <a href="../login/logout.php">🚪 Logout</a>
  </nav>
</div>

<div class="main">
  <div class="topbar">
    <h1>Detail Review Kuis</h1>
    <div class="profile-mini">🎓</div>
  </div>

  <div class="content-stack">
    <div class="card section-card">
      <a href="kuis.php" class="btn-back">← Kembali ke Kuis</a>

      <h3><?php echo htmlspecialchars($kuis['judul']); ?></h3>
      <p class="meta-text">
        <strong>Mata Kuliah:</strong> <?php echo htmlspecialchars($kuis['nama_matkul']); ?> | Kelas <?php echo htmlspecialchars($kuis['kelas']); ?><br>
        <strong>Nilai:</strong> <?php echo htmlspecialchars($kuis['nilai']); ?><br>
        <strong>Tanggal Submit:</strong> <?php echo htmlspecialchars($kuis['tanggal_submit']); ?><br>
        <strong>Deskripsi:</strong> <?php echo !empty($kuis['deskripsi']) ? htmlspecialchars($kuis['deskripsi']) : '-'; ?>
      </p>

      <div class="summary-grid">
        <div class="summary-card">
          <h4>Total Soal</h4>
          <p><?php echo $totalSoal; ?></p>
        </div>
        <div class="summary-card">
          <h4>Benar</h4>
          <p><?php echo $jumlahBenar; ?></p>
        </div>
        <div class="summary-card">
          <h4>Salah</h4>
          <p><?php echo $jumlahSalah; ?></p>
        </div>
        <div class="summary-card">
          <h4>Kosong</h4>
          <p><?php echo $jumlahKosong; ?></p>
        </div>
      </div>

      <?php $no = 1; ?>
      <?php foreach ($detailSoal as $soal): ?>
        <?php
          $jawabanUser = $soal['jawaban_user'] ?? '';
          $jawabanBenar = $soal['jawaban_benar'];

          $statusClass = 'tag-kosong';
          $statusText = 'Tidak Dijawab';

          if ($jawabanUser === '') {
              $statusClass = 'tag-kosong';
              $statusText = 'Tidak Dijawab';
          } elseif ($jawabanUser === $jawabanBenar) {
              $statusClass = 'tag-benar';
              $statusText = 'Jawaban Benar';
          } else {
              $statusClass = 'tag-salah';
              $statusText = 'Jawaban Salah';
          }

          $opsiList = [
              'A' => $soal['opsi_a'],
              'B' => $soal['opsi_b'],
              'C' => $soal['opsi_c'],
              'D' => $soal['opsi_d'],
          ];
        ?>
        <div class="question-card">
          <h4><?php echo $no++; ?>. <?php echo htmlspecialchars($soal['pertanyaan']); ?></h4>

          <div class="option-list">
            <?php foreach ($opsiList as $kode => $teks): ?>
              <?php
                $optionClass = 'option-item';

                if ($kode === $jawabanBenar) {
                    $optionClass .= ' option-correct';
                }

                if ($jawabanUser !== '' && $jawabanUser !== $jawabanBenar && $kode === $jawabanUser) {
                    $optionClass .= ' option-user-wrong';
                }
              ?>
              <div class="<?php echo $optionClass; ?>">
                <strong><?php echo $kode; ?>.</strong>
                <?php echo htmlspecialchars($teks); ?>
              </div>
            <?php endforeach; ?>
          </div>

          <div class="tag-row">
            <span class="tag <?php echo $statusClass; ?>"><?php echo $statusText; ?></span>
            <span class="tag tag-kosong">Jawaban Anda: <?php echo $jawabanUser !== '' ? htmlspecialchars($jawabanUser) : '-'; ?></span>
            <span class="tag tag-benar">Jawaban Benar: <?php echo htmlspecialchars($jawabanBenar); ?></span>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<script src="../js/darkmode.js"></script>
</body>
</html>