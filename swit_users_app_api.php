<?php
// api.php para la aplicación ConnectMe
// Este archivo sirve como API RESTful para la gestión de usuarios

// Configuración de la base de datos
$host = getenv('DB_HOST');
$db = "swift_users_app";
$user = getenv('DB_USER');
$password = getenv('DB_PASSWORD');
$port = getenv('DB_PORT');          

// Cabeceras para permitir solicitudes desde la app
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

// Función para registrar errores
function logError($message, $data = null) {
    $logFile = 'api_errors.log';
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $message\n";
    
    if ($data !== null) {
        $logMessage .= "Data: " . json_encode($data) . "\n";
    }
    
    file_put_contents($logFile, $logMessage, FILE_APPEND);
}

// Conectar a la base de datos
try {
    $conn = new PDO("mysql:host=$host;port=$port;dbname=$db", $user, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    logError("Error de conexión: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Error de conexión: ' . $e->getMessage()]);
    exit;
}

// Obtener el método de solicitud
$method = $_SERVER['REQUEST_METHOD'];

// Detectar endpoint - permitiendo tanto URLs con parámetros como URLs con ruta
// Esto permite que funcione con ambos formatos:
// - api.php?endpoint=register
// - api.php/register
$endpoint = '';

// Verificar si hay un parámetro GET 'endpoint'
if (isset($_GET['endpoint'])) {
    $endpoint = $_GET['endpoint'];
} else {
    // Obtener el endpoint de la ruta URL
    $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $uri = explode('/', $uri);
    $endpoint = end($uri);
    
    // Si el endpoint es 'api.php', lo cambiamos a vacío
    if ($endpoint == 'api.php') {
        // Verificar si hay un segmento después de api.php
        $segments = explode('/', $_SERVER['REQUEST_URI']);
        $apiIndex = array_search('api.php', $segments);
        if ($apiIndex !== false && isset($segments[$apiIndex + 1])) {
            $endpoint = $segments[$apiIndex + 1];
        } else {
            $endpoint = '';
        }
    }
}

// Log de la solicitud
$requestData = file_get_contents('php://input');
logError("Solicitud recibida - Método: $method, Endpoint: $endpoint", json_decode($requestData, true));

// Rutas de la API
switch($method) {
    case 'GET':
        // Obtener todos los usuarios
        if ($endpoint == 'users' || $endpoint == '') {
            try {
                $stmt = $conn->prepare("SELECT id, username, fullname, email, profile_image, created_at FROM users");
                $stmt->execute();
                $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(['status' => 'success', 'data' => $users]);
            } catch(PDOException $e) {
                logError("Error al obtener usuarios: " . $e->getMessage());
                echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
            }
        }
        // Obtener un usuario por ID
        else if (preg_match('/^user-(\d+)$/', $endpoint, $matches)) {
            try {
                $userId = $matches[1];
                $stmt = $conn->prepare("SELECT id, username, fullname, email, profile_image, created_at FROM users WHERE id = :id");
                $stmt->bindParam(':id', $userId);
                $stmt->execute();
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($user) {
                    echo json_encode(['status' => 'success', 'data' => $user]);
                } else {
                    echo json_encode(['status' => 'error', 'message' => 'Usuario no encontrado']);
                }
            } catch(PDOException $e) {
                logError("Error al obtener usuario por ID: " . $e->getMessage());
                echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
            }
        }
        else {
            echo json_encode(['status' => 'error', 'message' => 'Endpoint no válido']);
        }
        break;
    
    case 'POST':
        // Datos recibidos en formato JSON
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!$data) {
            logError("Error: JSON inválido recibido", $requestData);
            echo json_encode(['status' => 'error', 'message' => 'JSON inválido o vacío']);
            break;
        }
        
        // Registro de usuario
        if ($endpoint == 'register') {
            try {
                // Validar campos obligatorios
                if (empty($data['username']) || empty($data['password']) || 
                    empty($data['fullname']) || empty($data['email'])) {
                    
                    logError("Error: Faltan campos obligatorios para registro", $data);
                    echo json_encode(['status' => 'error', 'message' => 'Faltan campos obligatorios']);
                    break;
                }
                
                $username = $data['username'];
                $password = hash('sha256', $data['password']); // Encriptamos la contraseña
                $fullname = $data['fullname'];
                $email = $data['email'];
                $profile_image = isset($data['profile_image']) ? $data['profile_image'] : null;
                
                // Verificar si el usuario ya existe
                $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE username = :username OR email = :email");
                $stmt->bindParam(':username', $username);
                $stmt->bindParam(':email', $email);
                $stmt->execute();
                
                if ($stmt->fetchColumn() > 0) {
                    echo json_encode(['status' => 'error', 'message' => 'El nombre de usuario o email ya está en uso']);
                    break;
                }
                
                $stmt = $conn->prepare("INSERT INTO users (username, password, fullname, email, profile_image) 
                                       VALUES (:username, :password, :fullname, :email, :profile_image)");
                
                $stmt->bindParam(':username', $username);
                $stmt->bindParam(':password', $password);
                $stmt->bindParam(':fullname', $fullname);
                $stmt->bindParam(':email', $email);
                $stmt->bindParam(':profile_image', $profile_image);
                
                $stmt->execute();
                
                echo json_encode(['status' => 'success', 'message' => 'Usuario registrado correctamente']);
            } catch(PDOException $e) {
                logError("Error al registrar usuario: " . $e->getMessage(), $data);
                echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
            }
        }
        
        // Login de usuario
        else if ($endpoint == 'login') {
            try {
                // Validar campos obligatorios
                if (empty($data['username']) || empty($data['password'])) {
                    logError("Error: Faltan campos obligatorios para login", $data);
                    echo json_encode(['status' => 'error', 'message' => 'Usuario y contraseña son obligatorios']);
                    break;
                }
                
                $username = $data['username'];
                $password = hash('sha256', $data['password']); // Encriptamos para comparar
                
                $stmt = $conn->prepare("SELECT id, username, fullname, email, profile_image FROM users 
                                        WHERE username = :username AND password = :password");
                
                $stmt->bindParam(':username', $username);
                $stmt->bindParam(':password', $password);
                
                $stmt->execute();
                
                if ($stmt->rowCount() > 0) {
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);
                    echo json_encode(['status' => 'success', 'data' => $user]);
                } else {
                    echo json_encode(['status' => 'error', 'message' => 'Credenciales incorrectas']);
                }
            } catch(PDOException $e) {
                logError("Error en login: " . $e->getMessage(), $data);
                echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
            }
        }
        
        // Actualizar usuario
        else if ($endpoint == 'update-user') {
            try {
                // Validar campos obligatorios
                if (!isset($data['id']) || empty($data['fullname']) || empty($data['email'])) {
                    logError("Error: Faltan campos obligatorios para actualización", $data);
                    echo json_encode(['status' => 'error', 'message' => 'Faltan campos obligatorios']);
                    break;
                }
                
                $id = $data['id'];
                $fullname = $data['fullname'];
                $email = $data['email'];
                
                // Si hay una nueva imagen de perfil
                if (isset($data['profile_image']) && $data['profile_image'] !== null) {
                    $stmt = $conn->prepare("UPDATE users SET fullname = :fullname, email = :email, profile_image = :profile_image WHERE id = :id");
                    $stmt->bindParam(':profile_image', $data['profile_image']);
                } else {
                    $stmt = $conn->prepare("UPDATE users SET fullname = :fullname, email = :email WHERE id = :id");
                }
                
                $stmt->bindParam(':id', $id);
                $stmt->bindParam(':fullname', $fullname);
                $stmt->bindParam(':email', $email);
                
                $stmt->execute();
                
                echo json_encode(['status' => 'success', 'message' => 'Usuario actualizado correctamente']);
            } catch(PDOException $e) {
                logError("Error al actualizar usuario: " . $e->getMessage(), $data);
                echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
            }
        }
        
        // Cambiar contraseña
        else if ($endpoint == 'change-password') {
            try {
                // Validar campos obligatorios
                if (!isset($data['id']) || empty($data['current_password']) || empty($data['new_password'])) {
                    logError("Error: Faltan campos obligatorios para cambio de contraseña", $data);
                    echo json_encode(['status' => 'error', 'message' => 'Faltan campos obligatorios']);
                    break;
                }
                
                $id = $data['id'];
                $currentPassword = hash('sha256', $data['current_password']);
                $newPassword = hash('sha256', $data['new_password']);
                
                // Verificar contraseña actual
                $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE id = :id AND password = :password");
                $stmt->bindParam(':id', $id);
                $stmt->bindParam(':password', $currentPassword);
                $stmt->execute();
                
                if ($stmt->fetchColumn() == 0) {
                    echo json_encode(['status' => 'error', 'message' => 'La contraseña actual es incorrecta']);
                    break;
                }
                
                // Actualizar contraseña
                $stmt = $conn->prepare("UPDATE users SET password = :password WHERE id = :id");
                $stmt->bindParam(':id', $id);
                $stmt->bindParam(':password', $newPassword);
                $stmt->execute();
                
                echo json_encode(['status' => 'success', 'message' => 'Contraseña actualizada correctamente']);
            } catch(PDOException $e) {
                logError("Error al cambiar contraseña: " . $e->getMessage(), $data);
                echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
            }
        }
        else {
            logError("Endpoint no válido: $endpoint");
            echo json_encode(['status' => 'error', 'message' => 'Endpoint no válido']);
        }
        break;
        
    case 'DELETE':
        // Eliminar usuario
        if (preg_match('/^delete-user-(\d+)$/', $endpoint, $matches)) {
            try {
                $userId = $matches[1];
                
                $stmt = $conn->prepare("DELETE FROM users WHERE id = :id");
                $stmt->bindParam(':id', $userId);
                $stmt->execute();
                
                if ($stmt->rowCount() > 0) {
                    echo json_encode(['status' => 'success', 'message' => 'Usuario eliminado correctamente']);
                } else {
                    echo json_encode(['status' => 'error', 'message' => 'Usuario no encontrado o ya fue eliminado']);
                }
            } catch(PDOException $e) {
                logError("Error al eliminar usuario: " . $e->getMessage());
                echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Endpoint no válido']);
        }
        break;
        
    default:
        logError("Método no permitido: $method");
        echo json_encode(['status' => 'error', 'message' => 'Método no permitido']);
        break;
}
?>
