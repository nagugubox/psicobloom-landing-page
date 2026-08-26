<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: GET, POST");

$jsonPath = __DIR__ . '/servicios.json';
$configPath = __DIR__ . '/config.php';
$exampleConfigPath = __DIR__ . '/config.example.php';

function getAdminCredentials() {
    global $configPath, $exampleConfigPath;
    if (file_exists($configPath)) {
        return require $configPath;
    }
    if (file_exists($exampleConfigPath)) {
        return require $exampleConfigPath;
    }
    return ["username" => "laura", "password" => "laura2026"];
}

$action = $_GET['action'] ?? '';

// GET REQUESTS
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if ($action === 'services') {
        if (!file_exists($jsonPath)) {
            http_response_code(404);
            echo json_encode(["error" => "Archivo servicios.json no encontrado."]);
            exit;
        }
        $data = file_get_contents($jsonPath);
        $services = json_decode($data, true);
        if (isset($services['admin_credentials'])) {
            unset($services['admin_credentials']);
        }
        echo json_encode($services);
        exit;
    }
}

// POST REQUESTS
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    if ($action === 'login') {
        $username = $input['username'] ?? '';
        $password = $input['password'] ?? '';
        $creds = getAdminCredentials();

        if ($username === $creds['username'] && $password === $creds['password']) {
            echo json_encode(["success" => true, "message" => "Acceso concedido."]);
        } else {
            http_response_code(401);
            echo json_encode(["error" => "Usuario o contraseña incorrectos."]);
        }
        exit;
    }

    if ($action === 'change-credentials') {
        $username = $input['username'] ?? 'laura';
        $password = $input['password'] ?? 'laura2026';

        $configContent = "<?php\nreturn [\n    'username' => " . var_export($username, true) . ",\n    'password' => " . var_export($password, true) . "\n];\n";

        if (file_put_contents($configPath, $configContent) === false) {
            http_response_code(500);
            echo json_encode(["error" => "No se pudieron guardar las nuevas credenciales."]);
            exit;
        }
        
        echo json_encode(["success" => true, "message" => "Usuario y contraseña actualizados correctamente."]);
        exit;
    }

    if ($action === 'save') {
        if (!$input || empty($input)) {
            http_response_code(400);
            echo json_encode(["error" => "El cuerpo de la solicitud no puede estar vacío."]);
            exit;
        }

        if (!file_exists($jsonPath)) {
            http_response_code(500);
            echo json_encode(["error" => "Archivo de servicios no encontrado en el servidor."]);
            exit;
        }

        if (isset($input['admin_credentials'])) {
            unset($input['admin_credentials']);
        }

        if (file_put_contents($jsonPath, json_encode($input, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) === false) {
            http_response_code(500);
            echo json_encode(["error" => "No se pudieron guardar los servicios en el archivo."]);
            exit;
        }

        echo json_encode(["success" => true, "message" => "Servicios guardados exitosamente."]);
        exit;
    }
}
