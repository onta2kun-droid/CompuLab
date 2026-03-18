<?php
session_start();
require_once __DIR__ . '/../../sambung/forsafety.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login/admin.php");
    exit();
}

$admin_nama = $_SESSION['user_nama'] ?? 'Admin';
$success = "";
$error   = "";
$tab = $_GET['tab'] ?? 'dosen';

/* =========================
   HAPUS AKUN
========================= */
if (isset($_GET['hapus']) && isset($_GET['role_hapus'])) {
    $nim_or_id   = $_GET['hapus'];
    $role_hapus  = $_GET['role_hapus'];

    $conn->begin_transaction();

    try {
        if ($role_hapus === 'mahasiswa') {
            $nim = trim($nim_or_id);

            /* hapus jawaban mahasiswa dulu jika tabel ada */
            $checkJawaban = $conn->query("SHOW TABLES LIKE 'jawaban_mahasiswa'");
            if ($checkJawaban && $checkJawaban->num_rows > 0) {
                $stmtJawaban = $conn->prepare("
                    DELETE jm
                    FROM jawaban_mahasiswa jm
                    JOIN hasil_kuis hk ON jm.hasil_id = hk.id
                    WHERE hk.nim = ?
                ");
                if ($stmtJawaban) {
                    $stmtJawaban->bind_param("s", $nim);
                    $stmtJawaban->execute();
                    $stmtJawaban->close();
                }
            }

            /* hapus hasil kuis */
            $stmtHasil = $conn->prepare("DELETE FROM hasil_kuis WHERE nim = ?");
            if ($stmtHasil) {
                $stmtHasil->bind_param("s", $nim);
                $stmtHasil->execute();
                $stmtHasil->close();
            }

            /* hapus enroll matkul mahasiswa */
            $stmtMhsMatkul = $conn->prepare("DELETE FROM mahasiswa_matkul WHERE nim = ?");
            if ($stmtMhsMatkul) {
                $stmtMhsMatkul->bind_param("s", $nim);
                $stmtMhsMatkul->execute();
                $stmtMhsMatkul->close();
            }

            /* hapus akun mahasiswa */
            $stmtAkun = $conn->prepare("DELETE FROM akun WHERE nim = ? AND role = 'mahasiswa'");
            if (!$stmtAkun) {
                throw new Exception("Query hapus akun mahasiswa gagal.");
            }
            $stmtAkun->bind_param("s", $nim);
            $stmtAkun->execute();

            if ($stmtAkun->affected_rows <= 0) {
                throw new Exception("Akun mahasiswa tidak ditemukan.");
            }
            $stmtAkun->close();

            $success = "Akun mahasiswa dan seluruh relasinya berhasil dihapus.";
        } else {
            $dosen_id = (int)$nim_or_id;

            /* hapus relasi dosen_matkul */
            $stmtDosenMatkul = $conn->prepare("DELETE FROM dosen_matkul WHERE dosen_id = ?");
            if ($stmtDosenMatkul) {
                $stmtDosenMatkul->bind_param("i", $dosen_id);
                $stmtDosenMatkul->execute();
                $stmtDosenMatkul->close();
            }

            /* hapus akun dosen */
            $stmtAkun = $conn->prepare("DELETE FROM akun WHERE id = ? AND role = 'dosen'");
            if (!$stmtAkun) {
                throw new Exception("Query hapus akun dosen gagal.");
            }
            $stmtAkun->bind_param("i", $dosen_id);
            $stmtAkun->execute();

            if ($stmtAkun->affected_rows <= 0) {
                throw new Exception("Akun dosen tidak ditemukan.");
            }
            $stmtAkun->close();

            $success = "Akun dosen dan relasi mengajarnya berhasil dihapus.";
        }

        $conn->commit();
    } catch (Exception $e) {
        $conn->rollback();
        $error = "Gagal menghapus akun: " . $e->getMessage();
    }
}

/* =========================
   TAMBAH DOSEN
========================= */
if (isset($_POST['tambah_dosen'])) {
    $nama  = trim($_POST['nama'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $pass  = trim($_POST['password'] ?? '');
    $wa    = trim($_POST['whatsapp'] ?? '');

    if ($nama && $email && $pass && $wa) {
        $hash = password_hash($pass, PASSWORD_DEFAULT);

        $st = $conn->prepare("
            INSERT INTO akun (nama, email, whatsapp, password, role, is_verified)
            VALUES (?, ?, ?, ?, 'dosen', 1)
        ");

        if ($st) {
            $st->bind_param("ssss", $nama, $email, $wa, $hash);
            if ($st->execute()) {
                $success = "Dosen berhasil ditambahkan!";
                $tab = 'dosen';
            } else {
                $error = "Gagal: email mungkin sudah terdaftar.";
            }
            $st->close();
        } else {
            $error = "Query tambah dosen gagal.";
        }
    } else {
        $error = "Semua field wajib diisi!";
    }
}

/* =========================
   RESET PASSWORD
========================= */
if (isset($_POST['reset_pass'])) {
    $akun_id  = trim($_POST['akun_id'] ?? '');
    $new_pass = trim($_POST['new_pass'] ?? '');

    if ($akun_id !== '' && $new_pass !== '') {

        $hash = password_hash($new_pass, PASSWORD_DEFAULT);

        // 🔥 CEK BERDASARKAN DATABASE (BUKAN ANGKA ATAU BUKAN)
        $cek = $conn->prepare("SELECT id, nim FROM akun WHERE id = ? OR nim = ? LIMIT 1");
        $cek->bind_param("ss", $akun_id, $akun_id);
        $cek->execute();
        $res = $cek->get_result();

        if ($res->num_rows > 0) {
            $user = $res->fetch_assoc();

            if (!empty($user['id']) && $user['id'] == $akun_id) {
                $st = $conn->prepare("UPDATE akun SET password = ? WHERE id = ?");
                $st->bind_param("si", $hash, $akun_id);
            } else {
                $st = $conn->prepare("UPDATE akun SET password = ? WHERE nim = ?");
                $st->bind_param("ss", $hash, $akun_id);
            }

            if ($st->execute()) {
                $success = "Password berhasil direset!";
            } else {
                $error = "Gagal reset password.";
            }

            $st->close();

        } else {
            $error = "Akun tidak ditemukan.";
        }

        $cek->close();

    } else {
        $error = "Password baru wajib diisi.";
    }
}

/* =========================
   ATUR DOSEN MENGAJAR MATKUL
========================= */
if (isset($_POST['simpan_ajar'])) {
    $dosen_id = (int)($_POST['dosen_id'] ?? 0);
    $matkul_ids = $_POST['matkul_ids'] ?? [];

    if ($dosen_id <= 0) {
        $error = "Dosen tidak valid.";
    } else {
        $conn->begin_transaction();

        try {
            /* validasi dosen */
            $stmtCekDosen = $conn->prepare("SELECT id FROM akun WHERE id = ? AND role = 'dosen' LIMIT 1");
            if (!$stmtCekDosen) {
                throw new Exception("Query validasi dosen gagal.");
            }
            $stmtCekDosen->bind_param("i", $dosen_id);
            $stmtCekDosen->execute();
            $resCekDosen = $stmtCekDosen->get_result();
            if ($resCekDosen->num_rows <= 0) {
                throw new Exception("Dosen tidak ditemukan.");
            }
            $stmtCekDosen->close();

            /* hapus relasi lama */
            $stmtHapusRelasi = $conn->prepare("DELETE FROM dosen_matkul WHERE dosen_id = ?");
            if (!$stmtHapusRelasi) {
                throw new Exception("Gagal menghapus relasi lama.");
            }
            $stmtHapusRelasi->bind_param("i", $dosen_id);
            $stmtHapusRelasi->execute();
            $stmtHapusRelasi->close();

            /* insert relasi baru */
            if (!empty($matkul_ids) && is_array($matkul_ids)) {
                $stmtInsertRelasi = $conn->prepare("
                    INSERT INTO dosen_matkul (dosen_id, matkul_id)
                    VALUES (?, ?)
                ");

                if (!$stmtInsertRelasi) {
                    throw new Exception("Gagal menambahkan relasi dosen_matkul.");
                }

                foreach ($matkul_ids as $matkul_id) {
                    $matkul_id = (int)$matkul_id;
                    if ($matkul_id > 0) {
                        $stmtInsertRelasi->bind_param("ii", $dosen_id, $matkul_id);
                        $stmtInsertRelasi->execute();
                    }
                }

                $stmtInsertRelasi->close();
            }

            $conn->commit();
            $success = "Relasi dosen mengajar berhasil diperbarui.";
            $tab = 'dosen';
        } catch (Exception $e) {
            $conn->rollback();
            $error = "Gagal menyimpan relasi dosen_matkul: " . $e->getMessage();
        }
    }
}

/* =========================
   DATA
========================= */
$search = trim($_GET['q'] ?? '');
$dosen_list = [];
$mhs_list = [];
$matkul_list = [];

/* daftar semua matkul */
$stmtMatkul = $conn->prepare("
    SELECT id, nama_matkul, angkatan, kelas
    FROM mata_kuliah
    ORDER BY nama_matkul ASC, angkatan ASC, kelas ASC
");
if ($stmtMatkul) {
    $stmtMatkul->execute();
    $matkul_list = $stmtMatkul->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmtMatkul->close();
}

/* daftar dosen + jumlah matkul yang diampu */
$sqlD = "
    SELECT 
        a.id,
        a.nama,
        a.email,
        a.created_at,
        COUNT(dm.id) AS total_matkul
    FROM akun a
    LEFT JOIN dosen_matkul dm ON a.id = dm.dosen_id
    WHERE a.role = 'dosen'
";
if ($search) {
    $sqlD .= " AND (a.nama LIKE ? OR a.email LIKE ?)";
}
$sqlD .= "
    GROUP BY a.id, a.nama, a.email, a.created_at
    ORDER BY a.created_at DESC
";

$stD = $conn->prepare($sqlD);
if ($stD) {
    if ($search) {
        $like = "%$search%";
        $stD->bind_param("ss", $like, $like);
    }
    $stD->execute();
    $dosen_list = $stD->get_result()->fetch_all(MYSQLI_ASSOC);
    $stD->close();
}

/* relasi dosen_matkul untuk modal */
$dosen_matkul_map = [];
$stmtMap = $conn->prepare("
    SELECT dosen_id, matkul_id
    FROM dosen_matkul
");
if ($stmtMap) {
    $stmtMap->execute();
    $resMap = $stmtMap->get_result();
    while ($row = $resMap->fetch_assoc()) {
        $dosen_id = (int)$row['dosen_id'];
        if (!isset($dosen_matkul_map[$dosen_id])) {
            $dosen_matkul_map[$dosen_id] = [];
        }
        $dosen_matkul_map[$dosen_id][] = (int)$row['matkul_id'];
    }
    $stmtMap->close();
}

/* daftar mahasiswa */
$sqlM = "
    SELECT nim, nama, email, is_verified, created_at
    FROM akun
    WHERE role = 'mahasiswa'
";
if ($search) {
    $sqlM .= " AND (nama LIKE ? OR email LIKE ? OR nim LIKE ?)";
}
$sqlM .= " ORDER BY created_at DESC";

$stM = $conn->prepare($sqlM);
if ($stM) {
    if ($search) {
        $like = "%$search%";
        $stM->bind_param("sss", $like, $like, $like);
    }
    $stM->execute();
    $mhs_list = $stM->get_result()->fetch_all(MYSQLI_ASSOC);
    $stM->close();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Kelola Akun | COMPULAB Admin</title>
<link rel="stylesheet" href="../css/admin.css">
<style>
.tab-nav::-webkit-scrollbar { display: none; }

.matkul-badge-list{
  display:flex;
  flex-wrap:wrap;
  gap:6px;
}
.matkul-badge{
  display:inline-flex;
  align-items:center;
  gap:4px;
  padding:6px 10px;
  border-radius:999px;
  font-size:11px;
  font-weight:600;
  background:rgba(220,38,38,0.08);
  color:var(--crimson-light);
  border:1px solid rgba(220,38,38,0.18);
}
.checkbox-grid{
  display:grid;
  grid-template-columns:repeat(auto-fit,minmax(220px,1fr));
  gap:10px;
  margin-top:12px;
}
.checkbox-card{
  display:flex;
  align-items:flex-start;
  gap:10px;
  padding:12px;
  border:1px solid var(--border);
  border-radius:14px;
  background:var(--card);
}
.checkbox-card input{
  margin-top:2px;
}
.checkbox-info{
  display:flex;
  flex-direction:column;
  gap:3px;
}
.checkbox-info strong{
  font-size:13px;
  color:var(--text-1);
}
.checkbox-info span{
  font-size:11px;
  color:var(--text-3);
}
@media (max-width: 768px){
  .table-wrap{
    overflow-x:auto;
  }
}

@media (max-width: 640px) {
  /* Sembunyikan tabel header */
  .tbl-dosen thead, .tbl-mhs thead { display: none; }

  /* Setiap baris jadi card */
  .tbl-dosen tbody tr, .tbl-mhs tbody tr {
    display: block;
    border: 1px solid var(--border);
    border-radius: var(--radius);
    margin-bottom: 12px;
    padding: 14px 16px;
    background: var(--bg-2);
  }

  /* Setiap cell jadi baris */
  .tbl-dosen td, .tbl-mhs td {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    padding: 6px 0;
    border: none;
    font-size: 13px;
    gap: 8px;
  }

  /* Sembunyikan No */
  .tbl-dosen td:nth-child(1),
  .tbl-mhs td:nth-child(1) { display: none; }

  /* Label di kiri */
  .tbl-dosen td::before,
  .tbl-mhs td::before {
    content: attr(data-label);
    font-size: 11px;
    font-weight: 700;
    color: var(--text-3);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    white-space: nowrap;
    flex-shrink: 0;
    padding-top: 2px;
  }

  /* Kolom aksi full width */
  .tbl-dosen td:last-child,
  .tbl-mhs td:last-child {
    display: flex;
    flex-direction: column;
    gap: 6px;
    margin-top: 8px;
    padding-top: 12px;
    border-top: 1px solid var(--border) !important;
  }
  .tbl-dosen td:last-child::before,
  .tbl-mhs td:last-child::before { display: none; }
  .tbl-dosen td:last-child .btn-sm,
  .tbl-mhs td:last-child .btn-sm { width: 100%; justify-content: center; }
}
</style>
</head>
<body>
<div class="mobile-overlay" id="mobileOverlay"></div>

<aside class="sidebar" id="sidebar">
  <div class="sidebar-logo">
    <div class="sidebar-logo-mark">
      <span class="sidebar-logo-mark-text">
        <span class="cl-c">C</span><span class="cl-l">L</span>
      </span>
    </div>
    <div class="sidebar-logo-text">COMPU<span>LAB</span></div>
  </div>

  <nav class="sidebar-nav">
    <div class="nav-label">Menu</div>
    <a href="dashboard.php" class="nav-link"><span class="nav-icon">📊</span> Overview</a>
    <a href="kelola_akun.php" class="nav-link active"><span class="nav-icon">👥</span> Kelola Akun</a>
    <a href="kelola_matkul.php" class="nav-link"><span class="nav-icon">📚</span> Mata Kuliah</a>
    <a href="verifikasi_otp.php" class="nav-link"><span class="nav-icon">🔑</span> Verifikasi OTP</a>
    <a href="kelola_konten.php" class="nav-link"><span class="nav-icon">🗂️</span> Kuis & Materi</a>
    <div class="nav-label">Sistem</div>
    <a href="kelola_admin.php" class="nav-link"><span class="nav-icon">🛡️</span> Kelola Admin</a>
  </nav>

  <div class="sidebar-bottom">
    <a href="../login/logout.php" class="nav-link logout"><span class="nav-icon">🚪</span> Logout</a>
  </div>
</aside>

<div class="main">
  <div class="topbar">
    <button class="topbar-hamburger" id="hamburgerBtn"><span></span><span></span><span></span></button>
    <h1>Kelola Akun</h1>
    <div class="topbar-right">
      <div class="topbar-badge" onclick="this.classList.toggle('show')">
        <div class="topbar-avatar">A</div>
        <span class="topbar-badge-text"><?php echo htmlspecialchars($admin_nama); ?></span>
      </div>
    </div>
  </div>

  <div class="page">
    <?php if ($success): ?>
      <div class="alert alert-success">✅ <?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
      <div class="alert alert-error">⚠️ <?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <!-- Tabs -->
    <div class="tab-nav" style="display:flex; gap:6px; margin-bottom:20px; border-bottom:1px solid var(--border); padding-bottom:0; overflow-x:auto; white-space:nowrap; -webkit-overflow-scrolling:touch; scrollbar-width:none; -ms-overflow-style:none;">
      <?php foreach (['dosen'=>'👨‍🏫 Dosen','mahasiswa'=>'🎓 Mahasiswa','tambah_dosen'=>'+ Tambah Dosen'] as $k => $label): ?>
        <a href="?tab=<?php echo $k; ?>"
           style="padding:10px 16px; font-size:13px; font-weight:600; text-decoration:none; border-bottom:2px solid <?php echo $tab===$k ? 'var(--crimson)' : 'transparent'; ?>; color:<?php echo $tab===$k ? 'var(--crimson-light)' : 'var(--text-2)'; ?>; margin-bottom:-1px; transition:color 0.2s;">
          <?php echo $label; ?>
        </a>
      <?php endforeach; ?>
    </div>

    <?php if ($tab === 'tambah_dosen'): ?>

    <div style="display:flex; justify-content:center;">
      <div class="card" style="width:100%; max-width:600px;">
        <h3 style="font-family:'Syne',sans-serif; font-size:16px; font-weight:700; margin-bottom:20px;">Tambah Akun Dosen</h3>
        <form method="POST">
          <div class="form-group">
            <label>Nama Lengkap</label>
            <input type="text" name="nama" required placeholder="Nama dosen">
          </div>

          <div class="form-group">
            <label>Email</label>
            <input type="email" name="email" required placeholder="email@kampus.ac.id">
          </div>

          <div class="form-group">
            <label>No. WhatsApp</label>
            <input type="text" name="whatsapp" required placeholder="08123456789">
          </div>

          <div class="form-group">
            <label>Password</label>
            <input type="password" name="password" required placeholder="Password awal">
          </div>

          <button type="submit" name="tambah_dosen" class="btn btn-red">👨‍🏫 Tambah Dosen</button>
        </form>
      </div>
    </div>

    <?php elseif ($tab === 'dosen'): ?>

      <div class="search-bar">
        <div class="search-input-wrap">
          <input
            type="text"
            id="searchInput"
            placeholder="Cari nama atau email dosen..."
            value="<?php echo htmlspecialchars($search); ?>"
            onkeydown="if(event.key==='Enter')window.location='?tab=dosen&q='+encodeURIComponent(this.value)">
        </div>
        <button class="btn btn-ghost" onclick="window.location='?tab=dosen&q='+document.getElementById('searchInput').value">Cari</button>
        <a href="?tab=tambah_dosen" class="btn btn-red">+ Tambah Dosen</a>
      </div>

      <div class="table-wrap">
        <table class="admin-table tbl-dosen">
          <thead>
            <tr>
              <th>No</th>
              <th>Nama</th>
              <th>Email</th>
              <th>Mengajar</th>
              <th style="max-width:220px;">Relasi Matkul</th>
              <th>Terdaftar</th>
              <th>Aksi</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($dosen_list): ?>
              <?php $no = 1; foreach ($dosen_list as $row): ?>
                <?php
                  $assignedIds = $dosen_matkul_map[(int)$row['id']] ?? [];
                ?>
                <tr>
                  <td style="color:var(--text-3);"><?php echo $no++; ?></td>
                  <td data-label="Nama"><strong><?php echo htmlspecialchars($row['nama']); ?></strong></td>
                  <td data-label="Email" style="color:var(--text-2);"><?php echo htmlspecialchars($row['email']); ?></td>
                  <td data-label="Mengajar">
                    <span class="badge badge-green"><?php echo (int)$row['total_matkul']; ?> Matkul</span>
                  </td>
                  <td data-label="Relasi Matkul" style="max-width:220px;">
                    <div class="matkul-badge-list" style="max-width:220px;">
                      <?php if (!empty($assignedIds)): ?>
                        <?php foreach ($matkul_list as $mk): ?>
                          <?php if (in_array((int)$mk['id'], $assignedIds, true)): ?>
                            <span class="matkul-badge">
                              <?php echo htmlspecialchars($mk['nama_matkul']); ?>
                              • <?php echo htmlspecialchars($mk['angkatan']); ?>
                              <?php echo htmlspecialchars($mk['kelas']); ?>
                            </span>
                          <?php endif; ?>
                        <?php endforeach; ?>
                      <?php else: ?>
                        <span style="color:var(--text-3); font-size:12px;">Belum diatur</span>
                      <?php endif; ?>
                    </div>
                  </td>
                  <td data-label="Terdaftar" style="color:var(--text-3); font-size:12px;"><?php echo date('d M Y', strtotime($row['created_at'])); ?></td>
                  <td>
                    <div style="display:flex; flex-direction:column; gap:6px; min-width:120px;">
                      <button
                        class="btn btn-ghost btn-sm" style="justify-content:flex-start;"
                        onclick='openAturMatkul(<?php echo (int)$row["id"]; ?>, <?php echo json_encode($row["nama"]); ?>, <?php echo json_encode($assignedIds); ?>)'>
                        📚 Atur Matkul
                      </button>

                      <button
                        class="btn btn-ghost btn-sm"
                        onclick='openReset(<?php echo json_encode($row["id"]); ?>, <?php echo json_encode($row["nama"]); ?>)'>
                        🔑 Reset
                      </button>

                      <a
                        href="?tab=dosen&hapus=<?php echo (int)$row['id']; ?>&role_hapus=dosen"
                        class="btn btn-danger btn-sm" style="justify-content:flex-start;"
                        onclick="return confirm('Hapus akun <?php echo htmlspecialchars($row['nama']); ?>? Relasi dosen_matkul juga akan dihapus.')">
                        🗑️ Hapus
                      </a>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr>
                <td colspan="7">
                  <div class="empty-state">
                    <div class="icon">👤</div>
                    <p>Tidak ada dosen.</p>
                  </div>
                </td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

    <?php elseif ($tab === 'mahasiswa'): ?>

      <div class="search-bar">
        <div class="search-input-wrap">
          <input
            type="text"
            id="searchInput"
            placeholder="Cari nama, NIM, atau email..."
            value="<?php echo htmlspecialchars($search); ?>"
            onkeydown="if(event.key==='Enter')window.location='?tab=mahasiswa&q='+encodeURIComponent(this.value)">
        </div>
        <button class="btn btn-ghost" onclick="window.location='?tab=mahasiswa&q='+document.getElementById('searchInput').value">Cari</button>
      </div>

      <div class="table-wrap">
        <table class="admin-table tbl-mhs">
          <thead>
            <tr>
              <th>NIM</th>
              <th>Nama</th>
              <th>Email</th>
              <th>Status</th>
              <th>Terdaftar</th>
              <th>Aksi</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($mhs_list): ?>
              <?php foreach ($mhs_list as $row): ?>
                <tr>
                  <td data-label="NIM" style="font-family:'Syne',sans-serif; font-weight:600; font-size:13px;"><?php echo htmlspecialchars($row['nim']); ?></td>
                  <td data-label="Nama"><?php echo htmlspecialchars($row['nama']); ?></td>
                  <td data-label="Email" style="color:var(--text-2);"><?php echo htmlspecialchars($row['email']); ?></td>
                  <td data-label="Status">
                    <?php if ($row['is_verified']): ?>
                      <span class="badge badge-green">✓ Terverifikasi</span>
                    <?php else: ?>
                      <span class="badge badge-red">⏳ Belum Verif</span>
                    <?php endif; ?>
                  </td>
                  <td data-label="Terdaftar" style="color:var(--text-3); font-size:12px;"><?php echo date('d M Y', strtotime($row['created_at'])); ?></td>
                  <td>
                    <div style="display:flex; flex-direction:column; gap:6px;">
                      <button class="btn btn-ghost btn-sm" style="justify-content:flex-start;"
                        onclick='openReset(<?php echo json_encode($row["nim"]); ?>, <?php echo json_encode($row["nama"]); ?>)'>
                        🔑 Reset Pass
                      </button>
                      <a href="?tab=mahasiswa&hapus=<?php echo urlencode($row['nim']); ?>&role_hapus=mahasiswa"
                        class="btn btn-danger btn-sm" style="justify-content:flex-start;"
                        onclick="return confirm('Hapus akun <?php echo htmlspecialchars($row['nama']); ?>? Data enroll dan nilai mahasiswa juga akan ikut terhapus.')">
                        🗑️ Hapus
                      </a>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr>
                <td colspan="6">
                  <div class="empty-state">
                    <div class="icon">🎓</div>
                    <p>Tidak ada mahasiswa.</p>
                  </div>
                </td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

    <?php endif; ?>
  </div>
</div>

<!-- Modal Reset Password -->
<div class="modal-overlay" id="resetModal">
  <div class="modal-box">
    <div class="modal-header">
      <h3>🔑 Reset Password</h3>
      <button class="modal-close" onclick="closeReset()">✕</button>
    </div>

    <p style="font-size:13px; color:var(--text-2); margin-bottom:16px;">
      Reset password untuk: <strong id="resetNama"></strong>
    </p>

    <form method="POST">
      <input type="hidden" name="akun_id" id="resetId">
      <div class="form-group">
        <label>Password Baru</label>
        <input type="password" name="new_pass" required placeholder="Masukkan password baru">
      </div>
      <div style="display:flex; gap:10px; margin-top:8px;">
        <button type="submit" name="reset_pass" class="btn btn-red">Simpan</button>
        <button type="button" class="btn btn-ghost" onclick="closeReset()">Batal</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal Atur Matkul Dosen -->
<div class="modal-overlay" id="aturMatkulModal">
  <div class="modal-box" style="max-width:760px;">
    <div class="modal-header">
      <h3>📚 Atur Mata Kuliah Dosen</h3>
      <button class="modal-close" onclick="closeAturMatkul()">✕</button>
    </div>

    <p style="font-size:13px; color:var(--text-2); margin-bottom:16px;">
      Dosen: <strong id="aturNamaDosen"></strong>
    </p>

    <form method="POST">
      <input type="hidden" name="dosen_id" id="aturDosenId">

      <div class="checkbox-grid">
        <?php foreach ($matkul_list as $mk): ?>
          <label class="checkbox-card">
            <input
              type="checkbox"
              name="matkul_ids[]"
              value="<?php echo (int)$mk['id']; ?>"
              class="matkul-checkbox">
            <div class="checkbox-info">
              <strong><?php echo htmlspecialchars($mk['nama_matkul']); ?></strong>
              <span>Angkatan <?php echo htmlspecialchars($mk['angkatan']); ?> • Kelas <?php echo htmlspecialchars($mk['kelas']); ?></span>
            </div>
          </label>
        <?php endforeach; ?>
      </div>

      <div style="display:flex; gap:10px; margin-top:18px; flex-wrap:wrap;">
        <button type="submit" name="simpan_ajar" class="btn btn-red">💾 Simpan Relasi</button>
        <button type="button" class="btn btn-ghost" onclick="closeAturMatkul()">Batal</button>
      </div>
    </form>
  </div>
</div>

<script>
const btn = document.getElementById('hamburgerBtn');
const sidebar = document.getElementById('sidebar');
const overlay = document.getElementById('mobileOverlay');

btn.addEventListener('click', () => {
  sidebar.classList.toggle('open');
  overlay.classList.toggle('show');
});

overlay.addEventListener('click', () => {
  sidebar.classList.remove('open');
  overlay.classList.remove('show');
});

/* reset password modal */
function openReset(id, nama) {
  document.getElementById('resetId').value = id;
  document.getElementById('resetNama').textContent = nama;
  document.getElementById('resetModal').classList.add('show');
}

function closeReset() {
  document.getElementById('resetModal').classList.remove('show');
}

document.getElementById('resetModal').addEventListener('click', function(e) {
  if (e.target === document.getElementById('resetModal')) {
    closeReset();
  }
});

/* atur matkul modal */
function openAturMatkul(dosenId, namaDosen, assignedIds) {
  document.getElementById('aturDosenId').value = dosenId;
  document.getElementById('aturNamaDosen').textContent = namaDosen;

  const checkboxes = document.querySelectorAll('.matkul-checkbox');
  checkboxes.forEach(cb => {
    cb.checked = assignedIds.includes(parseInt(cb.value));
  });

  document.getElementById('aturMatkulModal').classList.add('show');
}

function closeAturMatkul() {
  document.getElementById('aturMatkulModal').classList.remove('show');
}

document.getElementById('aturMatkulModal').addEventListener('click', e => {
  if (e.target === document.getElementById('aturMatkulModal')) closeAturMatkul();
});
</script>
</body>
</html>