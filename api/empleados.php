<?php
// ========================================================
// SISTEMA DE NÓMINAS Y GESTIÓN DE PERSONAL
// Archivo: api/empleados.php
// Servicio 1: HR Core y Expediente Digital
// ========================================================

require_once __DIR__ . '/../config/database.php';

$pdo = getDBConnection();
$currentUser = requireAuth();
$method = $_SERVER['REQUEST_METHOD'];

// Diccionario de bancos por CLABE
function getBancoPorClabe(string $clabe): string {
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
// GET: Obtener lista, detalle o constancia laboral
// --------------------------------------------------------
if ($method === 'GET') {
    $id     = isset($_GET['id']) ? (int)$_GET['id'] : null;
    $action = isset($_GET['action']) ? trim($_GET['action']) : '';

    // Si el usuario es rol EMPLEADO, forzar consulta únicamente a su propio expediente
    if ($currentUser['rol'] === 'EMPLEADO') {
        $id = $currentUser['empleado_id'];
        if (!$id) {
            sendJsonResponse(false, 'No tienes un expediente de empleado vinculado.', null, 404);
        }
    }

    // Sub-acción: Generar Constancia Laboral Oficial Membretada
    if ($action === 'constancia' && $id) {
        $stmt = $pdo->prepare("
            SELECT e.*, u.email
            FROM empleados e
            LEFT JOIN usuarios u ON u.empleado_id = e.id
            WHERE e.id = ?
        ");
        $stmt->execute([$id]);
        $emp = $stmt->fetch();

        if (!$emp) {
            sendJsonResponse(false, 'Empleado no encontrado.', null, 404);
        }

        $fechaIngresoDt = new DateTime($emp['fecha_ingreso']);
        $hoy = new DateTime();
        $diff = $fechaIngresoDt->diff($hoy);
        $antiguedadTexto = "{$diff->y} años y {$diff->m} meses";
        $salarioMensual = round((float)$emp['salario_diario'] * 30, 2);
        $folioConstancia = "CONST-NOM-" . date('Y') . "-" . str_pad($emp['id'], 5, '0', STR_PAD_LEFT);
        $selloDigital = hash('sha256', $folioConstancia . $emp['curp'] . date('Ymd'));

        $constancia = [
            'empresa' => [
                'razon_social'  => 'SISTEMA DE NÓMINAS Y GESTIÓN DE PERSONAL S.A. DE C.V.',
                'rfc'           => 'NOM210315AB1',
                'registro_patronal' => 'Y5623841103',
                'direccion'     => 'Av. Paseo de la Reforma 405, Piso 14, Cuauhtémoc, C.P. 06600, CDMX',
                'representante' => 'Lic. Ana Sofía Garza Valdés',
                'cargo_rep'     => 'Gerente de Recursos Humanos'
            ],
            'empleado' => [
                'id'            => $emp['id'],
                'nombre'        => $emp['nombre'],
                'rfc'           => $emp['rfc'],
                'curp'          => $emp['curp'],
                'puesto'        => $emp['puesto'],
                'departamento'  => $emp['departamento'],
                'tipo_contrato' => $emp['tipo_contrato'] ?? 'TIEMPO INDETERMINADO',
                'fecha_ingreso' => $emp['fecha_ingreso'],
                'antiguedad'    => $antiguedadTexto,
                'salario_diario'=> (float)$emp['salario_diario'],
                'salario_mensual'=> $salarioMensual,
                'cuenta_clabe'  => $emp['cuenta_clabe'],
                'banco'         => $emp['banco'] ?? getBancoPorClabe($emp['cuenta_clabe']),
                'estatus'       => $emp['estatus']
            ],
            'certificacion' => [
                'folio'         => $folioConstancia,
                'fecha_emision' => date('d/m/Y'),
                'sello_digital' => $selloDigital,
                'leyenda'       => 'Se expide la presente constancia a petición del interesado para los fines legales, administrativos o crediticios que a su derecho convengan.'
            ]
        ];

        logAuditAction('DESCARGA_CONSTANCIA', 'empleados', $emp['id'], "Generación y descarga de constancia laboral oficial folio {$folioConstancia}");

        sendJsonResponse(true, 'Constancia laboral oficial generada.', $constancia);
    }

    // Consulta de detalle de un empleado
    if ($id) {
        $stmt = $pdo->prepare("
            SELECT e.*, u.email, u.rol
            FROM empleados e
            LEFT JOIN usuarios u ON u.empleado_id = e.id
            WHERE e.id = ?
        ");
        $stmt->execute([$id]);
        $empleado = $stmt->fetch();

        if (!$empleado) {
            sendJsonResponse(false, 'Empleado no encontrado.', null, 404);
        }

        // Calcular días de vacaciones tomados
        $stmtVac = $pdo->prepare("
            SELECT COUNT(*) FROM incidencias 
            WHERE empleado_id = ? AND tipo = 'VACACIONES' AND estatus = 'APROBADO'
        ");
        $stmtVac->execute([$id]);
        $diasTomados = (int)$stmtVac->fetchColumn();

        $diasDerecho = calcularDiasVacacionesPorLey($empleado['fecha_ingreso']);
        $vacacionesDisponibles = max(0, $diasDerecho - $diasTomados);

        // Historial de incidencias recientes
        $stmtInc = $pdo->prepare("
            SELECT id, fecha, tipo, modalidad, horas_extra, tipo_incapacidad, folio_medico, estatus, observaciones
            FROM incidencias
            WHERE empleado_id = ?
            ORDER BY fecha DESC
            LIMIT 15
        ");
        $stmtInc->execute([$id]);
        $incidencias = $stmtInc->fetchAll();

        // Historial de recibos
        $stmtNom = $pdo->prepare("
            SELECT dn.*, n.periodo, n.fecha_inicio, n.fecha_fin, n.estatus as estatus_nomina
            FROM detalle_nomina dn
            INNER JOIN nominas n ON dn.nomina_id = n.id
            WHERE dn.empleado_id = ?
            ORDER BY n.fecha_inicio DESC
            LIMIT 10
        ");
        $stmtNom->execute([$id]);
        $recibos = $stmtNom->fetchAll();

        $empleado['salario_diario'] = (float)$empleado['salario_diario'];
        $empleado['salario_diario_integrado'] = (float)($empleado['salario_diario_integrado'] ?? round($empleado['salario_diario'] * 1.0493, 2));
        $empleado['salario_quincenal_estimado'] = round($empleado['salario_diario'] * 15, 2);
        $empleado['salario_mensual_estimado'] = round($empleado['salario_diario'] * 30, 2);
        $empleado['dias_vacaciones_derecho'] = $diasDerecho;
        $empleado['dias_vacaciones_tomados'] = $diasTomados;
        $empleado['dias_vacaciones_disponibles'] = $vacacionesDisponibles;
        $empleado['banco'] = $empleado['banco'] ?? getBancoPorClabe($empleado['cuenta_clabe']);
        $empleado['incidencias_recientes'] = $incidencias;
        $empleado['recibos_recientes'] = $recibos;

        sendJsonResponse(true, 'Expediente digital recuperado con éxito.', $empleado);
    } else {
        // Catálogo completo (solo para ADMIN, RH o AUDITOR)
        if ($currentUser['rol'] === 'EMPLEADO') {
            sendJsonResponse(false, 'Permisos insuficientes para listar todo el catálogo.', null, 403);
        }

        $stmt = $pdo->query("
            SELECT e.*, u.email, u.rol,
                   (SELECT COUNT(*) FROM incidencias i WHERE i.empleado_id = e.id) as total_incidencias
            FROM empleados e
            LEFT JOIN usuarios u ON u.empleado_id = e.id
            ORDER BY e.id ASC
        ");
        $empleados = $stmt->fetchAll();

        foreach ($empleados as &$emp) {
            $emp['salario_diario'] = (float)$emp['salario_diario'];
            $emp['salario_diario_integrado'] = (float)($emp['salario_diario_integrado'] ?? round($emp['salario_diario'] * 1.0493, 2));
            $emp['salario_quincenal_estimado'] = round($emp['salario_diario'] * 15, 2);
            $emp['banco'] = $emp['banco'] ?? getBancoPorClabe($emp['cuenta_clabe']);
        }

        sendJsonResponse(true, 'Catálogo de empleados recuperado.', [
            'total'     => count($empleados),
            'empleados' => $empleados
        ]);
    }
}

// --------------------------------------------------------
// POST: Registrar empleado, modificar estatus o actualizar salario
// --------------------------------------------------------
if ($method === 'POST') {
    requireRole(['ADMIN', 'RH']);

    $input = getJsonInput();
    $action = $input['action'] ?? 'crear';

    // 1. Modificar estatus (Baja / Reingreso)
    if ($action === 'cambiar_estatus') {
        $empId = (int)($input['id'] ?? 0);
        $nuevoEstatus = strtoupper(trim($input['estatus'] ?? ''));
        $motivo = trim($input['motivo'] ?? 'Modificación de estatus laboral');

        if ($empId <= 0 || !in_array($nuevoEstatus, ['ACTIVO', 'INACTIVO'], true)) {
            sendJsonResponse(false, 'Datos inválidos para cambio de estatus.', null, 400);
        }

        $stmtOld = $pdo->prepare("SELECT id, nombre, estatus FROM empleados WHERE id = ?");
        $stmtOld->execute([$empId]);
        $empOld = $stmtOld->fetch();

        if (!$empOld) {
            sendJsonResponse(false, 'Empleado no encontrado.', null, 404);
        }

        $stmtUpd = $pdo->prepare("UPDATE empleados SET estatus = ? WHERE id = ?");
        $stmtUpd->execute([$nuevoEstatus, $empId]);

        logAuditAction(
            $nuevoEstatus === 'INACTIVO' ? 'BAJA_EMPLEADO' : 'REINGRESO_EMPLEADO',
            'empleados',
            $empId,
            "Cambio de estatus a {$nuevoEstatus}. Motivo: {$motivo}",
            ['estatus' => $empOld['estatus']],
            ['estatus' => $nuevoEstatus, 'motivo' => $motivo]
        );

        sendJsonResponse(true, "Estatus de {$empOld['nombre']} actualizado a {$nuevoEstatus}.");
    }

    // 2. Modificación de salario justificada
    if ($action === 'actualizar_salario') {
        $empId = (int)($input['id'] ?? 0);
        $nuevoSalario = (float)($input['salario_diario'] ?? 0);
        $motivo = trim($input['motivo'] ?? '');

        if ($empId <= 0 || $nuevoSalario <= 0) {
            sendJsonResponse(false, 'Debe indicar un salario diario válido mayor a cero.', null, 400);
        }
        if (empty($motivo)) {
            sendJsonResponse(false, 'Por requisitos de auditoría y cumplimiento laboral, el motivo del ajuste salarial es obligatorio.', null, 400);
        }

        $stmtOld = $pdo->prepare("SELECT id, nombre, salario_diario, salario_diario_integrado FROM empleados WHERE id = ?");
        $stmtOld->execute([$empId]);
        $empOld = $stmtOld->fetch();

        if (!$empOld) {
            sendJsonResponse(false, 'Empleado no encontrado.', null, 404);
        }

        $nuevoSDI = round($nuevoSalario * 1.0493, 2);

        $stmtUpd = $pdo->prepare("UPDATE empleados SET salario_diario = ?, salario_diario_integrado = ? WHERE id = ?");
        $stmtUpd->execute([$nuevoSalario, $nuevoSDI, $empId]);

        logAuditAction(
            'AJUSTE_SALARIO',
            'empleados',
            $empId,
            "Ajuste salarial para {$empOld['nombre']}: de \${$empOld['salario_diario']} a \${$nuevoSalario}. Motivo: {$motivo}",
            ['salario_diario' => (float)$empOld['salario_diario']],
            ['salario_diario' => $nuevoSalario, 'motivo' => $motivo]
        );

        sendJsonResponse(true, "Salario diario actualizado a \${$nuevoSalario} para {$empOld['nombre']}.");
    }

    // 3. Crear nuevo empleado
    $nombre         = trim($input['nombre'] ?? '');
    $rfc            = strtoupper(trim($input['rfc'] ?? ''));
    $curp           = strtoupper(trim($input['curp'] ?? ''));
    $puesto         = trim($input['puesto'] ?? '');
    $departamento   = trim($input['departamento'] ?? '');
    $tipo_contrato  = trim($input['tipo_contrato'] ?? 'INDETERMINADO');
    $salario_diario = (float)($input['salario_diario'] ?? 0);
    $cuenta_clabe   = trim($input['cuenta_clabe'] ?? '');
    $fecha_ingreso  = trim($input['fecha_ingreso'] ?? date('Y-m-d'));
    $email          = trim($input['email'] ?? '');
    $crear_usuario  = !empty($input['crear_usuario']) || !empty($email);

    if (empty($nombre)) {
        sendJsonResponse(false, 'El nombre completo del empleado es obligatorio.', null, 400);
    }
    if (strlen($rfc) < 12 || strlen($rfc) > 13) {
        sendJsonResponse(false, 'El RFC debe tener entre 12 y 13 caracteres válidos.', null, 400);
    }
    if (strlen($curp) !== 18) {
        sendJsonResponse(false, 'El CURP debe tener exactamente 18 caracteres.', null, 400);
    }
    if (empty($puesto) || empty($departamento)) {
        sendJsonResponse(false, 'El puesto y el departamento son obligatorios.', null, 400);
    }
    if ($salario_diario <= 0) {
        sendJsonResponse(false, 'El salario diario debe ser un valor mayor a cero.', null, 400);
    }
    if (strlen($cuenta_clabe) !== 18 || !ctype_digit($cuenta_clabe)) {
        sendJsonResponse(false, 'La cuenta CLABE debe contener exactamente 18 dígitos numéricos.', null, 400);
    }

    $banco = getBancoPorClabe($cuenta_clabe);
    $salario_integrado = round($salario_diario * 1.0493, 2);
    $vacacionesIniciales = calcularDiasVacacionesPorLey($fecha_ingreso);

    try {
        $checkStmt = $pdo->prepare("
            SELECT id, rfc, curp, cuenta_clabe 
            FROM empleados 
            WHERE rfc = ? OR curp = ? OR cuenta_clabe = ?
            LIMIT 1
        ");
        $checkStmt->execute([$rfc, $curp, $cuenta_clabe]);
        $existing = $checkStmt->fetch();

        if ($existing) {
            if ($existing['rfc'] === $rfc) {
                sendJsonResponse(false, 'Ya existe un empleado registrado con el RFC: ' . $rfc, null, 409);
            }
            if ($existing['curp'] === $curp) {
                sendJsonResponse(false, 'Ya existe un empleado registrado con el CURP: ' . $curp, null, 409);
            }
            if ($existing['cuenta_clabe'] === $cuenta_clabe) {
                sendJsonResponse(false, 'Ya existe un empleado registrado con la cuenta CLABE: ' . $cuenta_clabe, null, 409);
            }
        }

        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            INSERT INTO empleados (
                nombre, rfc, curp, puesto, departamento, tipo_contrato, 
                salario_diario, salario_diario_integrado, cuenta_clabe, banco, 
                fecha_ingreso, dias_vacaciones_disponibles, estatus
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'ACTIVO')
        ");
        $stmt->execute([
            $nombre,
            $rfc,
            $curp,
            $puesto,
            $departamento,
            $tipo_contrato,
            $salario_diario,
            $salario_integrado,
            $cuenta_clabe,
            $banco,
            $fecha_ingreso,
            $vacacionesIniciales
        ]);

        $nuevoEmpleadoId = (int)$pdo->lastInsertId();

        if ($crear_usuario && !empty($email)) {
            $checkEmail = $pdo->prepare("SELECT id FROM usuarios WHERE email = ?");
            $checkEmail->execute([$email]);
            if ($checkEmail->fetch()) {
                $pdo->rollBack();
                sendJsonResponse(false, 'El correo electrónico ya se encuentra registrado en el sistema.', null, 409);
            }

            $passwordPorDefecto = password_hash('123456', PASSWORD_BCRYPT);
            $stmtUser = $pdo->prepare("
                INSERT INTO usuarios (email, password, rol, empleado_id)
                VALUES (?, ?, 'EMPLEADO', ?)
            ");
            $stmtUser->execute([$email, $passwordPorDefecto, $nuevoEmpleadoId]);
        }

        logAuditAction(
            'ALTA_EMPLEADO',
            'empleados',
            $nuevoEmpleadoId,
            "Alta de nuevo colaborador: {$nombre} ({$puesto} - {$departamento}) con salario diario \${$salario_diario}",
            null,
            ['nombre' => $nombre, 'rfc' => $rfc, 'salario_diario' => $salario_diario, 'puesto' => $puesto]
        );

        $pdo->commit();

        sendJsonResponse(true, 'Empleado registrado exitosamente en el catálogo de personal.', [
            'id'             => $nuevoEmpleadoId,
            'nombre'         => $nombre,
            'rfc'            => $rfc,
            'salario_diario' => $salario_diario,
            'cuenta_clabe'   => $cuenta_clabe,
            'banco'          => $banco
        ], 201);

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        sendJsonResponse(false, 'Error al registrar empleado: ' . $e->getMessage(), null, 500);
    }
}

sendJsonResponse(false, 'Método HTTP no soportado.', null, 405);
