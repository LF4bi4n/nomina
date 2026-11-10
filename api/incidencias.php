<?php
// ========================================================
// SISTEMA DE NÓMINAS Y GESTIÓN DE PERSONAL
// Archivo: api/incidencias.php
// Servicio 2: Incidencias, Asistencia y Checador Digital
// ========================================================

require_once __DIR__ . '/../config/database.php';

$pdo = getDBConnection();
$currentUser = requireAuth();
$method = $_SERVER['REQUEST_METHOD'];

// --------------------------------------------------------
// GET: Obtener historial o resumen de incidencias por periodo
// --------------------------------------------------------
if ($method === 'GET') {
    $action      = isset($_GET['action']) ? trim($_GET['action']) : '';
    $empleado_id = isset($_GET['empleado_id']) ? (int)$_GET['empleado_id'] : null;
    $tipo        = isset($_GET['tipo']) ? trim($_GET['tipo']) : null;
    $estatus     = isset($_GET['estatus']) ? trim($_GET['estatus']) : null;
    $fecha_inicio = isset($_GET['fecha_inicio']) ? trim($_GET['fecha_inicio']) : null;
    $fecha_fin   = isset($_GET['fecha_fin']) ? trim($_GET['fecha_fin']) : null;

    // Si el usuario autenticado es rol EMPLEADO, solo puede ver sus propias incidencias
    if ($currentUser['rol'] === 'EMPLEADO') {
        $empleado_id = $currentUser['empleado_id'];
        if (!$empleado_id) {
            sendJsonResponse(false, 'No tienes un expediente de empleado asignado.', null, 404);
        }
    }

    // Sub-acción: Resumen de incidencias por periodo (Salida oficial del Servicio 2 en el documento)
    if ($action === 'resumen_periodo') {
        if (!$fecha_inicio || !$fecha_fin) {
            // Si no se pasaron fechas, tomar las de la última nómina activa
            $stmtLast = $pdo->query("SELECT fecha_inicio, fecha_fin, periodo FROM nominas ORDER BY fecha_inicio DESC LIMIT 1");
            $lastNom = $stmtLast->fetch();
            if ($lastNom) {
                $fecha_inicio = $lastNom['fecha_inicio'];
                $fecha_fin = $lastNom['fecha_fin'];
            } else {
                $fecha_inicio = date('Y-m-01');
                $fecha_fin = date('Y-m-15');
            }
        }

        $sqlResumen = "
            SELECT e.id as empleado_id, e.nombre as empleado_nombre, e.puesto, e.departamento, e.salario_diario,
                   SUM(CASE WHEN i.tipo = 'ASISTENCIA' AND i.estatus = 'APROBADO' THEN 1 ELSE 0 END) as asistencias,
                   SUM(CASE WHEN i.tipo = 'FALTA' AND i.estatus = 'APROBADO' THEN 1 ELSE 0 END) as faltas,
                   SUM(CASE WHEN i.tipo = 'RETARDO' AND i.estatus = 'APROBADO' THEN 1 ELSE 0 END) as retardos,
                   SUM(CASE WHEN i.tipo = 'HORA_EXTRA' AND i.estatus = 'APROBADO' THEN i.horas_extra ELSE 0 END) as horas_extra_total,
                   SUM(CASE WHEN i.tipo = 'VACACIONES' AND i.estatus = 'APROBADO' THEN 1 ELSE 0 END) as vacaciones_dias,
                   SUM(CASE WHEN i.tipo = 'INCAPACIDAD' AND i.estatus = 'APROBADO' THEN 1 ELSE 0 END) as incapacidades_dias,
                   SUM(CASE WHEN i.tipo = 'PERMISO' AND i.estatus = 'APROBADO' THEN 1 ELSE 0 END) as permisos_dias
            FROM empleados e
            LEFT JOIN incidencias i ON e.id = i.empleado_id AND i.fecha BETWEEN ? AND ?
            WHERE e.estatus = 'ACTIVO'
        ";
        $paramsResumen = [$fecha_inicio, $fecha_fin];

        if ($empleado_id) {
            $sqlResumen .= " AND e.id = ?";
            $paramsResumen[] = $empleado_id;
        }

        $sqlResumen .= " GROUP BY e.id ORDER BY e.id ASC";

        $stmtR = $pdo->prepare($sqlResumen);
        $stmtR->execute($paramsResumen);
        $resumen = $stmtR->fetchAll();

        foreach ($resumen as &$r) {
            $r['asistencias']        = (int)$r['asistencias'];
            $r['faltas']             = (int)$r['faltas'];
            $r['retardos']           = (int)$r['retardos'];
            $r['horas_extra_total']  = (float)$r['horas_extra_total'];
            $r['vacaciones_dias']    = (int)$r['vacaciones_dias'];
            $r['incapacidades_dias'] = (int)$r['incapacidades_dias'];
            $r['dias_laborados_efectivos'] = max(0, 15 - $r['faltas'] - $r['incapacidades_dias']);
        }

        sendJsonResponse(true, 'Resumen de incidencias por periodo recuperado.', [
            'periodo_rango' => ['inicio' => $fecha_inicio, 'fin' => $fecha_fin],
            'resumen'       => $resumen
        ]);
    }

    // Listado general de incidencias
    $sql = "
        SELECT i.*, e.nombre as empleado_nombre, e.puesto, e.departamento, e.salario_diario, e.cuenta_clabe
        FROM incidencias i
        INNER JOIN empleados e ON i.empleado_id = e.id
        WHERE 1=1
    ";
    $params = [];

    if ($empleado_id) {
        $sql .= " AND i.empleado_id = ?";
        $params[] = $empleado_id;
    }
    if ($tipo) {
        $sql .= " AND i.tipo = ?";
        $params[] = $tipo;
    }
    if ($estatus) {
        $sql .= " AND i.estatus = ?";
        $params[] = $estatus;
    }
    if ($fecha_inicio && $fecha_fin) {
        $sql .= " AND i.fecha BETWEEN ? AND ?";
        $params[] = $fecha_inicio;
        $params[] = $fecha_fin;
    }

    $sql .= " ORDER BY i.fecha DESC, i.id DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $incidencias = $stmt->fetchAll();

    foreach ($incidencias as &$inc) {
        $inc['horas_extra'] = (float)$inc['horas_extra'];
        $inc['salario_diario'] = (float)$inc['salario_diario'];
        $inc['latitud'] = $inc['latitud'] !== null ? (float)$inc['latitud'] : null;
        $inc['longitud'] = $inc['longitud'] !== null ? (float)$inc['longitud'] : null;
    }

    sendJsonResponse(true, 'Historial de incidencias recuperado.', [
        'total'       => count($incidencias),
        'incidencias' => $incidencias
    ]);
}

// --------------------------------------------------------
// POST: Registrar marca de checador, incidencia o cambiar estatus
// --------------------------------------------------------
if ($method === 'POST') {
    $input = getJsonInput();
    $action = $input['action'] ?? 'registrar';

    // Sub-acción: Aprobar o Rechazar Incidencia (Exclusivo RH / ADMIN)
    if ($action === 'cambiar_estatus') {
        requireRole(['ADMIN', 'RH']);

        $incidencia_id = (int)($input['id'] ?? 0);
        $nuevo_estatus = strtoupper(trim($input['estatus'] ?? ''));

        if (!in_array($nuevo_estatus, ['APROBADO', 'RECHAZADO', 'PENDIENTE'], true)) {
            sendJsonResponse(false, 'Estatus inválido. Use APROBADO, RECHAZADO o PENDIENTE.', null, 400);
        }

        $stmtOld = $pdo->prepare("
            SELECT i.*, e.nombre as empleado_nombre 
            FROM incidencias i 
            JOIN empleados e ON i.empleado_id = e.id 
            WHERE i.id = ?
        ");
        $stmtOld->execute([$incidencia_id]);
        $incOld = $stmtOld->fetch();

        if (!$incOld) {
            sendJsonResponse(false, 'Incidencia no encontrada.', null, 404);
        }

        $stmt = $pdo->prepare("UPDATE incidencias SET estatus = ? WHERE id = ?");
        $stmt->execute([$nuevo_estatus, $incidencia_id]);

        // Registrar en Audit Trail
        logAuditAction(
            'CAMBIO_ESTATUS_INCIDENCIA',
            'incidencias',
            $incidencia_id,
            "{$nuevo_estatus} incidencia de tipo {$incOld['tipo']} para {$incOld['empleado_nombre']}",
            ['estatus' => $incOld['estatus']],
            ['estatus' => $nuevo_estatus]
        );

        // Notificar al empleado
        $tituloNotif = ($nuevo_estatus === 'APROBADO') ? 'Solicitud Aprobada por RH' : 'Solicitud Rechazada por RH';
        $msgNotif = "Tu solicitud de {$incOld['tipo']} para la fecha {$incOld['fecha']} ha sido marcada como {$nuevo_estatus}.";
        createNotification(null, (int)$incOld['empleado_id'], 'SOLICITUD_ACTUALIZADA', $tituloNotif, $msgNotif, 'PORTAL');

        sendJsonResponse(true, "La incidencia #$incidencia_id fue actualizada a estatus: $nuevo_estatus.");
    }

    // Registro de incidencia o marcaje de checador
    $tipo             = strtoupper(trim($input['tipo'] ?? 'ASISTENCIA'));
    $modalidad        = strtoupper(trim($input['modalidad'] ?? 'PRESENCIAL'));
    $fecha            = trim($input['fecha'] ?? date('Y-m-d'));
    $horas_extra      = (float)($input['horas_extra'] ?? 0.00);
    $observaciones    = trim($input['observaciones'] ?? '');
    $latitud          = !empty($input['latitud']) ? (float)$input['latitud'] : null;
    $longitud         = !empty($input['longitud']) ? (float)$input['longitud'] : null;
    $ip_origen        = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $tipo_incapacidad = !empty($input['tipo_incapacidad']) ? trim($input['tipo_incapacidad']) : null;
    $folio_medico     = !empty($input['folio_medico']) ? strtoupper(trim($input['folio_medico'])) : null;

    if (!in_array($modalidad, ['PRESENCIAL', 'TELETRABAJO'], true)) {
        $modalidad = 'PRESENCIAL';
    }

    // Determinar empleado_id
    if ($currentUser['rol'] === 'EMPLEADO') {
        $empleado_id = $currentUser['empleado_id'];
        if (!$empleado_id) {
            sendJsonResponse(false, 'Tu usuario no tiene un expediente de empleado vinculado.', null, 403);
        }
        // Asistencia marcada por el checador entra como APROBADO de inmediato
        $estatus = ($tipo === 'ASISTENCIA') ? 'APROBADO' : 'PENDIENTE';
    } else {
        $empleado_id = (int)($input['empleado_id'] ?? 0);
        if ($empleado_id <= 0) {
            sendJsonResponse(false, 'Debes seleccionar un empleado válido.', null, 400);
        }
        $estatus = in_array(strtoupper(trim($input['estatus'] ?? 'APROBADO')), ['APROBADO', 'PENDIENTE'], true) 
                    ? strtoupper(trim($input['estatus'] ?? 'APROBADO')) 
                    : 'APROBADO';
    }

    $tiposPermitidos = ['ASISTENCIA', 'FALTA', 'RETARDO', 'HORA_EXTRA', 'VACACIONES', 'INCAPACIDAD', 'PERMISO'];
    if (!in_array($tipo, $tiposPermitidos, true)) {
        sendJsonResponse(false, 'Tipo de incidencia inválido. Permitidos: ' . implode(', ', $tiposPermitidos), null, 400);
    }

    if ($tipo === 'HORA_EXTRA' && $horas_extra <= 0) {
        sendJsonResponse(false, 'Para registro de horas extra, debes especificar una cantidad mayor a 0.', null, 400);
    }

    // Si es asistencia del checador, evitar doble marcaje el mismo día
    if ($tipo === 'ASISTENCIA') {
        $checkStmt = $pdo->prepare("SELECT id FROM incidencias WHERE empleado_id = ? AND fecha = ? AND tipo = 'ASISTENCIA'");
        $checkStmt->execute([$empleado_id, $fecha]);
        if ($checkStmt->fetch()) {
            sendJsonResponse(false, 'Ya se encuentra registrado un marcaje de asistencia para hoy (' . $fecha . ').', null, 409);
        }
    }

    $stmt = $pdo->prepare("
        INSERT INTO incidencias (
            empleado_id, fecha, tipo, modalidad, latitud, longitud, ip_origen, 
            horas_extra, tipo_incapacidad, folio_medico, estatus, observaciones
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $empleado_id,
        $fecha,
        $tipo,
        $modalidad,
        $latitud,
        $longitud,
        $ip_origen,
        $horas_extra,
        $tipo_incapacidad,
        $folio_medico,
        $estatus,
        $observaciones ?: ($tipo === 'ASISTENCIA' ? "Marcaje checador digital [{$modalidad}]" : null)
    ]);

    $nuevoId = (int)$pdo->lastInsertId();

    // Notificar a RH si fue una solicitud pendiente de empleado
    if ($estatus === 'PENDIENTE') {
        createNotification(1, $empleado_id, 'NUEVA_SOLICITUD', "Nueva Solicitud: {$tipo}", "El empleado ha enviado una solicitud de {$tipo} para la fecha {$fecha}.", 'PORTAL');
    }

    sendJsonResponse(true, 'Registro de incidencia guardado correctamente.', [
        'id'               => $nuevoId,
        'empleado_id'      => $empleado_id,
        'tipo'             => $tipo,
        'modalidad'        => $modalidad,
        'fecha'            => $fecha,
        'horas_extra'      => $horas_extra,
        'tipo_incapacidad' => $tipo_incapacidad,
        'folio_medico'     => $folio_medico,
        'estatus'          => $estatus
    ], 201);
}

sendJsonResponse(false, 'Método HTTP no soportado.', null, 405);
