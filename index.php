<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

// Conexión a la base de datos
$host = 'trolley.proxy.rlwy.net';
$port = 34484;
$db = 'swift_users_app';
$user = 'root';
$pass = 'aaszmdaiQdpHOzaDkbIpJPvouPYJPkGI';

$conn = new mysqli($host, $user, $pass, $db, $port);

if ($conn->connect_error) {
    die(json_encode(["status" => "error", "message" => "Conexión fallida: " . $conn->connect_error]));
}

// Obtenemos solo la ruta sin query params
$request = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Si termina en /users o /index.php/users
if (preg_match('/\/(index\.php\/)?users$/', $request)) {
    $query = "SELECT * FROM users";
    $result = $conn->query($query);

    $users = [];
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }

    echo json_encode($users);
    exit;
}

// Puedes añadir más rutas aquí con más `if`

// Ruta no encontrada
http_response_code(404);
echo json_encode([
    "status" => "error",
    "message" => "Ruta no encontrada: $request"
]);
?>
