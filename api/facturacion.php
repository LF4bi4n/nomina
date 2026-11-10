<?php
// ========================================================
// SISTEMA DE NÓMINAS Y GESTIÓN DE PERSONAL
// Archivo: api/facturacion.php
// Módulo de Facturación Electrónica CFDI 4.0 (Ingreso) con ProFact
// ========================================================

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../services/ProfactService.php';

$pdo = getDBConnection();
$currentUser = requireAuth();
$method = $_SERVER['REQUEST_METHOD'];

// --------------------------------------------------------
// GET: Listar facturas, consultar detalle o descargar XML
// --------------------------------------------------------
if ($method === 'GET') {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : null;
    $format = isset($_GET['format']) ? strtolower(trim($_GET['format'])) : 'json';

    // 1. Descarga directa de archivo XML CFDI 4.0
    if ($id && $format === 'xml') {
        $stmt = $pdo->prepare("SELECT * FROM facturas WHERE id = ?");
        $stmt->execute([$id]);
        $factura = $stmt->fetch();

        if (!$factura || empty($factura['xml_cfdi'])) {
            sendJsonResponse(false, 'Factura no encontrada o sin XML timbrado.', null, 404);
        }

        if (!headers_sent()) {
            header('Content-Type: application/xml; charset=utf-8');
            header('Content-Disposition: attachment; filename="Factura_' . ($factura['serie'] ?: 'F') . '_' . $factura['folio'] . '_' . ($factura['folio_fiscal'] ?: $factura['id']) . '.xml"');
        }
        echo $factura['xml_cfdi'];
        exit();
    }

    // 2. Consultar una sola factura con sus conceptos
    if ($id) {
        $stmt = $pdo->prepare("SELECT * FROM facturas WHERE id = ?");
        $stmt->execute([$id]);
        $factura = $stmt->fetch();

        if (!$factura) {
            sendJsonResponse(false, 'Factura no encontrada.', null, 404);
        }

        $stmtDet = $pdo->prepare("SELECT * FROM detalle_factura WHERE factura_id = ?");
        $stmtDet->execute([$id]);
        $factura['conceptos'] = $stmtDet->fetchAll();

        $factura['subtotal'] = (float)$factura['subtotal'];
        $factura['iva']      = (float)$factura['iva'];
        $factura['total']    = (float)$factura['total'];

        sendJsonResponse(true, 'Detalle de factura obtenido.', $factura);
    }

    // 3. Listado de facturas con KPIs
    $filtroEstatus = $_GET['estatus'] ?? null;
    $sql = "SELECT * FROM facturas";
    $params = [];

    if ($filtroEstatus && in_array($filtroEstatus, ['BORRADOR', 'TIMBRADA', 'CANCELADA'])) {
        $sql .= " WHERE estatus = ?";
        $params[] = $filtroEstatus;
    }
    $sql .= " ORDER BY id DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $facturas = $stmt->fetchAll();

    foreach ($facturas as &$f) {
        $f['subtotal'] = (float)$f['subtotal'];
        $f['iva']      = (float)$f['iva'];
        $f['total']    = (float)$f['total'];
    }

    // Calcular estadísticas globales
    $statsStmt = $pdo->query("
        SELECT 
            COUNT(*) as total_facturas,
            SUM(CASE WHEN estatus = 'TIMBRADA' THEN 1 ELSE 0 END) as timbradas,
            SUM(CASE WHEN estatus = 'BORRADOR' THEN 1 ELSE 0 END) as borradores,
            SUM(CASE WHEN estatus = 'CANCELADA' THEN 1 ELSE 0 END) as canceladas,
            COALESCE(SUM(CASE WHEN estatus = 'TIMBRADA' THEN total ELSE 0 END), 0) as monto_total_facturado
        FROM facturas
    ");
    $stats = $statsStmt->fetch();

    sendJsonResponse(true, 'Facturas obtenidas.', [
        'facturas' => $facturas,
        'stats'    => [
            'total_facturas'         => (int)$stats['total_facturas'],
            'timbradas'              => (int)$stats['timbradas'],
            'borradores'             => (int)$stats['borradores'],
            'canceladas'             => (int)$stats['canceladas'],
            'monto_total_facturado'  => (float)$stats['monto_total_facturado']
        ]
    ]);
}

// --------------------------------------------------------
// POST: Crear, Timbrar o Cancelar Factura
// --------------------------------------------------------
if ($method === 'POST') {
    requireRole(['ADMIN', 'RH']);
    $input = getJsonInput();
    $action = $input['action'] ?? 'crear';

    // A) CREAR NUEVA FACTURA
    if ($action === 'crear') {
        $clienteNombre = trim($input['cliente_nombre'] ?? '');
        $clienteRfc    = strtoupper(trim($input['cliente_rfc'] ?? ''));
        $usoCfdi       = trim($input['cliente_uso_cfdi'] ?? 'G03');
        $regimenFiscal = trim($input['cliente_regimen_fiscal'] ?? '601');
        $domicilio     = trim($input['cliente_domicilio_fiscal'] ?? '06600');
        $formaPago     = trim($input['forma_pago'] ?? '03');
        $metodoPago    = trim($input['metodo_pago'] ?? 'PUE');
        $serie         = trim($input['serie'] ?? 'F');
        $conceptos     = $input['conceptos'] ?? [];

        if (empty($clienteNombre) || empty($clienteRfc)) {
            sendJsonResponse(false, 'El nombre/razón social y el RFC del cliente son obligatorios.', null, 400);
        }

        if (empty($conceptos) || !is_array($conceptos)) {
            sendJsonResponse(false, 'Debes agregar al menos un concepto a la factura.', null, 400);
        }

        // Obtener siguiente folio de la serie
        $stmtFolio = $pdo->prepare("SELECT COALESCE(MAX(folio), 0) + 1 as next_folio FROM facturas WHERE serie = ?");
        $stmtFolio->execute([$serie]);
        $nextFolio = (int)$stmtFolio->fetchColumn();

        // Calcular importes de conceptos
        $subtotal = 0.0;
        $ivaTotal = 0.0;
        $conceptosProcesados = [];

        foreach ($conceptos as $c) {
            $cantidad      = max(0.01, (float)($c['cantidad'] ?? 1));
            $valorUnitario = max(0.01, (float)($c['valor_unitario'] ?? 0));
            $importe       = round($cantidad * $valorUnitario, 2);
            $ivaConcepto   = round($importe * 0.16, 2);

            $subtotal += $importe;
            $ivaTotal += $ivaConcepto;

            $conceptosProcesados[] = [
                'clave_prod_serv' => trim($c['clave_prod_serv'] ?? '84111500'),
                'descripcion'     => trim($c['descripcion'] ?? 'Servicio Profesional'),
                'cantidad'        => $cantidad,
                'clave_unidad'    => trim($c['clave_unidad'] ?? 'E48'),
                'valor_unitario'  => $valorUnitario,
                'importe'         => $importe,
                'objeto_imp'      => '02',
                'tasa_iva'        => 0.1600,
                'iva'             => $ivaConcepto
            ];
        }

        $total = round($subtotal + $ivaTotal, 2);

        try {
            $pdo->beginTransaction();

            $stmtIns = $pdo->prepare("
                INSERT INTO facturas 
                (serie, folio, tipo_comprobante, cliente_nombre, cliente_rfc, cliente_uso_cfdi, cliente_regimen_fiscal, cliente_domicilio_fiscal, fecha_emision, moneda, forma_pago, metodo_pago, subtotal, iva, total, estatus, pac_utilizado, creado_por)
                VALUES 
                (?, ?, 'I', ?, ?, ?, ?, ?, NOW(), 'MXN', ?, ?, ?, ?, ?, 'BORRADOR', 'PROFACT', ?)
            ");
            $stmtIns->execute([
                $serie,
                $nextFolio,
                $clienteNombre,
                $clienteRfc,
                $usoCfdi,
                $regimenFiscal,
                $domicilio,
                $formaPago,
                $metodoPago,
                $subtotal,
                $ivaTotal,
                $total,
                $currentUser['id'] ?? null
            ]);
            $facturaId = (int)$pdo->lastInsertId();

            $stmtDetIns = $pdo->prepare("
                INSERT INTO detalle_factura 
                (factura_id, clave_prod_serv, descripcion, cantidad, clave_unidad, valor_unitario, importe, objeto_imp, tasa_iva, iva)
                VALUES 
                (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            foreach ($conceptosProcesados as $cp) {
                $stmtDetIns->execute([
                    $facturaId,
                    $cp['clave_prod_serv'],
                    $cp['descripcion'],
                    $cp['cantidad'],
                    $cp['clave_unidad'],
                    $cp['valor_unitario'],
                    $cp['importe'],
                    $cp['objeto_imp'],
                    $cp['tasa_iva'],
                    $cp['iva']
                ]);
            }

            logAuditAction('FACTURA_CREADA', 'facturas', $facturaId, "Factura $serie-$nextFolio creada para $clienteNombre ($clienteRfc) por $$total MXN.");

            $pdo->commit();

            sendJsonResponse(true, "Factura $serie-$nextFolio guardada como borrador con éxito.", [
                'id'       => $facturaId,
                'serie'    => $serie,
                'folio'    => $nextFolio,
                'subtotal' => $subtotal,
                'iva'      => $ivaTotal,
                'total'    => $total
            ]);

        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            sendJsonResponse(false, 'Error al guardar factura: ' . $e->getMessage(), null, 500);
        }
    }

    // B) TIMBRAR FACTURA CON PROFACT (CFDI 4.0)
    if ($action === 'timbrar') {
        $facturaId = (int)($input['factura_id'] ?? 0);
        if ($facturaId <= 0) {
            sendJsonResponse(false, 'ID de factura inválido.', null, 400);
        }

        $stmt = $pdo->prepare("SELECT * FROM facturas WHERE id = ?");
        $stmt->execute([$facturaId]);
        $factura = $stmt->fetch();

        if (!$factura) {
            sendJsonResponse(false, 'Factura no encontrada.', null, 404);
        }

        if ($factura['estatus'] === 'TIMBRADA') {
            sendJsonResponse(false, 'Esta factura ya se encuentra timbrada fiscalmente.', null, 400);
        }

        if ($factura['estatus'] === 'CANCELADA') {
            sendJsonResponse(false, 'No se puede timbrar una factura cancelada.', null, 400);
        }

        $stmtDet = $pdo->prepare("SELECT * FROM detalle_factura WHERE factura_id = ?");
        $stmtDet->execute([$facturaId]);
        $conceptos = $stmtDet->fetchAll();

        // 1. Construir XML CFDI 4.0
        $xmlComprobante = ProfactService::generarXMLFacturaIngreso($factura, $conceptos);

        // 2. Invocar ProFact TimbraCFDI
        $identificadorComprobante = "FAC-{$factura['serie']}-{$factura['folio']}";
        $resultadoTimbrado = ProfactService::timbrarComprobanteXML($xmlComprobante, $identificadorComprobante);

        if (!$resultadoTimbrado['success']) {
            sendJsonResponse(false, $resultadoTimbrado['mensaje'] ?? 'Error devuelto por el PAC ProFact.', $resultadoTimbrado, 400);
        }

        // 3. Actualizar factura con datos del Timbre Fiscal Digital del SAT
        $updateStmt = $pdo->prepare("
            UPDATE facturas 
            SET folio_fiscal = ?,
                sello_sat = ?,
                sello_cfd = ?,
                codigo_qr = ?,
                xml_cfdi = ?,
                estatus = 'TIMBRADA',
                fecha_timbrado = ?,
                pac_utilizado = ?
            WHERE id = ?
        ");
        $updateStmt->execute([
            $resultadoTimbrado['uuid'],
            $resultadoTimbrado['sello_sat'],
            $resultadoTimbrado['sello_cfd'],
            $resultadoTimbrado['codigo_qr'],
            $resultadoTimbrado['xml_timbrado'],
            $resultadoTimbrado['fecha_timbrado'],
            $resultadoTimbrado['pac'] ?? 'PROFACT',
            $facturaId
        ]);

        logAuditAction(
            'FACTURA_TIMBRADA_PROFACT',
            'facturas',
            $facturaId,
            "Factura {$factura['serie']}-{$factura['folio']} timbrada por ProFact con Folio Fiscal UUID {$resultadoTimbrado['uuid']}.",
            ['estatus' => 'BORRADOR'],
            ['estatus' => 'TIMBRADA', 'uuid' => $resultadoTimbrado['uuid']]
        );

        sendJsonResponse(true, "¡Factura timbrada exitosamente con ProFact! Folio Fiscal UUID: {$resultadoTimbrado['uuid']}", [
            'factura_id'     => $facturaId,
            'serie'          => $factura['serie'],
            'folio'          => $factura['folio'],
            'folio_fiscal'   => $resultadoTimbrado['uuid'],
            'fecha_timbrado' => $resultadoTimbrado['fecha_timbrado'],
            'pac_utilizado'  => $resultadoTimbrado['pac'] ?? 'PROFACT'
        ]);
    }

    // C) CANCELAR FACTURA CON PROFACT (CancelaCFDI40)
    if ($action === 'cancelar') {
        $facturaId = (int)($input['factura_id'] ?? 0);
        $motivo = trim($input['motivo_cancelacion'] ?? '02');
        $sustitucion = trim($input['folio_sustitucion'] ?? '');

        $stmt = $pdo->prepare("SELECT * FROM facturas WHERE id = ?");
        $stmt->execute([$facturaId]);
        $factura = $stmt->fetch();

        if (!$factura) {
            sendJsonResponse(false, 'Factura no encontrada.', null, 404);
        }

        if ($factura['estatus'] !== 'TIMBRADA') {
            sendJsonResponse(false, 'Solo se pueden cancelar facturas que hayan sido timbradas.', null, 400);
        }

        $configPac = ProfactService::getConfig();
        $resultadoCancelacion = ProfactService::cancelarCFDI40($configPac['rfc_emisor'], $factura['folio_fiscal'], $motivo, $sustitucion);

        $pdo->prepare("UPDATE facturas SET estatus = 'CANCELADA', motivo_cancelacion = ? WHERE id = ?")
            ->execute([$motivo, $facturaId]);

        logAuditAction('FACTURA_CANCELADA', 'facturas', $facturaId, "Factura {$factura['serie']}-{$factura['folio']} cancelada (UUID: {$factura['folio_fiscal']}). Motivo SAT: $motivo.");

        sendJsonResponse(true, "Factura cancelada correctamente en ProFact.", $resultadoCancelacion);
    }

    sendJsonResponse(false, 'Acción no soportada.', null, 400);
}

sendJsonResponse(false, 'Método HTTP no soportado.', null, 405);

