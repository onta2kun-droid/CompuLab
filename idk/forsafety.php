<?php
$host = "127.0.0.1";
$user = "itsonta";      // ganti jika ada password
$pass = "bagus1";
$db   = "compulab";

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}
?>