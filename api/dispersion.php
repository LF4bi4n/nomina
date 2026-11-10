<?php
// ========================================================
// SISTEMA DE NÓMINAS Y GESTIÓN DE PERSONAL
// Archivo: api/dispersion.php
// Servicio 5: Dispersión Bancaria Masiva (Layouts Bancarios & SPEI)
// ========================================================

require_once __DIR__ . '/../config/database.php';

$pdo = getDBConnection();
$currentUser = requireAuth();
$method = $_SERVER['REQUEST_METHOD'];

// Diccionario de bancos por CLABE
function getBankNameByClabe(string $clabe): string {
    $code = substr($clabe, 0, 3);
    $banks = [
        '002' => 'BANAMEX',
        '012' => 'BBVA MEXICO',
        '014' => 'SANTANDER',
        '021' => 'HSBC',
        '044' => 'SCOTIABANK',
        '072' => 'BANORTE',
        '127' => 'BANCO AZTECA',
        '137' => 'BANCOPPEL',
        '058' => 'BANREGIO'
    ];
    return $banks[$code] ?? 'BANCO NACIONAL';
}

// --------------------------------------------------------
// GET: Consultar o descargar archivo de Layout bancario
// --------------------------------------------------------
if ($method === 'GET') {
    $nomina_id = isset($_GET['nomina_id']) ? (int)$_GET['nomina_id'] : null;
    $format    = isset($_GET['format']) ? strtolower(trim($_GET['format'])) : 'json';
    $banco_convenio = isset($_GET['banco']) ? strtoupper(trim($_GET['banco'])) : 'UNIVERSAL';

    if (!$nomina_id) {
        sendJsonResponse(false, 'Debes proporcionar un ID de nómina.', null, 400);
    }

    $stmtNom = $pdo->prepare("SELECT * FROM nominas WHERE id = ?");
    $stmtNom->execute([$nomina_id]);
    $nomina = $stmtNom->fetch();

    if (!$nomina) {
        sendJsonResponse(false, 'Periodo de nómina no encontrado.', null, 404);
    }

    $stmtDet = $pdo->prepare("
        SELECT dn.id, dn.sueldo_neto, dn.folio_fiscal, dn.estado_dispersion, dn.referencia_bancaria,
               e.id as empleado_id, e.nombre, e.cuenta_clabe, e.rfc, e.departamento, e.banco
        FROM detalle_nomina dn
        INNER JOIN empleados e ON dn.empleado_id = e.id
        WHERE dn.nomina_id = ?
        ORDER BY e.id ASC
    ");
    $stmtDet->execute([$nomina_id]);
    $transferencias = $stmtDet->fetchAll();

    $cuentaOrigenEmisor = '012180001009988771';
    $razonSocialEmisora = 'SISTEMA DE NOMINAS Y PERSONAL SA DE CV';
    $fechaLote = date('Ymd');
    $folioLote = 'DISP-' . $nomina['periodo'] . '-' . date('His');

    // Si se solicita descarga directa de archivo Layout TXT / CSV para pasarela bancaria
    if ($format === 'txt' || $format === 'csv' || isset($_GET['download'])) {
        $filename = "LAYOUT_DISPERSION_{$banco_convenio}_{$nomina['periodo']}_{$fechaLote}." . ($format === 'csv' ? 'csv' : 'txt');
        
        header('Content-Type: text/plain; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $delimiter = ($format === 'csv') ? ',' : "\t";

        // Registro de Cabecera (Header Lote)
        echo "01" . $delimiter . $cuentaOrigenEmisor . $delimiter . $razonSocialEmisora . $delimiter . $fechaLote . $delimiter . $folioLote . $delimiter . "CONVENIO_" . $banco_convenio . "\r\n";

        // Registros de Detalle (1 por empleado)
        $secuencia = 1;
        foreach ($transferencias as $t) {
            $monto = number_format((float)$t['sueldo_neto'], 2, '.', '');
            $nombreLimpio = strtoupper(strtr($t['nombre'], ['Á'=>'A','É'=>'E','Í'=>'I','Ó'=>'O','Ú'=>'U','Ñ'=>'N']));
            $concepto = "NOMINA " . $nomina['periodo'];
            $referencia = $t['referencia_bancaria'] ?: ('SPEI-' . str_pad($secuencia, 7, '0', STR_PAD_LEFT));
            $bancoReceptor = $t['banco'] ?? getBankNameByClabe($t['cuenta_clabe']);

            echo "02" . $delimiter 
                 . str_pad($secuencia, 5, '0', STR_PAD_LEFT) . $delimiter 
                 . $t['cuenta_clabe'] . $delimiter 
                 . str_pad($monto, 12, ' ', STR_PAD_LEFT) . $delimiter 
                 . $nombreLimpio . $delimiter 
                 . $concepto . $delimiter 
                 . $referencia . $delimiter 
                 . $bancoReceptor . "\r\n";
            $secuencia++;
        }

        // Registro de Fin de Lote (Trailer)
        $totalMonto = number_format((float)$nomina['total_neto'], 2, '.', '');
        echo "09" . $delimiter . str_pad(count($transferencias), 5, '0', STR_PAD_LEFT) . $delimiter . str_pad($totalMonto, 15, ' ', STR_PAD_LEFT) . "\r\n";
        exit();
    }

    // Respuesta estructurada JSON con resumen de dispersión
    $listaFormateada = [];
    $conteoExitoso = 0;
    $conteoRebotado = 0;
    $conteoPendiente = 0;

    foreach ($transferencias as $t) {
        $estado = $t['estado_dispersion'] ?: ($nomina['estatus'] === 'DISPERSADA' ? 'EXITOSO' : 'PENDIENTE');
        if ($estado === 'EXITOSO') $conteoExitoso++;
        elseif ($estado === 'REBOTADO') $conteoRebotado++;
        else $conteoPendiente++;

        $listaFormateada[] = [
            'id'                  => (int)$t['id'],
            'empleado_id'         => (int)$t['empleado_id'],
            'nombre'              => $t['nombre'],
            'cuenta_clabe'        => $t['cuenta_clabe'],
            'banco'               => $t['banco'] ?? getBankNameByClabe($t['cuenta_clabe']),
            'sueldo_neto'         => (float)$t['sueldo_neto'],
            'folio_fiscal'        => $t['folio_fiscal'],
            'estado_pago'         => $estado,
            'referencia_bancaria' => $t['referencia_bancaria'] ?: ('SPEI-' . str_pad($t['id'], 6, '0', STR_PAD_LEFT))
        ];
    }

    sendJsonResponse(true, 'Resumen de transferencias de dispersión bancaria.', [
        'nomina_id'           => $nomina_id,
        'periodo'             => $nomina['periodo'],
        'estatus_nomina'      => $nomina['estatus'],
        'cuenta_origen'       => $cuentaOrigenEmisor,
        'banco_convenio'      => $banco_convenio,
        'total_transferencias'=> count($listaFormateada),
        'conteo_exitosos'     => $conteoExitoso,
        'conteo_rebotados'    => $conteoRebotado,
        'conteo_pendientes'   => $conteoPendiente,
        'monto_total_neto'    => (float)$nomina['total_neto'],
        'transferencias'      => $listaFormateada
    ]);
}

// --------------------------------------------------------
// POST: Ejecutar dispersión bancaria masiva o reintentar rebotados
// --------------------------------------------------------
if ($method === 'POST') {
    requireRole(['ADMIN', 'RH']);

    $input = getJsonInput();
    $action = $input['action'] ?? 'dispersar';
    $nomina_id = (int)($input['nomina_id'] ?? 0);

    if ($nomina_id <= 0) {
        sendJsonResponse(false, 'Debes proporcionar un ID de nómina válido.', null, 400);
    }

    $stmtNom = $pdo->prepare("SELECT * FROM nominas WHERE id = ?");
    $stmtNom->execute([$nomina_id]);
    $nomina = $stmtNom->fetch();

    if (!$nomina) {
        sendJsonResponse(false, 'Periodo de nómina no encontrado.', null, 404);
    }

    // Sub-acción: Reintentar pago individual rebotado
    if ($action === 'reintentar_pago') {
        $detalle_id = (int)($input['detalle_id'] ?? 0);
        $nueva_clabe = trim($input['cuenta_clabe'] ?? '');

        if ($detalle_id <= 0) {
            sendJsonResponse(false, 'ID de detalle inválido.', null, 400);
        }

        $stmtDet = $pdo->prepare("SELECT dn.*, e.nombre, e.id as emp_id FROM detalle_nomina dn JOIN empleados e ON dn.empleado_id = e.id WHERE dn.id = ?");
        $stmtDet->execute([$detalle_id]);
        $det = $stmtDet->fetch();

        if (!$det) {
            sendJsonResponse(false, 'Registro no encontrado.', null, 404);
        }

        if (!empty($nueva_clabe) && strlen($nueva_clabe) === 18 && ctype_digit($nueva_clabe)) {
            $stmtUpdEmp = $pdo->prepare("UPDATE empleados SET cuenta_clabe = ?, banco = ? WHERE id = ?");
            $stmtUpdEmp->execute([$nueva_clabe, getBankNameByClabe($nueva_clabe), $det['emp_id']]);
        }

        $nuevaRef = 'SPEI-RE-' . date('ymd') . '-' . str_pad($detalle_id, 4, '0', STR_PAD_LEFT);
        $stmtUpdDet = $pdo->prepare("UPDATE detalle_nomina SET estado_dispersion = 'EXITOSO', referencia_bancaria = ? WHERE id = ?");
        $stmtUpdDet->execute([$nuevaRef, $detalle_id]);

        logAuditAction('REINTENTO_PAGO_DISPERSION', 'detalle_nomina', $detalle_id, "Reintento exitoso de dispersión SPEI para {$det['nombre']}. Ref: {$nuevaRef}");

        sendJsonResponse(true, "Transferencia reintentada exitosamente para {$det['nombre']}. Nuevo estado: EXITOSO.");
    }

    // Dispersión Masiva General
    if ($nomina['estatus'] === 'BORRADOR') {
        sendJsonResponse(false, 'La nómina debe estar previamente calculada y timbrada antes de dispersar.', null, 400);
    }

    $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM detalle_nomina WHERE nomina_id = ?");
    $stmtCount->execute([$nomina_id]);
    $totalRecibos = (int)$stmtCount->fetchColumn();

    if ($totalRecibos === 0) {
        sendJsonResponse(false, 'No hay recibos calculados para dispersar en este periodo.', null, 400);
    }

    // Simulación de dispersión masiva: la gran mayoría son EXITOSOS (se puede simular 1 rebotado si CLABE es de prueba y se desea demostrar robustez)
    $stmtUpdateDet = $pdo->prepare("
        UPDATE detalle_nomina 
        SET estado_dispersion = 'EXITOSO'
        WHERE nomina_id = ?
    ");
    $stmtUpdateDet->execute([$nomina_id]);

    // Actualizar nómina a estatus DISPERSADA
    $stmtUpd = $pdo->prepare("UPDATE nominas SET estatus = 'DISPERSADA' WHERE id = ?");
    $stmtUpd->execute([$nomina_id]);

    $folioLote = 'DISP-' . $nomina['periodo'] . '-' . date('His');

    // Registrar en Audit Trail
    logAuditAction(
        'DISPERSION_BANCARIA_MASIVA',
        'nominas',
        $nomina_id,
        "Dispersión masiva SPEI ejecutada para periodo {$nomina['periodo']}. Total transferencias: {$totalRecibos}, Monto: \${$nomina['total_neto']}",
        ['estatus' => $nomina['estatus']],
        ['estatus' => 'DISPERSADA', 'folio_lote' => $folioLote, 'monto' => $nomina['total_neto']]
    );

    // Emisión de evento y notificación global
    createNotification(
        null,
        null,
        'DispersionEjecutada',
        "Dispersión Bancaria Realizada ({$nomina['periodo']})",
        "Se aplicaron transferencias electrónicas masivas SPEI por un monto global de \${$nomina['total_neto']} a las cuentas CLABE de los colaboradores.",
        'PORTAL'
    );

    sendJsonResponse(true, "Dispersión bancaria masiva autorizada y transmitida exitosamente a las cuentas CLABE.", [
        'nomina_id'               => $nomina_id,
        'periodo'                 => $nomina['periodo'],
        'estatus'                 => 'DISPERSADA',
        'folio_lote'              => $folioLote,
        'evento_emitido'          => 'DispersionEjecutada',
        'transferencias_exitosas' => $totalRecibos,
        'monto_dispersado'        => (float)$nomina['total_neto'],
        'fecha_dispersion'        => date('Y-m-d H:i:s'),
        'layout_url'              => "api/dispersion.php?nomina_id={$nomina_id}&format=txt"
    ]);
}

sendJsonResponse(false, 'Método HTTP no soportado.', null, 405);
