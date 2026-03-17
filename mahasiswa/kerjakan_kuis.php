<?php
session_start();
require_once __DIR__ . '/../../sambung/forsafety.php';

date_default_timezone_set('Asia/Makassar');

if (!isset($_SESSION['email'])) {
    header("Location: ../login/mahasiswa.php");
    exit;
}

$email = $_SESSION['email'];
$error = "";

/* =========================
   AMBIL DATA MAHASISWA
========================= */
$stmtUser = $conn->prepare("
    SELECT nim, nama, email, is_verified
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

$nim  = $user['nim'];
$nama = $user['nama'];

$kuis_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($kuis_id <= 0) {
    header("Location: kuis.php");
    exit;
}

/* =========================
   CEK KUIS VALID & SESUAI ENROLL
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
        mk.kelas
    FROM kuis k
    JOIN mata_kuliah mk ON k.matkul_id = mk.id
    JOIN mahasiswa_matkul mm ON k.matkul_id = mm.matkul_id
    WHERE k.id = ? AND mm.nim = ?
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

/* =========================
   CEK JADWAL GLOBAL KUIS
========================= */
$sekarang = date('Y-m-d H:i:s');

if (!empty($kuis['waktu_mulai']) && $sekarang < $kuis['waktu_mulai']) {
    die("Kuis belum dimulai.");
}

if (!empty($kuis['waktu_selesai']) && $sekarang > $kuis['waktu_selesai']) {
    die("Kuis sudah ditutup.");
}

/* =========================
   AMBIL SOAL
========================= */
$stmtSoal = $conn->prepare("
    SELECT id, pertanyaan, opsi_a, opsi_b, opsi_c, opsi_d, jawaban_benar
    FROM soal_kuis
    WHERE kuis_id = ?
    ORDER BY id ASC
");
$stmtSoal->bind_param("i", $kuis_id);
$stmtSoal->execute();
$resultSoal = $stmtSoal->get_result();

$soalList = [];
while ($row = $resultSoal->fetch_assoc()) {
    $soalList[] = $row;
}
$stmtSoal->close();

if (count($soalList) <= 0) {
    die("Soal kuis belum tersedia.");
}

/* =========================
   CEK / BUAT SESI PENGERJAAN
========================= */
$stmtHasil = $conn->prepare("
    SELECT id, nilai, waktu_mulai, deadline, status
    FROM hasil_kuis
    WHERE kuis_id = ? AND nim = ?
    LIMIT 1
");
$stmtHasil->bind_param("is", $kuis_id, $nim);
$stmtHasil->execute();
$resultHasil = $stmtHasil->get_result();

$hasil_id = 0;
$deadline = null;

if ($resultHasil->num_rows > 0) {
    $hasil = $resultHasil->fetch_assoc();

    if ($hasil['status'] === 'selesai') {
        header("Location: kuis.php");
        exit;
    }

    $hasil_id = (int)$hasil['id'];
    $deadline = $hasil['deadline'];
} else {
    $waktuMulaiKerja = date('Y-m-d H:i:s');
    $deadlineBaru = date('Y-m-d H:i:s', strtotime("+{$kuis['durasi']} minutes"));

    $stmtInsertMulai = $conn->prepare("
        INSERT INTO hasil_kuis (kuis_id, nim, nilai, waktu_mulai, deadline, status, tanggal_submit)
        VALUES (?, ?, 0, ?, ?, 'sedang_mengerjakan', NOW())
    ");
    $stmtInsertMulai->bind_param("isss", $kuis_id, $nim, $waktuMulaiKerja, $deadlineBaru);

    if (!$stmtInsertMulai->execute()) {
        die("Gagal memulai sesi kuis.");
    }

    $hasil_id = $conn->insert_id;
    $deadline = $deadlineBaru;
    $stmtInsertMulai->close();
}
$stmtHasil->close();

/* =========================
   AMBIL JAWABAN TERSIMPAN
========================= */
$jawabanTersimpan = [];

$stmtSaved = $conn->prepare("
    SELECT soal_id, jawaban
    FROM jawaban_mahasiswa
    WHERE hasil_id = ?
");
$stmtSaved->bind_param("i", $hasil_id);
$stmtSaved->execute();
$resultSaved = $stmtSaved->get_result();

while ($rowSaved = $resultSaved->fetch_assoc()) {
    $jawabanTersimpan[$rowSaved['soal_id']] = $rowSaved['jawaban'];
}
$stmtSaved->close();

/* =========================
   FINALISASI DARI DB
========================= */
function finalisasiKuisDariDatabase($conn, $hasil_id, $kuis_id, $nim, $soalList)
{
    $stmtJawaban = $conn->prepare("
        SELECT soal_id, jawaban
        FROM jawaban_mahasiswa
        WHERE hasil_id = ?
    ");
    $stmtJawaban->bind_param("i", $hasil_id);
    $stmtJawaban->execute();
    $resultJawaban = $stmtJawaban->get_result();

    $jawabanMap = [];
    while ($row = $resultJawaban->fetch_assoc()) {
        $jawabanMap[$row['soal_id']] = $row['jawaban'];
    }
    $stmtJawaban->close();

    $jumlah_soal = count($soalList);
    $jumlah_benar = 0;

    foreach ($soalList as $soal) {
        $soal_id = $soal['id'];
        $jawaban = $jawabanMap[$soal_id] ?? '';

        if ($jawaban === $soal['jawaban_benar']) {
            $jumlah_benar++;
        }
    }

    $nilai = $jumlah_soal > 0 ? round(($jumlah_benar / $jumlah_soal) * 100) : 0;

    $stmtUpdate = $conn->prepare("
        UPDATE hasil_kuis
        SET nilai = ?, status = 'selesai', tanggal_submit = NOW()
        WHERE id = ? AND kuis_id = ? AND nim = ?
    ");
    $stmtUpdate->bind_param("iiis", $nilai, $hasil_id, $kuis_id, $nim);
    $ok = $stmtUpdate->execute();
    $stmtUpdate->close();

    return $ok;
}

/* =========================
   CEK WAKTU SUDAH HABIS
========================= */
$nowTs = time();
$deadlineTs = strtotime($deadline);

if ($deadlineTs <= $nowTs) {
    finalisasiKuisDariDatabase($conn, $hasil_id, $kuis_id, $nim, $soalList);
    header("Location: kuis.php");
    exit;
}

/* =========================
   SUBMIT FINAL MANUAL
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (finalisasiKuisDariDatabase($conn, $hasil_id, $kuis_id, $nim, $soalList)) {
        header("Location: kuis.php");
        exit;
    } else {
        $error = "Gagal menyimpan hasil kuis.";
    }
}

$sisaDetik = $deadlineTs - time();
if ($sisaDetik < 0) {
    $sisaDetik = 0;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Kerjakan Kuis | COMPULAB</title>
<link rel="stylesheet" href="../css/global.css">
<link rel="stylesheet" href="../css/dashboard-mahasiswa.css">
<style>
  .quiz-box { padding: 28px; }
  .timer-box {
    position: sticky;
    top: 20px;
    background: rgba(255,255,255,0.06);
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 14px;
    padding: 14px 18px;
    margin-bottom: 20px;
    font-size: 22px;
    font-weight: 700;
    color: #6ee7a8;
    display: inline-block;
    z-index: 10;
  }
  .question-card {
    background: rgba(255,255,255,0.04);
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 16px;
    padding: 20px;
    margin-bottom: 18px;
  }
  .question-card h4 { margin-bottom: 14px; }
  .option-item { margin-bottom: 10px; display:block; }
  .btn-submit {
    border:none;
    border-radius:12px;
    padding:12px 18px;
    background: linear-gradient(90deg, #49a078, #2d6a4f);
    color:white;
    font-weight:700;
    cursor:pointer;
  }
  .warning-text {
    color: #ffb3b3;
    margin-top: 8px;
    font-size: 14px;
  }
  .save-status {
    margin-top: 10px;
    font-size: 14px;
    color: #cfe9dd;
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
    <a href="../login/logout.php">🚪 Logout</a>
  </nav>
</div>

<div class="main">
  <div class="topbar">
    <h1><?php echo htmlspecialchars($kuis['judul']); ?></h1>
    <div class="profile-mini">🎓</div>
  </div>

  <div class="card quiz-box">
    <p><strong>Mata Kuliah:</strong> <?php echo htmlspecialchars($kuis['nama_matkul']); ?> | Kelas <?php echo htmlspecialchars($kuis['kelas']); ?></p>
    <p><strong>Durasi:</strong> <?php echo (int)$kuis['durasi']; ?> menit</p>
    <p><strong>Deadline Pengerjaan:</strong> <?php echo htmlspecialchars($deadline); ?></p>

    <div class="timer-box">
      Sisa Waktu: <span id="timer">00:00</span>
      <div class="warning-text">Jawaban tersimpan otomatis. Refresh tidak akan mengulang timer.</div>
    </div>

    <div class="save-status" id="save-status">Status penyimpanan: siap</div>

    <?php if (!empty($error)): ?>
      <p style="color:#ff8f8f;"><?php echo htmlspecialchars($error); ?></p>
    <?php endif; ?>

    <form method="POST" id="form-kuis">
      <?php $no = 1; ?>
      <?php foreach ($soalList as $soal): ?>
        <?php $selected = $jawabanTersimpan[$soal['id']] ?? ''; ?>
        <div class="question-card">
          <h4><?php echo $no++; ?>. <?php echo htmlspecialchars($soal['pertanyaan']); ?></h4>

          <label class="option-item">
            <input type="radio"
                   name="jawaban[<?php echo $soal['id']; ?>]"
                   value="A"
                   data-soal-id="<?php echo $soal['id']; ?>"
                   <?php echo ($selected === 'A') ? 'checked' : ''; ?>>
            A. <?php echo htmlspecialchars($soal['opsi_a']); ?>
          </label>

          <label class="option-item">
            <input type="radio"
                   name="jawaban[<?php echo $soal['id']; ?>]"
                   value="B"
                   data-soal-id="<?php echo $soal['id']; ?>"
                   <?php echo ($selected === 'B') ? 'checked' : ''; ?>>
            B. <?php echo htmlspecialchars($soal['opsi_b']); ?>
          </label>

          <label class="option-item">
            <input type="radio"
                   name="jawaban[<?php echo $soal['id']; ?>]"
                   value="C"
                   data-soal-id="<?php echo $soal['id']; ?>"
                   <?php echo ($selected === 'C') ? 'checked' : ''; ?>>
            C. <?php echo htmlspecialchars($soal['opsi_c']); ?>
          </label>

          <label class="option-item">
            <input type="radio"
                   name="jawaban[<?php echo $soal['id']; ?>]"
                   value="D"
                   data-soal-id="<?php echo $soal['id']; ?>"
                   <?php echo ($selected === 'D') ? 'checked' : ''; ?>>
            D. <?php echo htmlspecialchars($soal['opsi_d']); ?>
          </label>
        </div>
      <?php endforeach; ?>

      <button type="submit" class="btn-submit" id="submit-btn">Kumpulkan Jawaban</button>
    </form>
  </div>
</div>

<script>
const kuisId = <?php echo (int)$kuis_id; ?>;
let waktu = <?php echo (int)$sisaDetik; ?>;
const timerEl = document.getElementById('timer');
const form = document.getElementById('form-kuis');
const submitBtn = document.getElementById('submit-btn');
const saveStatus = document.getElementById('save-status');
let submitted = false;
let autoSubmitting = false;

function formatTime(totalSeconds) {
    const menit = Math.floor(totalSeconds / 60);
    const detik = totalSeconds % 60;
    return String(menit).padStart(2, '0') + ':' + String(detik).padStart(2, '0');
}

function setSaveStatus(text) {
    saveStatus.textContent = 'Status penyimpanan: ' + text;
}

function doSubmit() {
    if (submitted) return;
    submitted = true;
    submitBtn.disabled = true;
    submitBtn.innerText = 'Mengirim Jawaban...';
    form.submit();
}

function autoSubmitQuiz() {
    if (submitted) return;
    autoSubmitting = true;
    doSubmit();
}

function updateTimer() {
    timerEl.textContent = formatTime(waktu);

    if (waktu <= 0) {
        alert('Waktu habis! Kuis akan diakhiri dan jawaban yang sudah tersimpan akan dikirim.');
        autoSubmitQuiz();
        return;
    }

    waktu--;
}

updateTimer();
const timerInterval = setInterval(function () {
    updateTimer();
    if (waktu < 0) {
        clearInterval(timerInterval);
    }
}, 1000);

/* autosave */
document.querySelectorAll('input[type="radio"]').forEach(function(radio) {
    radio.addEventListener('change', function() {
        const soalId = this.dataset.soalId;
        const jawaban = this.value;

        setSaveStatus('menyimpan...');

        const formData = new FormData();
        formData.append('kuis_id', kuisId);
        formData.append('soal_id', soalId);
        formData.append('jawaban', jawaban);

        fetch('autosave_jawaban.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                setSaveStatus('tersimpan');
            } else {
                setSaveStatus('gagal menyimpan');
                console.error(data.message);
            }
        })
        .catch(error => {
            setSaveStatus('gagal menyimpan');
            console.error(error);
        });
    });
});

/* confirm submit manual */
form.addEventListener('submit', function(e) {
    if (submitted) {
        return;
    }

    if (autoSubmitting) {
        return;
    }

    e.preventDefault();

    const yakin = confirm('Yakin ingin submit jawaban?');
    if (yakin) {
        doSubmit();
    }
});

/* warning kalau keluar / refresh, tapi jangan saat submit */
window.addEventListener('beforeunload', function (e) {
    if (!submitted) {
        e.preventDefault();
        e.returnValue = '';
    }
});
</script>

<script src="../js/darkmode.js"></script>
</body>
</html>