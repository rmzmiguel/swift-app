<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

$host = 'trolley.proxy.rlwy.net';
$port = 34484;
$db = 'railway';
$user = 'root';
$pass = 'AQUÍ_TU_PASSWORD_DE_RAILWAY';

$conn = new mysqli($host, $user, $pass, $db, $port);

if ($conn->connect_error) {
    die(json_encode(["status" => "error", "message" => "Conexión fallida: " . $conn->connect_error]));
}

// Aquí puedes añadir tus endpoints como /login, /register, etc.
echo json_encode(["status" => "ok", "message" => "API conectada correctamente"]);
?>