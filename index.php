<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

$host = 'trolley.proxy.rlwy.net';
$port = 34484;
$db = 'swift_users_app';
$user = 'root';
$pass = 'aaszmdaiQdpHOzaDkbIpJPvouPYJPkGI';

$conn = new mysqli($host, $user, $pass, $db, $port);

if ($conn->connect_error) {
    die(json_encode(["status" => "error", "message" => "Conexión fallida: " . $conn->connect_error]));
}

$uri = explode('?', $_SERVER['REQUEST_URI'])[0];
$method = $_SERVER['REQUEST_METHOD'];

// Endpoint: GET /users
if ($uri === '/users' && $method === 'GET') {
    $result = $conn->query("SELECT * FROM users");
    $users = [];
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
    echo json_encode($users);
    exit;
}

// Endpoint: POST /login
if ($uri === '/login' && $method === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    $stmt = $conn->prepare("SELECT * FROM users WHERE username = ? AND password = ?");
    $stmt->bind_param("ss", $username, $password);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($user = $result->fetch_assoc()) {
        echo json_encode(["status" => "success", "message" => "Login correcto", "user" => $user]);
    } else {
        echo json_encode(["status" => "error", "message" => "Usuario o contraseña incorrectos"]);
    }
    exit;
}

// Endpoint: POST /register
if ($uri === '/register' && $method === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $fullname = $_POST['fullname'] ?? '';
    $email = $_POST['email'] ?? '';

    $stmt = $conn->prepare("INSERT INTO users (username, password, fullname, email) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $username, $password, $fullname, $email);
    $success = $stmt->execute();

    if ($success) {
        echo json_encode(["status" => "success", "message" => "Usuario registrado"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Error al registrar"]);
    }
    exit;
}

// Ruta no encontrada
http_response_code(404);
echo json_encode(["status" => "error", "message" => "Ruta no encontrada"]);
?>
