<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once __DIR__ . '/../../sambung/forsafety.php';

/* =========================
   CEK LOGIN
========================= */
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'dosen') {
    header("Location: ../login/dosen.php");
    exit();
}

$dosen_id   = $_SESSION['user_id'];
$nama_dosen = $_SESSION['user_nama'];

$success = "";
$error   = "";

$materi_id = (int) ($_GET['id'] ?? 0);

if ($materi_id <= 0) {
    header("Location: kelola_materi.php");
    exit();
}

/* =========================
   AMBIL MATA KULIAH DOSEN
========================= */
$stmtMatkul = $conn->prepare("
    SELECT mk.id, mk.nama_matkul, mk.angkatan, mk.kelas
    FROM mata_kuliah mk
    JOIN dosen_matkul dm ON mk.id = dm.matkul_id
    WHERE dm.dosen_id = ?
    ORDER BY mk.nama_matkul ASC, mk.kelas ASC
");

$matkulQuery = null;

if ($stmtMatkul) {
    $stmtMatkul->bind_param("i", $dosen_id);
    $stmtMatkul->execute();
    $matkulQuery = $stmtMatkul->get_result();
} else {
    $error = "Query mata kuliah gagal disiapkan.";
}

/* =========================
   AMBIL DATA MATERI
   pastikan milik dosen login
========================= */
$stmtMateri = $conn->prepare("
    SELECT id, matkul_id, dosen_id, judul, deskripsi, file_materi, created_at
    FROM materi
    WHERE id = ? AND dosen_id = ?
    LIMIT 1
");

if (!$stmtMateri) {
    die("Query materi gagal disiapkan.");
}

$stmtMateri->bind_param("ii", $materi_id, $dosen_id);
$stmtMateri->execute();
$resultMateri = $stmtMateri->get_result();

if ($resultMateri->num_rows <= 0) {
    header("Location: kelola_materi.php");
    exit();
}

$materi = $resultMateri->fetch_assoc();
$stmtMateri->close();

/* =========================
   PROSES UPDATE MATERI
========================= */
if (isset($_POST['update'])) {
    $judul      = trim($_POST['judul'] ?? '');
    $deskripsi  = trim($_POST['deskripsi'] ?? '');
    $matkul_id  = (int) ($_POST['matkul_id'] ?? 0);

    if ($judul === '' || $matkul_id <= 0) {
        $error = "Judul dan mata kuliah wajib diisi!";
    } else {

        /* validasi matkul milik dosen */
        $stmtCekMatkul = $conn->prepare("
            SELECT 1
            FROM dosen_matkul
            WHERE dosen_id = ? AND matkul_id = ?
            LIMIT 1
        ");

        if ($stmtCekMatkul) {
            $stmtCekMatkul->bind_param("ii", $dosen_id, $matkul_id);
            $stmtCekMatkul->execute();
            $resultCekMatkul = $stmtCekMatkul->get_result();

            if ($resultCekMatkul->num_rows <= 0) {
                $error = "Mata kuliah tidak valid atau bukan milik Anda!";
            } else {

                $fileBaruDipakai = false;
                $newName = $materi['file_materi'];

                /* =========================
                   kalau upload file baru
                ========================= */
                if (isset($_FILES['file']) && $_FILES['file']['error'] !== UPLOAD_ERR_NO_FILE) {

                    if ($_FILES['file']['error'] !== UPLOAD_ERR_OK) {
                        $error = "Terjadi kesalahan saat upload file.";
                    } else {
                        $fileName = $_FILES['file']['name'];
                        $tmpName  = $_FILES['file']['tmp_name'];
                        $fileSize = $_FILES['file']['size'];

                        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                        $allowedExt = ['pdf', 'ppt', 'pptx', 'doc', 'docx'];

                        if (!in_array($ext, $allowedExt)) {
                            $error = "Format file tidak didukung! Gunakan PDF, PPT, PPTX, DOC, atau DOCX.";
                        } elseif ($fileSize > 5 * 1024 * 1024) {
                            $error = "Ukuran file maksimal 5 MB!";
                        } else {
                            $uploadDir = "../uploads/materi/";

                            if (!is_dir($uploadDir)) {
                                mkdir($uploadDir, 0775, true);
                            }

                            if (!is_writable($uploadDir)) {
                                $error = "Folder upload tidak bisa ditulis.";
                            } else {
                                $newName = time() . "_" . uniqid() . "." . $ext;
                                $uploadPath = $uploadDir . $newName;

                                if (move_uploaded_file($tmpName, $uploadPath)) {
                                    $fileBaruDipakai = true;
                                } else {
                                    $error = "Gagal upload file baru.";
                                }
                            }
                        }
                    }
                }

                /* =========================
                   update database
                ========================= */
                if ($error === "") {
                    $stmtUpdate = $conn->prepare("
                        UPDATE materi
                        SET matkul_id = ?, judul = ?, deskripsi = ?, file_materi = ?
                        WHERE id = ? AND dosen_id = ?
                    ");

                    if ($stmtUpdate) {
                        $stmtUpdate->bind_param(
                            "isssii",
                            $matkul_id,
                            $judul,
                            $deskripsi,
                            $newName,
                            $materi_id,
                            $dosen_id
                        );

                        if ($stmtUpdate->execute()) {

                            /* hapus file lama kalau ada file baru */
                            if ($fileBaruDipakai && !empty($materi['file_materi'])) {
                                $fileLama = "../uploads/materi/" . $materi['file_materi'];
                                if (file_exists($fileLama)) {
                                    unlink($fileLama);
                                }
                            }

                            $success = "Materi berhasil diperbarui!";

                            /* refresh data materi */
                            $stmtRefresh = $conn->prepare("
                                SELECT id, matkul_id, dosen_id, judul, deskripsi, file_materi, created_at
                                FROM materi
                                WHERE id = ? AND dosen_id = ?
                                LIMIT 1
                            ");
                            $stmtRefresh->bind_param("ii", $materi_id, $dosen_id);
                            $stmtRefresh->execute();
                            $resultRefresh = $stmtRefresh->get_result();
                            $materi = $resultRefresh->fetch_assoc();
                            $stmtRefresh->close();

                        } else {
                            $error = "Gagal update materi: " . $stmtUpdate->error;

                            /* rollback file baru kalau query gagal */
                            if ($fileBaruDipakai) {
                                $fileBaru = "../uploads/materi/" . $newName;
                                if (file_exists($fileBaru)) {
                                    unlink($fileBaru);
                                }
                            }
                        }

                        $stmtUpdate->close();
                    } else {
                        $error = "Query update materi gagal disiapkan.";
                    }
                }
            }

            $stmtCekMatkul->close();
        } else {
            $error = "Validasi mata kuliah gagal.";
        }
    }

    /* refresh query matkul setelah submit */
    $stmtMatkul = $conn->prepare("
        SELECT mk.id, mk.nama_matkul, mk.angkatan, mk.kelas
        FROM mata_kuliah mk
        JOIN dosen_matkul dm ON mk.id = dm.matkul_id
        WHERE dm.dosen_id = ?
        ORDER BY mk.nama_matkul ASC, mk.kelas ASC
    ");
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
    <title>Edit Materi | COMPULAB</title>

    <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <script>
      const t = localStorage.getItem('compulab_theme');
      if (t) document.documentElement.setAttribute('data-theme', t);
    </script>

    <link rel="stylesheet" href="../css/dashboard-dosen.css">

    <style>
        .form-materi {
            display: flex;
            flex-direction: column;
            gap: 14px;
            max-width: 760px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .form-group label {
            font-size: 13px;
            font-weight: 600;
            color: var(--text-muted);
            margin: 0;
        }

        .form-materi input,
        .form-materi select,
        .form-materi textarea {
            width: 100%;
            box-sizing: border-box;
            padding: 12px 14px;
            font-size: 14px;
            border-radius: 12px;
            border: 1.5px solid var(--border);
            background: var(--bg-input);
            color: var(--text-primary);
            outline: none;
        }

        .form-materi input:focus,
        .form-materi select:focus,
        .form-materi textarea:focus {
            border-color: var(--navy-light);
        }

        .form-materi textarea {
            min-height: 120px;
            resize: vertical;
        }

        .info-box {
            background: var(--bg-input);
            border: 1px solid var(--border);
            padding: 16px 18px;
            border-radius: 14px;
            margin-bottom: 18px;
            line-height: 1.8;
            color: var(--text-primary);
        }

        .btn-back {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 18px;
            padding: 10px 14px;
            background: var(--bg-input);
            color: var(--text-primary);
            text-decoration: none;
            border-radius: 12px;
            border: 1px solid var(--border);
            font-size: 14px;
        }

        .small-note {
            font-size: 13px;
            color: var(--text-muted);
            margin-top: -4px;
        }

        .form-actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 8px;
        }

        .form-actions .btn-gold,
        .form-actions .btn-secondary {
            text-decoration: none;
        }

        .upload-box {
            border: 2px dashed var(--border);
            border-radius: 14px;
            padding: 22px 16px;
            text-align: center;
            background: var(--bg-input);
            position: relative;
            transition: border-color 0.2s, background 0.2s;
        }

        .upload-box:hover {
            border-color: var(--gold);
            background: rgba(245,166,35,0.04);
        }

        .upload-box input[type="file"] {
            position: absolute;
            inset: 0;
            opacity: 0;
            cursor: pointer;
            width: 100%;
            height: 100%;
        }

        .upload-hint {
            font-size: 12px;
            color: var(--text-muted);
            margin-top: 4px;
        }

        .file-preview {
            margin-top: 8px;
            font-size: 13px;
            font-weight: 600;
            color: var(--navy-fresh);
            display: none;
        }

        @media (max-width: 768px) {
            .form-materi {
                max-width: 100%;
            }

            .form-actions {
                flex-direction: column;
            }

            .form-actions .btn-gold,
            .form-actions .btn-secondary,
            .btn-back {
                width: 100%;
                justify-content: center;
                box-sizing: border-box;
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
        <a href="hasil.php">📈 Hasil Mahasiswa</a>
        <a href="scoreboard.php">🏆 Scoreboard</a>
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
        <a href="hasil.php">📈 Hasil Mahasiswa</a>
        <a href="scoreboard.php">🏆 Scoreboard</a>
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
        <h1>Edit Materi 📚</h1>
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
        <a href="kelola_materi.php" class="btn-back">← Kembali ke Kelola Materi</a>

        <h3 style="margin-bottom:16px;">Edit Materi</h3>

        <div class="info-box">
            <strong>File saat ini:</strong>
            <?php echo !empty($materi['file_materi']) ? htmlspecialchars($materi['file_materi']) : '-'; ?><br>
            <strong>Tanggal Upload:</strong>
            <?php echo htmlspecialchars($materi['created_at']); ?>
        </div>

        <form method="POST" enctype="multipart/form-data" class="form-materi">

            <div class="form-group">
                <label for="judul">Judul Materi</label>
                <input
                    type="text"
                    name="judul"
                    id="judul"
                    required
                    value="<?php echo htmlspecialchars($_POST['judul'] ?? $materi['judul']); ?>"
                >
            </div>

            <div class="form-group">
                <label for="deskripsi">Deskripsi</label>
                <textarea
                    name="deskripsi"
                    id="deskripsi"
                    placeholder="Masukkan deskripsi materi..."
                ><?php echo htmlspecialchars($_POST['deskripsi'] ?? $materi['deskripsi']); ?></textarea>
            </div>

            <div class="form-group">
                <label for="matkul_id">Mata Kuliah</label>
                <select name="matkul_id" id="matkul_id" required>
                    <option value="">-- Pilih Mata Kuliah --</option>
                    <?php if ($matkulQuery && $matkulQuery->num_rows > 0): ?>
                        <?php while ($matkul = $matkulQuery->fetch_assoc()): ?>
                            <?php
                                $selectedMatkul = (int)($_POST['matkul_id'] ?? $materi['matkul_id']);
                            ?>
                            <option
                                value="<?php echo $matkul['id']; ?>"
                                <?php echo ($selectedMatkul === (int)$matkul['id']) ? 'selected' : ''; ?>
                            >
                                <?php echo htmlspecialchars($matkul['nama_matkul'] . " | " . $matkul['angkatan'] . " " . $matkul['kelas']); ?>
                            </option>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="file">Ganti File Materi (opsional)</label>
                <div class="upload-box">
                    <input type="file" name="file" id="file" accept=".pdf,.ppt,.pptx,.doc,.docx">
                    <div style="font-size:28px; margin-bottom:6px;">📎</div>
                    <div style="font-size:13px; color:var(--text-muted);">Klik untuk pilih file baru</div>
                    <div class="upload-hint">PDF, PPT, PPTX, DOC, DOCX — Maks. 5 MB</div>
                    <div id="filePreview" class="file-preview"></div>
                </div>
                <div class="small-note">Kosongkan jika tidak ingin mengganti file.</div>
            </div>

            <div class="form-actions">
                <button type="submit" name="update" class="btn-gold">💾 Simpan Perubahan</button>
                <a href="kelola_materi.php" class="btn-secondary">Batal</a>
            </div>
        </form>
    </div>
</div>

<script src="../js/darkmode.js"></script>
<script>
    const btn = document.getElementById('hamburgerBtn');
    const drawer = document.getElementById('mobileDrawer');
    const overlay = document.getElementById('mobileOverlay');

    btn.addEventListener('click', () => {
        btn.classList.toggle('open');
        drawer.classList.toggle('open');
        overlay.classList.toggle('show');
    });

    overlay.addEventListener('click', () => {
        btn.classList.remove('open');
        drawer.classList.remove('open');
        overlay.classList.remove('show');
    });

    document.getElementById('file').addEventListener('change', function() {
        const preview = document.getElementById('filePreview');
        if (this.files.length > 0) {
            preview.textContent = '✅ ' + this.files[0].name;
            preview.style.display = 'block';
        } else {
            preview.style.display = 'none';
        }
    });
</script>
</body>
</html>