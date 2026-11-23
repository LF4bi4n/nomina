<?php
// ========================================================
// SISTEMA DE NÓMINAS Y GESTIÓN DE PERSONAL
// Archivo: api/monedas.php
// Servicio de Moneda Virtual Interna ("NóminaCoins" 🪙 NC)
// Billetera, Tienda de Servicios, Canjes y Gamificación
// ========================================================

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../api/notificaciones.php';

$pdo = getDBConnection();
$currentUser = requireAuth();
$userId = (int)$currentUser['id'];
$method = $_SERVER['REQUEST_METHOD'];

/**
 * Obtiene o inicializa la billetera del usuario
 */
function getOrCreateBilletera(PDO $pdo, int $userId): array {
    $stmt = $pdo->prepare("SELECT * FROM billetera_monedas WHERE usuario_id = ?");
    $stmt->execute([$userId]);
    $billetera = $stmt->fetch();

    if (!$billetera) {
        $pdo->prepare("
            INSERT INTO billetera_monedas (usuario_id, saldo, total_ganado, total_gastado) 
            VALUES (?, 250, 250, 0)
        ")->execute([$userId]);

        $stmt->execute([$userId]);
        $billetera = $stmt->fetch();
    }

    return $billetera;
}

// --------------------------------------------------------
// GET: Consultar saldo, catálogo y transacciones recientes
// --------------------------------------------------------
if ($method === 'GET') {
    $billetera = getOrCreateBilletera($pdo, $userId);

    // Obtener catálogo de recompensas según rol
    $rol = $currentUser['rol'] ?? 'EMPLEADO';
    $filtroCat = ($rol === 'EMPLEADO') ? "'COLABORADOR'" : "'EMPRESA', 'COLABORADOR'";
    $stmtCat = $pdo->query("SELECT * FROM catalogo_recompensas WHERE activo = 1 AND categoria IN ($filtroCat) ORDER BY precio_monedas ASC");
    $catalogo = $stmtCat->fetchAll();

    // Obtener transacciones recientes
    $stmtTx = $pdo->prepare("
        SELECT * FROM transacciones_monedas 
        WHERE usuario_id = ? 
        ORDER BY id DESC LIMIT 15
    ");
    $stmtTx->execute([$userId]);
    $transacciones = $stmtTx->fetchAll();

    // Obtener canjes realizados
    $stmtCanjes = $pdo->prepare("
        SELECT c.*, r.titulo, r.descripcion, r.icono, r.color 
        FROM canjes_realizados c
        INNER JOIN catalogo_recompensas r ON c.recompensa_id = r.id
        WHERE c.usuario_id = ? 
        ORDER BY c.id DESC LIMIT 10
    ");
    $stmtCanjes->execute([$userId]);
    $canjes = $stmtCanjes->fetchAll();

    sendJsonResponse(true, 'Datos de billetera NóminaCoins recuperados.', [
        'saldo'          => (int)$billetera['saldo'],
        'total_ganado'   => (int)$billetera['total_ganado'],
        'total_gastado'  => (int)$billetera['total_gastado'],
        'moneda_nombre'  => 'NóminaCoins',
        'moneda_simbolo' => '🪙 NC',
        'catalogo'       => $catalogo,
        'transacciones'  => $transacciones,
        'canjes'         => $canjes
    ]);
}

// --------------------------------------------------------
// POST: Operaciones de billetera (Recarga demo y Canje)
// --------------------------------------------------------
if ($method === 'POST') {
    $input = getJsonInput();
    $action = $input['action'] ?? '';

    // A) RECARGAR MONEDAS EN MODO DEMO
    if ($action === 'recargar') {
        $monto = (int)($input['monto'] ?? 100);
        if ($monto <= 0) $monto = 100;

        // Verificar si el plan es ORO para bonificación VIP de +20%
        $stmtPlan = $pdo->query("SELECT plan_actual FROM configuracion_sistema LIMIT 1");
        $planActual = $stmtPlan->fetchColumn() ?: 'ORO';
        $bonoOro = ($planActual === 'ORO') ? (int)round($monto * 0.20) : 0;
        $montoTotal = $monto + $bonoOro;

        $pdo->beginTransaction();
        try {
            $billetera = getOrCreateBilletera($pdo, $userId);
            $saldoAnterior = (int)$billetera['saldo'];
            $saldoPosterior = $saldoAnterior + $montoTotal;

            $upd = $pdo->prepare("
                UPDATE billetera_monedas 
                SET saldo = saldo + ?, total_ganado = total_ganado + ? 
                WHERE usuario_id = ?
            ");
            $upd->execute([$montoTotal, $montoTotal, $userId]);

            $concepto = "Recarga demo de {$monto} NC" . ($bonoOro > 0 ? " (+{$bonoOro} NC Bono VIP Oro)" : "");

            $insTx = $pdo->prepare("
                INSERT INTO transacciones_monedas (usuario_id, tipo, monto, concepto, categoria, saldo_anterior, saldo_posterior)
                VALUES (?, 'INGRESO', ?, ?, 'RECARGA', ?, ?)
            ");
            $insTx->execute([$userId, $montoTotal, $concepto, $saldoAnterior, $saldoPosterior]);

            $pdo->commit();

            sendJsonResponse(true, "¡Has recargado {$montoTotal} NóminaCoins exitosamente!", [
                'saldo'     => $saldoPosterior,
                'monto'     => $montoTotal,
                'bono_oro'  => $bonoOro
            ]);
        } catch (Exception $e) {
            $pdo->rollBack();
            sendJsonResponse(false, 'Error al procesar recarga: ' . $e->getMessage(), null, 500);
        }
    }

    // B) CANJEAR / COMPRAR SERVICIO CON MONEDAS
    if ($action === 'canjear') {
        $recompensaId = (int)($input['recompensa_id'] ?? 0);
        if ($recompensaId <= 0) {
            sendJsonResponse(false, 'Debes especificar una recompensa válida.', null, 400);
        }

        $stmtRec = $pdo->prepare("SELECT * FROM catalogo_recompensas WHERE id = ? AND activo = 1");
        $stmtRec->execute([$recompensaId]);
        $recompensa = $stmtRec->fetch();

        if (!$recompensa) {
            sendJsonResponse(false, 'Servicio o beneficio no disponible en el catálogo.', null, 404);
        }

        $billetera = getOrCreateBilletera($pdo, $userId);
        $precio = (int)$recompensa['precio_monedas'];

        if ((int)$billetera['saldo'] < $precio) {
            sendJsonResponse(false, "Saldo insuficiente en tu billetera. Necesitas {$precio} NC y tienes {$billetera['saldo']} NC. ¡Usa el botón de recarga demo para obtener más!", [
                'saldo_actual'     => (int)$billetera['saldo'],
                'precio_requerido' => $precio
            ], 400);
        }

        $pdo->beginTransaction();
        try {
            $saldoAnterior = (int)$billetera['saldo'];
            $saldoPosterior = $saldoAnterior - $precio;

            // Descontar saldo
            $upd = $pdo->prepare("
                UPDATE billetera_monedas 
                SET saldo = saldo - ?, total_gastado = total_gastado + ? 
                WHERE usuario_id = ?
            ");
            $upd->execute([$precio, $precio, $userId]);

            // Registrar transacción
            $insTx = $pdo->prepare("
                INSERT INTO transacciones_monedas (usuario_id, tipo, monto, concepto, categoria, saldo_anterior, saldo_posterior)
                VALUES (?, 'EGRESO', ?, ?, 'CANJE', ?, ?)
            ");
            $insTx->execute([$userId, $precio, "Canje: {$recompensa['titulo']}", $saldoAnterior, $saldoPosterior]);

            // Generar código de canje único
            $codigoCanje = 'NC-' . strtoupper(substr(md5(uniqid((string)rand(), true)), 0, 8));

            $insCanje = $pdo->prepare("
                INSERT INTO canjes_realizados (usuario_id, recompensa_id, codigo_canje, precio_pagado, detalles, estatus)
                VALUES (?, ?, ?, ?, ?, 'APLICADO')
            ");
            $insCanje->execute([
                $userId,
                $recompensaId,
                $codigoCanje,
                $precio,
                "Canje aprobado por el sistema. Código de verificación: {$codigoCanje}"
            ]);

            // Registrar en auditoría
            logAuditAction(
                'CANJE_NOMINA_COINS',
                'catalogo_recompensas',
                $recompensaId,
                "Usuario {$currentUser['email']} canjeó '{$recompensa['titulo']}' por {$precio} NóminaCoins (Ref: {$codigoCanje}).",
                ['saldo_anterior' => $saldoAnterior],
                ['saldo_posterior' => $saldoPosterior, 'codigo' => $codigoCanje]
            );

            // Crear notificación para el usuario
            if (function_exists('createNotification')) {
                createNotification(
                    $userId,
                    $currentUser['empleado_id'] ? (int)$currentUser['empleado_id'] : null,
                    'CanjeMonedasExitoso',
                    "¡Canje exitoso de {$recompensa['titulo']}!",
                    "Tu beneficio fue aplicado y registrado con código {$codigoCanje}. Se descontaron {$precio} NC de tu billetera.",
                    'PORTAL'
                );
            }

            $pdo->commit();

            sendJsonResponse(true, "¡Has canjeado '{$recompensa['titulo']}' exitosamente!", [
                'saldo'        => $saldoPosterior,
                'codigo_canje' => $codigoCanje,
                'recompensa'   => $recompensa
            ]);
        } catch (Exception $e) {
            $pdo->rollBack();
            sendJsonResponse(false, 'Error al procesar canje: ' . $e->getMessage(), null, 500);
        }
    }

    sendJsonResponse(false, 'Acción no reconocida.', null, 400);
}

sendJsonResponse(false, 'Método HTTP no soportado.', null, 405);
