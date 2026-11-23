<?php
// ========================================================
// SISTEMA DE NÓMINAS Y GESTIÓN DE PERSONAL
// Archivo: api/membresias.php
// Servicio de Membresías y Planes de Servicio: Bronce, Plata, Oro
// ========================================================

require_once __DIR__ . '/../config/database.php';

$pdo = getDBConnection();
$currentUser = requireAuth();
$method = $_SERVER['REQUEST_METHOD'];

/**
 * Matriz comparativa de especificaciones y límites de planes
 */
function getMatrizPlanes(): array {
    return [
        'BRONCE' => [
            'nombre'         => 'Plan Bronce (Básico)',
            'nivel'          => 1,
            'color'          => '#cd7f32',
            'badge_class'    => 'bg-warning text-dark border',
            'icono'          => 'bi-award',
            'max_empleados'  => 5,
            'precio_mensual' => 0,
            'permite_timbrado_ilimitado' => false,
            'costo_timbre_coins'         => 10,
            'permite_dispersion_spei'    => false,
            'costo_layout_coins'         => 20,
            'permite_facturacion'        => false,
            'permite_graphql_broker'     => false,
            'permite_auditoria_completa' => false,
            'soporte_prioritario'        => false,
            'descripcion'    => 'Ideal para micronegocios o emprendedores con hasta 5 colaboradores. Funciones básicas de pre-nómina y asistencias.',
            'ventajas'       => [
                'Hasta 5 empleados activos en catálogo',
                'Cálculo fiscal automatizado (ISR e IMSS)',
                'Checador digital básico de asistencias',
                'Recibos pre-nómina visuales y descargables'
            ],
            'restricciones'  => [
                'Máximo 5 colaboradores en nómina',
                'Timbrado CFDI requiere 10 NóminaCoins por recibo',
                'Descarga de layouts bancarios SPEI bloqueada (requiere 20 Coins)',
                'Módulo de facturación comercial CFDI desactivado',
                'Consola GraphQL y broker de eventos desactivados'
            ]
        ],
        'PLATA' => [
            'nombre'         => 'Plan Plata (Pyme Avanzada)',
            'nivel'          => 2,
            'color'          => '#94a3b8',
            'badge_class'    => 'bg-secondary text-white',
            'icono'          => 'bi-shield-shaded',
            'max_empleados'  => 15,
            'precio_mensual' => 499,
            'permite_timbrado_ilimitado' => true,
            'timbres_incluidos_mes'      => 30,
            'costo_timbre_coins'         => 0,
            'permite_dispersion_spei'    => true,
            'costo_layout_coins'         => 0,
            'permite_facturacion'        => true,
            'max_facturas_mes'           => 10,
            'permite_graphql_broker'     => false,
            'permite_auditoria_completa' => true,
            'soporte_prioritario'        => false,
            'descripcion'    => 'Diseñado para empresas en crecimiento de hasta 15 colaboradores. Timbrado ProFact y dispersión SPEI incluidos.',
            'ventajas'       => [
                'Hasta 15 empleados activos en plantilla',
                'Timbrado fiscal CFDI 4.0 ProFact incluido (hasta 30 timbres/mes)',
                'Dispersión bancaria SPEI y generación de layouts TXT',
                'Facturación electrónica comercial (hasta 10 facturas/mes)',
                'Checador GPS y clima en tiempo real OpenWeatherMap',
                'Historial completo de auditoría (Audit Trail)'
            ],
            'restricciones'  => [
                'Límite de 15 colaboradores en nómina',
                'Límite de 10 facturas comerciales mensuales',
                'Consola GraphQL y broker distribuido desactivados (exclusivo Oro)',
                'Sin bonificación VIP en recargas de NóminaCoins'
            ]
        ],
        'ORO' => [
            'nombre'         => 'Plan Oro (VIP Empresarial)',
            'nivel'          => 3,
            'color'          => '#f59e0b',
            'badge_class'    => 'bg-warning text-dark fw-bold shadow-sm',
            'icono'          => 'bi-trophy-fill',
            'max_empleados'  => 9999,
            'precio_mensual' => 1299,
            'permite_timbrado_ilimitado' => true,
            'timbres_incluidos_mes'      => 99999,
            'costo_timbre_coins'         => 0,
            'permite_dispersion_spei'    => true,
            'costo_layout_coins'         => 0,
            'permite_facturacion'        => true,
            'max_facturas_mes'           => 99999,
            'permite_graphql_broker'     => true,
            'permite_auditoria_completa' => true,
            'soporte_prioritario'        => true,
            'descripcion'    => 'Solución corporativa completa. Todo ilimitado, acceso a GraphQL, broker de mensajería y máxima prioridad.',
            'ventajas'       => [
                'Colaboradores ILIMITADOS en plantilla y nómina',
                'Timbrado fiscal CFDI 4.0 ProFact ILIMITADO',
                'Facturación comercial CFDI 4.0 ILIMITADA',
                'Dispersión bancaria masiva SPEI multbanco sin costo',
                'Consola de consultas GraphQL y Broker de eventos asíncronos',
                'Auditoría forense inmutable de todas las transacciones',
                'Bonificación VIP de +20% en recargas de NóminaCoins',
                'Soporte técnico 24/7 y SLA garantizado'
            ],
            'restricciones'  => []
        ]
    ];
}

/**
 * Obtiene el plan actual desde la base de datos
 */
function getPlanActual(PDO $pdo): string {
    try {
        $stmt = $pdo->query("SELECT plan_actual FROM configuracion_sistema LIMIT 1");
        $plan = $stmt->fetchColumn();
        return $plan ?: 'ORO';
    } catch (Exception $e) {
        return 'ORO';
    }
}

// --------------------------------------------------------
// GET: Consultar plan activo, matriz de planes y límites
// --------------------------------------------------------
if ($method === 'GET') {
    $planActual = getPlanActual($pdo);
    $matriz = getMatrizPlanes();

    // Contar total de empleados actuales para verificar límites
    $stmtCount = $pdo->query("SELECT COUNT(*) FROM empleados WHERE estatus = 'ACTIVO'");
    $empleadosActivos = (int)$stmtCount->fetchColumn();

    $planInfo = $matriz[$planActual] ?? $matriz['ORO'];

    sendJsonResponse(true, 'Información de membresías y planes recuperada.', [
        'plan_actual'        => $planActual,
        'plan_info'          => $planInfo,
        'empleados_activos'  => $empleadosActivos,
        'limite_alcanzado'   => ($empleadosActivos >= $planInfo['max_empleados']),
        'matriz_planes'      => $matriz
    ]);
}

// --------------------------------------------------------
// POST: Cambiar plan de servicio (Demo Switcher)
// --------------------------------------------------------
if ($method === 'POST') {
    $input = getJsonInput();
    $nuevoPlan = strtoupper(trim($input['plan'] ?? ''));

    if (!in_array($nuevoPlan, ['BRONCE', 'PLATA', 'ORO'])) {
        sendJsonResponse(false, 'Plan no válido. Debe ser BRONCE, PLATA u ORO.', null, 400);
    }

    try {
        $stmt = $pdo->prepare("UPDATE configuracion_sistema SET plan_actual = ? WHERE id = 1");
        $stmt->execute([$nuevoPlan]);

        // Registrar en Auditoría
        logAuditAction(
            'CAMBIO_PLAN_MEMBRESIA',
            'configuracion_sistema',
            1,
            "Plan de servicio actualizado a {$nuevoPlan} mediante selector interactivo demo.",
            ['plan_anterior' => getPlanActual($pdo)],
            ['plan_nuevo' => $nuevoPlan]
        );

        $matriz = getMatrizPlanes();
        sendJsonResponse(true, "¡Plan de membresía actualizado exitosamente a {$nuevoPlan}!", [
            'plan_actual' => $nuevoPlan,
            'plan_info'   => $matriz[$nuevoPlan]
        ]);
    } catch (Exception $e) {
        sendJsonResponse(false, 'Error al actualizar plan: ' . $e->getMessage(), null, 500);
    }
}

sendJsonResponse(false, 'Método HTTP no soportado.', null, 405);
