<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once __DIR__ . '/../../sambung/forsafety.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'dosen') {
    header("Location: ../login/dosen.php");
    exit();
}

$dosen_id   = $_SESSION['user_id'];
$nama_dosen = $_SESSION['user_nama'];
$success = "";
$error   = "";
$editData = null;

/* ── HAPUS ── */
if (isset($_GET['hapus'])) {
    $id_materi = (int) $_GET['hapus'];
    $stmtCek = $conn->prepare("SELECT id, file_materi FROM materi WHERE id = ? AND dosen_id = ? LIMIT 1");
    if ($stmtCek) {
        $stmtCek->bind_param("ii", $id_materi, $dosen_id);
        $stmtCek->execute();
        $resultCek = $stmtCek->get_result();
        if ($resultCek->num_rows > 0) {
            $dataMateri = $resultCek->fetch_assoc();
            $filePath   = "../uploads/materi/" . $dataMateri['file_materi'];
            $stmtHapus  = $conn->prepare("DELETE FROM materi WHERE id = ? AND dosen_id = ?");
            if ($stmtHapus) {
                $stmtHapus->bind_param("ii", $id_materi, $dosen_id);
                if ($stmtHapus->execute()) {
                    if (!empty($dataMateri['file_materi']) && file_exists($filePath)) unlink($filePath);
                    $success = "Materi berhasil dihapus!";
                } else {
                    $error = "Gagal menghapus materi!";
                }
                $stmtHapus->close();
            }
        } else {
            $error = "Materi tidak ditemukan atau bukan milik Anda!";
        }
        $stmtCek->close();
    }
}

/* ── AMBIL DATA EDIT ── */
if (isset($_GET['edit'])) {
    $id_edit = (int) $_GET['edit'];
    $stmtEdit = $conn->prepare("SELECT m.*, mk.nama_matkul, mk.angkatan, mk.kelas FROM materi m JOIN mata_kuliah mk ON m.matkul_id = mk.id WHERE m.id = ? AND m.dosen_id = ? LIMIT 1");
    if ($stmtEdit) {
        $stmtEdit->bind_param("ii", $id_edit, $dosen_id);
        $stmtEdit->execute();
        $resEdit = $stmtEdit->get_result();
        if ($resEdit->num_rows > 0) {
            $editData = $resEdit->fetch_assoc();
        } else {
            $error = "Materi tidak ditemukan!";
        }
        $stmtEdit->close();
    }
}

/* ── PROSES EDIT ── */
if (isset($_POST['update_materi'])) {
    $id_materi  = (int) $_POST['id_materi'];
    $judul      = trim($_POST['judul'] ?? '');
    $deskripsi  = trim($_POST['deskripsi'] ?? '');
    $matkul_id  = (int) $_POST['matkul_id'];

    if (empty($judul) || empty($matkul_id)) {
        $error = "Judul dan mata kuliah wajib diisi!";
    } else {
        $newFileName = null;
        $oldFile = '';

        if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
            $allowedExt = ['pdf', 'ppt', 'pptx', 'doc', 'docx'];

            if (!in_array($ext, $allowedExt)) {
                $error = "Format tidak didukung! Gunakan PDF, PPT, PPTX, DOC, atau DOCX.";
            } elseif ($_FILES['file']['size'] > 5 * 1024 * 1024) {
                $error = "Ukuran file maksimal 5 MB!";
            } else {
                $uploadDir = "../uploads/materi/";
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

                $newFileName = time() . "_" . uniqid() . "." . $ext;
                if (!move_uploaded_file($_FILES['file']['tmp_name'], $uploadDir . $newFileName)) {
                    $error = "Upload file gagal!";
                    $newFileName = null;
                }
            }
        }

        if ($error == "") {
            if ($newFileName) {
                $stmtOld = $conn->prepare("SELECT file_materi FROM materi WHERE id = ? AND dosen_id = ?");
                $stmtOld->bind_param("ii", $id_materi, $dosen_id);
                $stmtOld->execute();
                $oldRes  = $stmtOld->get_result()->fetch_assoc();
                $oldFile = $oldRes['file_materi'] ?? '';
                $stmtOld->close();

                $stmtUpd = $conn->prepare("UPDATE materi SET judul=?, deskripsi=?, matkul_id=?, file_materi=? WHERE id=? AND dosen_id=?");
                $stmtUpd->bind_param("ssisii", $judul, $deskripsi, $matkul_id, $newFileName, $id_materi, $dosen_id);
            } else {
                $stmtUpd = $conn->prepare("UPDATE materi SET judul=?, deskripsi=?, matkul_id=? WHERE id=? AND dosen_id=?");
                $stmtUpd->bind_param("ssiii", $judul, $deskripsi, $matkul_id, $id_materi, $dosen_id);
            }

            if ($stmtUpd->execute()) {
                if ($newFileName && !empty($oldFile) && file_exists("../uploads/materi/" . $oldFile)) {
                    unlink("../uploads/materi/" . $oldFile);
                }
                $success  = "Materi berhasil diperbarui!";
                $editData = null;
            } else {
                $error = "Gagal memperbarui materi!";
            }
            $stmtUpd->close();
        }
    }
}

/* ── AMBIL MATKUL UNTUK FORM EDIT ── */
$matkulList = [];
$stmtMk = $conn->prepare("SELECT mk.id, mk.nama_matkul, mk.angkatan, mk.kelas FROM mata_kuliah mk JOIN dosen_matkul dm ON mk.id = dm.matkul_id WHERE dm.dosen_id = ? ORDER BY mk.nama_matkul ASC");
if ($stmtMk) {
    $stmtMk->bind_param("i", $dosen_id);
    $stmtMk->execute();
    $resMk = $stmtMk->get_result();
    while ($mk = $resMk->fetch_assoc()) $matkulList[] = $mk;
    $stmtMk->close();
}

/* =========================
   FILTER
========================= */
$filter_matkul = trim($_GET['matkul'] ?? '');
$filter_kelas  = trim($_GET['kelas'] ?? '');

/* =========================
   DATA FILTER
========================= */
$matkulFilter = [];
$stmtFilterMatkul = $conn->prepare("
SELECT DISTINCT mk.nama_matkul
FROM materi m
JOIN mata_kuliah mk ON m.matkul_id = mk.id
WHERE m.dosen_id = ?
ORDER BY mk.nama_matkul
");
if ($stmtFilterMatkul) {
    $stmtFilterMatkul->bind_param("i", $dosen_id);
    $stmtFilterMatkul->execute();
    $resMatkul = $stmtFilterMatkul->get_result();

    while ($row = $resMatkul->fetch_assoc()) {
        $matkulFilter[] = $row['nama_matkul'];
    }
    $stmtFilterMatkul->close();
}

$kelasFilter = [];
$stmtFilterKelas = $conn->prepare("
SELECT DISTINCT mk.kelas
FROM materi m
JOIN mata_kuliah mk ON m.matkul_id = mk.id
WHERE m.dosen_id = ?
ORDER BY mk.kelas
");
if ($stmtFilterKelas) {
    $stmtFilterKelas->bind_param("i", $dosen_id);
    $stmtFilterKelas->execute();
    $resKelas = $stmtFilterKelas->get_result();

    while ($row = $resKelas->fetch_assoc()) {
        $kelasFilter[] = $row['kelas'];
    }
    $stmtFilterKelas->close();
}

/* ── AMBIL SEMUA MATERI ── */
$materiQuery = null;

$sqlMateri = "
SELECT 
    m.id,
    m.judul,
    m.deskripsi,
    m.file_materi,
    m.created_at,
    m.matkul_id,
    mk.nama_matkul,
    mk.angkatan,
    mk.kelas
FROM materi m
JOIN mata_kuliah mk ON m.matkul_id = mk.id
WHERE m.dosen_id = ?
";

$params = [$dosen_id];
$types = "i";

if ($filter_matkul !== "") {
    $sqlMateri .= " AND mk.nama_matkul = ? ";
    $params[] = $filter_matkul;
    $types .= "s";
}

if ($filter_kelas !== "") {
    $sqlMateri .= " AND mk.kelas = ? ";
    $params[] = $filter_kelas;
    $types .= "s";
}

$sqlMateri .= " ORDER BY m.created_at DESC ";

$stmtMateri = $conn->prepare($sqlMateri);

if ($stmtMateri) {
    $stmtMateri->bind_param($types, ...$params);
    $stmtMateri->execute();
    $materiQuery = $stmtMateri->get_result();
} else {
    $error = "Query data materi gagal.";
}
?>
<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Kelola Materi | COMPULAB</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<script>
  const t = localStorage.getItem('compulab_theme');
  if (t) document.documentElement.setAttribute('data-theme', t);
</script>
<link rel="stylesheet" href="../css/dashboard-dosen.css">
<style>
/* Edit modal */
.modal-overlay {
  display: none;
  position: fixed; inset: 0;
  background: rgba(0,0,0,0.5);
  z-index: 300;
  align-items: center;
  justify-content: center;
  padding: 20px;
}
.modal-overlay.show { display: flex; }

.modal-box {
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 20px;
  padding: 32px;
  width: 100%;
  max-width: 560px;
  max-height: 90vh;
  overflow-y: auto;
  box-shadow: 0 20px 60px rgba(0,0,0,0.25);
  animation: fadeIn 0.3s ease both;
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 24px;
}

.modal-header h3 {
  font-family: 'DM Serif Display', serif;
  font-size: 20px;
  font-weight: 400;
  color: var(--text-primary);
  display: flex; align-items: center; gap: 10px;
}

.modal-header h3::before {
  content: '';
  display: inline-block;
  width: 4px; height: 20px;
  background: var(--gold);
  border-radius: 4px;
}

.modal-close {
  width: 36px; height: 36px;
  border-radius: 10px;
  border: 1.5px solid var(--border);
  background: var(--bg-input);
  color: var(--text-muted);
  font-size: 18px;
  cursor: pointer;
  display: flex; align-items: center; justify-content: center;
  transition: background 0.2s, color 0.2s;
}
.modal-close:hover { background: rgba(220,38,38,0.08); color: #ef4444; }

/* File upload in modal */
.file-upload-area {
  border: 2px dashed var(--border);
  border-radius: 14px;
  padding: 24px 16px;
  text-align: center;
  background: var(--bg-input);
  cursor: pointer;
  transition: border-color 0.2s, background 0.2s;
  position: relative;
}
.file-upload-area:hover { border-color: var(--gold); background: rgba(245,166,35,0.04); }
.file-upload-area input[type="file"] {
  position: absolute; inset: 0;
  opacity: 0; cursor: pointer; width: 100%; height: 100%;
}
.file-upload-hint { font-size: 12px; color: var(--text-muted); margin-top: 4px; }

/* Aksi buttons in table */
.aksi-group {
  display: flex;
  flex-direction: row;
  gap: 6px;
  align-items: center;
  flex-wrap: nowrap;
  white-space: nowrap;
}

.aksi-group a,
.aksi-group button {
  padding: 7px 12px;
  font-size: 12px;
  border-radius: 10px;
  white-space: nowrap;
}

.custom-table td {
  vertical-align: middle;
}

.custom-table td:last-child {
  min-width: 200px;
}

.custom-table td:nth-child(4) {
  max-width: 140px;
  min-width: 100px;
  font-size: 13px;
  color: var(--text-muted);
  word-break: break-word;
  line-height: 1.5;
}

/* Empty state */
.empty-state {
  text-align: center;
  padding: 48px 24px;
  color: var(--text-muted);
}
.empty-state .empty-icon { font-size: 48px; margin-bottom: 12px; }
.empty-state p { font-size: 14px; margin-bottom: 16px; }

@media (max-width: 640px) {
  .modal-box { padding: 20px 16px; }
  .aksi-group { flex-direction: column; }
  .aksi-group a, .aksi-group button { width: 100%; justify-content: center; }
}

@media (max-width: 640px) {
  .custom-table td:nth-child(4) {
    max-width: 100%;
    width: 100%;
    white-space: normal;
    word-break: break-word;
    overflow: visible;
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
    <a class="active" href="kelola_materi.php">📚 Kelola Materi</a>
    <a href="tambah_kuis.php">📝 Tambah Kuis</a>
    <a href="kelola_kuis.php">📋 Kelola Kuis</a>
    <a href="scoreboard.php">📈 Scoreboard</a>
    <a href="profil.php">👨‍🏫 Profil</a>
  </nav>
  <div class="sidebar-bottom"><a href="logout.php">🚪 Logout</a></div>
</div>

<div class="sidebar">
  <div class="logo">COMPULAB</div>
  <nav>
    <a href="dashboard.php">📊 Dashboard</a>
    <a href="upload_materi.php">📤 Upload Materi</a>
    <a class="active" href="kelola_materi.php">📚 Kelola Materi</a>
    <a href="tambah_kuis.php">📝 Tambah Kuis</a>
    <a href="kelola_kuis.php">📋 Kelola Kuis</a>
    <a href="scoreboard.php">📈 Scoreboard</a>
    <a href="profil.php">👨‍🏫 Profil</a>
  </nav>
  <div class="sidebar-bottom"><a href="logout.php">🚪 Logout</a></div>
</div>

<div class="main">

  <div class="topbar">
    <button class="hamburger" id="hamburgerBtn" aria-label="Menu">
      <span></span><span></span><span></span>
    </button>
    <h1>Kelola Materi 📚</h1>
    <div class="topbar-right">
      <a href="profil.php" class="profile-mini" title="Profil Dosen">👨‍🏫</a>
      <button class="dark-toggle-top" id="darkToggle">🌙</button>
    </div>
  </div>

  <?php if ($success): ?>
    <div class="alert-success" style="margin-bottom:20px;">✅ <?php echo htmlspecialchars($success); ?></div>
  <?php endif; ?>
  <?php if ($error): ?>
    <div class="alert-error" style="margin-bottom:20px;">⚠️ <?php echo htmlspecialchars($error); ?></div>
  <?php endif; ?>

  <div class="table-card">
      <h3 style="margin-bottom:16px;">Daftar Materi Anda</h3>

      <div style="display:flex; justify-content:space-between; align-items:end; margin-bottom:20px; flex-wrap:wrap; gap:12px;">
        
        <form method="GET" style="display:flex; gap:12px; flex-wrap:wrap; align-items:end; margin:0;">
          <div class="form-group" style="min-width:220px; margin:0;">
            <label>Mata Kuliah</label>
            <select name="matkul">
              <option value="">Semua Mata Kuliah</option>
              <?php foreach($matkulFilter as $mk): ?>
                <option value="<?php echo htmlspecialchars($mk); ?>" <?php if($filter_matkul==$mk) echo "selected"; ?>>
                  <?php echo htmlspecialchars($mk); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-group" style="min-width:160px; margin:0;">
            <label>Kelas</label>
            <select name="kelas">
              <option value="">Semua Kelas</option>
              <?php foreach($kelasFilter as $kls): ?>
                <option value="<?php echo htmlspecialchars($kls); ?>" <?php if($filter_kelas==$kls) echo "selected"; ?>>
                  <?php echo htmlspecialchars($kls); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div style="display:flex; gap:10px; flex-wrap:wrap;">
            <button type="submit" class="btn-gold">Filter</button>
            <a href="kelola_materi.php" class="btn-secondary">Reset</a>
          </div>
        </form>

        <a href="upload_materi.php" class="btn-gold" style="font-size:13px; padding:10px 18px;">+ Upload Materi Baru</a>
      </div>

    <?php if ($materiQuery && $materiQuery->num_rows > 0): ?>
    <table class="custom-table">
      <thead>
        <tr>
          <th>No</th>
          <th>Judul</th>
          <th>Mata Kuliah</th>
          <th>Deskripsi</th>
          <th>Tanggal</th>
          <th>Aksi</th>
        </tr>
      </thead>
      <tbody>
        <?php $no = 1; while ($row = $materiQuery->fetch_assoc()): ?>
        <tr>
          <td data-label="No"><?php echo $no++; ?></td>
          <td data-label="Judul"><strong><?php echo htmlspecialchars($row['judul']); ?></strong></td>
          <td data-label="Mata Kuliah">
            <span class="badge badge-navy"><?php echo htmlspecialchars($row['nama_matkul']); ?></span><br>
            <small style="color:var(--text-muted);">Angkatan <?php echo $row['angkatan']; ?> Kelas <?php echo $row['kelas']; ?></small>
          </td>
          <td data-label="Deskripsi" style="max-width:200px; word-break:break-word;">
            <?php 
                $desk = $row['deskripsi'] ?? '';
                if (!empty($desk)): 
                    $short = mb_substr($desk, 0, 60);
                    $isTrimmed = mb_strlen($desk) > 60;
            ?>
                <span title="<?php echo htmlspecialchars($desk); ?>" style="cursor:default;">
                    <?php echo htmlspecialchars($short) . ($isTrimmed ? '...' : ''); ?>
                </span>
            <?php else: ?>
                <span style="color:var(--text-muted);">-</span>
            <?php endif; ?>
          </td>
          <td data-label="Tanggal" style="white-space:nowrap; font-size:13px; color:var(--text-muted);">
            <?php echo date('d M Y', strtotime($row['created_at'])); ?>
          </td>
          <td data-label="Aksi">
            <div class="aksi-group">
              <?php if (!empty($row['file_materi'])): ?>
              <a href="../uploads/materi/<?php echo urlencode($row['file_materi']); ?>" target="_blank" class="btn-main" style="font-size:12px; padding:8px 14px;">📄 File</a>
              <?php endif; ?>
              <a href="edit_materi.php?id=<?php echo $row['id']; ?>" class="btn-secondary" style="font-size:12px; padding:8px 14px; text-decoration:none; display:inline-block;">✏️ Edit</a>
              <a href="kelola_materi.php?hapus=<?php echo $row['id']; ?>" class="btn-danger" style="font-size:12px; padding:8px 14px;"
                onclick="return confirm('Yakin ingin menghapus materi ini?')">🗑️ Hapus</a>
            </div>
          </td>
        </tr>
        <?php endwhile; ?>
      </tbody>
    </table>

    <?php else: ?>
    <div class="empty-state">
      <div class="empty-icon">📭</div>
      <p>Tidak ada materi yang cocok dengan filter.</p>
      <a href="kelola_materi.php" class="btn-secondary">Reset Filter</a>
    </div>
    <?php endif; ?>
  </div>

</div>

<!-- MODAL EDIT -->
<div class="modal-overlay" id="editModal">
  <div class="modal-box">
    <div class="modal-header">
      <h3>Edit Materi</h3>
      <button class="modal-close" onclick="closeEdit()">✕</button>
    </div>

    <form method="POST" enctype="multipart/form-data">
      <input type="hidden" name="id_materi" id="edit_id">

      <div class="form-group">
        <label>Judul Materi</label>
        <input type="text" name="judul" id="edit_judul" required placeholder="Judul materi">
      </div>

      <div class="form-group">
        <label>Mata Kuliah / Kelas</label>
        <select name="matkul_id" id="edit_matkul" required>
          <option value="">-- Pilih Mata Kuliah --</option>
          <?php foreach ($matkulList as $mk): ?>
            <option value="<?php echo $mk['id']; ?>">
              <?php echo htmlspecialchars($mk['nama_matkul'] . " — Angkatan " . $mk['angkatan'] . " Kelas " . $mk['kelas']); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group">
        <label>Deskripsi <span style="color:var(--text-muted); font-weight:400;">(opsional)</span></label>
        <textarea name="deskripsi" id="edit_deskripsi" style="min-height:90px; resize:vertical;" placeholder="Deskripsi materi..."></textarea>
      </div>

      <div class="form-group">
        <label>Ganti File <span style="color:var(--text-muted); font-weight:400;">(kosongkan jika tidak ingin mengganti)</span></label>
        <div class="file-upload-area">
          <input type="file" name="file" id="editFileInput" accept=".pdf,.ppt,.pptx,.doc,.docx">
          <div style="font-size:28px; margin-bottom:6px;">📎</div>
          <div style="font-size:13px; color:var(--text-muted);">Klik untuk pilih file baru</div>
          <div class="file-upload-hint">PDF, PPT, PPTX, DOC, DOCX — Maks. 5 MB</div>
          <div id="editFileName" style="margin-top:8px; font-size:13px; font-weight:600; color:var(--navy-fresh); display:none;"></div>
        </div>
      </div>

      <div style="display:flex; gap:12px; margin-top:8px;">
        <button type="submit" name="update_materi" class="btn-gold">💾 Simpan Perubahan</button>
        <button type="button" class="btn-secondary" onclick="closeEdit()">Batal</button>
      </div>
    </form>
  </div>
</div>

<script src="../js/darkmode.js"></script>
<script>
  // Hamburger
  const btn = document.getElementById('hamburgerBtn');
  const drawer = document.getElementById('mobileDrawer');
  const overlay = document.getElementById('mobileOverlay');
  btn.addEventListener('click', () => { btn.classList.toggle('open'); drawer.classList.toggle('open'); overlay.classList.toggle('show'); });
  overlay.addEventListener('click', () => { btn.classList.remove('open'); drawer.classList.remove('open'); overlay.classList.remove('show'); });

  // Modal edit
  function openEdit(id, judul, deskripsi, matkulId) {
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_judul').value = judul;
    document.getElementById('edit_deskripsi').value = deskripsi;
    document.getElementById('edit_matkul').value = matkulId;
    document.getElementById('editFileName').style.display = 'none';
    document.getElementById('editModal').classList.add('show');
    document.body.style.overflow = 'hidden';
  }

  function closeEdit() {
    document.getElementById('editModal').classList.remove('show');
    document.body.style.overflow = '';
  }

  document.getElementById('editModal').addEventListener('click', function(e) {
    if (e.target === this) closeEdit();
  });

  // File name display in modal
  document.getElementById('editFileInput').addEventListener('change', function() {
    const display = document.getElementById('editFileName');
    if (this.files.length > 0) {
      display.textContent = '✅ ' + this.files[0].name;
      display.style.display = 'block';
    }
  });
</script>
</body>
</html>