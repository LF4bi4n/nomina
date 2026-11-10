<?php
// ========================================================
// SISTEMA DE NÓMINAS Y GESTIÓN DE PERSONAL
// Archivo: api/profact.php
// Endpoint de Configuración y Diagnóstico del PAC ProFact
// ========================================================

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../services/ProfactService.php';

$pdo = getDBConnection();
$currentUser = requireAuth();
$method = $_SERVER['REQUEST_METHOD'];

// --------------------------------------------------------
// GET: Consultar configuración actual y estatus PAC
// --------------------------------------------------------
if ($method === 'GET') {
    requireRole(['ADMIN', 'RH', 'AUDITOR']);

    $action = $_GET['action'] ?? 'config';

    if ($action === 'test') {
        $diag = ProfactService::testConnection();
        sendJsonResponse($diag['success'], $diag['mensaje'], $diag);
    }

    $config = ProfactService::getConfig();
    // Ocultar parcialmente el usuario integrador por seguridad si no es demo
    $maskedToken = $config['usuario_integrador'];
    if (strlen($maskedToken) > 8 && $maskedToken !== 'DEMO_PROFACT_INTEGRADOR') {
        $maskedToken = substr($maskedToken, 0, 4) . '••••••••' . substr($maskedToken, -4);
    }

    sendJsonResponse(true, 'Configuración del PAC ProFact obtenida.', [
        'config' => $config,
        'token_enmascarado' => $maskedToken
    ]);
}

// --------------------------------------------------------
// POST: Guardar configuración o Probar Conexión
// --------------------------------------------------------
if ($method === 'POST') {
    requireRole(['ADMIN', 'RH']);
    $input = getJsonInput();
    $action = $input['action'] ?? 'guardar_config';

    if ($action === 'test_conexion') {
        $diag = ProfactService::testConnection();
        logAuditAction('PROFACT_PING', 'configuracion_pac', null, "Prueba de conectividad Web Service ProFact: " . $diag['mensaje']);
        sendJsonResponse($diag['success'], $diag['mensaje'], $diag);
    }

    if ($action === 'guardar_config') {
        $ambiente = strtoupper(trim($input['ambiente'] ?? 'SANDBOX'));
        if (!in_array($ambiente, ['SANDBOX', 'PRODUCCION', 'SIMULADO'])) {
            sendJsonResponse(false, 'Ambiente no válido. Debe ser SANDBOX, PRODUCCION o SIMULADO.', null, 400);
        }

        $usuarioIntegrador = trim($input['usuario_integrador'] ?? '');
        $rfcEmisor = strtoupper(trim($input['rfc_emisor'] ?? 'NOM210315AB1'));
        $razonSocial = trim($input['razon_social_emisor'] ?? 'SISTEMA DE NOMINAS Y PERSONAL S.A. DE C.V.');
        $regimen = trim($input['regimen_fiscal_emisor'] ?? '601');
        $lugarExp = trim($input['lugar_expedicion'] ?? '06600');

        $success = ProfactService::saveConfig([
            'ambiente'              => $ambiente,
            'usuario_integrador'    => $usuarioIntegrador,
            'rfc_emisor'            => $rfcEmisor,
            'razon_social_emisor'   => $razonSocial,
            'regimen_fiscal_emisor' => $regimen,
            'lugar_expedicion'      => $lugarExp
        ]);

        if ($success) {
            logAuditAction('PROFACT_CONFIG_UPDATE', 'configuracion_pac', null, "Configuración PAC ProFact actualizada. Ambiente: $ambiente, RFC: $rfcEmisor");
            sendJsonResponse(true, 'Configuración de ProFact guardada exitosamente.', ProfactService::getConfig());
        } else {
            sendJsonResponse(false, 'No se pudo actualizar la configuración.', null, 500);
        }
    }

    sendJsonResponse(false, 'Acción no soportada.', null, 400);
}

sendJsonResponse(false, 'Método HTTP no soportado.', null, 405);

