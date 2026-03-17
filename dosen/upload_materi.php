<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
include "../idk/forsafety.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'dosen') {
    header("Location: ../login/dosen.php");
    exit();
}

$dosen_id   = $_SESSION['user_id'];
$nama_dosen = $_SESSION['user_nama'];
$success = "";
$error   = "";

$stmtMatkul = $conn->prepare("
    SELECT mk.id, mk.nama_matkul, mk.angkatan, mk.kelas
    FROM mata_kuliah mk
    JOIN dosen_matkul dm ON mk.id = dm.matkul_id
    WHERE dm.dosen_id = ?
    ORDER BY mk.nama_matkul ASC, mk.kelas ASC
");
if ($stmtMatkul) {
    $stmtMatkul->bind_param("i", $dosen_id);
    $stmtMatkul->execute();
    $matkulQuery = $stmtMatkul->get_result();
} else {
    $error = "Query mata kuliah gagal disiapkan.";
}

if (isset($_POST['upload'])) {
    $judul     = trim($_POST['judul'] ?? '');
    $deskripsi = trim($_POST['deskripsi'] ?? '');
    $matkul_id = (int)($_POST['matkul_id'] ?? 0);

    if (empty($judul) || empty($matkul_id)) {
        $error = "Judul dan mata kuliah wajib diisi!";
    } elseif (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        $error = "File wajib dipilih!";
    } else {
        $cekMatkul = $conn->prepare("SELECT 1 FROM dosen_matkul WHERE dosen_id = ? AND matkul_id = ? LIMIT 1");
        if ($cekMatkul) {
            $cekMatkul->bind_param("ii", $dosen_id, $matkul_id);
            $cekMatkul->execute();
            if ($cekMatkul->get_result()->num_rows === 0) {
                $error = "Mata kuliah tidak valid atau bukan milik Anda!";
            } else {
                $fileName = $_FILES['file']['name'];
                $tmpName  = $_FILES['file']['tmp_name'];
                $fileSize = $_FILES['file']['size'];
                $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                $allowedExt = ['pdf', 'ppt', 'pptx', 'doc', 'docx'];

                if (!in_array($ext, $allowedExt)) {
                    $error = "Format tidak didukung! Gunakan PDF, PPT, PPTX, DOC, atau DOCX.";
                } elseif ($fileSize > 5 * 1024 * 1024) {
                    $error = "Ukuran file maksimal 5 MB!";
                } else {
                    $uploadDir = "../uploads/materi/";
                    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
                    $newName    = time() . "_" . uniqid() . "." . $ext;
                    $uploadPath = $uploadDir . $newName;

                    if (move_uploaded_file($tmpName, $uploadPath)) {
                        $stmt = $conn->prepare("INSERT INTO materi (matkul_id, dosen_id, judul, deskripsi, file_materi) VALUES (?, ?, ?, ?, ?)");
                        if ($stmt) {
                            $stmt->bind_param("iisss", $matkul_id, $dosen_id, $judul, $deskripsi, $newName);
                            if ($stmt->execute()) {
                                $success = "Materi berhasil diupload!";
                            } else {
                                $error = "Gagal menyimpan ke database.";
                                if (file_exists($uploadPath)) unlink($uploadPath);
                            }
                            $stmt->close();
                        }
                    } else {
                        $error = "Upload file gagal!";
                    }
                }
            }
            $cekMatkul->close();
        }
    }

    $stmtMatkul = $conn->prepare("SELECT mk.id, mk.nama_matkul, mk.angkatan, mk.kelas FROM mata_kuliah mk JOIN dosen_matkul dm ON mk.id = dm.matkul_id WHERE dm.dosen_id = ? ORDER BY mk.nama_matkul ASC, mk.kelas ASC");
    $stmtMatkul->bind_param("i", $dosen_id);
    $stmtMatkul->execute();
    $matkulQuery = $stmtMatkul->get_result();
}
?>
<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Upload Materi | COMPULAB</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<script>
  const t = localStorage.getItem('compulab_theme');
  if (t) document.documentElement.setAttribute('data-theme', t);
</script>
<link rel="stylesheet" href="../css/dashboard-dosen.css">
<style>
.upload-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 20px;
  margin-bottom: 28px;
}
.upload-grid .form-group:last-child {
  grid-column: 1 / -1;
}
.file-upload-area {
  border: 2px dashed var(--border);
  border-radius: 14px;
  padding: 32px 20px;
  text-align: center;
  background: var(--bg-input);
  cursor: pointer;
  transition: border-color 0.2s, background 0.2s;
  position: relative;
}
.file-upload-area:hover,
.file-upload-area.dragover {
  border-color: var(--gold);
  background: rgba(245,166,35,0.05);
}
.file-upload-area input[type="file"] {
  position: absolute;
  inset: 0;
  opacity: 0;
  cursor: pointer;
  width: 100%;
  height: 100%;
}
.file-upload-icon { font-size: 36px; margin-bottom: 10px; }
.file-upload-text { font-size: 14px; color: var(--text-muted); }
.file-upload-text strong { color: var(--gold); }
.file-upload-hint { font-size: 12px; color: var(--text-muted); margin-top: 6px; }
.file-name-display {
  margin-top: 10px;
  font-size: 13px;
  font-weight: 600;
  color: var(--navy-fresh);
  display: none;
}
[data-theme="dark"] .file-name-display { color: var(--navy-light); }
.form-actions {
  display: flex;
  gap: 12px;
  align-items: center;
  margin-top: 8px;
}
@media (max-width: 640px) {
  .upload-grid { grid-template-columns: 1fr; }
  .upload-grid .form-group:last-child { grid-column: 1; }
}
</style>
</head>
<body>

<div class="mobile-overlay" id="mobileOverlay"></div>
<div class="mobile-drawer" id="mobileDrawer">
  <div class="logo">COMPULAB</div>
  <nav>
    <a href="dashboard.php">📊 Dashboard</a>
    <a class="active" href="upload_materi.php">📤 Upload Materi</a>
    <a href="kelola_materi.php">📚 Kelola Materi</a>
    <a href="tambah_kuis.php">📝 Tambah Kuis</a>
    <a href="kelola_kuis.php">📋 Kelola Kuis</a>
    <a href="scoreboard.php">📈 Scoreboard</a>
    <a href="profil.php">👨‍🏫 Profil</a>
  </nav>
  <div class="sidebar-bottom">
    <a href="logout.php">🚪 Logout</a>
  </div>
</div>

<div class="sidebar">
  <div class="logo">COMPULAB</div>
  <nav>
    <a href="dashboard.php">📊 Dashboard</a>
    <a class="active" href="upload_materi.php">📤 Upload Materi</a>
    <a href="kelola_materi.php">📚 Kelola Materi</a>
    <a href="tambah_kuis.php">📝 Tambah Kuis</a>
    <a href="kelola_kuis.php">📋 Kelola Kuis</a>
    <a href="scoreboard.php">📈 Scoreboard</a>
    <a href="profil.php">👨‍🏫 Profil</a>
  </nav>
  <div class="sidebar-bottom">
    <a href="logout.php">🚪 Logout</a>
  </div>
</div>

<div class="main">

  <div class="topbar">
    <button class="hamburger" id="hamburgerBtn" aria-label="Menu">
      <span></span><span></span><span></span>
    </button>
    <h1>Upload Materi 📤</h1>
    <div class="topbar-right">
      <a href="profil.php" class="profile-mini" title="Profil Dosen">👨‍🏫</a>
      <button class="dark-toggle-top" id="darkToggle" title="Toggle Dark Mode">🌙</button>
    </div>
  </div>

  <?php if ($error): ?>
    <div class="alert-error" style="margin-bottom:20px;">⚠️ <?php echo htmlspecialchars($error); ?></div>
  <?php endif; ?>
  <?php if ($success): ?>
    <div class="alert-success" style="margin-bottom:20px;">✅ <?php echo htmlspecialchars($success); ?></div>
  <?php endif; ?>

  <div class="table-card">
    <h3>Upload Materi Baru</h3>

    <form method="POST" enctype="multipart/form-data">

      <div class="upload-grid">

        <div class="form-group">
          <label for="judul">Judul Materi</label>
          <input type="text" name="judul" id="judul" placeholder="Contoh: Pertemuan 1 - Pengantar" required
            value="<?php echo isset($_POST['judul']) ? htmlspecialchars($_POST['judul']) : ''; ?>">
        </div>

        <div class="form-group">
          <label for="matkul_id">Mata Kuliah / Kelas</label>
          <select name="matkul_id" id="matkul_id" required>
            <option value="">-- Pilih Mata Kuliah --</option>
            <?php if (isset($matkulQuery) && $matkulQuery->num_rows > 0): ?>
              <?php while ($matkul = $matkulQuery->fetch_assoc()): ?>
                <option value="<?php echo $matkul['id']; ?>"
                  <?php echo (isset($_POST['matkul_id']) && $_POST['matkul_id'] == $matkul['id']) ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars($matkul['nama_matkul'] . " — Angkatan " . $matkul['angkatan'] . " Kelas " . $matkul['kelas']); ?>
                </option>
              <?php endwhile; ?>
            <?php else: ?>
              <option value="">Tidak ada mata kuliah</option>
            <?php endif; ?>
          </select>
        </div>

        <div class="form-group" style="grid-column: 1 / -1;">
          <label for="deskripsi">Deskripsi <span style="color:var(--text-muted); font-weight:400;">(opsional)</span></label>
          <textarea name="deskripsi" id="deskripsi" placeholder="Masukkan deskripsi singkat tentang materi ini..." style="min-height:110px; resize:vertical;"><?php echo isset($_POST['deskripsi']) ? htmlspecialchars($_POST['deskripsi']) : ''; ?></textarea>
        </div>

        <div class="form-group" style="grid-column: 1 / -1;">
          <label>File Materi</label>
          <div class="file-upload-area" id="fileDropArea">
            <input type="file" name="file" id="fileInput" accept=".pdf,.ppt,.pptx,.doc,.docx" required>
            <div class="file-upload-icon">📎</div>
            <div class="file-upload-text">Drag & drop file atau <strong>klik untuk pilih</strong></div>
            <div class="file-upload-hint">PDF, PPT, PPTX, DOC, DOCX — Maks. 5 MB</div>
            <div class="file-name-display" id="fileNameDisplay"></div>
          </div>
        </div>

      </div>

      <div class="form-actions">
        <button type="submit" name="upload" class="btn-gold">📤 Upload Materi</button>
        <a href="kelola_materi.php" class="btn-secondary">Lihat Materi</a>
      </div>

    </form>
  </div>

</div>

<script src="../js/darkmode.js"></script>
<script>
  const btn = document.getElementById('hamburgerBtn');
  const drawer = document.getElementById('mobileDrawer');
  const overlay = document.getElementById('mobileOverlay');
  btn.addEventListener('click', () => { btn.classList.toggle('open'); drawer.classList.toggle('open'); overlay.classList.toggle('show'); });
  overlay.addEventListener('click', () => { btn.classList.remove('open'); drawer.classList.remove('open'); overlay.classList.remove('show'); });

  // File upload display
  const fileInput = document.getElementById('fileInput');
  const fileNameDisplay = document.getElementById('fileNameDisplay');
  const dropArea = document.getElementById('fileDropArea');

  fileInput.addEventListener('change', () => {
    if (fileInput.files.length > 0) {
      fileNameDisplay.textContent = '✅ ' + fileInput.files[0].name;
      fileNameDisplay.style.display = 'block';
    }
  });

  dropArea.addEventListener('dragover', e => { e.preventDefault(); dropArea.classList.add('dragover'); });
  dropArea.addEventListener('dragleave', () => dropArea.classList.remove('dragover'));
  dropArea.addEventListener('drop', e => {
    e.preventDefault();
    dropArea.classList.remove('dragover');
    if (e.dataTransfer.files.length > 0) {
      fileInput.files = e.dataTransfer.files;
      fileNameDisplay.textContent = '✅ ' + e.dataTransfer.files[0].name;
      fileNameDisplay.style.display = 'block';
    }
  });
</script>
</body>
</html>