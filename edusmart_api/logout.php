<?php

require_once __DIR__ . '/../../sambung/edusmart.php';

$token = $_POST['token'];

$query = mysqli_query($conn,"UPDATE akun SET api_token=NULL WHERE api_token='$token'");

if($query){

    echo json_encode([
        "success" => true,
        "message" => "Logout berhasil"
    ]);

}else{

    echo json_encode([
        "success" => false,
        "message" => "Logout gagal"
    ]);

}

?>