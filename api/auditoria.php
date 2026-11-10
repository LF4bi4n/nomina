<?php
// ========================================================
// SISTEMA DE NÓMINAS Y GESTIÓN DE PERSONAL
// Archivo: api/auditoria.php
// Servicio: Trazabilidad (Audit Trail Inmutable) & Reglas Fiscales
// ========================================================

require_once __DIR__ . '/../config/database.php';

$pdo = getDBConnection();
$currentUser = requireRole(['ADMIN', 'AUDITOR', 'RH']);
$method = $_SERVER['REQUEST_METHOD'];

// --------------------------------------------------------
// GET: Consultar logs de auditoría o reglas fiscales
// --------------------------------------------------------
if ($method === 'GET') {
    $tab = isset($_GET['tab']) ? trim($_GET['tab']) : 'logs';

    if ($tab === 'reglas') {
        $stmt = $pdo->query("SELECT * FROM reglas_fiscales ORDER BY id ASC");
        $reglas = $stmt->fetchAll();

        foreach ($reglas as &$r) {
            $r['id']    = (int)$r['id'];
            $r['valor'] = (float)$r['valor'];
        }

        sendJsonResponse(true, 'Catálogo parametrizado de reglas fiscales recuperado.', [
            'total'  => count($reglas),
            'reglas' => $reglas
        ]);
    }

    // Tab 'logs': Registro inmutable de auditoría
    $limit  = isset($_GET['limit']) ? min(100, max(10, (int)$_GET['limit'])) : 50;
    $accion = isset($_GET['accion']) ? trim($_GET['accion']) : null;

    $sql = "SELECT * FROM auditoria_logs WHERE 1=1";
    $params = [];

    if ($accion) {
        $sql .= " AND accion LIKE ?";
        $params[] = "%{$accion}%";
    }

    $sql .= " ORDER BY id DESC LIMIT {$limit}";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $logs = $stmt->fetchAll();

    foreach ($logs as &$log) {
        $log['id'] = (int)$log['id'];
        $log['registro_id'] = $log['registro_id'] ? (int)$log['registro_id'] : null;
        $log['datos_anteriores'] = $log['datos_anteriores'] ? json_decode($log['datos_anteriores'], true) : null;
        $log['datos_nuevos'] = $log['datos_nuevos'] ? json_decode($log['datos_nuevos'], true) : null;
        // Hash de integridad para verificar inmutabilidad
        $log['hash_integridad'] = substr(hash('sha256', $log['id'] . $log['accion'] . $log['created_at']), 0, 16);
    }

    // Contadores para dashboard de auditoría
    $stmtCounts = $pdo->query("
        SELECT 
            COUNT(*) as total_eventos,
            COUNT(DISTINCT usuario_id) as usuarios_activos,
            SUM(CASE WHEN accion LIKE '%SALARIO%' OR accion LIKE '%AJUSTE%' THEN 1 ELSE 0 END) as ajustes_financieros,
            SUM(CASE WHEN accion LIKE '%TIMBRADO%' THEN 1 ELSE 0 END) as timbrados_fiscales
        FROM auditoria_logs
    ");
    $stats = $stmtCounts->fetch();

    sendJsonResponse(true, 'Logs de trazabilidad y auditoría recuperados.', [
        'stats' => $stats,
        'total' => count($logs),
        'logs'  => $logs
    ]);
}

// --------------------------------------------------------
// POST: Actualizar parámetro fiscal (Solo ADMIN)
// --------------------------------------------------------
if ($method === 'POST') {
    requireRole(['ADMIN']);

    $input = getJsonInput();
    $action = $input['action'] ?? 'actualizar_regla';

    if ($action === 'actualizar_regla') {
        $clave = trim($input['clave'] ?? '');
        $valor = (float)($input['valor'] ?? 0);
        $motivo = trim($input['motivo'] ?? 'Actualización de tabla conforme a DOF / SAT');

        if (empty($clave) || $valor < 0) {
            sendJsonResponse(false, 'Clave y valor positivo son requeridos.', null, 400);
        }

        $stmtOld = $pdo->prepare("SELECT * FROM reglas_fiscales WHERE clave = ?");
        $stmtOld->execute([$clave]);
        $oldRegla = $stmtOld->fetch();

        if (!$oldRegla) {
            sendJsonResponse(false, 'Regla fiscal no encontrada.', null, 404);
        }

        $stmtUpd = $pdo->prepare("UPDATE reglas_fiscales SET valor = ?, updated_at = NOW() WHERE clave = ?");
        $stmtUpd->execute([$valor, $clave]);

        logAuditAction(
            'ACTUALIZACION_PARAMETRO_FISCAL',
            'reglas_fiscales',
            (int)$oldRegla['id'],
            "Modificación de {$oldRegla['nombre']} ({$clave}): de {$oldRegla['valor']} a {$valor}. Motivo: {$motivo}",
            ['valor_anterior' => (float)$oldRegla['valor']],
            ['valor_nuevo' => $valor, 'motivo' => $motivo]
        );

        sendJsonResponse(true, "Regla fiscal {$oldRegla['nombre']} actualizada a {$valor}.");
    }
}

sendJsonResponse(false, 'Método HTTP no soportado.', null, 405);

