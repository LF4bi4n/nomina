/**
 * ========================================================
 * SISTEMA DE NÓMINAS Y GESTIÓN DE PERSONAL
 * Archivo: js/app.js
 * Cliente Frontend SPA / Peticiones Fetch API
 * Implementación Completa de los 8 Servicios y Arquitectura
 * ========================================================
 */

// Estado global reactivo de la aplicación
const AppState = {
    currentUser: null,
    empleados: [],
    nominas: [],
    currentNominaId: null,
    incidencias: [],
    notificaciones: [],
    auditLogs: [],
    reglasFiscales: [],
    clockInterval: null,
    currentGpsCoords: null
};

// ========================================================
// 1. INICIALIZACIÓN Y CONFIGURACIÓN DE EVENTOS
// ========================================================
document.addEventListener('DOMContentLoaded', () => {
    initApp();
    setupEventListeners();
});

/**
 * Inicializa la aplicación verificando la sesión activa
 */
async function initApp() {
    startDigitalClock();
    loadClimaWidget(); // Inicializa widget y píldora de clima (OpenWeatherMap)

    try {
        const res = await fetch('api/auth.php', { method: 'GET' });
        const data = await res.json();

        if (data.success && data.data && data.data.usuario) {
            handleLoginSuccess(data.data.usuario);
        } else {
            showLoginSection();
        }
    } catch (err) {
        console.error('Error al inicializar sesión:', err);
        showLoginSection();
    }
}

/**
 * Configura los escuchadores de eventos para formularios y botones
 */
function setupEventListeners() {
    // 1. Login Form Submit
    const loginForm = document.getElementById('loginForm');
    if (loginForm) {
        loginForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const email = document.getElementById('loginEmail').value.trim();
            const password = document.getElementById('loginPassword').value.trim();
            await login(email, password);
        });
    }

    // 2. Demo Buttons (Admin, RH, Auditor, Empleado)
    document.querySelectorAll('.demo-user-btn').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            const email = btn.getAttribute('data-email');
            document.getElementById('loginEmail').value = email;
            document.getElementById('loginPassword').value = '123456';
            login(email, '123456');
        });
    });

    // 3. Toggle Password Visibility
    const togglePassBtn = document.getElementById('togglePasswordBtn');
    if (togglePassBtn) {
        togglePassBtn.addEventListener('click', () => {
            const passInput = document.getElementById('loginPassword');
            const icon = togglePassBtn.querySelector('i');
            if (passInput.type === 'password') {
                passInput.type = 'text';
                icon.className = 'bi bi-eye-slash';
            } else {
                passInput.type = 'password';
                icon.className = 'bi bi-eye';
            }
        });
    }

    // 4. Formulario Nuevo Empleado (HR Core)
    const formNuevoEmpleado = document.getElementById('formNuevoEmpleado');
    if (formNuevoEmpleado) {
        formNuevoEmpleado.addEventListener('submit', handleNuevoEmpleadoSubmit);
    }

    // 5. Formulario Nueva Incidencia (Admin/RH)
    const formNuevaIncidencia = document.getElementById('formNuevaIncidencia');
    if (formNuevaIncidencia) {
        formNuevaIncidencia.addEventListener('submit', handleNuevaIncidenciaSubmit);
    }

    // Toggle campos dinámicos en modal de incidencias
    const incidenciaTipoSelect = document.getElementById('incidenciaTipoSelect');
    if (incidenciaTipoSelect) {
        incidenciaTipoSelect.addEventListener('change', (e) => {
            const boxHE = document.getElementById('boxHorasExtraInput');
            const boxIncap = document.getElementById('boxIncapacidadInput');
            
            boxHE.classList.toggle('d-none', e.target.value !== 'HORA_EXTRA');
            boxIncap.classList.toggle('d-none', e.target.value !== 'INCAPACIDAD');
        });
    }

    // Toggle campos dinámicos en solicitud de empleado
    const solicitudTipoSelect = document.getElementById('solicitudTipoSelect');
    if (solicitudTipoSelect) {
        solicitudTipoSelect.addEventListener('change', (e) => {
            const boxHE = document.getElementById('boxSolicitudHorasExtra');
            const boxIncap = document.getElementById('boxSolicitudIncapacidad');
            
            boxHE.classList.toggle('d-none', e.target.value !== 'HORA_EXTRA');
            boxIncap.classList.toggle('d-none', e.target.value !== 'INCAPACIDAD');
        });
    }

    // 6. Formulario Solicitud Incidencia (Empleado)
    const formSolicitudEmpleado = document.getElementById('formSolicitudEmpleado');
    if (formSolicitudEmpleado) {
        formSolicitudEmpleado.addEventListener('submit', handleSolicitudEmpleadoSubmit);
    }

    // 7. Botón Checador Digital (Marcar Asistencia)
    const btnMarcarAsistencia = document.getElementById('btnMarcarAsistencia');
    if (btnMarcarAsistencia) {
        btnMarcarAsistencia.addEventListener('click', handleMarcarAsistencia);
    }

    // Botón Capturar GPS en Checador
    const btnCapturarGps = document.getElementById('btnCapturarGps');
    if (btnCapturarGps) {
        btnCapturarGps.addEventListener('click', capturarGpsNavegador);
    }

    // 8. Botón Recargar Incidencias
    const btnRecargarIncidencias = document.getElementById('btnRecargarIncidencias');
    if (btnRecargarIncidencias) {
        btnRecargarIncidencias.addEventListener('click', () => loadIncidenciasAdmin());
    }

    // Botón Ver / Cerrar Resumen de Incidencias por Periodo
    const btnVerResumenPeriodo = document.getElementById('btnVerResumenPeriodo');
    if (btnVerResumenPeriodo) {
        btnVerResumenPeriodo.addEventListener('click', toggleResumenPeriodo);
    }
    const btnCerrarResumenPeriodo = document.getElementById('btnCerrarResumenPeriodo');
    if (btnCerrarResumenPeriodo) {
        btnCerrarResumenPeriodo.addEventListener('click', () => {
            document.getElementById('cardResumenIncidenciasPeriodo').classList.add('d-none');
        });
    }

    // 9. Selector de Periodo de Nómina
    const selectPeriodoNomina = document.getElementById('selectPeriodoNomina');
    if (selectPeriodoNomina) {
        selectPeriodoNomina.addEventListener('change', (e) => {
            AppState.currentNominaId = e.target.value;
            loadPreNominaData(AppState.currentNominaId);
        });
    }

    // 10. Formulario Nuevo Periodo
    const formNuevoPeriodo = document.getElementById('formNuevoPeriodo');
    if (formNuevoPeriodo) {
        formNuevoPeriodo.addEventListener('submit', handleNuevoPeriodoSubmit);
    }

    // 11. Botón Ejecutar Cálculo & Congelar Nómina
    const btnEjecutarCalculo = document.getElementById('btnEjecutarCalculo');
    if (btnEjecutarCalculo) {
        btnEjecutarCalculo.addEventListener('click', handleEjecutarCalculo);
    }

    // 12. Botón Timbrar Nómina Fiscal (CFDI 4.0 / PAC)
    const btnTimbrarNomina = document.getElementById('btnTimbrarNomina');
    if (btnTimbrarNomina) {
        btnTimbrarNomina.addEventListener('click', handleTimbrarNomina);
    }

    // 13. Botón Abrir Dispersión
    const btnAbrirDispersion = document.getElementById('btnAbrirDispersion');
    if (btnAbrirDispersion) {
        btnAbrirDispersion.addEventListener('click', () => {
            switchAdminTab('paneDispersion');
        });
    }

    // 14. Botón Ejecutar Dispersión Masiva
    const btnEjecutarDispersionMasiva = document.getElementById('btnEjecutarDispersionMasiva');
    if (btnEjecutarDispersionMasiva) {
        btnEjecutarDispersionMasiva.addEventListener('click', handleEjecutarDispersion);
    }

    // Selector de Convenio Bancario para Layout
    const selectConvenioBanco = document.getElementById('selectConvenioBanco');
    if (selectConvenioBanco) {
        selectConvenioBanco.addEventListener('change', (e) => {
            const banco = e.target.value;
            const btnLayout = document.getElementById('btnDescargarLayoutTxt');
            if (btnLayout && AppState.currentNominaId) {
                btnLayout.href = `api/dispersion.php?nomina_id=${AppState.currentNominaId}&banco=${banco}&download=1&format=txt`;
            }
        });
    }

    // 15. Formulario de Ajuste Manual Justificado (Auditoría)
    const formAjusteManual = document.getElementById('formAjusteManual');
    if (formAjusteManual) {
        formAjusteManual.addEventListener('submit', handleAjusteManualSubmit);
    }

    // 16. Formulario Enviar Comunicado Masivo
    const formEnviarAviso = document.getElementById('formEnviarAviso');
    if (formEnviarAviso) {
        formEnviarAviso.addEventListener('submit', handleEnviarAvisoSubmit);
    }

    // Botón Recargar Notificaciones Admin
    const btnRecargarNotificacionesAdmin = document.getElementById('btnRecargarNotificacionesAdmin');
    if (btnRecargarNotificacionesAdmin) {
        btnRecargarNotificacionesAdmin.addEventListener('click', loadNotificaciones);
    }

    // Botón Marcar Todas Leídas
    const btnMarcarTodasLeidas = document.getElementById('btnMarcarTodasLeidas');
    if (btnMarcarTodasLeidas) {
        btnMarcarTodasLeidas.addEventListener('click', marcarTodasNotificacionesLeidas);
    }

    // 17. Botón Recargar Logs de Auditoría
    const btnRecargarLogs = document.getElementById('btnRecargarLogs');
    if (btnRecargarLogs) {
        btnRecargarLogs.addEventListener('click', loadAuditTrail);
    }

    // 18. Formulario Editar Regla Fiscal
    const formEditarReglaFiscal = document.getElementById('formEditarReglaFiscal');
    if (formEditarReglaFiscal) {
        formEditarReglaFiscal.addEventListener('submit', handleEditarReglaFiscalSubmit);
    }

    // 19. Consola Interactiva GraphQL
    const btnEjecutarGraphQL = document.getElementById('btnEjecutarGraphQL');
    if (btnEjecutarGraphQL) {
        btnEjecutarGraphQL.addEventListener('click', handleEjecutarGraphQLQuery);
    }
    const btnCargarQueryEjemplo = document.getElementById('btnCargarQueryEjemplo');
    if (btnCargarQueryEjemplo) {
        btnCargarQueryEjemplo.addEventListener('click', cargarQueryGraphQLPagina8);
    }

    // Botón Recargar Eventos del Broker
    const btnRecargarEventosBroker = document.getElementById('btnRecargarEventosBroker');
    if (btnRecargarEventosBroker) {
        btnRecargarEventosBroker.addEventListener('click', loadMessageBrokerEvents);
    }

    // 20. Botón Descargar Constancia Laboral del Empleado (Autoconsulta)
    const btnDescargarMiConstancia = document.getElementById('btnDescargarMiConstancia');
    if (btnDescargarMiConstancia) {
        btnDescargarMiConstancia.addEventListener('click', () => {
            if (AppState.currentUser && AppState.currentUser.empleado_id) {
                generarYVerConstanciaLaboral(AppState.currentUser.empleado_id);
            }
        });
    }

    // 21. Búsqueda rápida de empleados en tabla
    const searchEmpleadoInput = document.getElementById('searchEmpleadoInput');
    if (searchEmpleadoInput) {
        searchEmpleadoInput.addEventListener('input', (e) => {
            const query = e.target.value.toLowerCase().trim();
            const filtered = AppState.empleados.filter(emp => 
                emp.nombre.toLowerCase().includes(query) || 
                emp.puesto.toLowerCase().includes(query) || 
                emp.rfc.toLowerCase().includes(query) ||
                emp.departamento.toLowerCase().includes(query)
            );
            renderEmpleadosTable(filtered);
        });
    }

    // 22. ProFact PAC & Facturación Eventos
    const btnTestConexionProfact = document.getElementById('btnTestConexionProfact');
    if (btnTestConexionProfact) {
        btnTestConexionProfact.addEventListener('click', testConexionProfactLive);
    }
    const formProfactConfig = document.getElementById('formProfactConfig');
    if (formProfactConfig) {
        formProfactConfig.addEventListener('submit', guardarProfactConfig);
    }
    const btnRecargarFacturas = document.getElementById('btnRecargarFacturas');
    if (btnRecargarFacturas) {
        btnRecargarFacturas.addEventListener('click', loadFacturasData);
    }
    const formNuevaFactura = document.getElementById('formNuevaFactura');
    if (formNuevaFactura) {
        formNuevaFactura.addEventListener('submit', guardarNuevaFactura);
    }
    const facturaCantidadInput = document.getElementById('facturaCantidadInput');
    const facturaPrecioInput = document.getElementById('facturaPrecioInput');
    if (facturaCantidadInput && facturaPrecioInput) {
        const updateTot = () => {
            const cant = parseFloat(facturaCantidadInput.value) || 0;
            const precio = parseFloat(facturaPrecioInput.value) || 0;
            const sub = cant * precio;
            const iva = sub * 0.16;
            const tot = sub + iva;
            const lblSub = document.getElementById('lblFacturaSubtotal');
            const lblIva = document.getElementById('lblFacturaIva');
            const lblTot = document.getElementById('lblFacturaTotal');
            if (lblSub) lblSub.textContent = '$' + formatMoney(sub);
            if (lblIva) lblIva.textContent = '$' + formatMoney(iva);
            if (lblTot) lblTot.textContent = '$' + formatMoney(tot) + ' MXN';
        };
        facturaCantidadInput.addEventListener('input', updateTot);
        facturaPrecioInput.addEventListener('input', updateTot);
    }

    // 25. Controladores del Widget de Clima (OpenWeatherMap API)
    const weatherCitySelect = document.getElementById('weatherCitySelect');
    if (weatherCitySelect) {
        weatherCitySelect.addEventListener('change', (e) => {
            const val = e.target.value;
            if (val === 'custom') {
                const customCity = prompt('Ingresa el nombre de la ciudad a consultar (ej. Toluca, Mazatlán, Miami):');
                if (customCity && customCity.trim()) {
                    loadClimaWidget(customCity.trim());
                } else {
                    weatherCitySelect.value = currentWeatherCity;
                }
            } else {
                loadClimaWidget(val);
            }
        });
    }

    const btnWeatherGps = document.getElementById('btnWeatherGps');
    if (btnWeatherGps) {
        btnWeatherGps.addEventListener('click', () => {
            if (!navigator.geolocation) {
                showToast('Geolocalización no soportada por el navegador.', 'warning');
                return;
            }
            showToast('Obteniendo ubicación GPS para clima local...', 'info');
            navigator.geolocation.getCurrentPosition(
                (pos) => {
                    loadClimaWidget(null, pos.coords.latitude, pos.coords.longitude);
                    showToast('Clima actualizado con tu ubicación GPS.', 'success');
                },
                (err) => {
                    showToast('No se pudo acceder a tu ubicación GPS.', 'warning');
                },
                { timeout: 7000 }
            );
        });
    }

    const btnWeatherRefresh = document.getElementById('btnWeatherRefresh');
    if (btnWeatherRefresh) {
        btnWeatherRefresh.addEventListener('click', () => {
            loadClimaWidget();
            showToast('Clima actualizado desde OpenWeatherMap.', 'info');
        });
    }

    const navWeatherPill = document.getElementById('navWeatherPill');
    if (navWeatherPill) {
        navWeatherPill.addEventListener('click', () => {
            loadClimaWidget();
            showToast(`Clima actual: ${document.getElementById('weatherTempDisplay')?.textContent || '24°C'} en ${currentWeatherCity}`, 'info');
        });
    }
}

// ========================================================
// 2. AUTENTICACIÓN Y SESIÓN RBAC
// ========================================================

/**
 * Procesa el inicio de sesión contra api/auth.php
 */
async function login(email, password) {
    const btn = document.getElementById('btnLoginSubmit');
    const originalContent = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = `<span class="spinner-border spinner-border-sm" role="status"></span> Validando credenciales...`;

    try {
        const res = await fetch('api/auth.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ email, password })
        });
        const data = await res.json();

        if (data.success && data.data && data.data.usuario) {
            showToast('¡Bienvenido! ' + data.message, 'success');
            handleLoginSuccess(data.data.usuario);
        } else {
            showToast(data.message || 'Error en las credenciales.', 'danger');
        }
    } catch (err) {
        console.error('Error al iniciar sesión:', err);
        showToast('Error de conexión con el servidor.', 'danger');
    } finally {
        btn.disabled = false;
        btn.innerHTML = originalContent;
    }
}

/**
 * Configura la UI una vez que el usuario ha iniciado sesión correctamente
 */
function handleLoginSuccess(user) {
    AppState.currentUser = user;

    document.getElementById('loginSection').classList.add('d-none');
    document.getElementById('appSection').classList.remove('d-none');

    // Actualizar datos del usuario en Navbar
    document.getElementById('navUserName').textContent = user.nombre || user.email;
    document.getElementById('navUserRoleDesc').textContent = 'Rol: ' + user.rol;
    document.getElementById('navBadgeRole').textContent = user.rol;

    renderNavigationMenu(user.rol);
    loadNotificaciones();
    cargarProfactConfigModal();

    if (user.rol === 'ADMIN' || user.rol === 'RH' || user.rol === 'AUDITOR') {
        document.getElementById('viewAdminRH').classList.remove('d-none');
        document.getElementById('viewEmpleado').classList.add('d-none');
        
        // Tab inicial según rol
        const defaultTab = (user.rol === 'AUDITOR') ? 'paneCalculo' : 'paneEmpleados';
        switchAdminTab(defaultTab);

        loadEmpleadosAdmin();
        loadIncidenciasAdmin();
        loadPreNominaData();
        loadFacturasData();
        loadAuditTrail();
        loadReglasFiscales();
        cargarQueryGraphQLPagina8();
        loadMessageBrokerEvents();
    } else {
        document.getElementById('viewAdminRH').classList.add('d-none');
        document.getElementById('viewEmpleado').classList.remove('d-none');
        loadEmpleadoPortalData();
    }
}

/**
 * Muestra la pantalla de inicio de sesión
 */
function showLoginSection() {
    AppState.currentUser = null;
    document.getElementById('loginSection').classList.remove('d-none');
    document.getElementById('appSection').classList.add('d-none');
}

/**
 * Construye dinámicamente las pestañas de navegación según el rol
 */
function renderNavigationMenu(rol) {
    const navTabs = document.getElementById('navTabsContainer');
    if (!navTabs) return;
    navTabs.innerHTML = '';

    if (rol === 'ADMIN' || rol === 'RH' || rol === 'AUDITOR') {
        const menuItems = [
            { id: 'paneEmpleados', num: '1', icon: 'bi-people', label: 'Personal & HR Core', allowed: ['ADMIN', 'RH'] },
            { id: 'paneIncidencias', num: '2', icon: 'bi-calendar-check', label: 'Asistencias & Checador', allowed: ['ADMIN', 'RH'] },
            { id: 'paneCalculo', num: '3', icon: 'bi-calculator', label: 'Cálculo & Pre-nómina', allowed: ['ADMIN', 'RH', 'AUDITOR'] },
            { id: 'paneFacturacion', num: '4', icon: 'bi-file-earmark-text', label: 'Facturación ProFact', allowed: ['ADMIN', 'RH', 'AUDITOR'] },
            { id: 'paneDispersion', num: '5', icon: 'bi-bank', label: 'Dispersión SPEI', allowed: ['ADMIN', 'RH', 'AUDITOR'] },
            { id: 'paneNotificaciones', num: '6', icon: 'bi-bell', label: 'Notificaciones', allowed: ['ADMIN', 'RH'] },
            { id: 'paneAuditoria', num: '7', icon: 'bi-shield-check', label: 'Auditoría & Reglas', allowed: ['ADMIN', 'AUDITOR'] },
            { id: 'paneGraphQL', num: '8', icon: 'bi-cpu', label: 'GraphQL & Broker', allowed: ['ADMIN', 'RH', 'AUDITOR'] }
        ];

        menuItems.forEach((item) => {
            if (item.allowed.includes(rol)) {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'app-tab-btn';
                btn.setAttribute('data-pane', item.id);
                btn.innerHTML = `
                    <span class="badge-num">${item.num}</span>
                    <i class="bi ${item.icon}"></i>
                    <span>${item.label}</span>
                `;
                btn.addEventListener('click', (e) => {
                    e.preventDefault();
                    switchAdminTab(item.id);
                });
                navTabs.appendChild(btn);
            }
        });
    } else {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'app-tab-btn active';
        btn.innerHTML = `
            <i class="bi bi-person-badge"></i>
            <span>Portal del Colaborador (Autoconsulta)</span>
        `;
        navTabs.appendChild(btn);
    }
}

/**
 * Cambia de pestaña activa dentro del panel administrativo
 */
function switchAdminTab(targetPaneId) {
    // 1. Ocultar todos los panes
    document.querySelectorAll('.admin-tab-pane').forEach(pane => {
        pane.classList.add('d-none');
    });

    // 2. Mostrar el pane objetivo
    const target = document.getElementById(targetPaneId);
    if (target) {
        target.classList.remove('d-none');
    }

    // 3. Actualizar estado en los botones de pestañas (.app-tab-btn)
    document.querySelectorAll('#navTabsContainer .app-tab-btn').forEach(btn => {
        if (btn.getAttribute('data-pane') === targetPaneId) {
            btn.classList.add('active');
        } else {
            btn.classList.remove('active');
        }
    });

    // 4. Si es paneCalculo, actualizar mini stepper con el estatus de la nómina
    if (targetPaneId === 'paneCalculo' && AppState.nominas && AppState.nominas.length > 0) {
        const curr = AppState.nominas.find(n => n.id == AppState.currentNominaId);
        if (curr) {
            updatePayrollFlowMiniStepper(curr.estatus);
        }
    }

    // 5. Cargar datos contextuales si es necesario
    if (targetPaneId === 'paneFacturacion') {
        loadFacturasData();
    } else if (targetPaneId === 'paneDispersion') {
        loadDispersionData(AppState.currentNominaId);
    } else if (targetPaneId === 'paneCalculo') {
        loadPreNominaData(AppState.currentNominaId);
    } else if (targetPaneId === 'paneAuditoria') {
        loadAuditTrail();
        loadReglasFiscales();
    } else if (targetPaneId === 'paneGraphQL') {
        loadMessageBrokerEvents();
    }
}

/**
 * Actualiza visualmente el mini-stepper contextual del flujo de nómina
 */
function updatePayrollFlowMiniStepper(estatus) {
    const stepCalc = document.getElementById('miniStepCalc');
    const stepTimbre = document.getElementById('miniStepTimbre');
    const stepDisp = document.getElementById('miniStepDisp');
    if (!stepCalc || !stepTimbre || !stepDisp) return;

    stepCalc.className = 'payroll-flow-step';
    stepTimbre.className = 'payroll-flow-step';
    stepDisp.className = 'payroll-flow-step';

    if (estatus === 'BORRADOR') {
        stepCalc.classList.add('active');
    } else if (estatus === 'CONGELADA') {
        stepCalc.classList.add('completed');
        stepTimbre.classList.add('active');
    } else if (estatus === 'TIMBRADA') {
        stepCalc.classList.add('completed');
        stepTimbre.classList.add('completed');
        stepDisp.classList.add('active');
    } else if (estatus === 'DISPERSADA') {
        stepCalc.classList.add('completed');
        stepTimbre.classList.add('completed');
        stepDisp.classList.add('completed');
    }
}

// ========================================================
// 3. NOTIFICACIONES Y EVENTOS EN TIEMPO REAL
// ========================================================

/**
 * Consulta la bandeja de notificaciones y actualiza la campana
 */
async function loadNotificaciones() {
    try {
        const res = await fetch('api/notificaciones.php');
        const data = await res.json();

        if (data.success && data.data) {
            AppState.notificaciones = data.data.notificaciones || [];
            const unread = data.data.no_leidas || 0;
            const badge = document.getElementById('notifBadgeCount');

            if (unread > 0) {
                badge.textContent = unread;
                badge.classList.remove('d-none');
            } else {
                badge.classList.add('d-none');
            }

            renderNotificacionesDropdown(AppState.notificaciones);
            renderNotificacionesAdminTable(AppState.notificaciones);
        }
    } catch (err) {
        console.error('Error al cargar notificaciones:', err);
    }
}

/**
 * Renderiza el menú desplegable de la campana
 */
function renderNotificacionesDropdown(notifs) {
    const container = document.getElementById('notifItemsContainer');
    if (!container) return;

    if (!notifs || notifs.length === 0) {
        container.innerHTML = `<div class="p-3 text-center text-muted small">No tienes notificaciones pendientes.</div>`;
        return;
    }

    container.innerHTML = notifs.slice(0, 7).map(n => `
        <div class="notif-item ${!n.leido ? 'unread' : ''}" onclick="marcarNotificacionLeida(${n.id})">
            <div class="d-flex justify-content-between align-items-center mb-1">
                <span class="badge ${getNotifBadgeClass(n.tipo_evento)}" style="font-size: 0.65rem;">${n.tipo_evento}</span>
                <span class="text-muted" style="font-size: 0.68rem;">${formatRelativeTime(n.created_at)}</span>
            </div>
            <div class="fw-semibold text-dark small">${escapeHtml(n.titulo)}</div>
            <div class="text-muted small text-truncate" style="font-size: 0.75rem;">${escapeHtml(n.mensaje)}</div>
        </div>
    `).join('');
}

/**
 * Renderiza la tabla completa de notificaciones en el panel administrativo
 */
function renderNotificacionesAdminTable(notifs) {
    const tbody = document.getElementById('tablaNotificacionesAdminBody');
    if (!tbody) return;

    if (!notifs || notifs.length === 0) {
        tbody.innerHTML = `<tr><td colspan="5" class="text-center py-4 text-muted">No hay notificaciones emitidas aún.</td></tr>`;
        return;
    }

    tbody.innerHTML = notifs.map(n => `
        <tr>
            <td class="small fw-semibold">${n.created_at}</td>
            <td>
                <span class="badge ${getNotifBadgeClass(n.tipo_evento)}">${n.tipo_evento}</span>
                <span class="badge bg-light text-dark border ms-1" style="font-size: 0.7rem;">${n.canal}</span>
            </td>
            <td class="fw-bold text-dark">${escapeHtml(n.titulo)}</td>
            <td class="small text-muted">${escapeHtml(n.mensaje)}</td>
            <td>
                ${n.leido ? '<span class="badge bg-light text-success border"><i class="bi bi-check2-all"></i> Leído</span>' : '<span class="badge bg-warning text-dark">No leído</span>'}
            </td>
        </tr>
    `).join('');
}

function getNotifBadgeClass(tipo) {
    switch (tipo) {
        case 'NominaAprobada': return 'bg-purple text-white';
        case 'ReciboTimbrado': return 'bg-success';
        case 'DispersionEjecutada': return 'bg-primary';
        case 'NUEVA_SOLICITUD': return 'bg-info text-dark';
        default: return 'bg-secondary';
    }
}

/**
 * Marca una notificación como leída
 */
async function marcarNotificacionLeida(id) {
    try {
        await fetch('api/notificaciones.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'marcar_leida', id })
        });
        loadNotificaciones();
    } catch (e) {
        console.error(e);
    }
}

/**
 * Marca todas las notificaciones como leídas
 */
async function marcarTodasNotificacionesLeidas() {
    try {
        await fetch('api/notificaciones.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'marcar_todas_leidas' })
        });
        showToast('Todas las notificaciones marcadas como leídas.', 'info');
        loadNotificaciones();
    } catch (e) {
        console.error(e);
    }
}

/**
 * Envía un aviso general
 */
async function handleEnviarAvisoSubmit(e) {
    e.preventDefault();
    const form = e.target;
    const formData = new FormData(form);
    const payload = Object.fromEntries(formData.entries());
    payload.action = 'enviar_aviso';

    try {
        const res = await fetch('api/notificaciones.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await res.json();

        if (data.success) {
            showToast(data.message, 'success');
            form.reset();
            loadNotificaciones();
        } else {
            showToast(data.message || 'Error al emitir comunicado.', 'danger');
        }
    } catch (err) {
        console.error(err);
        showToast('Error de comunicación.', 'danger');
    }
}

// ========================================================
// 4. SERVICIO 1: HR CORE (EMPLEADOS & EXPEDIENTES)
// ========================================================

/**
 * Carga el catálogo de empleados
 */
async function loadEmpleadosAdmin() {
    const tbody = document.getElementById('tablaEmpleadosBody');
    tbody.innerHTML = `<tr><td colspan="8" class="text-center py-4 text-muted"><span class="spinner-border spinner-border-sm me-2"></span>Cargando catálogo maestro de empleados...</td></tr>`;

    try {
        const res = await fetch('api/empleados.php');
        const data = await res.json();

        if (data.success && data.data && data.data.empleados) {
            AppState.empleados = data.data.empleados;
            renderEmpleadosTable(AppState.empleados);
            populateIncidenciaSelect(AppState.empleados);
            updateHRKpis(AppState.empleados);
        } else {
            tbody.innerHTML = `<tr><td colspan="8" class="text-center py-4 text-danger">${data.message || 'No se pudieron cargar los empleados.'}</td></tr>`;
        }
    } catch (err) {
        console.error('Error al cargar empleados:', err);
        tbody.innerHTML = `<tr><td colspan="8" class="text-center py-4 text-danger">Error de comunicación con el servidor.</td></tr>`;
    }
}

/**
 * Renderiza los empleados en la tabla principal de HR Core
 */
function renderEmpleadosTable(empleados) {
    const tbody = document.getElementById('tablaEmpleadosBody');
    if (!empleados || empleados.length === 0) {
        tbody.innerHTML = `<tr><td colspan="8" class="text-center py-4 text-muted">No hay empleados registrados. Presiona "Nuevo Empleado".</td></tr>`;
        return;
    }

    tbody.innerHTML = empleados.map((emp, idx) => `
        <tr>
            <td class="text-muted small fw-bold">${idx + 1}</td>
            <td>
                <div class="fw-bold text-dark">${escapeHtml(emp.nombre)}</div>
                <div class="text-muted small">${escapeHtml(emp.email || 'Sin usuario web')}</div>
            </td>
            <td>
                <div class="fw-semibold">${escapeHtml(emp.puesto)}</div>
                <span class="badge bg-light text-dark border" style="font-size: 0.72rem;">${escapeHtml(emp.departamento)}</span>
                <span class="badge bg-light text-primary border ms-1" style="font-size: 0.68rem;">${escapeHtml(emp.tipo_contrato || 'INDETERMINADO')}</span>
            </td>
            <td>
                <div class="font-monospace small fw-bold text-dark">${escapeHtml(emp.rfc)}</div>
                <div class="font-monospace text-muted" style="font-size: 0.72rem;">${escapeHtml(emp.curp)}</div>
            </td>
            <td>
                <div class="fw-bold text-success">$${formatMoney(emp.salario_diario)} <span class="text-muted fw-normal" style="font-size: 0.7rem;">/día</span></div>
                <div class="text-muted" style="font-size: 0.72rem;">SDI: $${formatMoney(emp.salario_diario_integrado)}</div>
            </td>
            <td>
                <span class="font-monospace small text-primary bg-light px-2 py-1 rounded border">${escapeHtml(emp.cuenta_clabe)}</span>
                <div class="text-muted" style="font-size: 0.72rem;">${escapeHtml(emp.banco || 'BBVA MEXICO')}</div>
            </td>
            <td>
                <span class="badge-status ${emp.estatus === 'ACTIVO' ? 'badge-timbrada' : 'badge-borrador'}">
                    ${emp.estatus}
                </span>
            </td>
            <td class="text-end">
                <div class="btn-group">
                    <button class="btn btn-sm btn-outline-primary" onclick="verExpedienteEmpleado(${emp.id})" title="Expediente Digital">
                        <i class="bi bi-person-vcard"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-success" onclick="generarYVerConstanciaLaboral(${emp.id})" title="Constancia Laboral Membretada">
                        <i class="bi bi-award"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-warning text-dark" onclick="abrirAjusteSalario(${emp.id}, '${escapeHtml(emp.nombre)}', ${emp.salario_diario})" title="Ajuste de Salario Justificado">
                        <i class="bi bi-cash"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-danger" onclick="cambiarEstatusEmpleado(${emp.id}, '${emp.estatus === 'ACTIVO' ? 'INACTIVO' : 'ACTIVO'}')" title="${emp.estatus === 'ACTIVO' ? 'Baja' : 'Reingreso'}">
                        <i class="bi ${emp.estatus === 'ACTIVO' ? 'bi-person-dash' : 'bi-person-check'}"></i>
                    </button>
                </div>
            </td>
        </tr>
    `).join('');
}

/**
 * Actualiza métricas de HR Core
 */
function updateHRKpis(empleados) {
    const total = empleados.length;
    document.getElementById('kpiTotalEmpleados').textContent = total;

    if (total > 0) {
        const sumSalarios = empleados.reduce((acc, curr) => acc + (parseFloat(curr.salario_diario) || 0), 0);
        const promedio = sumSalarios / total;
        document.getElementById('kpiSalarioPromedio').textContent = '$' + formatMoney(promedio);
    } else {
        document.getElementById('kpiSalarioPromedio').textContent = '$0.00';
    }
}

/**
 * Llena selector de empleados
 */
function populateIncidenciaSelect(empleados) {
    const select = document.getElementById('incidenciaEmpleadoSelect');
    if (!select) return;

    select.innerHTML = '<option value="">Seleccione un empleado...</option>' + 
        empleados.map(emp => `
            <option value="${emp.id}">${escapeHtml(emp.nombre)} - ${escapeHtml(emp.puesto)} (${emp.rfc})</option>
        `).join('');
}

/**
 * Ver Expediente Digital Completo
 */
async function verExpedienteEmpleado(id) {
    const modalEl = document.getElementById('modalExpediente');
    const modalContent = document.getElementById('modalExpedienteContent');
    const modalInstance = new bootstrap.Modal(modalEl);

    modalContent.innerHTML = `<div class="text-center py-5"><span class="spinner-border spinner-border-sm me-2"></span>Cargando expediente digital completo...</div>`;
    modalInstance.show();

    try {
        const res = await fetch(`api/empleados.php?id=${id}`);
        const data = await res.json();

        if (data.success && data.data) {
            const emp = data.data;
            modalContent.innerHTML = `
                <div class="row g-4">
                    <div class="col-md-4 text-center border-end">
                        <div class="navbar-brand-logo mx-auto mb-3" style="width: 72px; height: 72px; font-size: 2rem;">
                            <i class="bi bi-person"></i>
                        </div>
                        <h5 class="fw-bold mb-1">${escapeHtml(emp.nombre)}</h5>
                        <p class="text-muted small mb-2">${escapeHtml(emp.puesto)}</p>
                        <span class="badge bg-primary px-3 py-1 mb-3">${escapeHtml(emp.departamento)}</span>
                        
                        <div class="card bg-light border-0 p-3 text-start small">
                            <div class="mb-1"><span class="text-muted">Fecha de Ingreso:</span> <strong>${emp.fecha_ingreso}</strong></div>
                            <div class="mb-1"><span class="text-muted">Tipo Contrato:</span> <strong>${escapeHtml(emp.tipo_contrato || 'INDETERMINADO')}</strong></div>
                            <div class="mb-1"><span class="text-muted">Estatus Laboral:</span> <strong class="text-success">${emp.estatus}</strong></div>
                            <div class="mb-1"><span class="text-muted">Banco Receptor:</span> <strong>${escapeHtml(emp.banco)}</strong></div>
                            <div><span class="text-muted">Correo:</span> <strong>${escapeHtml(emp.email || 'N/A')}</strong></div>
                        </div>

                        <div class="mt-3">
                            <button class="btn btn-outline-success btn-sm w-100" onclick="generarYVerConstanciaLaboral(${emp.id})">
                                <i class="bi bi-award"></i> Constancia Laboral
                            </button>
                        </div>
                    </div>

                    <div class="col-md-8">
                        <h6 class="fw-bold border-bottom pb-2 mb-3 text-primary"><i class="bi bi-bank me-1"></i> Datos Salariales, Fiscales & Vacaciones</h6>
                        <div class="row g-2 small mb-4">
                            <div class="col-6"><span class="text-muted">RFC:</span> <span class="fw-bold font-monospace">${escapeHtml(emp.rfc)}</span></div>
                            <div class="col-6"><span class="text-muted">CURP:</span> <span class="fw-bold font-monospace">${escapeHtml(emp.curp)}</span></div>
                            <div class="col-12 mt-2"><span class="text-muted">Cuenta CLABE (18 dígitos):</span> <span class="fw-bold font-monospace text-primary bg-light p-1 rounded">${escapeHtml(emp.cuenta_clabe)}</span></div>
                            <div class="col-4 mt-2"><span class="text-muted">Salario Diario:</span> <div class="fs-6 fw-bold text-success">$${formatMoney(emp.salario_diario)}</div></div>
                            <div class="col-4 mt-2"><span class="text-muted">Salario Diario Int. (SDI):</span> <div class="fs-6 fw-bold text-dark">$${formatMoney(emp.salario_diario_integrado)}</div></div>
                            <div class="col-4 mt-2"><span class="text-muted">Vacaciones Disp. (LFT):</span> <div class="fs-6 fw-bold text-primary">${emp.dias_vacaciones_disponibles} días</div></div>
                        </div>

                        <h6 class="fw-bold border-bottom pb-2 mb-3 text-primary"><i class="bi bi-clock-history me-1"></i> Historial Reciente de Incidencias</h6>
                        <div class="table-responsive small">
                            <table class="table table-sm table-striped">
                                <thead>
                                    <tr>
                                        <th>Fecha</th>
                                        <th>Tipo</th>
                                        <th>Modalidad / Folio</th>
                                        <th>Estatus</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${emp.incidencias_recientes && emp.incidencias_recientes.length > 0 ? 
                                        emp.incidencias_recientes.map(inc => `
                                            <tr>
                                                <td>${inc.fecha}</td>
                                                <td><strong>${inc.tipo}</strong></td>
                                                <td>${inc.modalidad || inc.tipo_incapacidad || (inc.horas_extra > 0 ? inc.horas_extra + ' hrs' : '-')}</td>
                                                <td><span class="badge ${inc.estatus === 'APROBADO' ? 'bg-success' : 'bg-warning'}">${inc.estatus}</span></td>
                                            </tr>
                                        `).join('') : `<tr><td colspan="4" class="text-muted text-center">Sin incidencias registradas.</td></tr>`}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            `;
        }
    } catch (err) {
        console.error(err);
        modalContent.innerHTML = `<div class="alert alert-danger">Error al cargar expediente digital.</div>`;
    }
}

/**
 * Genera y muestra la Constancia Laboral Oficial Membretada (Requerimiento de la Página 4 y 6)
 */
async function generarYVerConstanciaLaboral(empId) {
    const modalEl = document.getElementById('modalConstanciaLaboral');
    const printable = document.getElementById('modalConstanciaPrintable');
    const modalInstance = new bootstrap.Modal(modalEl);

    printable.innerHTML = `<div class="text-center py-5"><span class="spinner-border spinner-border-sm me-2"></span>Generando constancia laboral oficial con sello digital...</div>`;
    modalInstance.show();

    try {
        const res = await fetch(`api/empleados.php?id=${empId}&action=constancia`);
        const data = await res.json();

        if (data.success && data.data) {
            const c = data.data;
            printable.innerHTML = `
                <div class="constancia-paper">
                    <div class="constancia-watermark">VALIDEZ OFICIAL</div>

                    <!-- Membrete Corporativo -->
                    <div class="constancia-header d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="fw-bold text-dark mb-0">${c.empresa.razon_social}</h5>
                            <div class="small text-muted">RFC: <strong>${c.empresa.rfc}</strong> | Registro Patronal IMSS: <strong>${c.empresa.registro_patronal}</strong></div>
                            <div class="small text-muted" style="font-size: 0.8rem;">${c.empresa.direccion}</div>
                        </div>
                        <div class="text-end">
                            <div class="badge bg-primary fs-6 px-3 py-1">CONSTANCIA LABORAL</div>
                            <div class="small text-muted mt-1 font-monospace">Folio: <strong>${c.certificacion.folio}</strong></div>
                        </div>
                    </div>

                    <div class="text-end mb-4 small">
                        <strong>Ciudad de México, a ${c.certificacion.fecha_emision}</strong>
                    </div>

                    <h5 class="fw-bold text-center mb-4 text-uppercase">A QUIEN CORRESPONDA:</h5>

                    <p style="text-align: justify; font-size: 1.05rem; line-height: 1.8;">
                        Por medio de la presente, la empresa <strong>${c.empresa.razon_social}</strong> hace constar que el (la) 
                        C. <strong>${c.empleado.nombre}</strong>, con RFC <strong>${c.empleado.rfc}</strong> y CURP <strong>${c.empleado.curp}</strong>, 
                        labora para esta institución desde el día <strong>${c.empleado.fecha_ingreso}</strong> (antigüedad de ${c.empleado.antiguedad}), 
                        desempeñando a la fecha el puesto de <strong>${c.empleado.puesto}</strong> en el departamento de 
                        <strong>${c.empleado.departamento}</strong>, bajo un contrato de <strong>${c.empleado.tipo_contrato}</strong>.
                    </p>

                    <p style="text-align: justify; font-size: 1.05rem; line-height: 1.8;">
                        Asimismo, se certifica que percibe un salario diario registrado de <strong>$${formatMoney(c.empleado.salario_diario)} MXN</strong>, 
                        equivalente a un ingreso mensual aproximado de <strong>$${formatMoney(c.empleado.salario_mensual)} MXN</strong>, 
                        el cual le es cubierto quincenalmente mediante dispersión bancaria a su cuenta CLABE 
                        <strong>${c.empleado.cuenta_clabe}</strong> de la entidad financiera <strong>${c.empleado.banco}</strong>.
                    </p>

                    <p style="text-align: justify; font-size: 0.95rem; line-height: 1.6;" class="text-muted mt-4">
                        ${c.certificacion.leyenda}
                    </p>

                    <!-- Firmas y Sello Criptográfico -->
                    <div class="row align-items-center mt-5 pt-3">
                        <div class="col-8 text-center">
                            <div style="border-top: 1px solid #0f172a; width: 280px; margin: 0 auto; padding-top: 5px;">
                                <strong>${c.empresa.representante}</strong>
                                <div class="small text-muted">${c.empresa.cargo_rep}</div>
                                <div class="small text-muted">${c.empresa.razon_social}</div>
                            </div>
                        </div>
                        <div class="col-4 text-center">
                            <div class="qr-placeholder mx-auto">
                                <i class="bi bi-qr-code fs-1 text-dark"></i>
                                <span style="font-size: 0.6rem;">VALIDACIÓN SAT/RRHH</span>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 pt-3 border-top small text-muted font-monospace" style="font-size: 0.65rem;">
                        <strong>Sello Digital de Autenticidad:</strong> ${c.certificacion.sello_digital}<br>
                        Documento emitido electrónicamente con validez jurídica y verificación en línea de auditoría.
                    </div>
                </div>
            `;
        } else {
            printable.innerHTML = `<div class="alert alert-danger">${data.message || 'Error al generar constancia.'}</div>`;
        }
    } catch (err) {
        console.error(err);
        printable.innerHTML = `<div class="alert alert-danger">Error de comunicación.</div>`;
    }
}

/**
 * Ajuste Salarial Justificado
 */
async function abrirAjusteSalario(empId, nombre, salarioActual) {
    const nuevoSalario = prompt(`Ajuste de Salario para ${nombre}\nSalario diario actual: $${salarioActual}\n\nIngresa el nuevo salario diario (MXN):`, salarioActual);
    if (!nuevoSalario || parseFloat(nuevoSalario) <= 0 || parseFloat(nuevoSalario) === parseFloat(salarioActual)) {
        return;
    }

    const motivo = prompt('Por motivos de Auditoría y Trazabilidad, ingresa la justificación legal/laboral del ajuste:');
    if (!motivo || motivo.trim() === '') {
        showToast('La justificación es obligatoria para el Audit Trail.', 'warning');
        return;
    }

    try {
        const res = await fetch('api/empleados.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'actualizar_salario',
                id: empId,
                salario_diario: parseFloat(nuevoSalario),
                motivo: motivo.trim()
            })
        });
        const data = await res.json();

        if (data.success) {
            showToast(data.message, 'success');
            loadEmpleadosAdmin();
            loadAuditTrail();
        } else {
            showToast(data.message || 'Error al actualizar salario.', 'danger');
        }
    } catch (err) {
        console.error(err);
        showToast('Error de comunicación.', 'danger');
    }
}

/**
 * Cambiar Estatus Empleado (Baja / Reingreso)
 */
async function cambiarEstatusEmpleado(empId, nuevoEstatus) {
    const motivo = prompt(`¿Deseas cambiar el estatus a ${nuevoEstatus}?\n\nIngresa el motivo del cambio para el Audit Trail:`, nuevoEstatus === 'INACTIVO' ? 'Término de relación laboral' : 'Recontratación');
    if (!motivo) return;

    try {
        const res = await fetch('api/empleados.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'cambiar_estatus',
                id: empId,
                estatus: nuevoEstatus,
                motivo: motivo
            })
        });
        const data = await res.json();

        if (data.success) {
            showToast(data.message, 'success');
            loadEmpleadosAdmin();
            loadAuditTrail();
        } else {
            showToast(data.message || 'Error al cambiar estatus.', 'danger');
        }
    } catch (err) {
        console.error(err);
        showToast('Error de comunicación.', 'danger');
    }
}

/**
 * Registra un nuevo empleado
 */
async function handleNuevoEmpleadoSubmit(e) {
    e.preventDefault();
    const form = e.target;
    const formData = new FormData(form);
    const payload = Object.fromEntries(formData.entries());

    if (!payload.cuenta_clabe || payload.cuenta_clabe.length !== 18 || !/^\d+$/.test(payload.cuenta_clabe)) {
        showToast('La cuenta CLABE debe contener exactamente 18 dígitos numéricos.', 'danger');
        return;
    }

    try {
        const res = await fetch('api/empleados.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await res.json();

        if (data.success) {
            showToast(data.message, 'success');
            form.reset();
            const modalEl = document.getElementById('modalNuevoEmpleado');
            bootstrap.Modal.getInstance(modalEl).hide();
            loadEmpleadosAdmin();
            loadAuditTrail();
        } else {
            showToast(data.message || 'Error al registrar empleado.', 'danger');
        }
    } catch (err) {
        console.error(err);
        showToast('Error de comunicación con el servidor.', 'danger');
    }
}

// ========================================================
// 5. SERVICIO 2: INCIDENCIAS & ASISTENCIA (CHECADOR INTELIGENTE)
// ========================================================

/**
 * Carga incidencias
 */
async function loadIncidenciasAdmin() {
    const tbody = document.getElementById('tablaIncidenciasAdminBody');
    tbody.innerHTML = `<tr><td colspan="8" class="text-center py-4 text-muted"><span class="spinner-border spinner-border-sm me-2"></span>Cargando bitácora de asistencias e incidencias...</td></tr>`;

    try {
        const res = await fetch('api/incidencias.php');
        const data = await res.json();

        if (data.success && data.data && data.data.incidencias) {
            AppState.incidencias = data.data.incidencias;
            document.getElementById('kpiIncidenciasPeriodo').textContent = AppState.incidencias.length;
            renderIncidenciasAdminTable(AppState.incidencias);
        } else {
            tbody.innerHTML = `<tr><td colspan="8" class="text-center py-4 text-danger">${data.message || 'No se pudieron cargar incidencias.'}</td></tr>`;
        }
    } catch (err) {
        console.error(err);
        tbody.innerHTML = `<tr><td colspan="8" class="text-center py-4 text-danger">Error de comunicación.</td></tr>`;
    }
}

/**
 * Renderiza incidencias en el panel administrativo
 */
function renderIncidenciasAdminTable(incidencias) {
    const tbody = document.getElementById('tablaIncidenciasAdminBody');
    if (!incidencias || incidencias.length === 0) {
        tbody.innerHTML = `<tr><td colspan="8" class="text-center py-4 text-muted">No hay incidencias capturadas en el sistema.</td></tr>`;
        return;
    }

    tbody.innerHTML = incidencias.map(inc => `
        <tr>
            <td class="fw-semibold text-dark">${inc.fecha}</td>
            <td>
                <div class="fw-bold">${escapeHtml(inc.empleado_nombre)}</div>
                <div class="small text-muted">${escapeHtml(inc.puesto)}</div>
            </td>
            <td>
                <span class="badge ${getTipoIncidenciaBadge(inc.tipo)}">${inc.tipo}</span>
            </td>
            <td>
                <span class="badge bg-light text-dark border">
                    <i class="bi ${inc.modalidad === 'TELETRABAJO' ? 'bi-laptop' : 'bi-building'}"></i> ${inc.modalidad || 'PRESENCIAL'}
                </span>
                ${inc.latitud ? `<div class="font-monospace text-muted" style="font-size: 0.65rem;">GPS: ${inc.latitud.toFixed(4)}, ${inc.longitud.toFixed(4)}</div>` : ''}
            </td>
            <td>
                ${inc.tipo === 'HORA_EXTRA' ? `<span class="fw-bold text-primary">${inc.horas_extra} hrs</span>` : 
                 (inc.folio_medico ? `<span class="badge bg-light text-danger border">Folio: ${escapeHtml(inc.folio_medico)}</span>` : '<span class="text-muted">-</span>')}
            </td>
            <td class="small text-muted">${escapeHtml(inc.observaciones || 'Sin comentarios')}</td>
            <td>
                <span class="badge ${inc.estatus === 'APROBADO' ? 'bg-success' : (inc.estatus === 'RECHAZADO' ? 'bg-danger' : 'bg-warning text-dark')}">
                    ${inc.estatus}
                </span>
            </td>
            <td class="text-end">
                ${inc.estatus === 'PENDIENTE' ? `
                    <button class="btn btn-sm btn-outline-success me-1" onclick="cambiarEstatusIncidencia(${inc.id}, 'APROBADO')" title="Aprobar">
                        <i class="bi bi-check-lg"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-danger" onclick="cambiarEstatusIncidencia(${inc.id}, 'RECHAZADO')" title="Rechazar">
                        <i class="bi bi-x-lg"></i>
                    </button>
                ` : `<span class="text-muted small"><i class="bi bi-check2-all text-success"></i> Procesado</span>`}
            </td>
        </tr>
    `).join('');
}

function getTipoIncidenciaBadge(tipo) {
    switch (tipo) {
        case 'ASISTENCIA': return 'bg-info text-dark';
        case 'HORA_EXTRA': return 'bg-primary';
        case 'VACACIONES': return 'bg-success';
        case 'INCAPACIDAD': return 'bg-danger';
        case 'RETARDO': return 'bg-warning text-dark';
        case 'FALTA': return 'bg-dark text-white';
        default: return 'bg-secondary';
    }
}

/**
 * Toggle resumen por periodo
 */
async function toggleResumenPeriodo() {
    const card = document.getElementById('cardResumenIncidenciasPeriodo');
    const tbody = document.getElementById('tablaResumenPeriodoBody');

    if (!card.classList.contains('d-none')) {
        card.classList.add('d-none');
        return;
    }

    card.classList.remove('d-none');
    tbody.innerHTML = `<tr><td colspan="8" class="text-center py-3"><span class="spinner-border spinner-border-sm me-2"></span>Calculando resumen acumulado de incidencias...</td></tr>`;

    try {
        const res = await fetch('api/incidencias.php?action=resumen_periodo');
        const data = await res.json();

        if (data.success && data.data && data.data.resumen) {
            tbody.innerHTML = data.data.resumen.map(r => `
                <tr>
                    <td><strong>${escapeHtml(r.empleado_nombre)}</strong> <small class="text-muted">(${escapeHtml(r.puesto)})</small></td>
                    <td class="text-center">${r.asistencias}</td>
                    <td class="text-center text-danger fw-bold">${r.faltas}</td>
                    <td class="text-center">${r.retardos}</td>
                    <td class="text-center text-primary fw-bold">${r.horas_extra_total > 0 ? r.horas_extra_total + ' hrs' : '-'}</td>
                    <td class="text-center text-success">${r.vacaciones_dias}</td>
                    <td class="text-center text-warning">${r.incapacidades_dias}</td>
                    <td class="text-center fw-bold text-success fs-6">${r.dias_laborados_efectivos} / 15</td>
                </tr>
            `).join('');
        }
    } catch (err) {
        console.error(err);
        tbody.innerHTML = `<tr><td colspan="8" class="text-danger text-center">Error al cargar resumen.</td></tr>`;
    }
}

/**
 * Cambia estatus de incidencia
 */
async function cambiarEstatusIncidencia(id, estatus) {
    try {
        const res = await fetch('api/incidencias.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'cambiar_estatus', id, estatus })
        });
        const data = await res.json();

        if (data.success) {
            showToast(data.message, 'success');
            loadIncidenciasAdmin();
            loadAuditTrail();
            loadNotificaciones();
        } else {
            showToast(data.message || 'Error al actualizar estatus.', 'danger');
        }
    } catch (err) {
        console.error(err);
        showToast('Error de comunicación.', 'danger');
    }
}

/**
 * Registra nueva incidencia desde formulario de RH/Admin
 */
async function handleNuevaIncidenciaSubmit(e) {
    e.preventDefault();
    const form = e.target;
    const formData = new FormData(form);
    const payload = Object.fromEntries(formData.entries());

    try {
        const res = await fetch('api/incidencias.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await res.json();

        if (data.success) {
            showToast(data.message, 'success');
            form.reset();
            const modalEl = document.getElementById('modalNuevaIncidencia');
            bootstrap.Modal.getInstance(modalEl).hide();
            loadIncidenciasAdmin();
        } else {
            showToast(data.message || 'Error al registrar incidencia.', 'danger');
        }
    } catch (err) {
        console.error(err);
        showToast('Error de comunicación.', 'danger');
    }
}

// ========================================================
// 6. SERVICIO 3 & 4: MOTOR DE CÁLCULO, PRE-NÓMINA & TIMBRADO FISCAL
// ========================================================

/**
 * Carga pre-nómina procesada
 */
async function loadPreNominaData(nominaId = null) {
    const tbody = document.getElementById('tablaPreNominaBody');
    tbody.innerHTML = `<tr><td colspan="12" class="text-center py-4 text-muted"><span class="spinner-border spinner-border-sm me-2"></span>Cargando pre-nómina procesada y auditoría...</td></tr>`;

    try {
        const url = nominaId ? `api/calculo.php?nomina_id=${nominaId}` : 'api/calculo.php';
        const res = await fetch(url);
        const data = await res.json();

        if (data.success && data.data) {
            AppState.nominas = data.data.periodos || [];
            renderPeriodosSelect(AppState.nominas, data.data.nomina_actual?.id);

            if (data.data.nomina_actual) {
                AppState.currentNominaId = data.data.nomina_actual.id;
                updateNominaHeaderUI(data.data.nomina_actual);
                renderPreNominaTable(data.data.detalles || []);
                updateFinancialKpis(data.data.resumen_global);
            } else {
                tbody.innerHTML = `<tr><td colspan="12" class="text-center py-4 text-muted">No hay nóminas registradas. Crea un nuevo periodo.</td></tr>`;
            }
        }
    } catch (err) {
        console.error(err);
        tbody.innerHTML = `<tr><td colspan="12" class="text-center py-4 text-danger">Error de comunicación.</td></tr>`;
    }
}

/**
 * Renderiza selector de periodos
 */
function renderPeriodosSelect(periodos, currentId) {
    const select = document.getElementById('selectPeriodoNomina');
    if (!select) return;

    select.innerHTML = periodos.map(p => `
        <option value="${p.id}" ${p.id == currentId ? 'selected' : ''}>
            ${escapeHtml(p.periodo)} (${p.fecha_inicio} al ${p.fecha_fin}) [${p.estatus}]
        </option>
    `).join('');
}

/**
 * Actualiza badges y botones según estatus de nómina
 */
function updateNominaHeaderUI(nomina) {
    const badge = document.getElementById('badgeEstatusPeriodo');
    const kpiEstatus = document.getElementById('kpiEstatusNomina');
    badge.className = `badge-status badge-${nomina.estatus.toLowerCase()}`;
    badge.textContent = nomina.estatus;

    if (kpiEstatus) {
        kpiEstatus.textContent = nomina.estatus;
        kpiEstatus.className = `stat-value fs-5 text-${getStatusColor(nomina.estatus)}`;
    }

    const btnCalc = document.getElementById('btnEjecutarCalculo');
    const btnTimbrar = document.getElementById('btnTimbrarNomina');

    if (btnCalc) {
        if (nomina.estatus === 'TIMBRADA' || nomina.estatus === 'DISPERSADA') {
            btnCalc.disabled = false;
            btnCalc.className = 'btn btn-outline-primary btn-sm d-flex align-items-center gap-1';
            btnCalc.innerHTML = '<i class="bi bi-arrow-repeat"></i> <span>Re-calcular</span>';
            btnCalc.title = "Re-calcular montos de este periodo.";
        } else {
            btnCalc.disabled = false;
            btnCalc.className = 'btn btn-primary btn-sm d-flex align-items-center gap-1';
            btnCalc.innerHTML = '<i class="bi bi-cpu"></i> <span>Calcular & Congelar</span>';
            btnCalc.title = "";
        }
    }

    if (btnTimbrar) {
        btnTimbrar.disabled = false;
        if (nomina.estatus === 'TIMBRADA' || nomina.estatus === 'DISPERSADA') {
            btnTimbrar.className = 'btn btn-outline-success btn-sm d-flex align-items-center gap-1';
            btnTimbrar.innerHTML = '<i class="bi bi-cloud-arrow-up-fill"></i> <span>Re-timbrar (ProFact)</span>';
            btnTimbrar.title = "Este periodo ya fue timbrado. Haz clic para re-timbrar de prueba con ProFact.";
        } else if (nomina.estatus === 'CONGELADA') {
            btnTimbrar.className = 'btn btn-success btn-sm d-flex align-items-center gap-1';
            btnTimbrar.innerHTML = '<i class="bi bi-shield-check"></i> <span>Timbrar (CFDI)</span>';
            btnTimbrar.title = "Timbrar fiscalmente con ProFact";
        } else {
            btnTimbrar.className = 'btn btn-outline-success btn-sm d-flex align-items-center gap-1';
            btnTimbrar.innerHTML = '<i class="bi bi-shield-check"></i> <span>Timbrar (CFDI)</span>';
            btnTimbrar.title = "Haz clic para calcular y timbrar con ProFact.";
        }
    }

    // Actualizar flujo visual del mini stepper
    updatePayrollFlowMiniStepper(nomina.estatus);
}

function getStatusColor(status) {
    switch(status) {
        case 'BORRADOR': return 'secondary';
        case 'CONGELADA': return 'info';
        case 'TIMBRADA': return 'success';
        case 'DISPERSADA': return 'primary';
        default: return 'dark';
    }
}

/**
 * Renderiza registros detallados de pre-nómina
 */
function renderPreNominaTable(detalles) {
    const tbody = document.getElementById('tablaPreNominaBody');
    document.getElementById('lblDetalleCount').textContent = `${detalles.length} colaboradores procesados`;

    if (!detalles || detalles.length === 0) {
        tbody.innerHTML = `<tr><td colspan="12" class="text-center py-4 text-muted">Aún no se ha ejecutado el cálculo para este periodo. Haz clic en "Calcular & Congelar".</td></tr>`;
        return;
    }

    tbody.innerHTML = detalles.map(d => `
        <tr>
            <td>
                <div class="fw-bold text-dark">${escapeHtml(d.empleado_nombre)}</div>
                <div class="font-monospace text-muted" style="font-size: 0.72rem;">${escapeHtml(d.rfc)}</div>
            </td>
            <td class="text-center fw-semibold">${d.dias_trabajados} / 15</td>
            <td class="text-dark">$${formatMoney(d.sueldo_base)}</td>
            <td class="text-primary">${d.pago_horas_extra > 0 ? '$' + formatMoney(d.pago_horas_extra) : '-'}</td>
            <td class="text-primary">${d.bonos > 0 ? '$' + formatMoney(d.bonos) : '-'}</td>
            <td class="fw-bold text-dark">$${formatMoney(d.percepciones_total)}</td>
            <td class="text-danger">-$${formatMoney(d.deduccion_isr)}</td>
            <td class="text-danger">-$${formatMoney(d.deduccion_imss)}</td>
            <td class="text-danger">-$${formatMoney(d.deduccion_infonavit + d.deduccion_prestamo)}</td>
            <td class="fw-bold text-success fs-6">$${formatMoney(d.sueldo_neto)}</td>
            <td>
                ${d.folio_fiscal ? `
                    <a href="javascript:void(0)" onclick="verReciboCFDI(${d.id})" class="text-decoration-none" title="Ver Comprobante CFDI: ${d.folio_fiscal}">
                        <span class="font-monospace small bg-light p-1 rounded border text-primary" style="font-size: 0.7rem; cursor: pointer;">
                            <i class="bi bi-shield-check text-success"></i> ${d.folio_fiscal.substring(0, 11)}...
                        </span>
                    </a>
                ` : `<span class="badge bg-light text-muted border">Sin Timbre</span>`}
            </td>
            <td class="text-end">
                <div class="btn-group">
                    <button class="btn btn-sm btn-outline-warning text-dark" onclick="abrirModalAjusteManual(${d.id}, '${escapeHtml(d.empleado_nombre)}', ${d.bonos}, ${d.deduccion_prestamo}, ${d.ajuste_manual}, '${escapeHtml(d.justificacion_ajuste || '')}')" title="Ajuste Manual Justificado (Audit Trail)">
                        <i class="bi bi-pencil-square"></i>
                    </button>
                    ${d.folio_fiscal ? `
                        <a href="api/timbrado.php?detalle_id=${d.id}&format=xml" class="btn btn-sm btn-outline-success" download="CFDI_Nomina_${d.folio_fiscal}.xml" title="Descargar XML CFDI">
                            <i class="bi bi-file-earmark-code"></i> XML
                        </a>
                    ` : ''}
                    <button class="btn btn-sm btn-outline-primary" onclick="verReciboCFDI(${d.id})" title="Ver Recibo CFDI 4.0">
                        <i class="bi bi-receipt"></i> CFDI
                    </button>
                </div>
            </td>
        </tr>
    `).join('');
}

/**
 * Actualiza tarjetas de resumen financiero
 */
function updateFinancialKpis(resumen) {
    if (!resumen) return;
    document.getElementById('cardTotalPercepciones').textContent = '$' + formatMoney(resumen.total_percepciones);
    document.getElementById('cardTotalDeducciones').textContent = '-$' + formatMoney(resumen.total_deducciones);
    document.getElementById('cardTotalNeto').textContent = '$' + formatMoney(resumen.total_neto);
}

/**
 * Ejecuta cálculo y congela nómina
 */
async function handleEjecutarCalculo() {
    if (!AppState.currentNominaId) {
        showToast('Seleccione un periodo de nómina válido.', 'warning');
        return;
    }

    const btn = document.getElementById('btnEjecutarCalculo');
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = `<span class="spinner-border spinner-border-sm me-1"></span> Procesando motor de cálculo...`;

    try {
        const res = await fetch('api/calculo.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'procesar', nomina_id: AppState.currentNominaId })
        });
        const data = await res.json();

        if (data.success) {
            showToast(data.message, 'success');
            await loadPreNominaData(AppState.currentNominaId);
            loadAuditTrail();
            loadNotificaciones();
            loadMessageBrokerEvents();
        } else {
            showToast(data.message || 'Error en el motor de cálculo.', 'danger');
        }
    } catch (err) {
        console.error(err);
        showToast('Error de comunicación.', 'danger');
    } finally {
        btn.disabled = false;
        btn.innerHTML = originalText;
    }
}

/**
 * Abre modal de ajuste manual justificado
 */
function abrirModalAjusteManual(detalleId, empleadoNombre, bonos, prestamo, ajusteNeto, justificacion) {
    document.getElementById('ajusteDetalleId').value = detalleId;
    document.getElementById('ajusteEmpleadoNombre').textContent = empleadoNombre;
    document.getElementById('ajusteBonos').value = bonos || 0;
    document.getElementById('ajustePrestamo').value = prestamo || 0;
    document.getElementById('ajusteNeto').value = ajusteNeto || 0;
    document.getElementById('ajusteJustificacion').value = justificacion || '';

    const modal = new bootstrap.Modal(document.getElementById('modalAjusteManual'));
    modal.show();
}

/**
 * Procesa el ajuste manual justificado
 */
async function handleAjusteManualSubmit(e) {
    e.preventDefault();
    const form = e.target;
    const formData = new FormData(form);
    const payload = Object.fromEntries(formData.entries());
    payload.action = 'ajuste_manual';

    try {
        const res = await fetch('api/calculo.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await res.json();

        if (data.success) {
            showToast(data.message, 'success');
            const modalEl = document.getElementById('modalAjusteManual');
            bootstrap.Modal.getInstance(modalEl).hide();
            await loadPreNominaData(AppState.currentNominaId);
            loadAuditTrail();
        } else {
            showToast(data.message || 'Error al aplicar ajuste manual.', 'danger');
        }
    } catch (err) {
        console.error(err);
        showToast('Error de comunicación.', 'danger');
    }
}

/**
 * Crea nuevo periodo
 */
async function handleNuevoPeriodoSubmit(e) {
    e.preventDefault();
    const form = e.target;
    const formData = new FormData(form);
    const payload = Object.fromEntries(formData.entries());
    payload.action = 'crear_periodo';

    try {
        const res = await fetch('api/calculo.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await res.json();

        if (data.success) {
            showToast(data.message, 'success');
            form.reset();
            const modalEl = document.getElementById('modalNuevoPeriodo');
            bootstrap.Modal.getInstance(modalEl).hide();
            await loadPreNominaData(data.data?.id);
        } else {
            showToast(data.message || 'No se pudo crear el periodo.', 'danger');
        }
    } catch (err) {
        console.error(err);
        showToast('Error de conexión.', 'danger');
    }
}

/**
 * Timbrar Nómina en Lote (PAC CFDI 4.0)
 */
async function handleTimbrarNomina() {
    if (!AppState.currentNominaId) {
        showToast('Seleccione un periodo de nómina válido.', 'warning');
        return;
    }

    const curr = AppState.nominas.find(n => n.id == AppState.currentNominaId);
    let force = false;

    if (curr && (curr.estatus === 'TIMBRADA' || curr.estatus === 'DISPERSADA')) {
        if (!confirm('Este periodo ya se encuentra timbrado. ¿Deseas forzar un re-timbrado de prueba con ProFact para certificar nuevamente todos los recibos?')) {
            return;
        }
        force = true;
    } else {
        if (!confirm('¿Deseas enviar este periodo de nómina al PAC ProFact para su certificación y Timbrado Fiscal CFDI 4.0?')) {
            return;
        }
    }

    const btn = document.getElementById('btnTimbrarNomina');
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = `<span class="spinner-border spinner-border-sm me-1"></span> Timbrando con ProFact...`;

    try {
        const res = await fetch('api/timbrado.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ nomina_id: AppState.currentNominaId, force: force })
        });
        const data = await res.json();

        if (data.success) {
            showToast(data.message, 'success');
            await loadPreNominaData(AppState.currentNominaId);
            loadAuditTrail();
            loadNotificaciones();
            loadMessageBrokerEvents();

            // Timbrado Nómina ProFact: Descarga automática inmediata del XML y apertura del CFDI
            const primerId = data.data?.primer_detalle_id;
            const uuid = data.data?.primer_uuid;

            if (primerId) {
                // 1. Descarga automática instantánea del archivo XML del CFDI timbrado
                const link = document.createElement('a');
                link.href = `api/timbrado.php?detalle_id=${primerId}&format=xml`;
                link.download = `CFDI_Nomina_${uuid || primerId}.xml`;
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);

                // 2. Abre automáticamente el comprobante oficial CFDI 4.0 con QR y sellos
                setTimeout(() => {
                    verReciboCFDI(primerId);
                }, 350);
            }
        } else {
            showToast(data.message || 'Error en el timbrado fiscal.', 'danger');
        }
    } catch (err) {
        console.error(err);
        showToast('Error de comunicación.', 'danger');
    } finally {
        btn.disabled = false;
        btn.innerHTML = originalText;
    }
}

/**
 * Abre visor oficial CFDI 4.0
 */
async function verReciboCFDI(detalleId) {
    const modalEl = document.getElementById('modalRecibo');
    const printableArea = document.getElementById('modalReciboPrintable');
    const btnXml = document.getElementById('btnDescargarXmlRecibo');
    const modalInstance = new bootstrap.Modal(modalEl);

    printableArea.innerHTML = `<div class="text-center py-5"><span class="spinner-border spinner-border-sm me-2"></span>Generando comprobante fiscal CFDI 4.0...</div>`;
    btnXml.classList.add('d-none');
    modalInstance.show();

    try {
        const res = await fetch(`api/timbrado.php?detalle_id=${detalleId}`);
        const data = await res.json();

        if (data.success && data.data) {
            const r = data.data;

            if (r.folio_fiscal) {
                btnXml.href = `api/timbrado.php?detalle_id=${detalleId}&format=xml`;
                btnXml.classList.remove('d-none');
            }

            printableArea.innerHTML = `
                ${r.folio_fiscal ? `
                    <div class="alert alert-success d-flex flex-wrap align-items-center justify-content-between p-3 mb-3 no-print rounded-3 border-0 shadow-sm" style="background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%);">
                        <div class="d-flex align-items-center gap-2 mb-2 mb-md-0">
                            <i class="bi bi-patch-check-fill fs-2 text-success"></i>
                            <div>
                                <strong class="text-dark d-block fs-6">¡Comprobante CFDI Timbrado con Éxito por ProFact!</strong>
                                <span class="small text-muted">Archivo <strong>XML</strong> disponible para descarga &bull; Folio Fiscal UUID: <span class="font-monospace text-dark fw-bold">${r.folio_fiscal}</span></span>
                            </div>
                        </div>
                        <div class="d-flex gap-2">
                            <a href="api/timbrado.php?detalle_id=${detalleId}&format=xml" download="CFDI_Nomina_${r.folio_fiscal}.xml" class="btn btn-sm btn-success text-white shadow-sm fw-semibold">
                                <i class="bi bi-file-earmark-code me-1"></i> Descargar XML
                            </a>
                            <button type="button" class="btn btn-sm btn-primary shadow-sm fw-semibold" onclick="window.print()">
                                <i class="bi bi-printer me-1"></i> Imprimir Recibo / Guardar PDF
                            </button>
                        </div>
                    </div>
                ` : `
                    <div class="alert alert-warning p-2 mb-3 no-print small">
                        <i class="bi bi-exclamation-triangle me-1"></i> Vista previa de pre-nómina sin timbrar. Haz clic en "Timbrar (CFDI)" para certificar con ProFact.
                    </div>
                `}
                <div class="recibo-cfdi">
                    <div class="recibo-header d-flex justify-content-between align-items-center">
                        <div>
                            <h4 class="fw-bold text-dark mb-1">SISTEMA DE NÓMINAS Y PERSONAL S.A. DE C.V.</h4>
                            <div class="small text-muted"><strong>RFC Emisor:</strong> NOM210315AB1 | <strong>Régimen Fiscal:</strong> 601 General de Ley</div>
                            <div class="small text-muted"><strong>Lugar de Expedición:</strong> C.P. 06600, Ciudad de México</div>
                        </div>
                        <div class="text-end">
                            <span class="badge bg-dark fs-6 px-3 py-2">RECIBO DE NÓMINA</span>
                            <div class="small text-muted mt-1">Versión CFDI: <strong>4.0 / Nómina 1.2</strong></div>
                        </div>
                    </div>

                    <div class="row g-2 mb-3 bg-light p-3 rounded small">
                        <div class="col-md-7">
                            <div class="mb-1"><span class="text-muted">Folio Fiscal (UUID):</span></div>
                            <div class="cfdi-uuid">${r.folio_fiscal || 'SIN TIMBRAR (PRE-NÓMINA)'}</div>
                        </div>
                        <div class="col-md-5 text-md-end">
                            <div><span class="text-muted">Fecha de Certificación:</span> <strong>${r.fecha_timbrado || 'Pendiente'}</strong></div>
                            <div><span class="text-muted">Periodo Liquidado:</span> <strong>${r.periodo} (${r.fecha_inicio} al ${r.fecha_fin})</strong></div>
                        </div>
                    </div>

                    <div class="card mb-3 border-0 bg-light p-3">
                        <div class="row g-2 small">
                            <div class="col-md-6">
                                <div><span class="text-muted">Trabajador:</span> <strong class="fs-6 text-dark">${escapeHtml(r.empleado_nombre)}</strong></div>
                                <div><span class="text-muted">RFC:</span> <strong class="font-monospace">${escapeHtml(r.rfc)}</strong></div>
                                <div><span class="text-muted">CURP:</span> <strong class="font-monospace">${escapeHtml(r.curp)}</strong></div>
                                <div><span class="text-muted">No. Empleado:</span> <strong>EMP-${strPad(r.empleado_id, 4, '0')}</strong></div>
                            </div>
                            <div class="col-md-6">
                                <div><span class="text-muted">Puesto:</span> <strong>${escapeHtml(r.puesto)}</strong></div>
                                <div><span class="text-muted">Departamento:</span> <strong>${escapeHtml(r.departamento)}</strong></div>
                                <div><span class="text-muted">Cuenta CLABE:</span> <span class="font-monospace text-primary fw-bold">${escapeHtml(r.cuenta_clabe)} (${escapeHtml(r.banco || 'BBVA')})</span></div>
                                <div><span class="text-muted">Días Pagados:</span> <strong>${r.dias_trabajados} días</strong> | Salario Diario: <strong>$${formatMoney(r.salario_diario)}</strong></div>
                            </div>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <h6 class="fw-bold text-success border-bottom pb-1"><i class="bi bi-plus-circle"></i> Percepciones</h6>
                            <table class="table-recibo">
                                <thead>
                                    <tr>
                                        <th>Concepto</th>
                                        <th class="text-end">Importe</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>001 Sueldo Quincenal (${r.dias_trabajados} días)</td>
                                        <td class="text-end fw-semibold">$${formatMoney(r.sueldo_base)}</td>
                                    </tr>
                                    ${r.pago_horas_extra > 0 ? `
                                        <tr>
                                            <td>019 Horas Extras Dobles</td>
                                            <td class="text-end fw-semibold text-primary">$${formatMoney(r.pago_horas_extra)}</td>
                                        </tr>
                                    ` : ''}
                                    ${r.bonos > 0 ? `
                                        <tr>
                                            <td>038 Bono de Productividad</td>
                                            <td class="text-end fw-semibold text-primary">$${formatMoney(r.bonos)}</td>
                                        </tr>
                                    ` : ''}
                                    <tr class="fw-bold table-light">
                                        <td>Total Percepciones:</td>
                                        <td class="text-end text-success">$${formatMoney(r.percepciones)}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="col-md-6">
                            <h6 class="fw-bold text-danger border-bottom pb-1"><i class="bi bi-dash-circle"></i> Deducciones</h6>
                            <table class="table-recibo">
                                <thead>
                                    <tr>
                                        <th>Concepto</th>
                                        <th class="text-end">Importe</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>002 Retención ISR de Ley</td>
                                        <td class="text-end fw-semibold text-danger">-$${formatMoney(r.deduccion_isr)}</td>
                                    </tr>
                                    <tr>
                                        <td>001 Seguridad Social IMSS Cuota Obrera</td>
                                        <td class="text-end fw-semibold text-danger">-$${formatMoney(r.deduccion_imss)}</td>
                                    </tr>
                                    ${r.deduccion_infonavit > 0 ? `
                                        <tr>
                                            <td>009 Aportación Infonavit</td>
                                            <td class="text-end fw-semibold text-danger">-$${formatMoney(r.deduccion_infonavit)}</td>
                                        </tr>
                                    ` : ''}
                                    ${r.deduccion_prestamo > 0 ? `
                                        <tr>
                                            <td>004 Préstamos / Deducciones Internas</td>
                                            <td class="text-end fw-semibold text-danger">-$${formatMoney(r.deduccion_prestamo)}</td>
                                        </tr>
                                    ` : ''}
                                    <tr class="fw-bold table-light">
                                        <td>Total Deducciones:</td>
                                        <td class="text-end text-danger">-$${formatMoney(r.deducciones)}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="recibo-total-box d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <span class="small fw-semibold text-uppercase text-muted">Importe Neto Liquidado a Transferir:</span>
                            <div class="small text-muted font-monospace">Moneda: MXN | Forma: Transferencia SPEI | Ref: ${r.referencia_bancaria || 'SPEI-PENDIENTE'}</div>
                        </div>
                        <div class="text-end">
                            <span class="fs-3 fw-bold text-success">$${formatMoney(r.sueldo_neto)}</span>
                        </div>
                    </div>

                    <div class="d-flex gap-3 align-items-center pt-2 border-top">
                        <div class="qr-placeholder">
                            <i class="bi bi-qr-code fs-1 text-dark"></i>
                            <span>CFDI 4.0 SAT</span>
                        </div>
                        <div class="flex-grow-1 font-monospace" style="font-size: 0.68rem; color: #64748b; line-height: 1.2;">
                            <div class="mb-1"><strong>Sello Digital del SAT:</strong> ${r.sello_sat ? r.sello_sat.substring(0, 95) + '...' : 'Generado tras timbrado oficial.'}</div>
                            <div class="mb-1"><strong>RFC Proveedor Certificación:</strong> PAC0906201A0 | <strong>No. Certificado SAT:</strong> 20001000000500004589</div>
                            <div>Este documento es una representación impresa de un CFDI de Nómina versión 4.0 conforme a los estándares técnicos oficiales del SAT.</div>
                        </div>
                    </div>
                </div>
            `;
        }
    } catch (err) {
        console.error(err);
        printableArea.innerHTML = `<div class="alert alert-danger">Error de comunicación.</div>`;
    }
}

// ========================================================
// 7. SERVICIO 5: DISPERSIÓN BANCARIA & LAYOUTS MASIVOS
// ========================================================

/**
 * Carga lote de transferencias bancarias
 */
async function loadDispersionData(nominaId) {
    if (!nominaId) return;

    const tbody = document.getElementById('tablaDispersionBody');
    tbody.innerHTML = `<tr><td colspan="8" class="text-center py-4 text-muted"><span class="spinner-border spinner-border-sm me-2"></span>Generando lote de transferencias bancarias SPEI...</td></tr>`;

    try {
        const banco = document.getElementById('selectConvenioBanco')?.value || 'UNIVERSAL';
        const res = await fetch(`api/dispersion.php?nomina_id=${nominaId}&banco=${banco}`);
        const data = await res.json();

        if (data.success && data.data) {
            const d = data.data;
            document.getElementById('badgeTotalTransferencias').textContent = `${d.total_transferencias} transferencias (${d.conteo_exitosos} exitosas, ${d.conteo_rebotados} rebotadas)`;
            document.getElementById('btnDescargarLayoutTxt').href = `api/dispersion.php?nomina_id=${nominaId}&banco=${banco}&download=1&format=txt`;

            renderDispersionTable(d.transferencias);
        } else {
            tbody.innerHTML = `<tr><td colspan="8" class="text-center py-4 text-warning">${data.message || 'La nómina debe estar previamente calculada.'}</td></tr>`;
        }
    } catch (err) {
        console.error(err);
        tbody.innerHTML = `<tr><td colspan="8" class="text-center py-4 text-danger">Error de comunicación.</td></tr>`;
    }
}

/**
 * Renderiza la tabla de transferencias SPEI
 */
function renderDispersionTable(transferencias) {
    const tbody = document.getElementById('tablaDispersionBody');
    if (!transferencias || transferencias.length === 0) {
        tbody.innerHTML = `<tr><td colspan="8" class="text-center py-4 text-muted">No hay transferencias en este lote.</td></tr>`;
        return;
    }

    tbody.innerHTML = transferencias.map(t => `
        <tr>
            <td>
                <div class="fw-bold">${escapeHtml(t.nombre)}</div>
                <div class="text-muted small">EMP-${strPad(t.empleado_id, 4, '0')}</div>
            </td>
            <td>
                <span class="font-monospace text-primary fw-semibold">${escapeHtml(t.cuenta_clabe)}</span>
            </td>
            <td>
                <span class="badge bg-light text-dark border">${escapeHtml(t.banco)}</span>
            </td>
            <td>
                <span class="fw-bold text-success fs-6">$${formatMoney(t.sueldo_neto)}</span>
            </td>
            <td class="small text-muted">PAGO NÓMINA QUINCENAL</td>
            <td>
                <span class="font-monospace small text-muted">${escapeHtml(t.referencia_bancaria)}</span>
            </td>
            <td>
                <span class="badge ${t.estado_pago === 'EXITOSO' ? 'bg-success' : (t.estado_pago === 'REBOTADO' ? 'bg-danger' : 'bg-warning text-dark')}">
                    <i class="bi ${t.estado_pago === 'EXITOSO' ? 'bi-check-circle' : (t.estado_pago === 'REBOTADO' ? 'bi-exclamation-triangle' : 'bi-clock')} me-1"></i>
                    ${t.estado_pago}
                </span>
            </td>
            <td class="text-end">
                ${t.estado_pago === 'REBOTADO' ? `
                    <button class="btn btn-sm btn-outline-warning text-dark" onclick="reintentarPagoRebotado(${t.id}, '${escapeHtml(t.nombre)}')">
                        <i class="bi bi-arrow-repeat"></i> Reintentar
                    </button>
                ` : `<span class="text-muted small"><i class="bi bi-shield-check text-success"></i> SPEI OK</span>`}
            </td>
        </tr>
    `).join('');
}

/**
 * Reintenta pago rebotado
 */
async function reintentarPagoRebotado(detalleId, nombre) {
    const nuevaClabe = prompt(`Reintentar Pago para ${nombre}\n\nIngresa la cuenta CLABE corregida (18 dígitos) o deja en blanco para reintentar con la misma:`);
    if (nuevaClabe === null) return;

    try {
        const res = await fetch('api/dispersion.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'reintentar_pago',
                detalle_id: detalleId,
                cuenta_clabe: nuevaClabe.trim(),
                nomina_id: AppState.currentNominaId
            })
        });
        const data = await res.json();

        if (data.success) {
            showToast(data.message, 'success');
            loadDispersionData(AppState.currentNominaId);
            loadAuditTrail();
        } else {
            showToast(data.message || 'Error al reintentar.', 'danger');
        }
    } catch (err) {
        console.error(err);
        showToast('Error de comunicación.', 'danger');
    }
}

/**
 * Aplica dispersión masiva
 */
async function handleEjecutarDispersion() {
    if (!AppState.currentNominaId) {
        showToast('Seleccione un periodo de nómina válido.', 'warning');
        return;
    }

    if (!confirm('¿Confirmas autorizar y transmitir la dispersión bancaria masiva a las cuentas CLABE de los empleados?')) {
        return;
    }

    const btn = document.getElementById('btnEjecutarDispersionMasiva');
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = `<span class="spinner-border spinner-border-sm me-1"></span> Transfiriendo fondos por SPEI...`;

    try {
        const res = await fetch('api/dispersion.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ nomina_id: AppState.currentNominaId })
        });
        const data = await res.json();

        if (data.success) {
            showToast(data.message, 'success');
            await loadDispersionData(AppState.currentNominaId);
            await loadPreNominaData(AppState.currentNominaId);
            loadAuditTrail();
            loadNotificaciones();
            loadMessageBrokerEvents();
        } else {
            showToast(data.message || 'Error en la dispersión bancaria.', 'danger');
        }
    } catch (err) {
        console.error(err);
        showToast('Error de comunicación.', 'danger');
    } finally {
        btn.disabled = false;
        btn.innerHTML = originalText;
    }
}

// ========================================================
// 8. SERVICIO 7: TRAZABILIDAD (AUDIT TRAIL) & REGLAS FISCALES
// ========================================================

/**
 * Carga logs de auditoría inmutables
 */
async function loadAuditTrail() {
    const tbody = document.getElementById('tablaAuditLogsBody');
    if (!tbody) return;

    try {
        const res = await fetch('api/auditoria.php?tab=logs');
        const data = await res.json();

        if (data.success && data.data) {
            AppState.auditLogs = data.data.logs || [];
            const stats = data.data.stats;

            if (stats) {
                document.getElementById('kpiAuditTotal').textContent = stats.total_eventos || 0;
                document.getElementById('kpiAuditAjustes').textContent = stats.ajustes_financieros || 0;
                document.getElementById('kpiAuditTimbrados').textContent = stats.timbrados_fiscales || 0;
            }

            renderAuditTrailTable(AppState.auditLogs);
        }
    } catch (err) {
        console.error(err);
    }
}

/**
 * Renderiza tabla de logs de auditoría
 */
function renderAuditTrailTable(logs) {
    const tbody = document.getElementById('tablaAuditLogsBody');
    if (!tbody) return;

    if (!logs || logs.length === 0) {
        tbody.innerHTML = `<tr><td colspan="7" class="text-center py-4 text-muted">No hay eventos registrados en la bitácora inmutable.</td></tr>`;
        return;
    }

    tbody.innerHTML = logs.map(l => `
        <tr>
            <td class="small fw-semibold">${l.created_at}</td>
            <td>
                <div class="fw-bold text-dark">${escapeHtml(l.usuario_nombre || 'Sistema')}</div>
                <span class="badge bg-light text-dark border" style="font-size: 0.65rem;">${l.rol || 'SYSTEM'}</span>
            </td>
            <td><span class="badge bg-secondary font-monospace" style="font-size: 0.72rem;">${l.accion}</span></td>
            <td class="small font-monospace">${l.tabla_afectada || '-'}</td>
            <td class="small text-muted">${escapeHtml(l.descripcion)}</td>
            <td class="small font-monospace text-muted">${l.ip || '127.0.0.1'}</td>
            <td>
                <span class="font-monospace small text-success bg-light px-1 rounded border" style="font-size: 0.68rem;" title="Hash de Integridad SHA-256">
                    <i class="bi bi-shield-check text-success"></i> ${l.hash_integridad}
                </span>
            </td>
        </tr>
    `).join('');
}

/**
 * Carga catálogo de reglas fiscales parametrizadas
 */
async function loadReglasFiscales() {
    const tbody = document.getElementById('tablaReglasFiscalesBody');
    if (!tbody) return;

    try {
        const res = await fetch('api/auditoria.php?tab=reglas');
        const data = await res.json();

        if (data.success && data.data && data.data.reglas) {
            AppState.reglasFiscales = data.data.reglas;
            renderReglasFiscalesTable(AppState.reglasFiscales);
        }
    } catch (err) {
        console.error(err);
    }
}

/**
 * Renderiza tabla de reglas fiscales
 */
function renderReglasFiscalesTable(reglas) {
    const tbody = document.getElementById('tablaReglasFiscalesBody');
    if (!tbody) return;

    tbody.innerHTML = reglas.map(r => `
        <tr>
            <td class="font-monospace fw-bold text-primary">${r.clave}</td>
            <td>${escapeHtml(r.nombre)}</td>
            <td><span class="fs-6 fw-bold text-success">${r.valor}</span></td>
            <td><span class="badge bg-light text-dark border">${r.unidad}</span></td>
            <td class="small text-muted">${escapeHtml(r.descripcion || '-')}</td>
            <td class="text-end">
                <button class="btn btn-sm btn-outline-primary" onclick="abrirModalEditarRegla('${r.clave}', '${escapeHtml(r.nombre)}', ${r.valor}, '${r.unidad}')" title="Modificar Parámetro">
                    <i class="bi bi-pencil"></i>
                </button>
            </td>
        </tr>
    `).join('');
}

/**
 * Abre modal para editar parámetro fiscal
 */
function abrirModalEditarRegla(clave, nombre, valor, unidad) {
    document.getElementById('reglaClaveInput').value = clave;
    document.getElementById('reglaNombreInput').value = nombre;
    document.getElementById('reglaValorInput').value = valor;
    document.getElementById('reglaUnidadInput').textContent = unidad;

    const modal = new bootstrap.Modal(document.getElementById('modalEditarReglaFiscal'));
    modal.show();
}

/**
 * Guarda modificación de parámetro fiscal
 */
async function handleEditarReglaFiscalSubmit(e) {
    e.preventDefault();
    const form = e.target;
    const formData = new FormData(form);
    const payload = Object.fromEntries(formData.entries());
    payload.action = 'actualizar_regla';

    try {
        const res = await fetch('api/auditoria.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await res.json();

        if (data.success) {
            showToast(data.message, 'success');
            const modalEl = document.getElementById('modalEditarReglaFiscal');
            bootstrap.Modal.getInstance(modalEl).hide();
            loadReglasFiscales();
            loadAuditTrail();
        } else {
            showToast(data.message || 'Error al actualizar parámetro.', 'danger');
        }
    } catch (err) {
        console.error(err);
        showToast('Error de comunicación.', 'danger');
    }
}

// ========================================================
// 9. SERVICIO 8: GRAPHQL GATEWAY & MESSAGE BROKER (PÁGINAS 7 Y 8)
// ========================================================

/**
 * Carga la query exacta de la Página 8 en la consola
 */
function cargarQueryGraphQLPagina8() {
    const editor = document.getElementById('graphqlQueryInput');
    if (!editor) return;

    editor.value = `query {
  empleado(id: "3") {
    nombre
    puesto
    vacacionesDisponibles
    ultimoRecibo(periodo: "2026-Q1") {
      sueldoNeto
      folioFiscal
      urlPdf
    }
  }
}`;
}

/**
 * Ejecuta consulta GraphQL en el Gateway
 */
async function handleEjecutarGraphQLQuery() {
    const editor = document.getElementById('graphqlQueryInput');
    const output = document.getElementById('graphqlResultOutput');
    const badgeTime = document.getElementById('graphqlDuration');
    const btn = document.getElementById('btnEjecutarGraphQL');

    const query = editor.value.trim();
    if (!query) {
        showToast('Ingresa una consulta GraphQL válida.', 'warning');
        return;
    }

    btn.disabled = true;
    output.innerHTML = `<span class="spinner-border spinner-border-sm me-2"></span>Orquestando subgrafos federados en el Gateway...`;

    try {
        const res = await fetch('api/graphql.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ query })
        });
        const data = await res.json();

        output.textContent = JSON.stringify(data, null, 2);
        badgeTime.textContent = (data.extensions?.executionTimeMs || 0) + ' ms';
        showToast('Consulta GraphQL resuelta con éxito por el Gateway.', 'success');
        loadAuditTrail();
    } catch (err) {
        console.error(err);
        output.textContent = 'Error al ejecutar consulta en el Gateway.';
    } finally {
        btn.disabled = false;
    }
}

/**
 * Carga eventos asíncronos del Message Broker
 */
async function loadMessageBrokerEvents() {
    const container = document.getElementById('messageBrokerEventsList');
    if (!container) return;

    try {
        const res = await fetch('api/graphql.php?events=1');
        const data = await res.json();

        if (data.success && data.data && data.data.eventos) {
            const eventos = data.data.eventos;
            if (eventos.length === 0) {
                container.innerHTML = `<div class="text-center text-muted py-3 small">No hay eventos en la cola del Message Broker.</div>`;
                return;
            }

            container.innerHTML = eventos.slice(0, 5).map(ev => `
                <div class="event-broker-card d-flex justify-content-between align-items-center">
                    <div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge ${getNotifBadgeClass(ev.tipo_evento)} font-monospace">${ev.tipo_evento}</span>
                            <strong class="text-dark">${escapeHtml(ev.titulo)}</strong>
                        </div>
                        <div class="text-muted small mt-1">${escapeHtml(ev.mensaje)}</div>
                    </div>
                    <div class="text-end">
                        <span class="badge bg-light text-dark border small">${ev.created_at}</span>
                        <div class="small text-success mt-1"><i class="bi bi-check2-circle"></i> Procesado</div>
                    </div>
                </div>
            `).join('');
        }
    } catch (err) {
        console.error(err);
    }
}

// ========================================================
// 10. PORTAL DE AUTOCONSULTA DEL EMPLEADO (AUTO-SERVICIO)
// ========================================================

/**
 * Carga información del portal de autoconsulta
 */
async function loadEmpleadoPortalData() {
    try {
        const res = await fetch('api/empleados.php');
        const data = await res.json();

        if (data.success && data.data) {
            const emp = data.data;

            document.getElementById('empNombreSaludo').textContent = emp.nombre.split(' ')[0] || emp.nombre;
            document.getElementById('empPuesto').textContent = emp.puesto;
            document.getElementById('empDepto').textContent = emp.departamento;
            document.getElementById('empContrato').textContent = emp.tipo_contrato || 'TIEMPO INDETERMINADO';
            document.getElementById('empRfc').textContent = emp.rfc;
            document.getElementById('empCurp').textContent = emp.curp;
            document.getElementById('empClabe').textContent = `${emp.cuenta_clabe} (${emp.banco})`;
            document.getElementById('empSalarioDiario').textContent = '$' + formatMoney(emp.salario_diario) + ' MXN';
            document.getElementById('empVacacionesDisponibles').textContent = `${emp.dias_vacaciones_disponibles} días laborables`;

            renderMisRecibosTable(emp.recibos_recientes || []);
            renderMisIncidenciasTable(emp.incidencias_recientes || []);

            // Consulta clima en vivo con OpenWeatherMap para el portal del colaborador
            loadClimaWidget();
        }
    } catch (err) {
        console.error('Error al cargar portal de empleado:', err);
    }
}

/**
 * Servicio de Clima para Empleados (OpenWeatherMap API)
 */
let currentWeatherCity = 'Ciudad de México';

async function loadClimaWidget(city = null, lat = null, lon = null) {
    if (city && city !== 'custom') {
        currentWeatherCity = city;
    }

    const tempEl = document.getElementById('weatherTempDisplay');
    const cityEl = document.getElementById('weatherCityDisplay');
    const descEl = document.getElementById('weatherDescDisplay');
    const iconEl = document.getElementById('weatherIconImg');
    const feelsEl = document.getElementById('weatherFeelsDisplay');
    const humEl = document.getElementById('weatherHumidityDisplay');
    const windEl = document.getElementById('weatherWindDisplay');
    const minMaxEl = document.getElementById('weatherMinMaxDisplay');
    const badgeStatus = document.getElementById('weatherBadgeStatus');
    const fuenteEl = document.getElementById('weatherSubtitleFuente');
    const selectCity = document.getElementById('weatherCitySelect');

    const navTemp = document.getElementById('navWeatherTemp');
    const navCity = document.getElementById('navWeatherCity');
    const navIcon = document.getElementById('navWeatherIcon');

    if (descEl) descEl.textContent = 'Consultando OpenWeatherMap...';

    try {
        let url = 'api/clima.php';
        if (lat !== null && lon !== null) {
            url += `?lat=${lat}&lon=${lon}`;
        } else {
            url += `?city=${encodeURIComponent(currentWeatherCity)}`;
        }

        const res = await fetch(url);
        const data = await res.json();

        if (data.success && data.data) {
            const w = data.data;

            if (tempEl) tempEl.textContent = `${w.temp}°C`;
            if (cityEl) cityEl.textContent = `${w.city}, ${w.country}`;
            if (descEl) descEl.textContent = w.description;
            if (iconEl) iconEl.src = w.icon_url;
            if (feelsEl) feelsEl.textContent = `${w.feels_like}°C`;
            if (humEl) humEl.textContent = `${w.humidity}%`;
            if (windEl) windEl.textContent = `${w.wind_speed} km/h`;
            if (minMaxEl) minMaxEl.textContent = `${w.temp_min}° / ${w.temp_max}°`;

            if (selectCity && !lat && !lon) {
                const optExists = Array.from(selectCity.options).some(o => o.value === currentWeatherCity);
                if (optExists) {
                    selectCity.value = currentWeatherCity;
                }
            }

            if (badgeStatus) {
                if (w.is_live) {
                    badgeStatus.className = 'badge bg-success bg-opacity-75 text-white border-0 px-2 py-1 small';
                    badgeStatus.innerHTML = '<i class="bi bi-broadcast me-1"></i> En vivo (OpenWeather)';
                } else {
                    badgeStatus.className = 'badge bg-info text-dark border-0 px-2 py-1 small';
                    badgeStatus.innerHTML = '<i class="bi bi-cloud-check me-1"></i> OpenWeatherMap';
                }
            }

            if (fuenteEl && w.source) {
                fuenteEl.textContent = `${w.source} • Act: ${new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}`;
            }

            // Actualizar píldora de Navbar superior
            if (navTemp) navTemp.textContent = `${Math.round(w.temp)}°C`;
            if (navCity) navCity.textContent = w.city.split(' ')[0];
            if (navIcon) navIcon.src = `https://openweathermap.org/img/wn/${w.icon}.png`;
        }
    } catch (err) {
        console.error('Error al consultar clima:', err);
        if (descEl) descEl.textContent = 'No se pudo conectar con el servicio de clima.';
    }
}

/**
 * Renderiza recibos en portal de empleado
 */
function renderMisRecibosTable(recibos) {
    const tbody = document.getElementById('tablaMisRecibosBody');
    if (!recibos || recibos.length === 0) {
        tbody.innerHTML = `<tr><td colspan="6" class="text-center py-4 text-muted">Aún no tienes recibos de nómina generados.</td></tr>`;
        return;
    }

    tbody.innerHTML = recibos.map(r => `
        <tr>
            <td class="fw-bold">${escapeHtml(r.periodo)}</td>
            <td class="text-dark">$${formatMoney(parseFloat(r.sueldo_base) + parseFloat(r.pago_horas_extra) + parseFloat(r.bonos || 0))}</td>
            <td class="text-danger">-$${formatMoney(parseFloat(r.deduccion_isr) + parseFloat(r.deduccion_imss) + parseFloat(r.deduccion_infonavit || 0))}</td>
            <td class="fw-bold text-success fs-6">$${formatMoney(r.sueldo_neto)}</td>
            <td>
                ${r.folio_fiscal ? `
                    <span class="font-monospace small text-primary bg-light p-1 rounded border" style="font-size: 0.7rem;">
                        <i class="bi bi-shield-check text-success"></i> ${r.folio_fiscal.substring(0, 11)}...
                    </span>
                ` : `<span class="badge bg-light text-muted border">En proceso</span>`}
            </td>
            <td class="text-end">
                <button class="btn btn-sm btn-outline-primary" onclick="verReciboCFDI(${r.id})">
                    <i class="bi bi-receipt"></i> Ver Recibo CFDI
                </button>
            </td>
        </tr>
    `).join('');
}

/**
 * Renderiza incidencias personales
 */
function renderMisIncidenciasTable(incidencias) {
    const tbody = document.getElementById('tablaMisIncidenciasBody');
    if (!incidencias || incidencias.length === 0) {
        tbody.innerHTML = `<tr><td colspan="5" class="text-center py-4 text-muted">Sin registros recientes de incidencias o checador.</td></tr>`;
        return;
    }

    tbody.innerHTML = incidencias.map(inc => `
        <tr>
            <td class="fw-semibold">${inc.fecha}</td>
            <td><span class="badge ${getTipoIncidenciaBadge(inc.tipo)}">${inc.tipo}</span></td>
            <td><span class="badge bg-light text-dark border">${inc.modalidad || 'PRESENCIAL'}</span></td>
            <td class="small text-muted">${escapeHtml(inc.observaciones || (inc.horas_extra > 0 ? inc.horas_extra + ' hrs' : 'Marcaje'))}</td>
            <td>
                <span class="badge ${inc.estatus === 'APROBADO' ? 'bg-success' : (inc.estatus === 'RECHAZADO' ? 'bg-danger' : 'bg-warning text-dark')}">
                    ${inc.estatus}
                </span>
            </td>
        </tr>
    `).join('');
}

/**
 * Captura ubicación GPS real mediante la Geolocation API del navegador
 */
function capturarGpsNavegador() {
    const statusLbl = document.getElementById('lblGpsStatus');
    statusLbl.textContent = 'Detectando satélites...';

    if (!navigator.geolocation) {
        showToast('Tu navegador no soporta geolocalización GPS.', 'warning');
        statusLbl.textContent = 'GPS no disponible';
        return;
    }

    navigator.geolocation.getCurrentPosition(
        (pos) => {
            AppState.currentGpsCoords = {
                lat: pos.coords.latitude,
                lng: pos.coords.longitude,
                acc: pos.coords.accuracy
            };
            statusLbl.innerHTML = `<i class="bi bi-geo-alt-fill text-success"></i> GPS: ${pos.coords.latitude.toFixed(4)}, ${pos.coords.longitude.toFixed(4)} (±${Math.round(pos.coords.accuracy)}m)`;
            showToast('Ubicación GPS capturada con precisión.', 'success');
        },
        (err) => {
            console.warn('GPS error:', err.message);
            // Coordenadas simuladas para oficina central si el usuario no tiene sensor o denegó permiso
            AppState.currentGpsCoords = { lat: 19.4326, lng: -99.1332, acc: 15 };
            statusLbl.innerHTML = `<i class="bi bi-geo-alt-fill text-primary"></i> Sede Central (19.4326, -99.1332)`;
            showToast('Ubicación de sede central asignada por IP.', 'info');
        },
        { enableHighAccuracy: true, timeout: 6000 }
    );
}

/**
 * Marca asistencia en el checador digital en tiempo real con GPS y sello horario
 */
async function handleMarcarAsistencia() {
    const btn = document.getElementById('btnMarcarAsistencia');
    const feedback = document.getElementById('clockFeedback');
    const originalContent = btn.innerHTML;

    // Obtener modalidad seleccionada
    const modalidad = document.querySelector('input[name="modalidadRadio"]:checked')?.value || 'PRESENCIAL';

    btn.disabled = true;
    btn.innerHTML = `<span class="spinner-border spinner-border-sm me-2"></span> Registrando marcaje biométrico / GPS...`;

    try {
        const today = new Date().toISOString().split('T')[0];
        const payload = {
            tipo: 'ASISTENCIA',
            fecha: today,
            modalidad: modalidad,
            observaciones: `Marcaje checador digital [${modalidad}] a las ${new Date().toLocaleTimeString()}`
        };

        if (AppState.currentGpsCoords) {
            payload.latitud = AppState.currentGpsCoords.lat;
            payload.longitud = AppState.currentGpsCoords.lng;
        }

        const res = await fetch('api/incidencias.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await res.json();

        if (data.success) {
            showToast('¡Asistencia registrada con éxito!', 'success');
            feedback.innerHTML = `<span class="text-success fw-bold"><i class="bi bi-check-circle-fill"></i> Entrada registrada hoy exitosamente a las ${new Date().toLocaleTimeString()} [${modalidad}]</span>`;
            loadEmpleadoPortalData();
        } else {
            showToast(data.message || 'Error al registrar marcaje.', 'warning');
            feedback.innerHTML = `<span class="text-warning fw-semibold"><i class="bi bi-exclamation-triangle-fill"></i> ${data.message}</span>`;
        }
    } catch (err) {
        console.error(err);
        showToast('Error de conexión.', 'danger');
    } finally {
        btn.disabled = false;
        btn.innerHTML = originalContent;
    }
}

/**
 * Solicitud de vacaciones o incidencias por empleado
 */
async function handleSolicitudEmpleadoSubmit(e) {
    e.preventDefault();
    const form = e.target;
    const formData = new FormData(form);
    const payload = Object.fromEntries(formData.entries());

    try {
        const res = await fetch('api/incidencias.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await res.json();

        if (data.success) {
            showToast('Solicitud enviada a Recursos Humanos para autorización.', 'success');
            form.reset();
            const modalEl = document.getElementById('modalSolicitudIncidencia');
            bootstrap.Modal.getInstance(modalEl).hide();
            loadEmpleadoPortalData();
        } else {
            showToast(data.message || 'Error al enviar solicitud.', 'danger');
        }
    } catch (err) {
        console.error(err);
        showToast('Error de comunicación.', 'danger');
    }
}

// ========================================================
// 11. RELOJ DIGITAL Y FUNCIONES DE UTILIDAD
// ========================================================

/**
 * Inicia reloj digital en vivo
 */
function startDigitalClock() {
    const timeDisplay = document.getElementById('liveClockDisplay');
    const dateDisplay = document.getElementById('liveDateDisplay');

    function updateTime() {
        const now = new Date();
        const hours = String(now.getHours()).padStart(2, '0');
        const minutes = String(now.getMinutes()).padStart(2, '0');
        const seconds = String(now.getSeconds()).padStart(2, '0');

        if (timeDisplay) {
            timeDisplay.textContent = `${hours}:${minutes}:${seconds}`;
        }

        if (dateDisplay) {
            const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
            dateDisplay.textContent = now.toLocaleDateString('es-MX', options);
        }
    }

    updateTime();
    AppState.clockInterval = setInterval(updateTime, 1000);
}

/**
 * Muestra notificación Toast flotante
 */
function showToast(message, type = 'info') {
    const container = document.getElementById('toastContainer');
    if (!container) return;

    const toastId = 'toast_' + Date.now();
    const bgClass = type === 'success' ? 'bg-success text-white' : 
                   (type === 'danger' ? 'bg-danger text-white' : 
                   (type === 'warning' ? 'bg-warning text-dark' : 'bg-primary text-white'));

    const icon = type === 'success' ? 'bi-check-circle-fill' : 
                (type === 'danger' ? 'bi-x-circle-fill' : 
                (type === 'warning' ? 'bi-exclamation-triangle-fill' : 'bi-info-circle-fill'));

    const toastEl = document.createElement('div');
    toastEl.className = `toast align-items-center ${bgClass} border-0 shadow-lg mb-2`;
    toastEl.id = toastId;
    toastEl.setAttribute('role', 'alert');
    toastEl.setAttribute('aria-live', 'assertive');
    toastEl.setAttribute('aria-atomic', 'true');

    toastEl.innerHTML = `
        <div class="d-flex">
            <div class="toast-body d-flex align-items-center gap-2">
                <i class="bi ${icon} fs-5"></i>
                <div>${escapeHtml(message)}</div>
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    `;

    container.appendChild(toastEl);
    const bsToast = new bootstrap.Toast(toastEl, { delay: 4500 });
    bsToast.show();

    toastEl.addEventListener('hidden.bs.toast', () => {
        toastEl.remove();
    });
}

/**
 * Formato de moneda
 */
function formatMoney(amount) {
    const num = parseFloat(amount);
    if (isNaN(num)) return '0.00';
    return num.toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

/**
 * Escapa HTML para prevenir XSS
 */
function escapeHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

/**
 * Formato relativo de tiempo
 */
function formatRelativeTime(dateStr) {
    if (!dateStr) return '';
    const d = new Date(dateStr);
    const now = new Date();
    const diffSec = Math.round((now - d) / 1000);

    if (diffSec < 60) return 'Hace un momento';
    if (diffSec < 3600) return `Hace ${Math.floor(diffSec / 60)} min`;
    if (diffSec < 86400) return `Hace ${Math.floor(diffSec / 3600)} h`;
    return d.toLocaleDateString('es-MX');
}

/**
 * Rellena strings
 */
function strPad(str, targetLength, padString) {
    str = String(str);
    while (str.length < targetLength) {
        str = padString + str;
    }
    return str;
}


// ========================================================
// 10. MÓDULO DE FACTURACIÓN ELECTRÓNICA & PAC PROFACT
// ========================================================

/**
 * Consulta la configuración de ProFact y actualiza los indicadores del Navbar y Modal
 */
async function cargarProfactConfigModal() {
    try {
        const res = await fetch('api/profact.php');
        const data = await res.json();
        if (data.success && data.data && data.data.config) {
            const cfg = data.data.config;
            const badgeNav = document.getElementById('badgeNavbarPacAmbiente');
            if (badgeNav) badgeNav.textContent = cfg.ambiente;

            const badgeStatus = document.getElementById('pacStatusBadge');
            if (badgeStatus) {
                badgeStatus.textContent = (cfg.ambiente === 'SIMULADO') ? 'SIMULADO' : 'ONLINE';
                badgeStatus.className = (cfg.ambiente === 'SIMULADO') ? 'badge bg-warning text-dark' : 'badge bg-success';
            }

            const badgeAmbiente = document.getElementById('pacAmbienteBadge');
            if (badgeAmbiente) badgeAmbiente.textContent = cfg.ambiente;

            const kpiTimbres = document.getElementById('kpiTimbresProfact');
            if (kpiTimbres) kpiTimbres.textContent = (cfg.timbres_disponibles || 10000).toLocaleString('es-MX');

            // Llenar inputs
            if (document.getElementById('profactAmbienteSelect')) document.getElementById('profactAmbienteSelect').value = cfg.ambiente;
            if (document.getElementById('profactUsuarioIntegradorInput')) document.getElementById('profactUsuarioIntegradorInput').value = cfg.usuario_integrador || '';
            if (document.getElementById('profactRfcEmisorInput')) document.getElementById('profactRfcEmisorInput').value = cfg.rfc_emisor || '';
            if (document.getElementById('profactRazonSocialInput')) document.getElementById('profactRazonSocialInput').value = cfg.razon_social_emisor || '';
            if (document.getElementById('profactRegimenInput')) document.getElementById('profactRegimenInput').value = cfg.regimen_fiscal_emisor || '601';
            if (document.getElementById('profactLugarExpedicionInput')) document.getElementById('profactLugarExpedicionInput').value = cfg.lugar_expedicion || '06600';
            if (document.getElementById('pacEndpointDesc')) {
                document.getElementById('pacEndpointDesc').textContent = 'Endpoint: ' + (cfg.wsdl_url || 'https://pruebas.timbracfdi33.mx/Timbrado.asmx?wsdl');
            }
        }
    } catch (err) {
        console.error('Error al cargar config de ProFact:', err);
    }
}

/**
 * Prueba la conexión en vivo contra el Web Service de ProFact
 */
async function testConexionProfactLive() {
    const btn = document.getElementById('btnTestConexionProfact');
    const output = document.getElementById('pacTestOutput');
    const origHtml = btn.innerHTML;

    btn.disabled = true;
    btn.innerHTML = `<span class="spinner-border spinner-border-sm me-1"></span> Conectando...`;
    output.innerHTML = `<span class="text-primary"><i class="bi bi-arrow-repeat spin me-1"></i> Enviando petición SOAP al Web Service de ProFact...</span>`;

    try {
        const res = await fetch('api/profact.php?action=test');
        const data = await res.json();

        if (data.success && data.data) {
            const d = data.data;
            output.innerHTML = `
                <div class="text-success fw-bold mb-1"><i class="bi bi-check-circle-fill"></i> ¡Conexión con ProFact Exitosa!</div>
                <div><strong>Ambiente:</strong> <span class="badge bg-secondary">${d.ambiente}</span> &bull; <strong>Latencia:</strong> <span class="badge bg-light text-dark font-monospace">${d.tiempo_ms} ms</span> &bull; <strong>Timbres Disponibles:</strong> <span class="text-primary fw-bold">${(d.timbres_disponibles || 0).toLocaleString('es-MX')}</span></div>
                <div class="text-muted mt-1 small">${escapeHtml(d.mensaje)}</div>
            `;
            showToast('Conexión con ProFact establecida con éxito (' + d.tiempo_ms + 'ms)', 'success');
            cargarProfactConfigModal();
        } else {
            output.innerHTML = `
                <div class="text-danger fw-bold mb-1"><i class="bi bi-exclamation-triangle-fill"></i> Respuesta de ProFact:</div>
                <div class="text-dark small">${escapeHtml(data.message || 'Error desconocido')}</div>
            `;
            showToast('Aviso de ProFact: ' + data.message, 'warning');
        }
    } catch (err) {
        output.innerHTML = `<div class="text-danger small"><i class="bi bi-x-circle me-1"></i> Error de conexión de red local.</div>`;
        showToast('Error de comunicación.', 'danger');
    } finally {
        btn.disabled = false;
        btn.innerHTML = origHtml;
    }
}

/**
 * Guarda los cambios en la configuración de ProFact
 */
async function guardarProfactConfig(e) {
    e.preventDefault();
    const form = e.target;
    const formData = new FormData(form);
    const payload = Object.fromEntries(formData.entries());
    payload.action = 'guardar_config';

    try {
        const res = await fetch('api/profact.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await res.json();

        if (data.success) {
            showToast(data.message, 'success');
            cargarProfactConfigModal();
            loadFacturasData();
            const modalEl = document.getElementById('modalProfactConfig');
            bootstrap.Modal.getInstance(modalEl).hide();
        } else {
            showToast(data.message || 'Error al guardar.', 'danger');
        }
    } catch (err) {
        showToast('Error al procesar la solicitud.', 'danger');
    }
}

/**
 * Carga el listado de facturas emitidas y KPIs
 */
async function loadFacturasData() {
    const tbody = document.getElementById('tablaFacturasBody');
    if (!tbody) return;

    tbody.innerHTML = `<tr><td colspan="10" class="text-center py-4"><span class="spinner-border spinner-border-sm me-2"></span>Cargando facturas...</td></tr>`;

    try {
        const res = await fetch('api/facturacion.php');
        const data = await res.json();

        if (data.success && data.data) {
            const facturas = data.data.facturas || [];
            const stats = data.data.stats || {};

            const kpiTot = document.getElementById('kpiTotalFacturas');
            const kpiTimb = document.getElementById('kpiFacturasTimbradas');
            const kpiMonto = document.getElementById('kpiMontoFacturado');
            const badgeTot = document.getElementById('badgeTotalFacturas');

            if (kpiTot) kpiTot.textContent = stats.total_facturas || 0;
            if (kpiTimb) kpiTimb.textContent = stats.timbradas || 0;
            if (kpiMonto) kpiMonto.textContent = '$' + formatMoney(stats.monto_total_facturado || 0);
            if (badgeTot) badgeTot.textContent = `${facturas.length} comprobantes`;

            renderFacturasTable(facturas);
        }
    } catch (err) {
        tbody.innerHTML = `<tr><td colspan="10" class="text-center py-4 text-danger">Error al cargar facturas.</td></tr>`;
    }
}

/**
 * Renderiza la tabla de facturas
 */
function renderFacturasTable(facturas) {
    const tbody = document.getElementById('tablaFacturasBody');
    if (!tbody) return;

    if (!facturas || facturas.length === 0) {
        tbody.innerHTML = `<tr><td colspan="10" class="text-center py-4 text-muted">No hay facturas registradas. Haz clic en "Nueva Factura" para emitir una con ProFact.</td></tr>`;
        return;
    }

    tbody.innerHTML = facturas.map(f => {
        const badgeClass = (f.estatus === 'TIMBRADA') ? 'bg-success' : (f.estatus === 'CANCELADA' ? 'bg-danger' : 'bg-secondary');
        const tieneUuid = !!f.folio_fiscal;

        return `
            <tr>
                <td><strong class="text-dark">${escapeHtml(f.serie)}-${f.folio}</strong></td>
                <td>
                    <div class="fw-semibold text-dark">${escapeHtml(f.cliente_nombre)}</div>
                    <small class="font-monospace text-muted">${escapeHtml(f.cliente_rfc)}</small>
                </td>
                <td class="small text-muted">${f.fecha_emision ? f.fecha_emision.substring(0, 16) : '-'}</td>
                <td>$${formatMoney(f.subtotal)}</td>
                <td class="text-muted">$${formatMoney(f.iva)}</td>
                <td class="fw-bold text-success">$${formatMoney(f.total)}</td>
                <td>
                    ${tieneUuid ? `
                        <span class="font-monospace small bg-light p-1 rounded border text-primary" style="font-size: 0.72rem;" title="${f.folio_fiscal}">
                            <i class="bi bi-shield-check text-success"></i> ${f.folio_fiscal.substring(0, 13)}...
                        </span>
                    ` : `<span class="badge bg-light text-muted border">Sin Timbre</span>`}
                </td>
                <td><span class="badge bg-info text-dark font-monospace" style="font-size: 0.65rem;">${escapeHtml(f.pac_utilizado || 'PROFACT')}</span></td>
                <td><span class="badge ${badgeClass}">${f.estatus}</span></td>
                <td class="text-end">
                    <div class="btn-group">
                        ${f.estatus === 'BORRADOR' ? `
                            <button class="btn btn-sm btn-success" onclick="timbrarFacturaProfact(${f.id})" title="Timbrar con ProFact">
                                <i class="bi bi-lightning-charge"></i> Timbrar
                            </button>
                        ` : ''}
                        ${tieneUuid ? `
                            <a href="api/facturacion.php?id=${f.id}&format=xml" class="btn btn-sm btn-outline-info" target="_blank" title="Descargar XML CFDI">
                                <i class="bi bi-file-code"></i>
                            </a>
                            <button class="btn btn-sm btn-outline-primary" onclick="verFacturaCFDI(${f.id})" title="Ver Factura / PDF">
                                <i class="bi bi-receipt"></i>
                            </button>
                        ` : ''}
                        ${f.estatus === 'TIMBRADA' ? `
                            <button class="btn btn-sm btn-outline-danger" onclick="cancelarFacturaCFDI(${f.id}, '${f.folio_fiscal}')" title="Cancelar CFDI 4.0">
                                <i class="bi bi-x-circle"></i>
                            </button>
                        ` : ''}
                    </div>
                </td>
            </tr>
        `;
    }).join('');
}

/**
 * Guarda una nueva factura desde el modal
 */
async function guardarNuevaFactura(e) {
    e.preventDefault();
    const form = e.target;
    const formData = new FormData(form);

    const cant = parseFloat(formData.get('concepto_cantidad')) || 1;
    const precio = parseFloat(formData.get('concepto_valor_unitario')) || 0;

    const payload = {
        action: 'crear',
        cliente_nombre: formData.get('cliente_nombre'),
        cliente_rfc: formData.get('cliente_rfc'),
        cliente_domicilio_fiscal: formData.get('cliente_domicilio_fiscal'),
        cliente_uso_cfdi: formData.get('cliente_uso_cfdi'),
        cliente_regimen_fiscal: formData.get('cliente_regimen_fiscal'),
        forma_pago: formData.get('forma_pago'),
        metodo_pago: formData.get('metodo_pago'),
        serie: 'F',
        conceptos: [
            {
                clave_prod_serv: formData.get('concepto_clave'),
                descripcion: formData.get('concepto_descripcion'),
                cantidad: cant,
                clave_unidad: 'E48',
                valor_unitario: precio
            }
        ]
    };

    try {
        const res = await fetch('api/facturacion.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await res.json();

        if (data.success) {
            showToast(data.message, 'success');
            form.reset();
            const modalEl = document.getElementById('modalNuevaFactura');
            bootstrap.Modal.getInstance(modalEl).hide();
            loadFacturasData();
        } else {
            showToast(data.message || 'Error al guardar factura.', 'danger');
        }
    } catch (err) {
        showToast('Error de comunicación al guardar.', 'danger');
    }
}

/**
 * Timbra una factura existente a través de ProFact
 */
async function timbrarFacturaProfact(facturaId) {
    if (!confirm('¿Deseas enviar esta factura al PAC ProFact para su certificación y timbrado fiscal ante el SAT?')) {
        return;
    }

    showToast('Enviando comprobante al Web Service de ProFact...', 'info');

    try {
        const res = await fetch('api/facturacion.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'timbrar', factura_id: facturaId })
        });
        const data = await res.json();

        if (data.success) {
            showToast(data.message, 'success');
            await loadFacturasData();
            loadAuditTrail();
            loadNotificaciones();

            // 1. Descarga automática instantánea del archivo XML del CFDI timbrado
            const link = document.createElement('a');
            link.href = `api/facturacion.php?id=${facturaId}&format=xml`;
            link.download = `CFDI_Factura_${data.data?.serie || 'F'}-${data.data?.folio || facturaId}.xml`;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);

            // 2. Abre automáticamente la factura oficial CFDI 4.0 con QR y sellos
            setTimeout(() => {
                verFacturaCFDI(facturaId);
            }, 350);
        } else {
            showToast(data.message || 'Error en el timbrado.', 'danger');
        }
    } catch (err) {
        showToast('Error de comunicación con el servidor.', 'danger');
    }
}

/**
 * Visualiza la factura oficial con sellos digitales y QR
 */
async function verFacturaCFDI(facturaId) {
    try {
        const res = await fetch(`api/facturacion.php?id=${facturaId}`);
        const data = await res.json();

        if (!data.success || !data.data) {
            showToast('No se pudo cargar la factura.', 'danger');
            return;
        }

        const f = data.data;
        const printable = document.getElementById('modalFacturaPrintable');
        const btnXml = document.getElementById('btnDescargarXmlFactura');
        if (btnXml) btnXml.href = `api/facturacion.php?id=${f.id}&format=xml`;

        printable.innerHTML = `
            ${f.folio_fiscal ? `
                <div class="alert alert-success d-flex flex-wrap align-items-center justify-content-between p-3 mb-3 no-print rounded-3 border-0 shadow-sm" style="background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%);">
                    <div class="d-flex align-items-center gap-2 mb-2 mb-md-0">
                        <i class="bi bi-patch-check-fill fs-2 text-success"></i>
                        <div>
                            <strong class="text-dark d-block fs-6">¡Factura CFDI 4.0 Timbrada con Éxito por ProFact!</strong>
                            <span class="small text-muted">Archivo <strong>XML</strong> disponible para descarga &bull; Folio Fiscal UUID: <span class="font-monospace text-dark fw-bold">${f.folio_fiscal}</span></span>
                        </div>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="api/facturacion.php?id=${f.id}&format=xml" download="CFDI_Factura_${f.serie}-${f.folio}.xml" class="btn btn-sm btn-success text-white shadow-sm fw-semibold">
                            <i class="bi bi-file-earmark-code me-1"></i> Descargar XML
                        </a>
                        <button type="button" class="btn btn-sm btn-primary shadow-sm fw-semibold" onclick="window.print()">
                            <i class="bi bi-printer me-1"></i> Imprimir Factura / Guardar PDF
                        </button>
                    </div>
                </div>
            ` : ''}
            <div class="p-3 bg-white border rounded">
                <div class="row align-items-center mb-4 pb-3 border-bottom">
                    <div class="col-sm-7">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <div class="navbar-brand-logo bg-primary text-white p-2 rounded" style="width: 36px; height: 36px; display: inline-flex; align-items: center; justify-content: center;">
                                <i class="bi bi-file-earmark-text"></i>
                            </div>
                            <h5 class="fw-bold mb-0 text-dark">FACTURA ELECTRÓNICA (CFDI 4.0)</h5>
                        </div>
                        <p class="small text-muted mb-0"><strong>Emisor:</strong> SISTEMA DE NOMINAS Y PERSONAL S.A. DE C.V.</p>
                        <p class="small text-muted mb-0"><strong>RFC Emisor:</strong> NOM210315AB1 &bull; <strong>Régimen:</strong> 601 General de Ley</p>
                        <p class="small text-muted mb-0"><strong>Lugar Expedición:</strong> 06600 &bull; <strong>PAC:</strong> PROFACT (SAT 56037)</p>
                    </div>
                    <div class="col-sm-5 text-sm-end mt-3 mt-sm-0">
                        <div class="badge bg-primary text-uppercase px-3 py-2 mb-2 fs-6">${f.serie}-${f.folio}</div>
                        <div class="small text-muted"><strong>Fecha:</strong> ${f.fecha_emision}</div>
                        <div class="small text-muted"><strong>Tipo:</strong> Ingreso (I) &bull; <strong>Moneda:</strong> MXN</div>
                    </div>
                </div>

                <div class="card bg-light border-0 p-3 mb-3">
                    <h6 class="fw-bold small text-dark mb-2">RECEPTOR (CLIENTE)</h6>
                    <div class="row small">
                        <div class="col-md-7">
                            <span class="text-muted">Nombre:</span> <strong>${escapeHtml(f.cliente_nombre)}</strong><br>
                            <span class="text-muted">RFC:</span> <span class="font-monospace fw-bold">${escapeHtml(f.cliente_rfc)}</span><br>
                            <span class="text-muted">Domicilio Fiscal (CP):</span> ${escapeHtml(f.cliente_domicilio_fiscal)}
                        </div>
                        <div class="col-md-5">
                            <span class="text-muted">Uso de CFDI:</span> ${escapeHtml(f.cliente_uso_cfdi)}<br>
                            <span class="text-muted">Régimen Fiscal:</span> ${escapeHtml(f.cliente_regimen_fiscal)}<br>
                            <span class="text-muted">Método de Pago:</span> ${escapeHtml(f.metodo_pago)} - PUE
                        </div>
                    </div>
                </div>

                <div class="table-responsive mb-3">
                    <table class="table table-sm table-bordered align-middle small mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Clave SAT</th>
                                <th>Descripción</th>
                                <th class="text-center">Cant.</th>
                                <th class="text-end">Precio Unitario</th>
                                <th class="text-end">Importe</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${(f.conceptos || []).map(c => `
                                <tr>
                                    <td><span class="font-monospace text-muted">${escapeHtml(c.clave_prod_serv)}</span></td>
                                    <td><strong>${escapeHtml(c.descripcion)}</strong></td>
                                    <td class="text-center">${c.cantidad}</td>
                                    <td class="text-end">$${formatMoney(c.valor_unitario)}</td>
                                    <td class="text-end fw-semibold">$${formatMoney(c.importe)}</td>
                                </tr>
                            `).join('')}
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="4" class="text-end fw-bold">Subtotal:</td>
                                <td class="text-end fw-bold">$${formatMoney(f.subtotal)}</td>
                            </tr>
                            <tr>
                                <td colspan="4" class="text-end text-muted">IVA Trasladado (16%):</td>
                                <td class="text-end text-muted">$${formatMoney(f.iva)}</td>
                            </tr>
                            <tr class="table-success">
                                <td colspan="4" class="text-end fs-6 fw-bold">Total a Pagar:</td>
                                <td class="text-end fs-6 fw-bold text-success">$${formatMoney(f.total)} MXN</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <div class="card bg-light border p-3 small">
                    <div class="row align-items-center">
                        <div class="col-sm-3 text-center mb-2 mb-sm-0">
                            <div class="p-2 bg-white border rounded d-inline-block">
                                <i class="bi bi-qr-code fs-1 text-dark"></i>
                                <div style="font-size: 0.65rem;" class="text-muted">SAT CFDI 4.0</div>
                            </div>
                        </div>
                        <div class="col-sm-9 font-monospace" style="font-size: 0.7rem;">
                            <div><strong class="text-dark">Folio Fiscal (UUID):</strong> <span class="text-primary">${f.folio_fiscal || 'SIN TIMBRAR'}</span></div>
                            <div><strong class="text-dark">No. Certificado SAT:</strong> 20001000000500004589 &bull; <strong>PAC:</strong> PROFACT (SAT 56037)</div>
                            <div><strong class="text-dark">Fecha Certificación:</strong> ${f.fecha_timbrado || '-'}</div>
                            <div class="text-truncate text-muted mt-1" title="${f.sello_sat || ''}"><strong>Sello SAT:</strong> ${f.sello_sat || '-'}</div>
                        </div>
                    </div>
                </div>
            </div>
        `;

        const modalEl = new bootstrap.Modal(document.getElementById('modalVisorFactura'));
        modalEl.show();

    } catch (err) {
        showToast('Error al consultar factura.', 'danger');
    }
}

/**
 * Cancela una factura ante ProFact
 */
async function cancelarFacturaCFDI(facturaId, folioFiscal) {
    const motivo = prompt(`Ingresa el motivo de cancelación ante el SAT para el Folio Fiscal UUID:\n${folioFiscal}\n\nOpciones SAT:\n01 - Comprobante emitido con errores con relación\n02 - Comprobante emitido con errores sin relación\n03 - No se llevó a cabo la operación\n04 - Operación nominativa relacionada en una factura global`, '02');
    
    if (!motivo) return;

    showToast('Enviando solicitud de cancelación a ProFact (CancelaCFDI40)...', 'info');

    try {
        const res = await fetch('api/facturacion.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'cancelar', factura_id: facturaId, motivo_cancelacion: motivo })
        });
        const data = await res.json();

        if (data.success) {
            showToast(data.message, 'success');
            loadFacturasData();
            loadAuditTrail();
        } else {
            showToast(data.message || 'Error al cancelar.', 'danger');
        }
    } catch (err) {
        showToast('Error de comunicación al cancelar.', 'danger');
    }
}
