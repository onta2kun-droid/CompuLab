<?php

require_once __DIR__ . '/../../sambung/edusmart.php';

$token = $_GET['token'];

$query = mysqli_query($conn,"SELECT id, nis, nama, email, nomor_wa, role, tanggal_pembuatan 
FROM akun WHERE api_token='$token'");

if(mysqli_num_rows($query) > 0){

    $user = mysqli_fetch_assoc($query);

    echo json_encode([
        "success" => true,
        "data" => $user
    ]);

}else{

    echo json_encode([
        "success" => false,
        "message" => "Token tidak valid"
    ]);

}

?>