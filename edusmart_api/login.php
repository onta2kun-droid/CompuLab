<?php

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

include "config.php";

$email = $_POST['email'];
$password = $_POST['password'];

$query = mysqli_query($conn,"SELECT * FROM akun WHERE email='$email' AND password='$password'");

if(mysqli_num_rows($query) > 0){

    $user = mysqli_fetch_assoc($query);

    $token = bin2hex(random_bytes(32));

    mysqli_query($conn,"UPDATE akun SET api_token='$token' WHERE id='".$user['id']."'");

    echo json_encode([
        "success" => true,
        "message" => "Login berhasil",
        "token" => $token,
        "user" => [
            "id" => $user['id'],
            "nama" => $user['nama'],
            "email" => $user['email'],
            "role" => $user['role']
        ]
    ]);

}else{

    echo json_encode([
        "success" => false,
        "message" => "Email atau password salah"
    ]);

}

?>