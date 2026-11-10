<?php
// ========================================================
// SISTEMA DE NÓMINAS Y GESTIÓN DE PERSONAL
// Archivo: api/calculo.php
// Servicio 3: Motor de Cálculo de Nómina y Pre-nómina
// ========================================================

require_once __DIR__ . '/../config/database.php';

$pdo = getDBConnection();
$currentUser = requireAuth();
$method = $_SERVER['REQUEST_METHOD'];

/**
 * Obtiene las reglas fiscales vigentes desde la base de datos
 */
function getReglasFiscalesVigentes(PDO $pdo): array {
    $stmt = $pdo->query("SELECT clave, valor FROM reglas_fiscales");
    $reglas = [];
    while ($row = $stmt->fetch()) {
        $reglas[$row['clave']] = (float)$row['valor'];
    }
    return [
        'isr_tasa'            => $reglas['ISR_TASA_BASE'] ?? 10.0,
        'imss_obrera'         => $reglas['IMSS_CUOTA_OBRERA'] ?? 2.5,
        'imss_patronal'       => $reglas['IMSS_CUOTA_PATRONAL'] ?? 20.5,
        'infonavit_tasa'      => $reglas['INFONAVIT_PORCENTAJE'] ?? 5.0,
        'salario_minimo'      => $reglas['SALARIO_MINIMO_GENERAL'] ?? 248.93,
        'prima_riesgo'        => $reglas['PRIMA_RIESGO_TRABAJO'] ?? 0.5435
    ];
}

// --------------------------------------------------------
// GET: Obtener Pre-nómina procesada o lista de periodos
// --------------------------------------------------------
if ($method === 'GET') {
    $nomina_id = isset($_GET['nomina_id']) ? (int)$_GET['nomina_id'] : null;

    $stmtNominas = $pdo->query("SELECT * FROM nominas ORDER BY fecha_inicio DESC, id DESC");
    $listaNominas = $stmtNominas->fetchAll();

    if (!$nomina_id && !empty($listaNominas)) {
        $nomina_id = (int)$listaNominas[0]['id'];
    }

    if (!$nomina_id) {
        sendJsonResponse(true, 'No hay periodos de nómina dados de alta aún.', [
            'periodos'       => [],
            'nomina_actual'  => null,
            'detalles'       => [],
            'resumen_global' => null,
            'reglas_fiscales'=> getReglasFiscalesVigentes($pdo)
        ]);
    }

    $stmtNom = $pdo->prepare("SELECT * FROM nominas WHERE id = ?");
    $stmtNom->execute([$nomina_id]);
    $nominaActual = $stmtNom->fetch();

    if (!$nominaActual) {
        sendJsonResponse(false, 'El periodo de nómina solicitado no existe.', null, 404);
    }

    $sqlDetalle = "
        SELECT dn.*, 
               e.nombre as empleado_nombre, e.rfc, e.curp, e.puesto, e.departamento, 
               e.salario_diario, e.salario_diario_integrado, e.cuenta_clabe, e.banco
        FROM detalle_nomina dn
        INNER JOIN empleados e ON dn.empleado_id = e.id
        WHERE dn.nomina_id = ?
    ";
    $paramsDetalle = [$nomina_id];

    if ($currentUser['rol'] === 'EMPLEADO') {
        $sqlDetalle .= " AND dn.empleado_id = ?";
        $paramsDetalle[] = $currentUser['empleado_id'];
    }

    $sqlDetalle .= " ORDER BY e.id ASC";
    $stmtDetalle = $pdo->prepare($sqlDetalle);
    $stmtDetalle->execute($paramsDetalle);
    $detalles = $stmtDetalle->fetchAll();

    $totalPercepciones = 0.00;
    $totalDeducciones  = 0.00;
    $totalNeto         = 0.00;
    $totalISR          = 0.00;
    $totalIMSS         = 0.00;
    $totalInfonavit    = 0.00;
    $totalHorasExtra   = 0.00;
    $totalBonos        = 0.00;
    $totalPrestamos    = 0.00;
    $totalAjustes      = 0.00;

    foreach ($detalles as &$d) {
        $d['sueldo_base']          = (float)$d['sueldo_base'];
        $d['pago_horas_extra']     = (float)$d['pago_horas_extra'];
        $d['bonos']                = (float)($d['bonos'] ?? 0.00);
        $d['percepciones_total']   = round($d['sueldo_base'] + $d['pago_horas_extra'] + $d['bonos'], 2);

        $d['deduccion_isr']        = (float)$d['deduccion_isr'];
        $d['deduccion_imss']       = (float)$d['deduccion_imss'];
        $d['deduccion_infonavit']  = (float)($d['deduccion_infonavit'] ?? 0.00);
        $d['deduccion_prestamo']   = (float)($d['deduccion_prestamo'] ?? 0.00);
        $d['deducciones_total']    = round($d['deduccion_isr'] + $d['deduccion_imss'] + $d['deduccion_infonavit'] + $d['deduccion_prestamo'], 2);

        $d['ajuste_manual']        = (float)($d['ajuste_manual'] ?? 0.00);
        $d['sueldo_neto']          = (float)$d['sueldo_neto'];
        $d['salario_diario']       = (float)$d['salario_diario'];
        $d['salario_diario_integrado'] = (float)($d['salario_diario_integrado'] ?? round($d['salario_diario'] * 1.0493, 2));

        $totalPercepciones += $d['percepciones_total'];
        $totalDeducciones  += $d['deducciones_total'];
        $totalNeto         += $d['sueldo_neto'];
        $totalISR          += $d['deduccion_isr'];
        $totalIMSS         += $d['deduccion_imss'];
        $totalInfonavit    += $d['deduccion_infonavit'];
        $totalHorasExtra   += $d['pago_horas_extra'];
        $totalBonos        += $d['bonos'];
        $totalPrestamos    += $d['deduccion_prestamo'];
        $totalAjustes      += $d['ajuste_manual'];
    }

    $resumenGlobal = [
        'total_empleados'          => count($detalles),
        'total_percepciones'       => round($totalPercepciones, 2),
        'total_deducciones'        => round($totalDeducciones, 2),
        'total_neto'               => round($totalNeto, 2),
        'total_retencion_isr'      => round($totalISR, 2),
        'total_cuota_imss'         => round($totalIMSS, 2),
        'total_cuota_infonavit'    => round($totalInfonavit, 2),
        'total_pago_horas_extra'   => round($totalHorasExtra, 2),
        'total_bonos'              => round($totalBonos, 2),
        'total_prestamos'          => round($totalPrestamos, 2),
        'total_ajustes'            => round($totalAjustes, 2)
    ];

    sendJsonResponse(true, 'Datos de cálculo y pre-nómina obtenidos.', [
        'periodos'        => $listaNominas,
        'nomina_actual'   => $nominaActual,
        'detalles'        => $detalles,
        'resumen_global'  => $resumenGlobal,
        'reglas_fiscales' => getReglasFiscalesVigentes($pdo)
    ]);
}

// --------------------------------------------------------
// POST: Procesar motor de cálculo, crear periodo o aplicar ajustes
// --------------------------------------------------------
if ($method === 'POST') {
    requireRole(['ADMIN', 'RH']);

    $input = getJsonInput();
    $action = $input['action'] ?? 'procesar';

    // 1. Crear nuevo periodo de nómina
    if ($action === 'crear_periodo') {
        $periodo = trim($input['periodo'] ?? '');
        $fecha_inicio = trim($input['fecha_inicio'] ?? '');
        $fecha_fin = trim($input['fecha_fin'] ?? '');

        if (empty($periodo) || empty($fecha_inicio) || empty($fecha_fin)) {
            sendJsonResponse(false, 'Debe especificar el código de periodo, fecha de inicio y fecha de fin.', null, 400);
        }

        try {
            $stmt = $pdo->prepare("
                INSERT INTO nominas (periodo, fecha_inicio, fecha_fin, estatus)
                VALUES (?, ?, ?, 'BORRADOR')
            ");
            $stmt->execute([$periodo, $fecha_inicio, $fecha_fin]);
            $nuevoId = (int)$pdo->lastInsertId();

            logAuditAction('APERTURA_PERIODO', 'nominas', $nuevoId, "Apertura de nuevo periodo de nómina {$periodo} ({$fecha_inicio} al {$fecha_fin})");

            sendJsonResponse(true, "Periodo de nómina {$periodo} creado exitosamente en estado BORRADOR.", [
                'id' => $nuevoId,
                'periodo' => $periodo
            ], 201);
        } catch (Exception $e) {
            sendJsonResponse(false, 'No se pudo crear el periodo. Puede que el código ya exista.', null, 400);
        }
    }

    // 2. Ajuste manual justificado (Requerimiento de Auditoría y Trazabilidad)
    if ($action === 'ajuste_manual') {
        $detalle_id          = (int)($input['detalle_id'] ?? 0);
        $bonos               = (float)($input['bonos'] ?? 0.00);
        $deduccion_prestamo  = (float)($input['deduccion_prestamo'] ?? 0.00);
        $ajuste_manual       = (float)($input['ajuste_manual'] ?? 0.00);
        $justificacion       = trim($input['justificacion'] ?? '');

        if ($detalle_id <= 0) {
            sendJsonResponse(false, 'Debe indicar un registro de detalle válido.', null, 400);
        }
        if (empty($justificacion)) {
            sendJsonResponse(false, 'Por requisitos estrictos de auditoría fiscal, la justificación del ajuste manual es obligatoria.', null, 400);
        }

        $stmtDet = $pdo->prepare("
            SELECT dn.*, n.estatus as estatus_nomina, e.nombre as empleado_nombre 
            FROM detalle_nomina dn
            JOIN nominas n ON dn.nomina_id = n.id
            JOIN empleados e ON dn.empleado_id = e.id
            WHERE dn.id = ?
        ");
        $stmtDet->execute([$detalle_id]);
        $det = $stmtDet->fetch();

        if (!$det) {
            sendJsonResponse(false, 'Registro de detalle no encontrado.', null, 404);
        }

        if ($det['estatus_nomina'] === 'TIMBRADA' || $det['estatus_nomina'] === 'DISPERSADA') {
            sendJsonResponse(false, 'No se pueden realizar ajustes a un periodo ya timbrado o dispersado.', null, 400);
        }

        // Recalcular percepciones y deducciones
        $sueldoBase = (float)$det['sueldo_base'];
        $horasExtra = (float)$det['pago_horas_extra'];
        $percepcionesTotal = round($sueldoBase + $horasExtra + $bonos, 2);

        $reglas = getReglasFiscalesVigentes($pdo);
        $deduccionISR = round($percepcionesTotal * ($reglas['isr_tasa'] / 100), 2);
        $deduccionIMSS = round($percepcionesTotal * ($reglas['imss_obrera'] / 100), 2);
        $deduccionInfonavit = round($percepcionesTotal * ($reglas['infonavit_tasa'] / 100), 2);
        $deduccionesTotal = round($deduccionISR + $deduccionIMSS + $deduccionInfonavit + $deduccion_prestamo, 2);

        $nuevoNeto = round($percepcionesTotal - $deduccionesTotal + $ajuste_manual, 2);

        $updStmt = $pdo->prepare("
            UPDATE detalle_nomina 
            SET bonos = ?, 
                deduccion_isr = ?,
                deduccion_imss = ?,
                deduccion_infonavit = ?,
                deduccion_prestamo = ?,
                ajuste_manual = ?,
                justificacion_ajuste = ?,
                sueldo_neto = ?
            WHERE id = ?
        ");
        $updStmt->execute([
            $bonos,
            $deduccionISR,
            $deduccionIMSS,
            $deduccionInfonavit,
            $deduccion_prestamo,
            $ajuste_manual,
            $justificacion,
            $nuevoNeto,
            $detalle_id
        ]);

        // Recalcular totales de la cabecera de la nómina
        $recalc = $pdo->prepare("
            UPDATE nominas n SET 
                total_percepciones = (SELECT SUM(sueldo_base + pago_horas_extra + bonos) FROM detalle_nomina WHERE nomina_id = n.id),
                total_deducciones = (SELECT SUM(deduccion_isr + deduccion_imss + deduccion_infonavit + deduccion_prestamo) FROM detalle_nomina WHERE nomina_id = n.id),
                total_neto = (SELECT SUM(sueldo_neto) FROM detalle_nomina WHERE nomina_id = n.id)
            WHERE n.id = ?
        ");
        $recalc->execute([$det['nomina_id']]);

        logAuditAction(
            'AJUSTE_MANUAL_NOMINA',
            'detalle_nomina',
            $detalle_id,
            "Ajuste manual aplicado a {$det['empleado_nombre']}: Bonos \${$bonos}, Préstamo \${$deduccion_prestamo}, Ajuste \${$ajuste_manual}. Motivo: {$justificacion}",
            ['neto_anterior' => (float)$det['sueldo_neto']],
            ['neto_nuevo' => $nuevoNeto, 'justificacion' => $justificacion]
        );

        sendJsonResponse(true, "Ajuste manual aplicado exitosamente a {$det['empleado_nombre']}. Nuevo neto: \${$nuevoNeto}", [
            'detalle_id'  => $detalle_id,
            'sueldo_neto' => $nuevoNeto
        ]);
    }

    // 3. Acción principal: Procesar motor de cálculo y congelar periodo
    $nomina_id = (int)($input['nomina_id'] ?? 0);
    if ($nomina_id <= 0) {
        sendJsonResponse(false, 'Debes proporcionar un ID de nómina válido para procesar el cálculo.', null, 400);
    }

    $stmtNom = $pdo->prepare("SELECT * FROM nominas WHERE id = ?");
    $stmtNom->execute([$nomina_id]);
    $nomina = $stmtNom->fetch();

    if (!$nomina) {
        sendJsonResponse(false, 'El periodo de nómina no fue encontrado.', null, 404);
    }

    if ($nomina['estatus'] === 'TIMBRADA' || $nomina['estatus'] === 'DISPERSADA') {
        sendJsonResponse(false, "Este periodo ya se encuentra {$nomina['estatus']}. Por integridad fiscal y auditoría, no puede recalcularse.", null, 400);
    }

    try {
        $pdo->beginTransaction();

        $stmtEmp = $pdo->query("
            SELECT id, nombre, rfc, salario_diario, salario_diario_integrado, puesto, departamento 
            FROM empleados 
            WHERE estatus = 'ACTIVO' 
            ORDER BY id ASC
        ");
        $empleados = $stmtEmp->fetchAll();

        if (empty($empleados)) {
            $pdo->rollBack();
            sendJsonResponse(false, 'No hay empleados activos registrados para procesar la nómina.', null, 400);
        }

        // Limpiar cálculos previos no timbrados para esta nómina
        $delStmt = $pdo->prepare("DELETE FROM detalle_nomina WHERE nomina_id = ?");
        $delStmt->execute([$nomina_id]);

        $reglas = getReglasFiscalesVigentes($pdo);
        $tasaISR       = $reglas['isr_tasa'] / 100;
        $tasaIMSS      = $reglas['imss_obrera'] / 100;
        $tasaInfonavit = $reglas['infonavit_tasa'] / 100;

        $granTotalPercepciones = 0.00;
        $granTotalDeducciones  = 0.00;
        $granTotalNeto         = 0.00;
        $granTotalISR          = 0.00;
        $granTotalIMSS         = 0.00;
        $granTotalHorasExtra   = 0.00;

        $insertStmt = $pdo->prepare("
            INSERT INTO detalle_nomina (
                nomina_id, empleado_id, dias_trabajados, sueldo_base, 
                pago_horas_extra, bonos, deduccion_isr, deduccion_imss, 
                deduccion_infonavit, deduccion_prestamo, ajuste_manual, sueldo_neto
            ) VALUES (?, ?, ?, ?, ?, 0.00, ?, ?, ?, 0.00, 0.00, ?)
        ");

        foreach ($empleados as $emp) {
            $salarioDiario = (float)$emp['salario_diario'];
            $empId = (int)$emp['id'];
            $diasPeriodoBase = 15;

            // Faltas injustificadas aprobadas
            $stmtFaltas = $pdo->prepare("
                SELECT COUNT(*) as total_faltas 
                FROM incidencias 
                WHERE empleado_id = ? 
                  AND tipo = 'FALTA' 
                  AND estatus = 'APROBADO'
                  AND fecha BETWEEN ? AND ?
            ");
            $stmtFaltas->execute([$empId, $nomina['fecha_inicio'], $nomina['fecha_fin']]);
            $faltas = (int)$stmtFaltas->fetchColumn();

            // Incapacidades de ley (IMSS subsidia, días no pagados por empresa)
            $stmtIncap = $pdo->prepare("
                SELECT COUNT(*) as total_incapacidades 
                FROM incidencias 
                WHERE empleado_id = ? 
                  AND tipo = 'INCAPACIDAD' 
                  AND estatus = 'APROBADO'
                  AND fecha BETWEEN ? AND ?
            ");
            $stmtIncap->execute([$empId, $nomina['fecha_inicio'], $nomina['fecha_fin']]);
            $incapacidades = (int)$stmtIncap->fetchColumn();

            $diasTrabajados = max(0, $diasPeriodoBase - $faltas - $incapacidades);
            $sueldoBase = round($diasTrabajados * $salarioDiario, 2);

            // Horas extra aprobadas en el periodo
            $stmtHorasExtra = $pdo->prepare("
                SELECT COALESCE(SUM(horas_extra), 0) as total_horas 
                FROM incidencias 
                WHERE empleado_id = ? 
                  AND tipo = 'HORA_EXTRA' 
                  AND estatus = 'APROBADO'
                  AND fecha BETWEEN ? AND ?
            ");
            $stmtHorasExtra->execute([$empId, $nomina['fecha_inicio'], $nomina['fecha_fin']]);
            $totalHorasExtra = (float)$stmtHorasExtra->fetchColumn();

            // Pago por hora con recargo doble (2x) conforme a la Ley Federal del Trabajo
            $cuotaPorHora = $salarioDiario / 8;
            $pagoHorasExtra = round($totalHorasExtra * $cuotaPorHora * 2, 2);

            $percepcionesBrutas = round($sueldoBase + $pagoHorasExtra, 2);

            // Deducciones de ley parametrizadas
            $deduccionISR       = round($percepcionesBrutas * $tasaISR, 2);
            $deduccionIMSS      = round($percepcionesBrutas * $tasaIMSS, 2);
            $deduccionInfonavit = round($percepcionesBrutas * $tasaInfonavit, 2);
            $totalDeducciones   = round($deduccionISR + $deduccionIMSS + $deduccionInfonavit, 2);

            $sueldoNeto = round($percepcionesBrutas - $totalDeducciones, 2);

            $insertStmt->execute([
                $nomina_id,
                $empId,
                $diasTrabajados,
                $sueldoBase,
                $pagoHorasExtra,
                $deduccionISR,
                $deduccionIMSS,
                $deduccionInfonavit,
                $sueldoNeto
            ]);

            $granTotalPercepciones += $percepcionesBrutas;
            $granTotalDeducciones  += $totalDeducciones;
            $granTotalNeto         += $sueldoNeto;
            $granTotalISR          += $deduccionISR;
            $granTotalIMSS         += $deduccionIMSS;
            $granTotalHorasExtra   += $pagoHorasExtra;
        }

        // Actualizar cabecera de la nómina y congelar periodo
        $updateNomina = $pdo->prepare("
            UPDATE nominas 
            SET estatus = 'CONGELADA',
                total_percepciones = ?,
                total_deducciones = ?,
                total_neto = ?,
                total_isr = ?,
                total_imss = ?,
                total_horas_extra = ?,
                fecha_cierre = NOW()
            WHERE id = ?
        ");
        $updateNomina->execute([
            round($granTotalPercepciones, 2),
            round($granTotalDeducciones, 2),
            round($granTotalNeto, 2),
            round($granTotalISR, 2),
            round($granTotalIMSS, 2),
            round($granTotalHorasExtra, 2),
            $nomina_id
        ]);

        // Registrar en Audit Trail
        logAuditAction(
            'CALCULO_Y_CONGELAMIENTO_NOMINA',
            'nominas',
            $nomina_id,
            "Ejecución de motor de cálculo y congelamiento de periodo {$nomina['periodo']}. Percepciones: \${$granTotalPercepciones}, Neto: \${$granTotalNeto}",
            null,
            ['periodo' => $nomina['periodo'], 'total_neto' => $granTotalNeto, 'empleados' => count($empleados)]
        );

        // Notificación de evento: NominaAprobada (Emisión en arquitectura orientada a eventos)
        createNotification(
            null,
            null,
            'NominaAprobada',
            "Periodo {$nomina['periodo']} Congelado y Aprobado",
            "El motor de cálculo completó la pre-nómina para " . count($empleados) . " colaboradores. Listo para Timbrado Fiscal.",
            'PORTAL'
        );

        $pdo->commit();

        sendJsonResponse(true, "Motor de cálculo ejecutado con éxito. El periodo {$nomina['periodo']} ha sido CONGELADO para auditoría y timbrado fiscal.", [
            'nomina_id'            => $nomina_id,
            'periodo'              => $nomina['periodo'],
            'estatus'              => 'CONGELADA',
            'evento_emitido'       => 'NominaAprobada',
            'empleados_calculados' => count($empleados),
            'total_percepciones'   => round($granTotalPercepciones, 2),
            'total_deducciones'    => round($granTotalDeducciones, 2),
            'total_neto'           => round($granTotalNeto, 2)
        ]);

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        sendJsonResponse(false, 'Error en el motor de cálculo: ' . $e->getMessage(), null, 500);
    }
}

sendJsonResponse(false, 'Método HTTP no soportado.', null, 405);
