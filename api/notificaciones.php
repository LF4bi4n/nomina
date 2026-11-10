<?php
// ========================================================
// SISTEMA DE NÓMINAS Y GESTIÓN DE PERSONAL
// Archivo: api/notificaciones.php
// Servicio 6: Notificaciones y Comunicaciones (Email / Push / Portal)
// ========================================================

require_once __DIR__ . '/../config/database.php';

$pdo = getDBConnection();
$currentUser = requireAuth();
$method = $_SERVER['REQUEST_METHOD'];

// --------------------------------------------------------
// GET: Listar notificaciones del usuario en sesión
// --------------------------------------------------------
if ($method === 'GET') {
    $userId = $currentUser['id'];
    $empId  = $currentUser['empleado_id'];
    $rol    = $currentUser['rol'];

    // Si es ADMIN o RH, ve notificaciones dirigidas a ellos o del sistema
    if ($rol === 'ADMIN' || $rol === 'RH') {
        $stmt = $pdo->prepare("
            SELECT * FROM notificaciones 
            WHERE usuario_id = ? OR usuario_id IS NULL OR tipo_evento IN ('NUEVA_SOLICITUD', 'NominaAprobada', 'DispersionEjecutada')
            ORDER BY created_at DESC 
            LIMIT 30
        ");
        $stmt->execute([$userId]);
    } else {
        // Rol EMPLEADO o AUDITOR
        $stmt = $pdo->prepare("
            SELECT * FROM notificaciones 
            WHERE usuario_id = ? OR empleado_id = ? OR usuario_id IS NULL
            ORDER BY created_at DESC 
            LIMIT 20
        ");
        $stmt->execute([$userId, $empId]);
    }

    $notificaciones = $stmt->fetchAll();
    $unreadCount = 0;

    foreach ($notificaciones as &$n) {
        $n['id'] = (int)$n['id'];
        $n['leido'] = (bool)$n['leido'];
        if (!$n['leido']) {
            $unreadCount++;
        }
    }

    sendJsonResponse(true, 'Bandeja de notificaciones recuperada.', [
        'total'       => count($notificaciones),
        'no_leidas'   => $unreadCount,
        'notificaciones' => $notificaciones
    ]);
}

// --------------------------------------------------------
// POST: Marcar como leída o crear notificación
// --------------------------------------------------------
if ($method === 'POST') {
    $input = getJsonInput();
    $action = $input['action'] ?? 'marcar_leida';

    if ($action === 'marcar_leida') {
        $notifId = (int)($input['id'] ?? 0);
        if ($notifId > 0) {
            $stmt = $pdo->prepare("UPDATE notificaciones SET leido = 1 WHERE id = ?");
            $stmt->execute([$notifId]);
            sendJsonResponse(true, 'Notificación marcada como leída.');
        }
        sendJsonResponse(false, 'ID de notificación inválido.', null, 400);
    }

    if ($action === 'marcar_todas_leidas') {
        $userId = $currentUser['id'];
        $empId  = $currentUser['empleado_id'];

        $stmt = $pdo->prepare("
            UPDATE notificaciones 
            SET leido = 1 
            WHERE usuario_id = ? OR empleado_id = ? OR usuario_id IS NULL
        ");
        $stmt->execute([$userId, $empId]);

        sendJsonResponse(true, 'Todas las notificaciones han sido marcadas como leídas.');
    }

    if ($action === 'enviar_aviso') {
        requireRole(['ADMIN', 'RH']);

        $titulo  = trim($input['titulo'] ?? '');
        $mensaje = trim($input['mensaje'] ?? '');
        $canal   = trim($input['canal'] ?? 'PORTAL');

        if (empty($titulo) || empty($mensaje)) {
            sendJsonResponse(false, 'Título y mensaje son requeridos.', null, 400);
        }

        createNotification(null, null, 'AVISO_GENERAL', $titulo, $mensaje, $canal);
        logAuditAction('ENVIO_NOTIFICACION_MASIVA', 'notificaciones', null, "Envío de aviso general: {$titulo}");

        sendJsonResponse(true, 'Notificación general enviada con éxito.');
    }
}

sendJsonResponse(false, 'Método HTTP no soportado.', null, 405);

