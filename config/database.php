<?php
// ========================================================
// SISTEMA DE NÓMINAS Y GESTIÓN DE PERSONAL
// Archivo: config/database.php
// Configuración de Conexión PDO a MySQL (Charset utf8mb4)
// ========================================================

// Prevenir salida de errores PHP en texto plano si se llama como API
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Constantes de configuración de Base de Datos
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'sistema_nominas');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

/**
 * Obtiene la instancia única de conexión PDO
 * @return PDO
 */
function getDBConnection(): PDO {
    static $pdo = null;

    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET . " COLLATE utf8mb4_unicode_ci"
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            sendJsonResponse(false, 'Error de conexión a la base de datos: ' . $e->getMessage(), null, 500);
        }
    }

    return $pdo;
}

/**
 * Inicia la sesión PHP de manera segura si aún no está activa
 */
function startSessionIfNeeded(): void {
    if (session_status() === PHP_SESSION_NONE) {
        // Configurar opciones de sesión seguras
        ini_set('session.cookie_httponly', 1);
        ini_set('session.use_only_cookies', 1);
        session_start();
    }
}

/**
 * Obtiene el cuerpo de la petición procesado desde JSON o $_POST
 * @return array
 */
function getJsonInput(): array {
    $raw = file_get_contents('php://input');
    if (!empty($raw)) {
        $decoded = json_decode($raw, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }
    }
    // Si no es JSON puro en el body, retornar $_POST o combinar
    return !empty($_POST) ? $_POST : [];
}

/**
 * Envía una respuesta HTTP estructurada en formato JSON
 * @param bool $success
 * @param string $message
 * @param mixed $data
 * @param int $statusCode
 */
function sendJsonResponse(bool $success, string $message = '', $data = null, int $statusCode = 200): void {
    // Limpiar buffers previos para garantizar JSON limpio
    if (ob_get_level()) {
        ob_clean();
    }

    if (!headers_sent()) {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
    }

    $response = [
        'success'   => $success,
        'message'   => $message,
        'timestamp' => date('c')
    ];

    if ($data !== null) {
        $response['data'] = $data;
    }

    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit();
}

/**
 * Verifica si hay una sesión activa, de lo contrario responde 401
 * @return array Datos del usuario en sesión
 */
function requireAuth(): array {
    startSessionIfNeeded();
    if (empty($_SESSION['usuario_id'])) {
        sendJsonResponse(false, 'No autenticado. Por favor inicia sesión.', null, 401);
    }
    return [
        'id'          => $_SESSION['usuario_id'],
        'email'       => $_SESSION['email'] ?? '',
        'rol'         => $_SESSION['rol'] ?? 'EMPLEADO',
        'empleado_id' => $_SESSION['empleado_id'] ?? null,
        'nombre'      => $_SESSION['nombre'] ?? 'Usuario'
    ];
}

/**
 * Verifica si el usuario autenticado tiene uno de los roles permitidos
 * @param array|string $allowedRoles
 * @return array
 */
function requireRole($allowedRoles): array {
    $user = requireAuth();
    if (!is_array($allowedRoles)) {
        $allowedRoles = [$allowedRoles];
    }
    if (!in_array($user['rol'], $allowedRoles, true)) {
        sendJsonResponse(false, 'Acceso denegado: permisos insuficientes.', null, 403);
    }
    return $user;
}

/**
 * Registra una acción inmutable en el registro de auditoría (Audit Trail)
 */
function logAuditAction(string $accion, ?string $tabla = null, ?int $registroId = null, string $descripcion = '', $datosAnteriores = null, $datosNuevos = null): void {
    try {
        $pdo = getDBConnection();
        $user = !empty($_SESSION['usuario_id']) ? [
            'id'     => $_SESSION['usuario_id'],
            'nombre' => $_SESSION['nombre'] ?? 'Usuario',
            'rol'    => $_SESSION['rol'] ?? 'SISTEMA'
        ] : [
            'id'     => null,
            'nombre' => 'Sistema Automático',
            'rol'    => 'SISTEMA'
        ];

        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $antStr = $datosAnteriores !== null ? json_encode($datosAnteriores, JSON_UNESCAPED_UNICODE) : null;
        $nueStr = $datosNuevos !== null ? json_encode($datosNuevos, JSON_UNESCAPED_UNICODE) : null;

        $stmt = $pdo->prepare("
            INSERT INTO auditoria_logs (usuario_id, usuario_nombre, rol, accion, tabla_afectada, registro_id, descripcion, datos_anteriores, datos_nuevos, ip)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $user['id'],
            $user['nombre'],
            $user['rol'],
            $accion,
            $tabla,
            $registroId,
            $descripcion,
            $antStr,
            $nueStr,
            $ip
        ]);
    } catch (Exception $e) {
        // En caso de error de logging, no romper el flujo principal pero registrar en syslog
        error_log("Error al registrar auditoria: " . $e->getMessage());
    }
}

/**
 * Crea una notificación para un usuario o empleado
 */
function createNotification(?int $usuarioId, ?int $empleadoId, string $tipoEvento, string $titulo, string $mensaje, string $canal = 'PORTAL'): void {
    try {
        $pdo = getDBConnection();
        
        // Si no se pasó usuarioId pero sí empleadoId, buscar el usuario vinculado
        if (!$usuarioId && $empleadoId) {
            $stmtUser = $pdo->prepare("SELECT id FROM usuarios WHERE empleado_id = ? LIMIT 1");
            $stmtUser->execute([$empleadoId]);
            $u = $stmtUser->fetch();
            if ($u) {
                $usuarioId = (int)$u['id'];
            }
        }

        $stmt = $pdo->prepare("
            INSERT INTO notificaciones (usuario_id, empleado_id, tipo_evento, titulo, mensaje, canal, leido)
            VALUES (?, ?, ?, ?, ?, ?, 0)
        ");
        $stmt->execute([
            $usuarioId,
            $empleadoId,
            $tipoEvento,
            $titulo,
            $mensaje,
            $canal
        ]);
    } catch (Exception $e) {
        error_log("Error al crear notificacion: " . $e->getMessage());
    }
}

/**
 * Calcula los días de vacaciones por ley federal del trabajo según antigüedad
 */
function calcularDiasVacacionesPorLey(string $fechaIngreso): int {
    $fechaIng = new DateTime($fechaIngreso);
    $hoy = new DateTime();
    $antiguedadAnios = $fechaIng->diff($hoy)->y;

    if ($antiguedadAnios < 1) return 12; // Primer año
    if ($antiguedadAnios === 1) return 14;
    if ($antiguedadAnios === 2) return 16;
    if ($antiguedadAnios === 3) return 18;
    if ($antiguedadAnios === 4) return 20;
    if ($antiguedadAnios <= 9) return 22;
    if ($antiguedadAnios <= 14) return 24;
    if ($antiguedadAnios <= 19) return 26;
    return 28;
}
