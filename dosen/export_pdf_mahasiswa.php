<?php
session_start();
include "../idk/forsafety.php";

require_once('../vendor/tecnickcom/tcpdf/tcpdf.php'); // sesuaikan path

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'dosen') {
    die("Akses ditolak");
}

$matkul_id = (int)($_GET['matkul_id'] ?? 0);
$nim       = $_GET['nim'] ?? '';

if ($matkul_id <= 0 || $nim === '') {
    die("Parameter tidak valid");
}

/* =========================
   DATA MAHASISWA + MATKUL
========================= */
$stmt = $conn->prepare("
    SELECT a.nama, a.nim, a.email, mk.nama_matkul
    FROM akun a
    JOIN mahasiswa_matkul mm ON a.nim = mm.nim
    JOIN mata_kuliah mk ON mm.matkul_id = mk.id
    WHERE a.nim = ? AND mk.id = ?
");
$stmt->bind_param("si", $nim, $matkul_id);
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();

/* =========================
   DATA KUIS + NILAI
========================= */
$stmt2 = $conn->prepare("
    SELECT k.judul, hk.nilai
    FROM kuis k
    LEFT JOIN hasil_kuis hk 
        ON hk.kuis_id = k.id AND hk.nim = ?
    WHERE k.matkul_id = ?
");
$stmt2->bind_param("si", $nim, $matkul_id);
$stmt2->execute();
$result = $stmt2->get_result();

/* =========================
   BUAT PDF
========================= */
$pdf = new TCPDF();
$pdf->SetCreator('COMPULAB');
$pdf->SetAuthor('COMPULAB');
$pdf->SetTitle('Laporan Nilai Mahasiswa');

$pdf->AddPage();

/* HEADER */
$html = "
<h2>Data Nilai Mahasiswa</h2>
<p>
<b>Nama:</b> {$data['nama']} <br>
<b>NIM:</b> {$data['nim']} <br>
<b>Email:</b> {$data['email']} <br>
<b>Mata Kuliah:</b> {$data['nama_matkul']}
</p>
<br>
";

/* TABLE */
$html .= "
<table border='1' cellpadding='6'>
<thead>
<tr style='background-color:#f2f2f2;'>
<th width='10%'><b>No</b></th>
<th width='60%'><b>Judul Kuis</b></th>
<th width='30%'><b>Nilai</b></th>
</tr>
</thead>
<tbody>
";

$no = 1;
$total = 0;
$count = 0;

while ($row = $result->fetch_assoc()) {
    $nilai = $row['nilai'] !== null ? $row['nilai'] : 0;

    if ($row['nilai'] !== null) {
        $total += $row['nilai'];
        $count++;
    }

    $html .= "
    <tr>
        <td>{$no}</td>
        <td>{$row['judul']}</td>
        <td align='center'>{$nilai}</td>
    </tr>
    ";

    $no++;
}

$rata = $no > 1 ? round($total / ($no - 1), 2) : 0;

/* FOOTER TABLE */
$html .= "
</tbody>
</table>
<br>
<h3>Total Nilai: {$total}</h3>
<h3>Nilai Akhir: {$rata}</h3>
";

$pdf->writeHTML($html, true, false, true, false, '');

$pdf->Output('nilai_mahasiswa.pdf', 'I');