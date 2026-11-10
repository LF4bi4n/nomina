<?php
// ========================================================
// SISTEMA DE NÓMINAS Y GESTIÓN DE PERSONAL
// Archivo: index.php
// Interfaz Moderna, Simple y Elegante (SaaS Style)
// ========================================================

require_once __DIR__ . '/config/database.php';
startSessionIfNeeded();

$isLoggedIn = !empty($_SESSION['usuario_id']);
$userRole   = $_SESSION['rol'] ?? null;
$userName   = $_SESSION['nombre'] ?? '';
$userEmail  = $_SESSION['email'] ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Nóminas y Gestión de Personal</title>
    <!-- Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="css/styles.css?v=<?php echo time(); ?>" rel="stylesheet">
</head>
<body>

    <!-- Toast Notifications Container -->
    <div class="toast-container" id="toastContainer"></div>

    <!-- =====================================================
         PANTALLA DE LOGIN
         ===================================================== -->
    <div id="loginSection" class="<?php echo $isLoggedIn ? 'd-none' : ''; ?> min-vh-100 d-flex align-items-center justify-content-center py-5" style="background: radial-gradient(circle at top right, #1e1b4b 0%, #0f172a 100%);">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-6 col-lg-5">
                    <div class="card card-modern shadow-lg border-0 overflow-hidden">
                        <div class="p-4 text-center text-white" style="background: linear-gradient(135deg, #4f46e5 0%, #0ea5e9 100%);">
                            <div class="navbar-brand-logo mx-auto mb-3" style="width: 48px; height: 48px; font-size: 1.5rem;">
                                <i class="bi bi-wallet2"></i>
                            </div>
                            <h4 class="fw-bold mb-1">Sistema de Nóminas</h4>
                            <p class="text-white-50 small mb-0">Gestión de Personal • CFDI 4.0 • GraphQL API</p>
                        </div>

                        <div class="card-body p-4 p-md-5">
                            <form id="loginForm">
                                <div class="mb-3">
                                    <label for="loginEmail" class="form-label small fw-semibold text-muted">Correo Electrónico</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-envelope"></i></span>
                                        <input type="email" class="form-control border-start-0 ps-0" id="loginEmail" placeholder="usuario@nominas.com" required>
                                    </div>
                                </div>

                                <div class="mb-4">
                                    <label for="loginPassword" class="form-label small fw-semibold text-muted">Contraseña</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-shield-lock"></i></span>
                                        <input type="password" class="form-control border-start-0 ps-0 border-end-0" id="loginPassword" placeholder="••••••••" required>
                                        <button class="btn btn-outline-secondary border-start-0 bg-transparent text-muted" type="button" id="togglePasswordBtn">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold d-flex align-items-center justify-content-center gap-2" id="btnLoginSubmit">
                                    <i class="bi bi-box-arrow-in-right"></i>
                                    <span>Iniciar Sesión</span>
                                </button>
                            </form>

                            <!-- Acceso Rápido Demo -->
                            <div class="mt-4 pt-3 border-top">
                                <p class="text-muted small fw-semibold text-center mb-2">Accesos Demo Rápidos (Contraseña: 123456):</p>
                                <div class="d-flex flex-wrap gap-2 justify-content-center">
                                    <button class="btn btn-sm btn-outline-primary demo-user-btn" data-email="admin@nominas.com">
                                        <i class="bi bi-shield-check"></i> Admin
                                    </button>
                                    <button class="btn btn-sm btn-outline-info demo-user-btn" data-email="rh@nominas.com">
                                        <i class="bi bi-people"></i> RH
                                    </button>
                                    <button class="btn btn-sm btn-outline-warning text-dark demo-user-btn" data-email="auditor@nominas.com">
                                        <i class="bi bi-search"></i> Auditor
                                    </button>
                                    <button class="btn btn-sm btn-outline-success demo-user-btn" data-email="empleado@nominas.com">
                                        <i class="bi bi-person-badge"></i> Empleado
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- =====================================================
         APLICACIÓN PRINCIPAL
         ===================================================== -->
    <div id="appSection" class="<?php echo $isLoggedIn ? '' : 'd-none'; ?> d-flex flex-column min-vh-100">

        <!-- Navbar Superior Minimalista -->
        <nav class="navbar navbar-dark navbar-custom sticky-top">
            <div class="container-fluid px-lg-4">
                <a class="navbar-brand d-flex align-items-center gap-2 fw-bold" href="#">
                    <div class="navbar-brand-logo">
                        <i class="bi bi-wallet2"></i>
                    </div>
                    <span>NóminaPay <span class="badge bg-primary text-uppercase" style="font-size: 0.65rem;" id="navBadgeRole">PRO</span></span>
                </a>

                <div class="d-flex align-items-center gap-3">
                    <!-- Botón ProFact PAC Estado / Config -->
                    <button class="btn btn-sm btn-outline-info d-flex align-items-center gap-1 border-opacity-50" data-bs-toggle="modal" data-bs-target="#modalProfactConfig" id="btnNavbarProfact" title="Configuración y pruebas del PAC ProFact">
                        <i class="bi bi-cloud-check-fill text-info"></i>
                        <span class="d-none d-md-inline small">PAC ProFact</span>
                        <span class="badge bg-info text-dark font-monospace" style="font-size: 0.65rem;" id="badgeNavbarPacAmbiente">SANDBOX</span>
                    </button>

                    <!-- Botón Mapa de Arquitectura -->
                    <button class="btn btn-sm btn-outline-light d-flex align-items-center gap-1 border-opacity-25" data-bs-toggle="modal" data-bs-target="#modalMapaServicios">
                        <i class="bi bi-diagram-3 text-warning"></i>
                        <span class="d-none d-md-inline small">Arquitectura &amp; Servicios</span>
                    </button>

                    <!-- Campana de Notificaciones -->
                    <div class="dropdown">
                        <button class="notif-bell-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false" id="btnNotifDropdown">
                            <i class="bi bi-bell"></i>
                            <span class="notif-badge d-none" id="notifBadgeCount">0</span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow border-0 p-0" style="width: 320px; max-height: 400px; overflow-y: auto;" id="notifDropdownList">
                            <li class="p-3 border-bottom d-flex justify-content-between align-items-center bg-light">
                                <span class="fw-bold small text-dark"><i class="bi bi-bell me-1"></i> Notificaciones</span>
                                <button class="btn btn-link btn-sm text-decoration-none p-0 small" id="btnMarcarTodasLeidas">Marcar leídas</button>
                            </li>
                            <li id="notifItemsContainer">
                                <div class="text-center py-4 text-muted small">Sin notificaciones.</div>
                            </li>
                        </ul>
                    </div>

                    <!-- Widget Clima Navbar (OpenWeatherMap) -->
                    <div class="d-flex align-items-center gap-1 px-2 py-1 rounded bg-white bg-opacity-10 text-white border border-light border-opacity-25" id="navWeatherPill" title="Clima en tiempo real (OpenWeatherMap)" style="font-size: 0.75rem; cursor: pointer;">
                        <img id="navWeatherIcon" src="https://openweathermap.org/img/wn/01d.png" style="width: 20px; height: 20px;" alt="Clima">
                        <span class="fw-bold" id="navWeatherTemp">--°C</span>
                        <span class="text-white-50 d-none d-lg-inline" id="navWeatherCity">CDMX</span>
                    </div>

                    <!-- Usuario y Salida -->
                    <div class="text-end d-none d-sm-block text-white">
                        <div class="fw-semibold small lh-1" id="navUserName"><?php echo htmlspecialchars($userName); ?></div>
                        <span class="badge bg-light text-dark border mt-1" style="font-size: 0.65rem;" id="navUserRoleDesc"><?php echo htmlspecialchars($userRole ?? ''); ?></span>
                    </div>

                    <a href="logout.php" class="btn btn-outline-light btn-sm d-flex align-items-center gap-1 text-white-50" id="btnLogout" title="Cerrar Sesión">
                        <i class="bi bi-box-arrow-right"></i>
                        <span class="d-none d-md-inline small">Salir</span>
                    </a>
                </div>
            </div>
        </nav>

        <!-- Barra de Pestañas Unificada (Clean Segmented Bar) -->
        <div class="app-tabs-bar" id="appTabsBar">
            <div class="container-fluid px-lg-4">
                <div class="app-tabs-container" id="navTabsContainer">
                    <!-- Dinámico por app.js -->
                </div>
            </div>
        </div>

        <!-- Contenedor Principal de Vistas -->
        <main class="container-fluid px-lg-4 py-4 flex-grow-1">

            <!-- =================================================
                 VISTAS PARA ADMINISTRACIÓN, RH Y AUDITORÍA
                 ================================================= -->
            <div id="viewAdminRH" class="d-none">

                <!-- PANE 1: PERSONAL (HR CORE) -->
                <div class="admin-tab-pane" id="paneEmpleados">
                    <div class="page-header-simple">
                        <div>
                            <h4><i class="bi bi-people text-primary"></i> Personal &amp; Expedientes</h4>
                            <p>Directorio maestro de colaboradores, puestos, salarios base y cuentas bancarias.</p>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <input type="text" class="form-control form-control-sm" id="searchEmpleadoInput" placeholder="Buscar colaborador..." style="width: 200px;">
                            <button class="btn btn-primary btn-sm d-flex align-items-center gap-1" data-bs-toggle="modal" data-bs-target="#modalNuevoEmpleado">
                                <i class="bi bi-plus-lg"></i> <span>Nuevo Empleado</span>
                            </button>
                        </div>
                    </div>

                    <!-- Métricas Clave -->
                    <div class="row g-3 mb-4">
                        <div class="col-sm-6 col-lg-3">
                            <div class="stat-card d-flex align-items-center justify-content-between">
                                <div>
                                    <div class="stat-label">Plantilla Total</div>
                                    <div class="stat-value" id="kpiTotalEmpleados">0</div>
                                </div>
                                <div class="stat-icon primary"><i class="bi bi-people"></i></div>
                            </div>
                        </div>
                        <div class="col-sm-6 col-lg-3">
                            <div class="stat-card d-flex align-items-center justify-content-between">
                                <div>
                                    <div class="stat-label">Sueldo Diario Promedio</div>
                                    <div class="stat-value" id="kpiSalarioPromedio">$0.00</div>
                                </div>
                                <div class="stat-icon success"><i class="bi bi-cash-stack"></i></div>
                            </div>
                        </div>
                        <div class="col-sm-6 col-lg-3">
                            <div class="stat-card d-flex align-items-center justify-content-between">
                                <div>
                                    <div class="stat-label">Incidencias Registradas</div>
                                    <div class="stat-value" id="kpiIncidenciasPeriodo">0</div>
                                </div>
                                <div class="stat-icon warning"><i class="bi bi-clock-history"></i></div>
                            </div>
                        </div>
                        <div class="col-sm-6 col-lg-3">
                            <div class="stat-card d-flex align-items-center justify-content-between">
                                <div>
                                    <div class="stat-label">Estatus Nómina</div>
                                    <div class="stat-value fs-5" id="kpiEstatusNomina">BORRADOR</div>
                                </div>
                                <div class="stat-icon info"><i class="bi bi-check-circle"></i></div>
                            </div>
                        </div>
                    </div>

                    <!-- Tabla de Empleados -->
                    <div class="card card-modern">
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-custom table-hover">
                                    <thead>
                                        <tr>
                                            <th>Colaborador</th>
                                            <th>Puesto &amp; Departamento</th>
                                            <th>RFC / CURP</th>
                                            <th>Salario Diario</th>
                                            <th>Cuenta CLABE (18 dits)</th>
                                            <th>Estatus</th>
                                            <th class="text-end">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody id="tablaEmpleadosBody">
                                        <!-- Dinámico -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- PANE 2: ASISTENCIAS & INCIDENCIAS -->
                <div class="admin-tab-pane d-none" id="paneIncidencias">
                    <div class="page-header-simple">
                        <div>
                            <h4><i class="bi bi-calendar-check text-primary"></i> Asistencias &amp; Incidencias</h4>
                            <p>Marcajes de reloj checador (GPS / IP), horas extra, faltas, vacaciones e incapacidades.</p>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <button class="btn btn-outline-secondary btn-sm" id="btnRecargarIncidencias" title="Actualizar asistencias">
                                <i class="bi bi-arrow-clockwise"></i>
                            </button>
                            <button class="btn btn-outline-secondary btn-sm" id="btnVerResumenPeriodo">
                                <i class="bi bi-bar-chart-steps"></i> Resumen Periodo
                            </button>
                            <button class="btn btn-primary btn-sm d-flex align-items-center gap-1" data-bs-toggle="modal" data-bs-target="#modalNuevaIncidencia">
                                <i class="bi bi-plus-lg"></i> Capturar Incidencia
                            </button>
                        </div>
                    </div>

                    <!-- Resumen Acumulado por Periodo (Colapsable) -->
                    <div class="card card-modern p-3 mb-3 d-none" id="cardResumenIncidenciasPeriodo">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="fw-bold mb-0 text-primary small"><i class="bi bi-calculator me-1"></i> Resumen de Incidencias para Cálculo</h6>
                            <button class="btn btn-sm btn-link text-muted p-0" id="btnCerrarResumenPeriodo"><i class="bi bi-x-lg"></i></button>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover align-middle mb-0">
                                <thead class="table-light small">
                                    <tr>
                                        <th>Empleado</th>
                                        <th class="text-center">Asistencias</th>
                                        <th class="text-center">Faltas</th>
                                        <th class="text-center">Retardos</th>
                                        <th class="text-center">Horas Extra</th>
                                        <th class="text-center">Vacaciones</th>
                                        <th class="text-center">Incapacidades</th>
                                        <th class="text-center fw-bold text-success">Días Efectivos</th>
                                    </tr>
                                </thead>
                                <tbody id="tablaResumenPeriodoBody">
                                    <!-- Dinámico -->
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Tabla de Incidencias -->
                    <div class="card card-modern">
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-custom table-hover">
                                    <thead>
                                        <tr>
                                            <th>Fecha</th>
                                            <th>Empleado</th>
                                            <th>Tipo Incidencia</th>
                                            <th>Modalidad</th>
                                            <th>Detalle / Folio</th>
                                            <th>Observaciones</th>
                                            <th>Estatus</th>
                                            <th class="text-end">Aprobación</th>
                                        </tr>
                                    </thead>
                                    <tbody id="tablaIncidenciasAdminBody">
                                        <!-- Dinámico -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- PANE 3: MOTOR DE CÁLCULO & PRE-NÓMINA -->
                <div class="admin-tab-pane d-none" id="paneCalculo">
                    <div class="page-header-simple">
                        <div>
                            <h4><i class="bi bi-calculator text-primary"></i> Cálculo de Nómina &amp; Pre-nómina</h4>
                            <p>Procesamiento de reglas de negocio, deducciones fiscales (ISR, IMSS, Infonavit) y auditoría.</p>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <button class="btn btn-primary btn-sm d-flex align-items-center gap-1" id="btnEjecutarCalculo">
                                <i class="bi bi-cpu"></i> <span>Calcular &amp; Congelar</span>
                            </button>
                            <button class="btn btn-success btn-sm d-flex align-items-center gap-1" id="btnTimbrarNomina">
                                <i class="bi bi-shield-check"></i> <span>Timbrar (CFDI)</span>
                            </button>
                            <button class="btn btn-purple btn-sm d-flex align-items-center gap-1 text-white" style="background:#7c3aed;" id="btnAbrirDispersion">
                                <i class="bi bi-bank"></i> <span>Ir a Dispersión</span>
                            </button>
                        </div>
                    </div>

                    <!-- Barra de Periodo & Mini-Stepper Contextual -->
                    <div class="card card-modern p-3 mb-4">
                        <div class="row align-items-center g-3">
                            <div class="col-md-4">
                                <label class="small fw-semibold text-muted mb-1">Periodo Quincenal</label>
                                <div class="d-flex gap-2">
                                    <select class="form-select form-select-sm" id="selectPeriodoNomina">
                                        <!-- Dinámico -->
                                    </select>
                                    <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalNuevoPeriodo" title="Nuevo Periodo">
                                        <i class="bi bi-plus-lg"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <label class="small fw-semibold text-muted mb-1">Estatus</label>
                                <div>
                                    <span class="badge-status badge-borrador" id="badgeEstatusPeriodo">BORRADOR</span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <!-- Mini-Stepper Contextual Limpio -->
                                <div class="payroll-flow-bar">
                                    <span class="payroll-flow-step completed"><span class="step-dot">1</span> Incidencias</span>
                                    <i class="bi bi-chevron-right payroll-flow-arrow"></i>
                                    <span class="payroll-flow-step" id="miniStepCalc"><span class="step-dot">2</span> Cálculo</span>
                                    <i class="bi bi-chevron-right payroll-flow-arrow"></i>
                                    <span class="payroll-flow-step" id="miniStepTimbre"><span class="step-dot">3</span> Timbrado</span>
                                    <i class="bi bi-chevron-right payroll-flow-arrow"></i>
                                    <span class="payroll-flow-step" id="miniStepDisp"><span class="step-dot">4</span> Pago SPEI</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Resumen Financiero -->
                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <div class="stat-card">
                                <div class="stat-label">Total Percepciones Brutas</div>
                                <div class="stat-value text-primary" id="cardTotalPercepciones">$0.00</div>
                                <small class="text-muted">Sueldos base + horas extra + bonos</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="stat-card">
                                <div class="stat-label">Retenciones &amp; Deducciones</div>
                                <div class="stat-value text-danger" id="cardTotalDeducciones">-$0.00</div>
                                <small class="text-muted">ISR retenido + IMSS obrero + Infonavit</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="stat-card">
                                <div class="stat-label">Total Neto a Dispersar</div>
                                <div class="stat-value text-success" id="cardTotalNeto">$0.00</div>
                                <small class="text-muted">Monto total por transferencias SPEI</small>
                            </div>
                        </div>
                    </div>

                    <!-- Tabla de Pre-nómina -->
                    <div class="card card-modern">
                        <div class="card-header-modern">
                            <h5><i class="bi bi-table text-primary"></i> Pre-nómina Detallada &amp; Auditoría</h5>
                            <span class="text-muted small" id="lblDetalleCount">0 colaboradores</span>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-custom table-hover">
                                    <thead>
                                        <tr>
                                            <th>Colaborador</th>
                                            <th>Días</th>
                                            <th>Sueldo Base</th>
                                            <th>Horas Extra</th>
                                            <th>Bonos</th>
                                            <th>Total Perc.</th>
                                            <th>ISR Ret.</th>
                                            <th>IMSS</th>
                                            <th>Infonavit / Préstamo</th>
                                            <th>Neto a Pagar</th>
                                            <th>Folio Fiscal (UUID)</th>
                                            <th class="text-end">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody id="tablaPreNominaBody">
                                        <!-- Dinámico -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- PANE 4: FACTURACIÓN ELECTRÓNICA & PROFACT (CFDI 4.0) -->
                <div class="admin-tab-pane d-none" id="paneFacturacion">
                    <div class="page-header-simple">
                        <div>
                            <h4><i class="bi bi-file-earmark-text text-primary"></i> Facturación Electrónica CFDI 4.0</h4>
                            <p>Emisión y timbrado oficial con el PAC ProFact (Web Service SOAP), control de folios y cancelación.</p>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <button class="btn btn-outline-info btn-sm d-flex align-items-center gap-1" data-bs-toggle="modal" data-bs-target="#modalProfactConfig">
                                <i class="bi bi-gear"></i> <span>Configurar ProFact</span>
                            </button>
                            <button class="btn btn-outline-secondary btn-sm" id="btnRecargarFacturas" title="Actualizar facturas">
                                <i class="bi bi-arrow-clockwise"></i>
                            </button>
                            <button class="btn btn-primary btn-sm d-flex align-items-center gap-1" data-bs-toggle="modal" data-bs-target="#modalNuevaFactura">
                                <i class="bi bi-plus-lg"></i> <span>Nueva Factura</span>
                            </button>
                        </div>
                    </div>

                    <!-- Métricas de Facturación -->
                    <div class="row g-3 mb-4">
                        <div class="col-sm-6 col-lg-3">
                            <div class="stat-card d-flex align-items-center justify-content-between">
                                <div>
                                    <div class="stat-label">Facturas Emitidas</div>
                                    <div class="stat-value" id="kpiTotalFacturas">0</div>
                                </div>
                                <div class="stat-icon primary"><i class="bi bi-file-earmark-check"></i></div>
                            </div>
                        </div>
                        <div class="col-sm-6 col-lg-3">
                            <div class="stat-card d-flex align-items-center justify-content-between">
                                <div>
                                    <div class="stat-label">Timbradas con ProFact</div>
                                    <div class="stat-value text-success" id="kpiFacturasTimbradas">0</div>
                                </div>
                                <div class="stat-icon success"><i class="bi bi-shield-check"></i></div>
                            </div>
                        </div>
                        <div class="col-sm-6 col-lg-3">
                            <div class="stat-card d-flex align-items-center justify-content-between">
                                <div>
                                    <div class="stat-label">Monto Facturado</div>
                                    <div class="stat-value text-primary fs-5" id="kpiMontoFacturado">$0.00</div>
                                </div>
                                <div class="stat-icon info"><i class="bi bi-cash-stack"></i></div>
                            </div>
                        </div>
                        <div class="col-sm-6 col-lg-3">
                            <div class="stat-card d-flex align-items-center justify-content-between">
                                <div>
                                    <div class="stat-label">Timbres ProFact Disp.</div>
                                    <div class="stat-value" style="color:#7c3aed;" id="kpiTimbresProfact">10,000</div>
                                </div>
                                <div class="stat-icon warning"><i class="bi bi-cloud-check"></i></div>
                            </div>
                        </div>
                    </div>

                    <!-- Tabla de Facturas -->
                    <div class="card card-modern">
                        <div class="card-header-modern">
                            <h5><i class="bi bi-receipt-cutoff text-primary"></i> Comprobantes Fiscales Emitidos (CFDI 4.0)</h5>
                            <span class="badge bg-light text-dark border" id="badgeTotalFacturas">0 comprobantes</span>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-custom table-hover">
                                    <thead>
                                        <tr>
                                            <th>Folio / Serie</th>
                                            <th>Cliente &amp; RFC</th>
                                            <th>Fecha Emisión</th>
                                            <th>Subtotal</th>
                                            <th>IVA (16%)</th>
                                            <th>Total</th>
                                            <th>Folio Fiscal (UUID SAT)</th>
                                            <th>PAC</th>
                                            <th>Estatus</th>
                                            <th class="text-end">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody id="tablaFacturasBody">
                                        <!-- Dinámico -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- PANE 5: DISPERSIÓN BANCARIA -->
                <div class="admin-tab-pane d-none" id="paneDispersion">
                    <div class="page-header-simple">
                        <div>
                            <h4><i class="bi bi-bank text-primary"></i> Dispersión Bancaria &amp; SPEI</h4>
                            <p>Generación de archivos de layout estructurado y transferencias masivas a cuentas CLABE.</p>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <select class="form-select form-select-sm" id="selectConvenioBanco" style="width: 160px;">
                                <option value="UNIVERSAL">Layout Universal SPEI</option>
                                <option value="BBVA">Layout BBVA</option>
                                <option value="BANORTE">Layout Banorte</option>
                                <option value="SANTANDER">Layout Santander</option>
                                <option value="BANAMEX">Layout Banamex</option>
                            </select>
                            <a href="#" class="btn btn-outline-primary btn-sm" id="btnDescargarLayoutTxt" target="_blank">
                                <i class="bi bi-download"></i> Layout (.txt)
                            </a>
                            <button class="btn btn-success btn-sm" id="btnEjecutarDispersionMasiva">
                                <i class="bi bi-check2-circle"></i> Aplicar Pagos SPEI
                            </button>
                        </div>
                    </div>

                    <!-- Tabla de Transferencias -->
                    <div class="card card-modern">
                        <div class="card-header-modern">
                            <h5><i class="bi bi-credit-card text-primary"></i> Lote de Transferencias Electrónicas</h5>
                            <span class="badge bg-light text-dark border" id="badgeTotalTransferencias">0 transferencias</span>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-custom table-hover">
                                    <thead>
                                        <tr>
                                            <th>Colaborador</th>
                                            <th>Cuenta CLABE (18 dígitos)</th>
                                            <th>Banco Receptor</th>
                                            <th>Monto Neto</th>
                                            <th>Concepto</th>
                                            <th>Referencia SPEI</th>
                                            <th>Estado Transacción</th>
                                            <th class="text-end">Acción</th>
                                        </tr>
                                    </thead>
                                    <tbody id="tablaDispersionBody">
                                        <!-- Dinámico -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- PANE 6: NOTIFICACIONES -->
                <div class="admin-tab-pane d-none" id="paneNotificaciones">
                    <div class="page-header-simple">
                        <div>
                            <h4><i class="bi bi-bell text-primary"></i> Notificaciones &amp; Comunicados</h4>
                            <p>Difusión masiva de eventos, publicación de recibos y alertas al portal del trabajador.</p>
                        </div>
                    </div>

                    <div class="row g-4">
                        <div class="col-lg-4">
                            <div class="card card-modern p-3">
                                <h6 class="fw-bold mb-3 text-dark small"><i class="bi bi-send me-1 text-primary"></i> Enviar Aviso a la Plantilla</h6>
                                <form id="formEnviarAviso">
                                    <div class="mb-2">
                                        <label class="form-label small fw-semibold text-muted">Título *</label>
                                        <input type="text" class="form-control form-control-sm" name="titulo" placeholder="Ej. Depósito de Nómina Listo" required>
                                    </div>
                                    <div class="mb-2">
                                        <label class="form-label small fw-semibold text-muted">Canal *</label>
                                        <select class="form-select form-select-sm" name="canal">
                                            <option value="PORTAL">Portal Web &amp; Campana</option>
                                            <option value="EMAIL">Correo Electrónico</option>
                                            <option value="PUSH">Mensaje Push Móvil</option>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label small fw-semibold text-muted">Mensaje *</label>
                                        <textarea class="form-control form-control-sm" name="mensaje" rows="3" placeholder="Escribe el aviso..." required></textarea>
                                    </div>
                                    <button type="submit" class="btn btn-primary btn-sm w-100">
                                        <i class="bi bi-send-check"></i> Emitir Notificación
                                    </button>
                                </form>
                            </div>
                        </div>

                        <div class="col-lg-8">
                            <div class="card card-modern">
                                <div class="card-header-modern">
                                    <h5><i class="bi bi-inbox text-primary"></i> Historial de Comunicados Emitidos</h5>
                                    <button class="btn btn-outline-secondary btn-sm" id="btnRecargarNotificacionesAdmin">
                                        <i class="bi bi-arrow-clockwise"></i> Actualizar
                                    </button>
                                </div>
                                <div class="card-body p-0">
                                    <div class="table-responsive">
                                        <table class="table table-custom table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Fecha</th>
                                                    <th>Canal</th>
                                                    <th>Título</th>
                                                    <th>Mensaje</th>
                                                    <th>Lectura</th>
                                                </tr>
                                            </thead>
                                            <tbody id="tablaNotificacionesAdminBody">
                                                <!-- Dinámico -->
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- PANE 7: AUDITORÍA & REGLAS FISCALES -->
                <div class="admin-tab-pane d-none" id="paneAuditoria">
                    <div class="page-header-simple">
                        <div>
                            <h4><i class="bi bi-shield-check text-primary"></i> Auditoría &amp; Parámetros Fiscales</h4>
                            <p>Trazabilidad inmutable de cambios (Audit Trail) y configuración de tablas fiscales vigentes.</p>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <button class="btn btn-outline-secondary btn-sm" id="btnRecargarLogs" title="Actualizar bitácora">
                                <i class="bi bi-arrow-clockwise"></i>
                            </button>
                            <ul class="nav nav-pills" role="tablist">
                                <li class="nav-item">
                                    <button class="nav-link active btn-sm py-1 px-3 fw-semibold" id="tabLogsBtn" data-bs-toggle="pill" data-bs-target="#tabLogsPane">
                                        <i class="bi bi-journal-text me-1"></i> Audit Trail
                                    </button>
                                </li>
                                <li class="nav-item">
                                    <button class="nav-link btn-sm py-1 px-3 fw-semibold" id="tabReglasBtn" data-bs-toggle="pill" data-bs-target="#tabReglasPane">
                                        <i class="bi bi-sliders me-1"></i> Reglas Fiscales
                                    </button>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <div class="tab-content">
                        <!-- Sub-tab Audit Trail -->
                        <div class="tab-pane fade show active" id="tabLogsPane">
                            <div class="row g-3 mb-3">
                                <div class="col-sm-6 col-lg-3">
                                    <div class="stat-card">
                                        <div class="stat-label">Total Eventos Auditados</div>
                                        <div class="stat-value" id="kpiAuditTotal">0</div>
                                    </div>
                                </div>
                                <div class="col-sm-6 col-lg-3">
                                    <div class="stat-card">
                                        <div class="stat-label">Ajustes Financieros</div>
                                        <div class="stat-value text-warning" id="kpiAuditAjustes">0</div>
                                    </div>
                                </div>
                                <div class="col-sm-6 col-lg-3">
                                    <div class="stat-card">
                                        <div class="stat-label">Timbrados Fiscales</div>
                                        <div class="stat-value text-primary" id="kpiAuditTimbrados">0</div>
                                    </div>
                                </div>
                                <div class="col-sm-6 col-lg-3">
                                    <div class="stat-card">
                                        <div class="stat-label">Integridad Criptográfica</div>
                                        <div class="stat-value fs-6 text-success"><i class="bi bi-patch-check-fill"></i> SHA-256 Verificada</div>
                                    </div>
                                </div>
                            </div>

                            <div class="card card-modern">
                                <div class="card-body p-0">
                                    <div class="table-responsive">
                                        <table class="table table-custom table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Fecha &amp; Hora</th>
                                                    <th>Usuario</th>
                                                    <th>Acción Auditada</th>
                                                    <th>Módulo</th>
                                                    <th>Descripción</th>
                                                    <th>IP</th>
                                                    <th>Hash SHA-256</th>
                                                </tr>
                                            </thead>
                                            <tbody id="tablaAuditLogsBody">
                                                <!-- Dinámico -->
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Sub-tab Reglas Fiscales -->
                        <div class="tab-pane fade" id="tabReglasPane">
                            <div class="card card-modern">
                                <div class="card-header-modern">
                                    <h5><i class="bi bi-cash-coin text-primary"></i> Tasas y Parámetros Laborales Parametrizados</h5>
                                </div>
                                <div class="card-body p-0">
                                    <div class="table-responsive">
                                        <table class="table table-custom table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Clave</th>
                                                    <th>Nombre del Parámetro</th>
                                                    <th>Valor Vigente</th>
                                                    <th>Unidad</th>
                                                    <th>Descripción</th>
                                                    <th class="text-end">Acción</th>
                                                </tr>
                                            </thead>
                                            <tbody id="tablaReglasFiscalesBody">
                                                <!-- Dinámico -->
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- PANE 8: GRAPHQL GATEWAY & BROKER -->
                <div class="admin-tab-pane d-none" id="paneGraphQL">
                    <div class="page-header-simple">
                        <div>
                            <h4><i class="bi bi-cpu text-primary"></i> GraphQL Gateway &amp; Message Broker</h4>
                            <p>Orquestador federado de subgrafos (Páginas 7 y 8) y visualizador de eventos asíncronos.</p>
                        </div>
                    </div>

                    <div class="row g-4">
                        <div class="col-lg-6">
                            <div class="graphql-terminal mb-4">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="text-info fw-bold small"><i class="bi bi-terminal me-1"></i> GraphQL Query (Página 8)</span>
                                    <button class="btn btn-sm btn-outline-info py-0 px-2" id="btnCargarQueryEjemplo">Recargar Query</button>
                                </div>
                                <textarea class="graphql-editor mb-2" id="graphqlQueryInput"></textarea>
                                <button class="btn btn-info btn-sm w-100 fw-bold" id="btnEjecutarGraphQL">
                                    <i class="bi bi-play-fill"></i> Ejecutar Consulta en el Gateway
                                </button>
                            </div>
                        </div>

                        <div class="col-lg-6">
                            <div class="graphql-terminal">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="text-success fw-bold small"><i class="bi bi-braces me-1"></i> Respuesta JSON Unificada</span>
                                    <span class="badge bg-secondary font-monospace" id="graphqlDuration">0.0 ms</span>
                                </div>
                                <div class="graphql-output" id="graphqlResultOutput">
                                    Presiona "Ejecutar Consulta" para federar los subgrafos en tiempo real.
                                </div>
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="card card-modern p-3">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <h6 class="fw-bold mb-0 text-dark small"><i class="bi bi-broadcast text-purple me-1" style="color:#7c3aed;"></i> Flujo de Eventos Asíncronos (Message Broker Simulado)</h6>
                                    <button class="btn btn-sm btn-outline-secondary py-0" id="btnRecargarEventosBroker"><i class="bi bi-arrow-clockwise"></i></button>
                                </div>
                                <div id="messageBrokerEventsList">
                                    <!-- Dinámico -->
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <!-- =================================================
                 VISTAS PARA EMPLEADO (Portal de Autoconsulta)
                 ================================================= -->
            <div id="viewEmpleado" class="d-none">

                <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
                    <div>
                        <h4 class="fw-bold mb-0">Hola, <span id="empNombreSaludo">Colaborador</span> 👋</h4>
                        <p class="text-muted small mb-0">Tu portal de autoconsulta, checador digital y recibos de nómina.</p>
                    </div>
                    <span class="badge bg-primary px-3 py-2"><i class="bi bi-shield-check"></i> Perfil Verificado</span>
                </div>

                <!-- Tarjeta de Clima en Vivo (OpenWeatherMap API) -->
                <div class="card weather-card-emp mb-4 shadow-sm border-0">
                    <div class="card-body p-4">
                        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge bg-white bg-opacity-25 text-white border-0 px-2 py-1 small">
                                    <i class="bi bi-cloud-sun me-1"></i> OpenWeatherMap
                                </span>
                                <span class="badge bg-success bg-opacity-75 text-white border-0 px-2 py-1 small" id="weatherBadgeStatus">
                                    <i class="bi bi-broadcast me-1"></i> En vivo
                                </span>
                                <span class="text-white-50 small d-none d-md-inline" id="weatherSubtitleFuente">Condiciones meteorológicas en tiempo real</span>
                            </div>
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                <div class="input-group input-group-sm" style="max-width: 250px;">
                                    <span class="input-group-text bg-white bg-opacity-20 text-white border-0"><i class="bi bi-geo-alt"></i></span>
                                    <select class="form-select form-select-sm bg-white bg-opacity-20 text-white border-0" id="weatherCitySelect" style="cursor: pointer;">
                                        <option value="Ciudad de México" class="text-dark" selected>Ciudad de México</option>
                                        <option value="Guadalajara" class="text-dark">Guadalajara</option>
                                        <option value="Monterrey" class="text-dark">Monterrey</option>
                                        <option value="Puebla" class="text-dark">Puebla</option>
                                        <option value="Querétaro" class="text-dark">Querétaro</option>
                                        <option value="Cancún" class="text-dark">Cancún</option>
                                        <option value="Tijuana" class="text-dark">Tijuana</option>
                                        <option value="custom" class="text-dark">🔍 Otra ciudad...</option>
                                    </select>
                                </div>
                                <button class="btn btn-sm btn-light bg-opacity-25 text-white border-0" id="btnWeatherGps" title="Detectar clima con mi ubicación GPS">
                                    <i class="bi bi-crosshair"></i> <span class="d-none d-sm-inline">Mi Ubicación GPS</span>
                                </button>
                                <button class="btn btn-sm btn-light bg-opacity-25 text-white border-0" id="btnWeatherRefresh" title="Actualizar datos del clima">
                                    <i class="bi bi-arrow-clockwise"></i>
                                </button>
                            </div>
                        </div>

                        <div class="row align-items-center g-3">
                            <div class="col-md-5 d-flex align-items-center gap-3">
                                <div class="weather-icon-wrapper p-2 rounded-circle d-flex align-items-center justify-content-center" style="width: 76px; height: 76px;">
                                    <img id="weatherIconImg" src="https://openweathermap.org/img/wn/01d@2x.png" alt="Clima" style="width: 64px; height: 64px;">
                                </div>
                                <div>
                                    <div class="d-flex align-items-baseline gap-2">
                                        <h1 class="display-5 fw-bold mb-0 text-white" id="weatherTempDisplay">--°C</h1>
                                        <span class="text-white-50 fs-6" id="weatherCityDisplay">Ciudad de México, MX</span>
                                    </div>
                                    <p class="mb-0 text-white fw-semibold text-capitalize small" id="weatherDescDisplay">Cargando estado del tiempo...</p>
                                </div>
                            </div>

                            <div class="col-md-7">
                                <div class="row g-2 text-center text-sm-start">
                                    <div class="col-6 col-sm-3">
                                        <div class="weather-metric-box">
                                            <div class="text-white-50 small" style="font-size: 0.72rem;"><i class="bi bi-thermometer-half text-warning"></i> Sensación</div>
                                            <div class="text-white fw-bold" id="weatherFeelsDisplay">--°C</div>
                                        </div>
                                    </div>
                                    <div class="col-6 col-sm-3">
                                        <div class="weather-metric-box">
                                            <div class="text-white-50 small" style="font-size: 0.72rem;"><i class="bi bi-droplet text-info"></i> Humedad</div>
                                            <div class="text-white fw-bold" id="weatherHumidityDisplay">--%</div>
                                        </div>
                                    </div>
                                    <div class="col-6 col-sm-3">
                                        <div class="weather-metric-box">
                                            <div class="text-white-50 small" style="font-size: 0.72rem;"><i class="bi bi-wind text-light"></i> Viento</div>
                                            <div class="text-white fw-bold" id="weatherWindDisplay">-- km/h</div>
                                        </div>
                                    </div>
                                    <div class="col-6 col-sm-3">
                                        <div class="weather-metric-box">
                                            <div class="text-white-50 small" style="font-size: 0.72rem;"><i class="bi bi-arrows-expand text-success"></i> Min / Max</div>
                                            <div class="text-white fw-bold" id="weatherMinMaxDisplay">--° / --°</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row g-4">
                    <!-- Columna Izquierda: Checador Digital & Perfil -->
                    <div class="col-lg-5">
                        <div class="clock-display-card mb-4">
                            <div class="small text-uppercase text-info mb-1"><i class="bi bi-geo-alt"></i> Checador Digital</div>
                            <div class="digital-time" id="liveClockDisplay">12:00:00</div>
                            <div class="digital-date" id="liveDateDisplay">Lunes, 01 de Octubre de 2026</div>

                            <div class="d-flex justify-content-center gap-3 mb-2">
                                <div class="form-check form-check-inline text-white small">
                                    <input class="form-check-input" type="radio" name="modalidadRadio" id="radioPresencial" value="PRESENCIAL" checked>
                                    <label class="form-check-label" for="radioPresencial">Presencial</label>
                                </div>
                                <div class="form-check form-check-inline text-white small">
                                    <input class="form-check-input" type="radio" name="modalidadRadio" id="radioTeletrabajo" value="TELETRABAJO">
                                    <label class="form-check-label" for="radioTeletrabajo">Teletrabajo</label>
                                </div>
                            </div>

                            <div class="mb-3">
                                <button class="btn btn-sm btn-outline-light border-opacity-25" type="button" id="btnCapturarGps">
                                    <i class="bi bi-crosshair"></i> <span id="lblGpsStatus">Ubicación GPS</span>
                                </button>
                            </div>

                            <button class="btn btn-clock-in" id="btnMarcarAsistencia">
                                <i class="bi bi-fingerprint fs-4"></i>
                                <span>Marcar Asistencia</span>
                            </button>

                            <div class="mt-2 text-white-50 small" style="font-size: 0.75rem;" id="clockFeedback">
                                Registro con sello de tiempo y geolocalización.
                            </div>
                        </div>

                        <!-- Tarjeta Expediente & Vacaciones -->
                        <div class="card card-modern">
                            <div class="card-header-modern">
                                <h5><i class="bi bi-person-vcard text-primary"></i> Mi Expediente</h5>
                            </div>
                            <div class="card-body">
                                <ul class="list-group list-group-flush small">
                                    <li class="list-group-item d-flex justify-content-between px-0">
                                        <span class="text-muted">Puesto:</span> <strong id="empPuesto">-</strong>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between px-0">
                                        <span class="text-muted">Departamento:</span> <strong id="empDepto">-</strong>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between px-0">
                                        <span class="text-muted">RFC:</span> <span class="font-monospace text-dark fw-bold" id="empRfc">-</span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between px-0">
                                        <span class="text-muted">CURP:</span> <span class="font-monospace text-dark" id="empCurp">-</span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between px-0">
                                        <span class="text-muted">Contrato:</span> <strong id="empContrato">INDETERMINADO</strong>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between px-0">
                                        <span class="text-muted">Cuenta CLABE:</span> <span class="font-monospace text-primary fw-bold" id="empClabe">-</span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between px-0">
                                        <span class="text-muted">Salario Diario:</span> <strong class="text-success" id="empSalarioDiario">$0.00</strong>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between px-0 bg-light rounded p-2 mt-2">
                                        <span class="text-muted"><i class="bi bi-sun text-warning me-1"></i> Vacaciones Disponibles (LFT):</span>
                                        <strong class="text-primary" id="empVacacionesDisponibles">12 días</strong>
                                    </li>
                                </ul>

                                <div class="mt-3 d-flex flex-column gap-2">
                                    <button class="btn btn-outline-success btn-sm" id="btnDescargarMiConstancia">
                                        <i class="bi bi-award"></i> Descargar Constancia Laboral
                                    </button>
                                    <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalSolicitudIncidencia">
                                        <i class="bi bi-calendar-plus"></i> Solicitar Vacaciones o Permiso
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Columna Derecha: Recibos e Incidencias -->
                    <div class="col-lg-7">
                        <div class="card card-modern mb-4">
                            <div class="card-header-modern">
                                <h5><i class="bi bi-file-earmark-pdf text-primary"></i> Mis Recibos de Nómina (CFDI 4.0)</h5>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-custom table-hover">
                                        <thead>
                                            <tr>
                                                <th>Periodo</th>
                                                <th>Percepciones</th>
                                                <th>Deducciones</th>
                                                <th>Neto a Recibir</th>
                                                <th>Folio Fiscal</th>
                                                <th class="text-end">Comprobante</th>
                                            </tr>
                                        </thead>
                                        <tbody id="tablaMisRecibosBody">
                                            <!-- Dinámico -->
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div class="card card-modern">
                            <div class="card-header-modern">
                                <h5><i class="bi bi-clock-history text-primary"></i> Mi Historial de Asistencias &amp; Solicitudes</h5>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-custom table-hover">
                                        <thead>
                                            <tr>
                                                <th>Fecha</th>
                                                <th>Tipo</th>
                                                <th>Modalidad</th>
                                                <th>Detalle</th>
                                                <th>Estatus</th>
                                            </tr>
                                        </thead>
                                        <tbody id="tablaMisIncidenciasBody">
                                            <!-- Dinámico -->
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

        </main>

        <!-- Footer -->
        <footer class="mt-auto py-3 bg-white border-top text-center text-muted small">
            <div class="container">
                <span>&copy; 2026 <strong>Sistema de Nóminas y Gestión de Personal</strong>. LFT &amp; SAT CFDI 4.0.</span>
            </div>
        </footer>
    </div>

    <!-- =====================================================
         MODALES
         ===================================================== -->

    <!-- Modal: Nuevo Empleado -->
    <div class="modal fade" id="modalNuevoEmpleado" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold"><i class="bi bi-person-plus text-primary"></i> Registrar Nuevo Empleado</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="formNuevoEmpleado">
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Nombre Completo *</label>
                                <input type="text" class="form-control" name="nombre" placeholder="Ej. Roberto Sánchez Díaz" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-semibold">RFC *</label>
                                <input type="text" class="form-control text-uppercase" name="rfc" placeholder="SADR900101XYZ" maxlength="13" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-semibold">CURP *</label>
                                <input type="text" class="form-control text-uppercase" name="curp" placeholder="SADR900101HDFR01" maxlength="18" required>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label small fw-semibold">Puesto *</label>
                                <input type="text" class="form-control" name="puesto" placeholder="Ej. Desarrollador Web" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold">Departamento *</label>
                                <select class="form-select" name="departamento" required>
                                    <option value="">Seleccione...</option>
                                    <option value="Tecnología">Tecnología e Informática</option>
                                    <option value="Recursos Humanos">Recursos Humanos</option>
                                    <option value="Finanzas">Finanzas y Contabilidad</option>
                                    <option value="Operaciones">Operaciones</option>
                                    <option value="Dirección General">Dirección General</option>
                                    <option value="Marketing">Marketing y Ventas</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold">Tipo Contrato *</label>
                                <select class="form-select" name="tipo_contrato" required>
                                    <option value="TIEMPO INDETERMINADO">Tiempo Indeterminado</option>
                                    <option value="OBRA DETERMINADA">Obra Determinada</option>
                                    <option value="TIEMPO DETERMINADO">Tiempo Determinado</option>
                                    <option value="PERIODO DE PRUEBA">Periodo de Prueba</option>
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label small fw-semibold">Salario Diario (MXN) *</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" step="0.01" min="1" class="form-control" name="salario_diario" placeholder="650.00" required>
                                </div>
                            </div>
                            <div class="col-md-5">
                                <label class="form-label small fw-semibold">Cuenta CLABE (18 dígitos) *</label>
                                <input type="text" class="form-control font-monospace" name="cuenta_clabe" placeholder="012180001234567890" maxlength="18" minlength="18" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-semibold">Fecha de Ingreso *</label>
                                <input type="date" class="form-control" name="fecha_ingreso" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>

                            <div class="col-12 mt-2 pt-2 border-top">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="checkCrearUsuario" name="crear_usuario" checked>
                                    <label class="form-check-label fw-semibold small" for="checkCrearUsuario">Crear usuario para portal de autoconsulta</label>
                                </div>
                            </div>
                            <div class="col-md-12" id="boxEmailUsuario">
                                <label class="form-label small fw-semibold">Correo de Acceso</label>
                                <input type="email" class="form-control" name="email" placeholder="empleado@empresa.com">
                                <small class="text-muted">Contraseña por defecto: <strong>123456</strong>.</small>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-save"></i> Guardar Empleado</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal: Nueva Incidencia (Admin/RH) -->
    <div class="modal fade" id="modalNuevaIncidencia" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold"><i class="bi bi-calendar-plus text-primary"></i> Registrar Incidencia</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="formNuevaIncidencia">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Empleado *</label>
                            <select class="form-select" name="empleado_id" id="incidenciaEmpleadoSelect" required>
                                <!-- Dinámico -->
                            </select>
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label small fw-semibold">Tipo *</label>
                                <select class="form-select" name="tipo" id="incidenciaTipoSelect" required>
                                    <option value="ASISTENCIA">ASISTENCIA (Checador)</option>
                                    <option value="HORA_EXTRA">HORA EXTRA</option>
                                    <option value="FALTA">FALTA</option>
                                    <option value="RETARDO">RETARDO</option>
                                    <option value="VACACIONES">VACACIONES</option>
                                    <option value="INCAPACIDAD">INCAPACIDAD MÉDICA</option>
                                    <option value="PERMISO">PERMISO</option>
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-semibold">Fecha *</label>
                                <input type="date" class="form-control" name="fecha" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label small fw-semibold">Modalidad *</label>
                                <select class="form-select" name="modalidad">
                                    <option value="PRESENCIAL">Presencial (Sede)</option>
                                    <option value="TELETRABAJO">Teletrabajo</option>
                                </select>
                            </div>
                            <div class="col-6 d-none" id="boxHorasExtraInput">
                                <label class="form-label small fw-semibold">Horas Extra</label>
                                <input type="number" step="0.5" min="0.5" max="12" class="form-control" name="horas_extra" value="2.0">
                            </div>
                        </div>
                        <div class="row g-2 mb-3 d-none" id="boxIncapacidadInput">
                            <div class="col-6">
                                <label class="form-label small fw-semibold">Ramo Incapacidad</label>
                                <select class="form-select" name="tipo_incapacidad">
                                    <option value="ENFERMEDAD GENERAL">Enfermedad General</option>
                                    <option value="RIESGO DE TRABAJO">Riesgo de Trabajo</option>
                                    <option value="MATERNIDAD">Maternidad</option>
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-semibold">Folio IMSS</label>
                                <input type="text" class="form-control text-uppercase" name="folio_medico" placeholder="IMSS-123456">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Observaciones</label>
                            <textarea class="form-control" name="observaciones" rows="2" placeholder="Detalle justificativo..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-check-circle"></i> Guardar Incidencia</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal: Solicitud de Incidencia (Empleado) -->
    <div class="modal fade" id="modalSolicitudIncidencia" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold"><i class="bi bi-send text-primary"></i> Solicitud de Incidencia</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="formSolicitudEmpleado">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Tipo de Solicitud *</label>
                            <select class="form-select" name="tipo" id="solicitudTipoSelect" required>
                                <option value="VACACIONES">Día de Vacaciones</option>
                                <option value="HORA_EXTRA">Reporte de Horas Extra</option>
                                <option value="INCAPACIDAD">Incapacidad Médica IMSS</option>
                                <option value="PERMISO">Permiso Personal</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Fecha *</label>
                            <input type="date" class="form-control" name="fecha" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="mb-3 d-none" id="boxSolicitudHorasExtra">
                            <label class="form-label small fw-semibold">Horas Trabajadas</label>
                            <input type="number" step="0.5" min="0.5" max="10" class="form-control" name="horas_extra" value="2.0">
                        </div>
                        <div class="row g-2 mb-3 d-none" id="boxSolicitudIncapacidad">
                            <div class="col-6">
                                <label class="form-label small fw-semibold">Ramo Incapacidad</label>
                                <select class="form-select" name="tipo_incapacidad">
                                    <option value="ENFERMEDAD GENERAL">Enfermedad General</option>
                                    <option value="RIESGO DE TRABAJO">Riesgo de Trabajo</option>
                                    <option value="MATERNIDAD">Maternidad</option>
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-semibold">Folio IMSS</label>
                                <input type="text" class="form-control text-uppercase" name="folio_medico" placeholder="MED-123456">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Justificación *</label>
                            <textarea class="form-control" name="observaciones" rows="2" placeholder="Escribe el motivo..." required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
                        <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-send-check"></i> Enviar Solicitud</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal: Ajuste Manual Justificado (Auditoría) -->
    <div class="modal fade" id="modalAjusteManual" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square text-primary"></i> Ajuste Manual Justificado</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="formAjusteManual">
                    <input type="hidden" name="detalle_id" id="ajusteDetalleId">
                    <div class="modal-body">
                        <div class="mb-2">
                            <span class="text-muted small">Colaborador:</span>
                            <strong id="ajusteEmpleadoNombre" class="d-block fs-6">-</strong>
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label small fw-semibold">Bono ($)</label>
                                <input type="number" step="0.01" min="0" class="form-control" name="bonos" id="ajusteBonos" value="0.00">
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-semibold">Deducción Préstamo ($)</label>
                                <input type="number" step="0.01" min="0" class="form-control" name="deduccion_prestamo" id="ajustePrestamo" value="0.00">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Ajuste Neto ($)</label>
                            <input type="number" step="0.01" class="form-control" name="ajuste_manual" id="ajusteNeto" value="0.00">
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-danger">Justificación de Auditoría *</label>
                            <textarea class="form-control" name="justificacion" id="ajusteJustificacion" rows="2" placeholder="Motivo del ajuste conforme a políticas internas..." required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-check-circle"></i> Aplicar Ajuste</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal: Expediente Digital Completo -->
    <div class="modal fade" id="modalExpediente" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold"><i class="bi bi-file-earmark-person text-primary"></i> Expediente Digital</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="modalExpedienteContent">
                    <!-- Dinámico -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal: Constancia Laboral Oficial Membretada -->
    <div class="modal fade" id="modalConstanciaLaboral" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header no-print">
                    <h5 class="modal-title fw-bold"><i class="bi bi-award text-primary"></i> Constancia Laboral Oficial</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4" id="modalConstanciaPrintable">
                    <!-- Dinámico -->
                </div>
                <div class="modal-footer no-print">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
                    <button type="button" class="btn btn-primary btn-sm" onclick="window.print()">
                        <i class="bi bi-printer"></i> Imprimir Constancia / Guardar PDF
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal: Nuevo Periodo de Nómina -->
    <div class="modal fade" id="modalNuevoPeriodo" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold"><i class="bi bi-calendar-event text-primary"></i> Apertura de Periodo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="formNuevoPeriodo">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Código *</label>
                            <input type="text" class="form-control text-uppercase" name="periodo" placeholder="Ej. 2026-Q2" required>
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label small fw-semibold">Inicio *</label>
                                <input type="date" class="form-control" name="fecha_inicio" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-semibold">Fin *</label>
                                <input type="date" class="form-control" name="fecha_fin" required>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-plus-circle"></i> Abrir Periodo</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal: Visor Recibo CFDI 4.0 -->
    <div class="modal fade" id="modalRecibo" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header no-print">
                    <h5 class="modal-title fw-bold"><i class="bi bi-receipt text-primary"></i> Recibo de Nómina CFDI 4.0</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4" id="modalReciboPrintable">
                    <!-- Dinámico -->
                </div>
                <div class="modal-footer no-print">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
                    <a href="#" class="btn btn-outline-info btn-sm" id="btnDescargarXmlRecibo" target="_blank">
                        <i class="bi bi-file-code"></i> Descargar XML CFDI
                    </a>
                    <button type="button" class="btn btn-primary btn-sm" onclick="window.print()">
                        <i class="bi bi-printer"></i> Imprimir Recibo / Guardar PDF
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal: Arquitectura & Servicios (Tabla Entradas y Salidas - Página 6) -->
    <div class="modal fade" id="modalMapaServicios" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold"><i class="bi bi-diagram-3-fill text-primary"></i> Arquitectura de Servicios (Páginas 2 a 8 del Documento)</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="table-responsive">
                        <table class="table table-bordered align-middle mb-0">
                            <thead class="table-dark small">
                                <tr>
                                    <th>Servicio</th>
                                    <th>Entradas Requeridas</th>
                                    <th>Salidas Generadas</th>
                                    <th>Mecanismo de Comunicación</th>
                                </tr>
                            </thead>
                            <tbody class="small">
                                <tr>
                                    <td class="fw-bold text-primary">1. Empleados y Expediente (HR Core)</td>
                                    <td>Datos personales, contrato, puesto, salario base, cuenta CLABE.</td>
                                    <td>Perfil completo, historial laboral, SDI y constancias laborales.</td>
                                    <td><span class="badge bg-light text-dark border">Subgrafo GraphQL</span></td>
                                </tr>
                                <tr>
                                    <td class="fw-bold text-info">2. Incidencias y Asistencia</td>
                                    <td>ID empleado, reloj checador (GPS/IP), horas extra, incapacidades.</td>
                                    <td>Resumen de incidencias por periodo (días trabajados, horas extra, faltas).</td>
                                    <td><span class="badge bg-light text-dark border">Subgrafo GraphQL</span></td>
                                </tr>
                                <tr>
                                    <td class="fw-bold text-warning text-dark">3. Motor de Cálculo</td>
                                    <td>Resumen incidencias, sueldo base, tablas fiscales/ISR vigentes.</td>
                                    <td>Desglose de pre-nómina (monto bruto, percepciones, deducciones, neto).</td>
                                    <td><span class="badge text-white" style="background:#7c3aed;">Emite: NominaAprobada</span></td>
                                </tr>
                                <tr>
                                    <td class="fw-bold text-success">4. Timbrado Fiscal</td>
                                    <td>Pre-nómina autorizada, certificados de sello digital (CSD).</td>
                                    <td>XML timbrado fiscalmente, UUID (Folio Fiscal), Sello SAT y estatus.</td>
                                    <td><span class="badge bg-success">Emite: ReciboTimbrado</span></td>
                                </tr>
                                <tr>
                                    <td class="fw-bold text-primary">5. Dispersión y Pagos</td>
                                    <td>Lista de pagos netos aprobados, cuentas CLABE destino, convenio.</td>
                                    <td>Archivo de dispersión (Layout bancario) y comprobante de transferencia.</td>
                                    <td><span class="badge bg-info text-dark">Escucha: ReciboTimbrado</span></td>
                                </tr>
                                <tr>
                                    <td class="fw-bold text-danger">6. Notificaciones</td>
                                    <td>Evento "Nómina Timbrada", ID recibo, canal (email/push/portal).</td>
                                    <td>Representación gráfica (PDF del recibo), estatus de entrega y notificación.</td>
                                    <td><span class="badge bg-info text-dark">Escucha: ReciboTimbrado</span></td>
                                </tr>
                                <tr>
                                    <td class="fw-bold text-secondary">7. Usuarios e Identidad (Auth)</td>
                                    <td>Credenciales (email/contraseña), token de autenticación.</td>
                                    <td>Token de acceso seguro (JWT), perfil de rol (ADMIN, RH, AUDITOR, EMPLEADO).</td>
                                    <td><span class="badge bg-dark">RBAC Seguro</span></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal: Editar Regla Fiscal (Admin) -->
    <div class="modal fade" id="modalEditarReglaFiscal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold"><i class="bi bi-sliders text-primary"></i> Modificar Parámetro Fiscal</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="formEditarReglaFiscal">
                    <input type="hidden" name="clave" id="reglaClaveInput">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Nombre del Parámetro</label>
                            <input type="text" class="form-control form-control-sm" id="reglaNombreInput" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Nuevo Valor *</label>
                            <div class="input-group input-group-sm">
                                <input type="number" step="0.0001" min="0" class="form-control" name="valor" id="reglaValorInput" required>
                                <span class="input-group-text" id="reglaUnidadInput">%</span>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-danger">Motivo de Auditoría *</label>
                            <textarea class="form-control form-control-sm" name="motivo" rows="2" placeholder="Ej. Actualización conforme a publicación del DOF..." required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-check-circle"></i> Guardar y Auditar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal: Configuración & Diagnóstico del PAC ProFact -->
    <div class="modal fade" id="modalProfactConfig" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold"><i class="bi bi-cloud-check-fill text-info me-1"></i> Configuración del PAC ProFact (Web Service SOAP)</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="formProfactConfig">
                    <div class="modal-body p-4">
                        <!-- Tarjeta de Diagnóstico y Estado en Vivo -->
                        <div class="card bg-light border-0 p-3 mb-4 rounded-3">
                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                                <div>
                                    <span class="fw-bold small text-dark"><i class="bi bi-activity text-success me-1"></i> Estado del Servicio ProFact:</span>
                                    <span class="badge bg-success" id="pacStatusBadge">ONLINE</span>
                                    <span class="badge bg-secondary ms-1" id="pacAmbienteBadge">SANDBOX</span>
                                </div>
                                <button type="button" class="btn btn-sm btn-info text-dark fw-bold d-flex align-items-center gap-1 shadow-sm" id="btnTestConexionProfact">
                                    <i class="bi bi-lightning-charge-fill"></i> Probar Conexión en Vivo
                                </button>
                            </div>
                            <div class="small text-muted mb-2 font-monospace" style="font-size: 0.75rem;" id="pacEndpointDesc">
                                Endpoint: https://pruebas.timbracfdi33.mx/Timbrado.asmx?wsdl
                            </div>
                            <div class="p-2 bg-white rounded border small" id="pacTestOutput" style="min-height: 48px; font-size: 0.8rem;">
                                Haz clic en "Probar Conexión en Vivo" para consultar el Web Service de ProFact y tus timbres disponibles.
                            </div>
                        </div>

                        <!-- Formulario de Parámetros de Integración -->
                        <h6 class="fw-bold text-dark small mb-3"><i class="bi bi-sliders me-1"></i> Parámetros de Timbrado Fiscal</h6>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold text-muted">Ambiente de Operación *</label>
                                <select class="form-select form-select-sm" name="ambiente" id="profactAmbienteSelect">
                                    <option value="SANDBOX">Sandbox de Pruebas (ProFact Test Server)</option>
                                    <option value="PRODUCCION">Producción (Timbres Oficiales SAT)</option>
                                    <option value="SIMULADO">Simulador Local (Desarrollo Offline)</option>
                                </select>
                                <small class="text-muted" style="font-size: 0.72rem;">En Sandbox no se consumen folios reales del SAT.</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold text-muted">Usuario Integrador (Token ProFact) *</label>
                                <input type="text" class="form-control form-control-sm font-monospace" name="usuario_integrador" id="profactUsuarioIntegradorInput" placeholder="Token asignado por ProFact">
                                <small class="text-muted" style="font-size: 0.72rem;">Proporcionado en tu kit de integración de ProFact.</small>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold text-muted">RFC Emisor *</label>
                                <input type="text" class="form-control form-control-sm text-uppercase" name="rfc_emisor" id="profactRfcEmisorInput" value="NOM210315AB1" required>
                            </div>
                            <div class="col-md-8">
                                <label class="form-label small fw-semibold text-muted">Razón Social Emisor *</label>
                                <input type="text" class="form-control form-control-sm" name="razon_social_emisor" id="profactRazonSocialInput" value="SISTEMA DE NOMINAS Y PERSONAL S.A. DE C.V." required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold text-muted">Régimen Fiscal *</label>
                                <select class="form-select form-select-sm" name="regimen_fiscal_emisor" id="profactRegimenInput">
                                    <option value="601">601 - General de Ley Personas Morales</option>
                                    <option value="605">605 - Sueldos y Salarios e Ingresos Asimilados</option>
                                    <option value="612">612 - Personas Físicas con Actividades Empresariales</option>
                                    <option value="626">626 - Régimen Simplificado de Confianza (RESICO)</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold text-muted">Lugar de Expedición (Código Postal) *</label>
                                <input type="text" class="form-control form-control-sm" name="lugar_expedicion" id="profactLugarExpedicionInput" value="06600" maxlength="5" required>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
                        <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-save"></i> Guardar Configuración</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal: Nueva Factura CFDI 4.0 -->
    <div class="modal fade" id="modalNuevaFactura" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold"><i class="bi bi-file-earmark-plus text-primary me-1"></i> Nueva Factura Electrónica CFDI 4.0</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="formNuevaFactura">
                    <div class="modal-body p-4">
                        <h6 class="fw-bold small text-dark mb-3"><i class="bi bi-building me-1"></i> Datos del Cliente (Receptor)</h6>
                        <div class="row g-3 mb-4">
                            <div class="col-md-8">
                                <label class="form-label small fw-semibold text-muted">Nombre o Razón Social *</label>
                                <input type="text" class="form-control form-control-sm" name="cliente_nombre" placeholder="Ej. EMPRESAS UNIDAS DEL NORTE S.A. DE C.V." required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold text-muted">RFC Cliente *</label>
                                <input type="text" class="form-control form-control-sm text-uppercase" name="cliente_rfc" placeholder="EUN190520AB3" maxlength="13" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold text-muted">Código Postal Receptor *</label>
                                <input type="text" class="form-control form-control-sm" name="cliente_domicilio_fiscal" placeholder="06600" maxlength="5" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold text-muted">Uso de CFDI *</label>
                                <select class="form-select form-select-sm" name="cliente_uso_cfdi" required>
                                    <option value="G03">G03 - Gastos en general</option>
                                    <option value="G01">G01 - Adquisición de mercancías</option>
                                    <option value="G02">G02 - Devoluciones, descuentos o bonificaciones</option>
                                    <option value="P01">P01 - Por definir</option>
                                    <option value="CP01">CP01 - Pagos</option>
                                    <option value="S01">S01 - Sin efectos fiscales</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold text-muted">Régimen Fiscal *</label>
                                <select class="form-select form-select-sm" name="cliente_regimen_fiscal" required>
                                    <option value="601">601 - General de Ley Personas Morales</option>
                                    <option value="612">612 - Personas Físicas con Actividades Empresariales</option>
                                    <option value="626">626 - Régimen Simplificado de Confianza (RESICO)</option>
                                    <option value="603">603 - Personas Morales con Fines no Lucrativos</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold text-muted">Forma de Pago *</label>
                                <select class="form-select form-select-sm" name="forma_pago" required>
                                    <option value="03">03 - Transferencia electrónica de fondos (SPEI)</option>
                                    <option value="01">01 - Efectivo</option>
                                    <option value="04">04 - Tarjeta de crédito</option>
                                    <option value="28">28 - Tarjeta de débito</option>
                                    <option value="99">99 - Por definir</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold text-muted">Método de Pago *</label>
                                <select class="form-select form-select-sm" name="metodo_pago" required>
                                    <option value="PUE">PUE - Pago en una sola exhibición</option>
                                    <option value="PPD">PPD - Pago en parcialidades o diferido</option>
                                </select>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="fw-bold small text-dark mb-0"><i class="bi bi-box-seam me-1"></i> Concepto Facturado</h6>
                        </div>
                        <div class="row g-2 mb-3 bg-light p-3 rounded">
                            <div class="col-md-3">
                                <label class="form-label small fw-semibold text-muted">Clave SAT *</label>
                                <input type="text" class="form-control form-control-sm" name="concepto_clave" value="84111500" placeholder="84111500" required>
                            </div>
                            <div class="col-md-5">
                                <label class="form-label small fw-semibold text-muted">Descripción del Servicio / Producto *</label>
                                <input type="text" class="form-control form-control-sm" name="concepto_descripcion" value="Servicios de procesamiento y cálculo de nómina corporativa" required>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small fw-semibold text-muted">Cantidad *</label>
                                <input type="number" step="1" min="1" class="form-control form-control-sm" name="concepto_cantidad" id="facturaCantidadInput" value="1" required>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small fw-semibold text-muted">Precio Unitario *</label>
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text">$</span>
                                    <input type="number" step="0.01" min="1" class="form-control" name="concepto_valor_unitario" id="facturaPrecioInput" value="12500.00" required>
                                </div>
                            </div>
                        </div>

                        <!-- Resumen de Totales -->
                        <div class="d-flex justify-content-end">
                            <div class="p-3 bg-light rounded text-end" style="min-width: 250px;">
                                <div class="small d-flex justify-content-between">
                                    <span class="text-muted">Subtotal:</span> <strong id="lblFacturaSubtotal">$12,500.00</strong>
                                </div>
                                <div class="small d-flex justify-content-between">
                                    <span class="text-muted">IVA (16%):</span> <strong id="lblFacturaIva">$2,000.00</strong>
                                </div>
                                <div class="fs-6 fw-bold text-success d-flex justify-content-between border-top pt-1 mt-1">
                                    <span>Total:</span> <span id="lblFacturaTotal">$14,500.00 MXN</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-save"></i> Guardar Factura</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal: Visor de Factura CFDI 4.0 (Printable / PDF) -->
    <div class="modal fade" id="modalVisorFactura" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header no-print">
                    <h5 class="modal-title fw-bold"><i class="bi bi-receipt text-primary me-1"></i> Factura Electrónica CFDI 4.0 (PAC ProFact)</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4" id="modalFacturaPrintable">
                    <!-- Dinámico -->
                </div>
                <div class="modal-footer no-print">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
                    <a href="#" class="btn btn-outline-info btn-sm" id="btnDescargarXmlFactura" target="_blank">
                        <i class="bi bi-file-code"></i> Descargar XML
                    </a>
                    <button type="button" class="btn btn-primary btn-sm" onclick="window.print()">
                        <i class="bi bi-printer"></i> Imprimir Factura / Guardar PDF
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5.3 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Cliente Frontend SPA JS -->
    <script src="js/app.js?v=<?php echo time(); ?>"></script>
</body>
</html>
