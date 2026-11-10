<?php
// ========================================================
// SISTEMA DE NÓMINAS Y GESTIÓN DE PERSONAL
// Archivo: api/timbrado.php
// Servicio 4: Timbrado Fiscal CFDI 4.0 / Complemento Nómina 1.2 / PAC
// ========================================================

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../services/ProfactService.php';

$pdo = getDBConnection();
$currentUser = requireAuth();
$method = $_SERVER['REQUEST_METHOD'];

/**
 * Genera un UUID versión 4 RFC 4122
 */
function generateUUIDv4(): string {
    $data = random_bytes(16);
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

/**
 * Genera un sello criptográfico simulado base64
 */
function generateSimulatedSeal(string $text): string {
    return base64_encode(hash('sha256', $text . microtime(true) . 'CERT_PAC_SAT_2026', true));
}

// --------------------------------------------------------
// GET: Descargar XML o consultar datos de recibo timbrado
// --------------------------------------------------------
if ($method === 'GET') {
    $detalle_id = isset($_GET['detalle_id']) ? (int)$_GET['detalle_id'] : null;
    $format     = isset($_GET['format']) ? strtolower(trim($_GET['format'])) : 'json';

    if (!$detalle_id) {
        sendJsonResponse(false, 'Debes proporcionar un detalle_id de recibo.', null, 400);
    }

    $stmt = $pdo->prepare("
        SELECT dn.*, n.periodo, n.fecha_inicio, n.fecha_fin, n.estatus as estatus_nomina,
               e.nombre as empleado_nombre, e.rfc, e.curp, e.puesto, e.departamento, 
               e.salario_diario, e.salario_diario_integrado, e.cuenta_clabe, e.banco, e.fecha_ingreso,
               u.email as empleado_email
        FROM detalle_nomina dn
        INNER JOIN nominas n ON dn.nomina_id = n.id
        INNER JOIN empleados e ON dn.empleado_id = e.id
        LEFT JOIN usuarios u ON u.empleado_id = e.id
        WHERE dn.id = ?
    ");
    $stmt->execute([$detalle_id]);
    $recibo = $stmt->fetch();

    if (!$recibo) {
        sendJsonResponse(false, 'Recibo no encontrado.', null, 404);
    }

    // Si el usuario es rol EMPLEADO, validar que sea su propio recibo
    if ($currentUser['rol'] === 'EMPLEADO' && (int)$recibo['empleado_id'] !== (int)$currentUser['empleado_id']) {
        sendJsonResponse(false, 'Acceso denegado: este recibo pertenece a otro colaborador.', null, 403);
    }

    // Si se solicitó descarga de XML puro
    if ($format === 'xml') {
        if (empty($recibo['xml_cfdi'])) {
            sendJsonResponse(false, 'Este recibo aún no cuenta con XML timbrado fiscalmente.', null, 400);
        }
        header('Content-Type: application/xml; charset=utf-8');
        header('Content-Disposition: attachment; filename="CFDI_Nomina_' . ($recibo['folio_fiscal'] ?: $recibo['id']) . '.xml"');
        echo $recibo['xml_cfdi'];
        exit();
    }

    $recibo['sueldo_base']          = (float)$recibo['sueldo_base'];
    $recibo['pago_horas_extra']     = (float)$recibo['pago_horas_extra'];
    $recibo['bonos']                = (float)($recibo['bonos'] ?? 0.00);
    $recibo['percepciones']         = round($recibo['sueldo_base'] + $recibo['pago_horas_extra'] + $recibo['bonos'], 2);

    $recibo['deduccion_isr']        = (float)$recibo['deduccion_isr'];
    $recibo['deduccion_imss']       = (float)$recibo['deduccion_imss'];
    $recibo['deduccion_infonavit']  = (float)($recibo['deduccion_infonavit'] ?? 0.00);
    $recibo['deduccion_prestamo']   = (float)($recibo['deduccion_prestamo'] ?? 0.00);
    $recibo['deducciones']          = round($recibo['deduccion_isr'] + $recibo['deduccion_imss'] + $recibo['deduccion_infonavit'] + $recibo['deduccion_prestamo'], 2);

    $recibo['ajuste_manual']        = (float)($recibo['ajuste_manual'] ?? 0.00);
    $recibo['sueldo_neto']          = (float)$recibo['sueldo_neto'];
    $recibo['salario_diario']       = (float)$recibo['salario_diario'];
    $recibo['salario_diario_integrado'] = (float)($recibo['salario_diario_integrado'] ?? round($recibo['salario_diario'] * 1.0493, 2));

    sendJsonResponse(true, 'Datos de comprobante fiscal recuperados.', $recibo);
}

// --------------------------------------------------------
// POST: Timbrar nómina en lote (Proveedor Autorizado PAC)
// --------------------------------------------------------
if ($method === 'POST') {
    requireRole(['ADMIN', 'RH']);

    $input = getJsonInput();
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

    $force = !empty($input['force']);

    if ($nomina['estatus'] === 'BORRADOR' && !$force) {
        sendJsonResponse(false, 'La nómina debe estar previamente calculada y CONGELADA antes de timbrar. Haz clic en "Calcular & Congelar".', null, 400);
    }

    if (($nomina['estatus'] === 'TIMBRADA' || $nomina['estatus'] === 'DISPERSADA') && !$force) {
        sendJsonResponse(false, 'Esta nómina ya fue timbrada previamente. Puedes forzar un re-timbrado de prueba con ProFact.', null, 400);
    }

    $stmtDetalles = $pdo->prepare("
        SELECT dn.*, 
               e.nombre, e.rfc, e.curp, e.puesto, e.departamento, e.salario_diario, 
               e.salario_diario_integrado, e.cuenta_clabe, e.banco, e.fecha_ingreso,
               u.id as usuario_id, u.email as usuario_email
        FROM detalle_nomina dn
        INNER JOIN empleados e ON dn.empleado_id = e.id
        LEFT JOIN usuarios u ON u.empleado_id = e.id
        WHERE dn.nomina_id = ?
    ");
    $stmtDetalles->execute([$nomina_id]);
    $detalles = $stmtDetalles->fetchAll();

    if (empty($detalles)) {
        sendJsonResponse(false, 'No hay detalles calculados para timbrar en esta nómina.', null, 400);
    }

    try {
        $pdo->beginTransaction();

        $updateDetalleStmt = $pdo->prepare("
            UPDATE detalle_nomina 
            SET folio_fiscal = ?,
                sello_sat = ?,
                xml_cfdi = ?,
                fecha_timbrado = ?,
                referencia_bancaria = ?
            WHERE id = ?
        ");

        $timbradosList = [];
        $emisorRfc = "NOM210315AB1";
        $emisorRazon = "SISTEMA DE NOMINAS Y PERSONAL S.A. DE C.V.";
        $pacRfc = "PAC0906201A0";
        $regimenFiscal = "601";

        foreach ($detalles as $det) {
            $uuid = strtoupper(generateUUIDv4());
            $fechaTimbrado = date('Y-m-d\TH:i:s');
            $selloSat = generateSimulatedSeal($uuid . $det['rfc']);
            $selloCfd = generateSimulatedSeal($det['sueldo_neto'] . $uuid);
            $refSpei = 'SPEI-' . date('ymd') . '-' . str_pad($det['id'], 5, '0', STR_PAD_LEFT);

            $sueldoBase         = (float)$det['sueldo_base'];
            $horasExtra         = (float)$det['pago_horas_extra'];
            $bonos              = (float)($det['bonos'] ?? 0.00);
            $totalPercepciones  = round($sueldoBase + $horasExtra + $bonos, 2);

            $deduccionISR       = (float)$det['deduccion_isr'];
            $deduccionIMSS      = (float)$det['deduccion_imss'];
            $deduccionInfonavit = (float)($det['deduccion_infonavit'] ?? 0.00);
            $deduccionPrestamo  = (float)($det['deduccion_prestamo'] ?? 0.00);
            $totalDeducciones   = round($deduccionISR + $deduccionIMSS + $deduccionInfonavit + $deduccionPrestamo, 2);

            $sueldoNeto         = (float)$det['sueldo_neto'];
            $sdi                = (float)($det['salario_diario_integrado'] ?? round($det['salario_diario'] * 1.0493, 2));

            // Construir XML estructurado CFDI 4.0 con Complemento Nómina 1.2 (Pre-timbrado)
            $xmlPre = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
            $xmlPre .= '<cfdi:Comprobante xmlns:cfdi="http://www.sat.gob.mx/cfd/4" xmlns:nomina12="http://www.sat.gob.mx/nomina12" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" Version="4.0" Serie="NOM" Folio="' . $det['id'] . '" Fecha="' . $fechaTimbrado . '" SubTotal="' . number_format($totalPercepciones, 2, '.', '') . '" Descuento="' . number_format($totalDeducciones, 2, '.', '') . '" Total="' . number_format($sueldoNeto, 2, '.', '') . '" Moneda="MXN" TipoDeComprobante="N" MetodoPago="PUE" LugarExpedicion="06600" Exportacion="01" Sello="' . $selloCfd . '" NoCertificado="30001000000500003416">' . "\n";
            $xmlPre .= '    <cfdi:Emisor Rfc="' . $emisorRfc . '" Nombre="' . htmlspecialchars($emisorRazon, ENT_XML1) . '" RegimenFiscal="' . $regimenFiscal . '"/>' . "\n";
            $xmlPre .= '    <cfdi:Receptor Rfc="' . htmlspecialchars($det['rfc'], ENT_XML1) . '" Nombre="' . htmlspecialchars($det['nombre'], ENT_XML1) . '" DomicilioFiscalReceptor="06600" RegimenFiscalReceptor="605" UsoCFDI="CN01"/>' . "\n";
            $xmlPre .= '    <cfdi:Conceptos>' . "\n";
            $xmlPre .= '        <cfdi:Concepto ClaveProdServ="84111505" Cantidad="1" ClaveUnidad="ACT" Descripcion="Pago de nómina y prestaciones laborales" ValorUnitario="' . number_format($totalPercepciones, 2, '.', '') . '" Importe="' . number_format($totalPercepciones, 2, '.', '') . '" Descuento="' . number_format($totalDeducciones, 2, '.', '') . '" ObjetoImp="01"/>' . "\n";
            $xmlPre .= '    </cfdi:Conceptos>' . "\n";
            $xmlPre .= '    <cfdi:Complemento>' . "\n";
            $xmlPre .= '        <nomina12:Nomina Version="1.2" TipoNomina="O" FechaPago="' . date('Y-m-d') . '" FechaInicialPago="' . $nomina['fecha_inicio'] . '" FechaFinalPago="' . $nomina['fecha_fin'] . '" NumDiasPagados="' . $det['dias_trabajados'] . '" TotalPercepciones="' . number_format($totalPercepciones, 2, '.', '') . '" TotalDeducciones="' . number_format($totalDeducciones, 2, '.', '') . '">' . "\n";
            $xmlPre .= '            <nomina12:Emisor RegistroPatronal="Y5623841103"/>' . "\n";
            $xmlPre .= '            <nomina12:Receptor Curp="' . htmlspecialchars($det['curp'], ENT_XML1) . '" NumSeguridadSocial="12948572183" FechaInicioRelLaboral="' . $det['fecha_ingreso'] . '" Antiguedad="P2Y" TipoContrato="01" TipoJornada="01" TipoRegimen="02" NumEmpleado="' . $det['empleado_id'] . '" Departamento="' . htmlspecialchars($det['departamento'], ENT_XML1) . '" Puesto="' . htmlspecialchars($det['puesto'], ENT_XML1) . '" PeriodicidadPago="04" Banco="012" CuentaBancaria="' . $det['cuenta_clabe'] . '" SalarioBaseCotApor="' . number_format($det['salario_diario'], 2, '.', '') . '" SalarioDiarioIntegrado="' . number_format($sdi, 2, '.', '') . '"/>' . "\n";
            $xmlPre .= '            <nomina12:Percepciones TotalSueldos="' . number_format($totalPercepciones, 2, '.', '') . '" TotalGravado="' . number_format($totalPercepciones, 2, '.', '') . '" TotalExento="0.00">' . "\n";
            $xmlPre .= '                <nomina12:Percepcion TipoPercepcion="001" Clave="001" Concepto="Sueldo Quincenal" ImporteGravado="' . number_format($sueldoBase, 2, '.', '') . '" ImporteExento="0.00"/>' . "\n";
            if ($horasExtra > 0) {
                $xmlPre .= '                <nomina12:Percepcion TipoPercepcion="019" Clave="019" Concepto="Horas Extra Dobles" ImporteGravado="' . number_format($horasExtra, 2, '.', '') . '" ImporteExento="0.00"/>' . "\n";
            }
            if ($bonos > 0) {
                $xmlPre .= '                <nomina12:Percepcion TipoPercepcion="038" Clave="038" Concepto="Bono de Productividad" ImporteGravado="' . number_format($bonos, 2, '.', '') . '" ImporteExento="0.00"/>' . "\n";
            }
            $xmlPre .= '            </nomina12:Percepciones>' . "\n";
            $xmlPre .= '            <nomina12:Deducciones TotalOtrasDeducciones="' . number_format($deduccionIMSS + $deduccionInfonavit + $deduccionPrestamo, 2, '.', '') . '" TotalImpuestosRetenidos="' . number_format($deduccionISR, 2, '.', '') . '">' . "\n";
            $xmlPre .= '                <nomina12:Deduccion TipoDeduccion="002" Clave="002" Concepto="ISR Retenido de Ley" Importe="' . number_format($deduccionISR, 2, '.', '') . '"/>' . "\n";
            $xmlPre .= '                <nomina12:Deduccion TipoDeduccion="001" Clave="001" Concepto="Seguridad Social IMSS Cuota Obrera" Importe="' . number_format($deduccionIMSS, 2, '.', '') . '"/>' . "\n";
            if ($deduccionInfonavit > 0) {
                $xmlPre .= '                <nomina12:Deduccion TipoDeduccion="009" Clave="009" Concepto="Aportacion Vivienda Infonavit" Importe="' . number_format($deduccionInfonavit, 2, '.', '') . '"/>' . "\n";
            }
            if ($deduccionPrestamo > 0) {
                $xmlPre .= '                <nomina12:Deduccion TipoDeduccion="004" Clave="004" Concepto="Prestamos de Empresa y Retenciones Internas" Importe="' . number_format($deduccionPrestamo, 2, '.', '') . '"/>' . "\n";
            }
            $xmlPre .= '            </nomina12:Deducciones>' . "\n";
            $xmlPre .= '        </nomina12:Nomina>' . "\n";
            $xmlPre .= '    </cfdi:Complemento>' . "\n";
            $xmlPre .= '</cfdi:Comprobante>';

            // Timbrado Fiscal a través de ProFact PAC
            $resProfact = ProfactService::timbrarComprobanteXML($xmlPre, "NOM-{$nomina['periodo']}-{$det['id']}");
            $uuid = $resProfact['uuid'];
            $selloSat = $resProfact['sello_sat'];
            $xml = $resProfact['xml_timbrado'];
            $fechaTimbrado = $resProfact['fecha_timbrado'];

            $updateDetalleStmt->execute([
                $uuid,
                $selloSat,
                $xml,
                date('Y-m-d H:i:s'),
                $refSpei,
                $det['id']
            ]);

            // Emisión de evento asíncrono: ReciboTimbrado -> Crea notificación al empleado
            createNotification(
                $det['usuario_id'] ? (int)$det['usuario_id'] : null,
                (int)$det['empleado_id'],
                'ReciboTimbrado',
                "Recibo CFDI Timbrado ({$nomina['periodo']})",
                "Tu recibo de nómina quincenal ha sido certificado por el SAT con Folio Fiscal UUID {$uuid}. Ya puedes descargarlo en PDF y XML.",
                'PORTAL'
            );

            $timbradosList[] = [
                'detalle_id'   => $det['id'],
                'empleado'     => $det['nombre'],
                'folio_fiscal' => $uuid,
                'sueldo_neto'  => $sueldoNeto,
                'referencia'   => $refSpei
            ];
        }

        // Actualizar estatus general de la nómina
        $updNom = $pdo->prepare("UPDATE nominas SET estatus = 'TIMBRADA' WHERE id = ?");
        $updNom->execute([$nomina_id]);

        // Registrar en Audit Trail
        logAuditAction(
            'TIMBRADO_FISCAL_CFDI',
            'nominas',
            $nomina_id,
            "Timbrado fiscal completado por el PAC para {$nomina['periodo']}. Se generaron " . count($timbradosList) . " comprobantes CFDI 4.0",
            ['estatus' => 'CONGELADA'],
            ['estatus' => 'TIMBRADA', 'recibos' => count($timbradosList)]
        );

        $pdo->commit();

        $primerDetalleId = !empty($timbradosList) ? $timbradosList[0]['detalle_id'] : null;
        $primerUuid = !empty($timbradosList) ? $timbradosList[0]['folio_fiscal'] : null;

        sendJsonResponse(true, "Timbrado fiscal completado con éxito por ProFact. Se certificaron " . count($timbradosList) . " recibos con CFDI 4.0 y se emitieron los eventos ReciboTimbrado.", [
            'nomina_id'          => $nomina_id,
            'periodo'            => $nomina['periodo'],
            'estatus'            => 'TIMBRADA',
            'evento_emitido'     => 'ReciboTimbrado',
            'recibos_timbrados'  => count($timbradosList),
            'detalles_timbrados' => $timbradosList,
            'primer_detalle_id'  => $primerDetalleId,
            'primer_uuid'        => $primerUuid,
            'xml_url'            => "api/timbrado.php?detalle_id={$primerDetalleId}&format=xml"
        ]);

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        sendJsonResponse(false, 'Error en el proceso de timbrado fiscal: ' . $e->getMessage(), null, 500);
    }
}

sendJsonResponse(false, 'Método HTTP no soportado.', null, 405);
