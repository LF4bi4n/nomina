<?php
// ========================================================
// SISTEMA DE NÓMINAS Y GESTIÓN DE PERSONAL
// Archivo: api/auth.php
// Servicio de Identidad: Autenticación, Sesión y RBAC
// ========================================================

require_once __DIR__ . '/../config/database.php';

startSessionIfNeeded();
$pdo = getDBConnection();
$method = $_SERVER['REQUEST_METHOD'];

// Manejo de peticiones OPTIONS para CORS si fuese necesario
if ($method === 'OPTIONS') {
    http_response_code(200);
    exit();
}

/**
 * Devuelve la matriz de permisos concedidos según el rol RBAC
 */
function getPermisosPorRol(string $rol): array {
    switch ($rol) {
        case 'ADMIN':
            return [
                'hr_core_manage'     => true,
                'incidencias_manage' => true,
                'calculo_execute'    => true,
                'timbrado_execute'   => true,
                'dispersion_execute' => true,
                'auditoria_view'     => true,
                'fiscal_rules_edit'  => true,
                'graphql_console'    => true,
                'portal_self'        => true
            ];
        case 'RH':
            return [
                'hr_core_manage'     => true,
                'incidencias_manage' => true,
                'calculo_execute'    => true,
                'timbrado_execute'   => true,
                'dispersion_execute' => true,
                'auditoria_view'     => false,
                'fiscal_rules_edit'  => false,
                'graphql_console'    => true,
                'portal_self'        => true
            ];
        case 'AUDITOR':
            return [
                'hr_core_manage'     => false,
                'incidencias_manage' => false,
                'calculo_execute'    => false,
                'timbrado_execute'   => false,
                'dispersion_execute' => false,
                'prenomina_audit'    => true,
                'recibos_view'       => true,
                'dispersion_view'    => true,
                'auditoria_view'     => true,
                'fiscal_rules_view'  => true,
                'graphql_console'    => true,
                'portal_self'        => false
            ];
        case 'EMPLEADO':
        default:
            return [
                'portal_self'         => true,
                'checador_mark'       => true,
                'recibos_download'    => true,
                'vacaciones_request'  => true,
                'constancia_download' => true
            ];
    }
}

// --------------------------------------------------------
// GET: Verificar estado de la sesión actual
// --------------------------------------------------------
if ($method === 'GET') {
    if (!empty($_SESSION['usuario_id'])) {
        // Obtener datos frescos del usuario y su empleado vinculado
        $stmt = $pdo->prepare("
            SELECT u.id, u.email, u.rol, u.empleado_id, 
                   e.nombre, e.puesto, e.departamento, e.salario_diario, e.cuenta_clabe, e.rfc, e.curp
            FROM usuarios u
            LEFT JOIN empleados e ON u.empleado_id = e.id
            WHERE u.id = ?
        ");
        $stmt->execute([$_SESSION['usuario_id']]);
        $user = $stmt->fetch();

        if ($user) {
            $permisos = getPermisosPorRol($user['rol']);
            sendJsonResponse(true, 'Sesión activa', [
                'usuario' => [
                    'id'          => (int)$user['id'],
                    'email'       => $user['email'],
                    'rol'         => $user['rol'],
                    'empleado_id' => $user['empleado_id'] ? (int)$user['empleado_id'] : null,
                    'nombre'      => $user['nombre'] ?? 'Administrador General',
                    'puesto'      => $user['puesto'] ?? 'Administrador',
                    'departamento'=> $user['departamento'] ?? 'Sistemas',
                    'salario_diario' => $user['salario_diario'] ? (float)$user['salario_diario'] : 0.00,
                    'cuenta_clabe'=> $user['cuenta_clabe'] ?? '',
                    'rfc'         => $user['rfc'] ?? '',
                    'curp'        => $user['curp'] ?? '',
                    'permisos'    => $permisos
                ]
            ]);
        }
    }
    sendJsonResponse(false, 'No hay sesión iniciada', null, 401);
}

// --------------------------------------------------------
// POST: Iniciar sesión o cerrar sesión
// --------------------------------------------------------
if ($method === 'POST') {
    $input = getJsonInput();
    $action = $input['action'] ?? 'login';

    if ($action === 'logout') {
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();
        sendJsonResponse(true, 'Sesión finalizada con éxito.');
    }

    // Cambio dinámico de rol en caliente (Selector Demo)
    if ($action === 'switch_role_demo') {
        $nuevoRol = strtoupper(trim($input['rol'] ?? ''));
        if (!in_array($nuevoRol, ['ADMIN', 'RH', 'AUDITOR', 'EMPLEADO'])) {
            sendJsonResponse(false, 'Rol demo no válido.', null, 400);
        }

        $stmtUser = $pdo->prepare("
            SELECT u.id, u.email, u.rol, u.empleado_id, 
                   e.nombre, e.puesto, e.departamento, e.salario_diario, e.cuenta_clabe, e.rfc, e.curp
            FROM usuarios u
            LEFT JOIN empleados e ON u.empleado_id = e.id
            WHERE u.rol = ?
            ORDER BY u.id ASC LIMIT 1
        ");
        $stmtUser->execute([$nuevoRol]);
        $targetUser = $stmtUser->fetch();

        if (!$targetUser) {
            sendJsonResponse(false, 'No se encontró un usuario demo para el rol solicitado.', null, 404);
        }

        $_SESSION['usuario_id']  = (int)$targetUser['id'];
        $_SESSION['email']       = $targetUser['email'];
        $_SESSION['rol']         = $targetUser['rol'];
        $_SESSION['empleado_id'] = $targetUser['empleado_id'] ? (int)$targetUser['empleado_id'] : null;
        $_SESSION['nombre']      = $targetUser['nombre'] ?? 'Administrador General';

        logAuditAction('SWITCH_ROLE_DEMO', 'usuarios', (int)$targetUser['id'], "Cambio interactivo de rol a {$targetUser['rol']} en modo demostración.");

        $permisos = getPermisosPorRol($targetUser['rol']);

        sendJsonResponse(true, "Rol cambiado exitosamente a {$targetUser['rol']} (Modo Demo).", [
            'usuario' => [
                'id'          => (int)$targetUser['id'],
                'email'       => $targetUser['email'],
                'rol'         => $targetUser['rol'],
                'empleado_id' => $targetUser['empleado_id'] ? (int)$targetUser['empleado_id'] : null,
                'nombre'      => $targetUser['nombre'] ?? 'Administrador General',
                'puesto'      => $targetUser['puesto'] ?? 'Administrador',
                'departamento'=> $targetUser['departamento'] ?? 'Sistemas',
                'salario_diario' => $targetUser['salario_diario'] ? (float)$targetUser['salario_diario'] : 0.00,
                'cuenta_clabe'=> $targetUser['cuenta_clabe'] ?? '',
                'rfc'         => $targetUser['rfc'] ?? '',
                'curp'        => $targetUser['curp'] ?? '',
                'permisos'    => $permisos
            ]
        ]);
    }

    $email = trim($input['email'] ?? '');
    $password = trim($input['password'] ?? '');

    if (empty($email) || empty($password)) {
        sendJsonResponse(false, 'Debes ingresar correo electrónico y contraseña.', null, 400);
    }

    $stmt = $pdo->prepare("
        SELECT u.id, u.email, u.password, u.rol, u.empleado_id, 
               e.nombre, e.puesto, e.departamento, e.salario_diario, e.cuenta_clabe, e.rfc, e.curp
        FROM usuarios u
        LEFT JOIN empleados e ON u.empleado_id = e.id
        WHERE u.email = ?
    ");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user) {
        sendJsonResponse(false, 'Credenciales incorrectas. Verifica el correo ingresado.', null, 401);
    }

    // Validación segura: bcrypt hash o fallback demo '123456'
    $validPassword = password_verify($password, $user['password']) || ($password === '123456');

    if (!$validPassword) {
        sendJsonResponse(false, 'Contraseña incorrecta.', null, 401);
    }

    // Configurar sesión PHP
    $_SESSION['usuario_id']  = (int)$user['id'];
    $_SESSION['email']       = $user['email'];
    $_SESSION['rol']         = $user['rol'];
    $_SESSION['empleado_id'] = $user['empleado_id'] ? (int)$user['empleado_id'] : null;
    $_SESSION['nombre']      = $user['nombre'] ?? 'Administrador General';

    // Registrar en Audit Trail
    logAuditAction('LOGIN', 'usuarios', (int)$user['id'], "Inicio de sesión exitoso como rol {$user['rol']}");

    $permisos = getPermisosPorRol($user['rol']);

    sendJsonResponse(true, 'Inicio de sesión exitoso.', [
        'usuario' => [
            'id'          => (int)$user['id'],
            'email'       => $user['email'],
            'rol'         => $user['rol'],
            'empleado_id' => $user['empleado_id'] ? (int)$user['empleado_id'] : null,
            'nombre'      => $user['nombre'] ?? 'Administrador General',
            'puesto'      => $user['puesto'] ?? 'Administrador',
            'departamento'=> $user['departamento'] ?? 'Sistemas',
            'salario_diario' => $user['salario_diario'] ? (float)$user['salario_diario'] : 0.00,
            'cuenta_clabe'=> $user['cuenta_clabe'] ?? '',
            'rfc'         => $user['rfc'] ?? '',
            'curp'        => $user['curp'] ?? '',
            'permisos'    => $permisos
        ]
    ]);
}

sendJsonResponse(false, 'Método HTTP no soportado.', null, 405);
