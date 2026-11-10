-- ========================================================
-- SISTEMA DE NÓMINAS Y GESTIÓN DE PERSONAL
-- Base de Datos: sistema_nominas
-- Esquema Completo conforme a Especificación de Servicios
-- ========================================================

CREATE DATABASE IF NOT EXISTS `sistema_nominas` 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

USE `sistema_nominas`;

-- Desactivar temporalmente revisión de llaves foráneas para reinicialización limpia
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `auditoria_logs`;
DROP TABLE IF EXISTS `notificaciones`;
DROP TABLE IF EXISTS `reglas_fiscales`;
DROP TABLE IF EXISTS `detalle_nomina`;
DROP TABLE IF EXISTS `nominas`;
DROP TABLE IF EXISTS `incidencias`;
DROP TABLE IF EXISTS `usuarios`;
DROP TABLE IF EXISTS `empleados`;

SET FOREIGN_KEY_CHECKS = 1;

-- --------------------------------------------------------
-- 1. TABLA: empleados (Servicio 1: HR Core y Expedientes)
-- --------------------------------------------------------
CREATE TABLE `empleados` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `nombre` VARCHAR(150) NOT NULL,
    `rfc` VARCHAR(13) NOT NULL UNIQUE,
    `curp` VARCHAR(18) NOT NULL UNIQUE,
    `puesto` VARCHAR(100) NOT NULL,
    `departamento` VARCHAR(100) NOT NULL,
    `tipo_contrato` VARCHAR(50) NOT NULL DEFAULT 'INDETERMINADO',
    `salario_diario` DECIMAL(10,2) NOT NULL,
    `salario_diario_integrado` DECIMAL(10,2) NULL,
    `cuenta_clabe` VARCHAR(18) NOT NULL UNIQUE,
    `banco` VARCHAR(50) NOT NULL DEFAULT 'BBVA MEXICO',
    `fecha_ingreso` DATE NOT NULL,
    `dias_vacaciones_disponibles` INT NOT NULL DEFAULT 12,
    `estatus` ENUM('ACTIVO', 'INACTIVO') NOT NULL DEFAULT 'ACTIVO',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 2. TABLA: usuarios (Servicio 7: Identidad & RBAC)
-- --------------------------------------------------------
CREATE TABLE `usuarios` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `email` VARCHAR(120) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `rol` ENUM('ADMIN', 'RH', 'AUDITOR', 'EMPLEADO') NOT NULL DEFAULT 'EMPLEADO',
    `empleado_id` INT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_usuarios_empleados` 
        FOREIGN KEY (`empleado_id`) REFERENCES `empleados` (`id`) 
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 3. TABLA: incidencias (Servicio 2: Incidencias y Asistencia)
-- --------------------------------------------------------
CREATE TABLE `incidencias` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `empleado_id` INT NOT NULL,
    `fecha` DATE NOT NULL,
    `tipo` ENUM('ASISTENCIA', 'FALTA', 'RETARDO', 'HORA_EXTRA', 'VACACIONES', 'INCAPACIDAD', 'PERMISO') NOT NULL,
    `modalidad` ENUM('PRESENCIAL', 'TELETRABAJO') NOT NULL DEFAULT 'PRESENCIAL',
    `latitud` DECIMAL(10,8) NULL,
    `longitud` DECIMAL(11,8) NULL,
    `ip_origen` VARCHAR(45) NULL,
    `horas_extra` DECIMAL(4,2) DEFAULT 0.00,
    `tipo_incapacidad` VARCHAR(50) NULL,
    `folio_medico` VARCHAR(50) NULL,
    `estatus` ENUM('PENDIENTE', 'APROBADO', 'RECHAZADO') NOT NULL DEFAULT 'PENDIENTE',
    `observaciones` VARCHAR(255) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_incidencias_empleados` 
        FOREIGN KEY (`empleado_id`) REFERENCES `empleados` (`id`) 
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 4. TABLA: nominas (Ciclo de Nómina y Congelamiento)
-- --------------------------------------------------------
CREATE TABLE `nominas` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `periodo` VARCHAR(50) NOT NULL UNIQUE, -- Ej: "2026-Q1", "2026-Q2"
    `fecha_inicio` DATE NOT NULL,
    `fecha_fin` DATE NOT NULL,
    `estatus` ENUM('BORRADOR', 'CONGELADA', 'TIMBRADA', 'DISPERSADA') NOT NULL DEFAULT 'BORRADOR',
    `total_percepciones` DECIMAL(12,2) DEFAULT 0.00,
    `total_horas_extra` DECIMAL(12,2) DEFAULT 0.00,
    `total_bonos` DECIMAL(12,2) DEFAULT 0.00,
    `total_deducciones` DECIMAL(12,2) DEFAULT 0.00,
    `total_isr` DECIMAL(12,2) DEFAULT 0.00,
    `total_imss` DECIMAL(12,2) DEFAULT 0.00,
    `total_neto` DECIMAL(12,2) DEFAULT 0.00,
    `fecha_cierre` DATETIME NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 5. TABLA: detalle_nomina (Cálculo, CFDI y Dispersión)
-- --------------------------------------------------------
CREATE TABLE `detalle_nomina` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `nomina_id` INT NOT NULL,
    `empleado_id` INT NOT NULL,
    `dias_trabajados` INT NOT NULL DEFAULT 15,
    `sueldo_base` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `pago_horas_extra` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `bonos` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `deduccion_isr` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `deduccion_imss` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `deduccion_infonavit` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `deduccion_prestamo` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `ajuste_manual` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `justificacion_ajuste` VARCHAR(255) NULL,
    `sueldo_neto` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `estado_dispersion` ENUM('PENDIENTE', 'EXITOSO', 'REBOTADO') NOT NULL DEFAULT 'PENDIENTE',
    `referencia_bancaria` VARCHAR(50) NULL,
    `folio_fiscal` VARCHAR(36) NULL, -- UUID de Timbrado
    `sello_sat` TEXT NULL,
    `xml_cfdi` LONGTEXT NULL,
    `fecha_timbrado` DATETIME NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_detalle_nomina_nominas` 
        FOREIGN KEY (`nomina_id`) REFERENCES `nominas` (`id`) 
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_detalle_nomina_empleados` 
        FOREIGN KEY (`empleado_id`) REFERENCES `empleados` (`id`) 
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 6. TABLA: notificaciones (Servicio 6: Notificaciones)
-- --------------------------------------------------------
CREATE TABLE `notificaciones` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `usuario_id` INT NULL,
    `empleado_id` INT NULL,
    `tipo_evento` VARCHAR(50) NOT NULL,
    `titulo` VARCHAR(150) NOT NULL,
    `mensaje` TEXT NOT NULL,
    `canal` ENUM('PUSH', 'EMAIL', 'PORTAL') DEFAULT 'PORTAL',
    `leido` TINYINT(1) DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (`usuario_id`),
    INDEX (`empleado_id`),
    INDEX (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 7. TABLA: auditoria_logs (Audit Trail Inmutable)
-- --------------------------------------------------------
CREATE TABLE `auditoria_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `usuario_id` INT NULL,
    `usuario_nombre` VARCHAR(150) NULL,
    `rol` VARCHAR(50) NULL,
    `accion` VARCHAR(100) NOT NULL,
    `tabla_afectada` VARCHAR(50) NULL,
    `registro_id` INT NULL,
    `descripcion` TEXT NOT NULL,
    `datos_anteriores` LONGTEXT NULL,
    `datos_nuevos` LONGTEXT NULL,
    `ip` VARCHAR(45) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (`usuario_id`),
    INDEX (`accion`),
    INDEX (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 8. TABLA: reglas_fiscales (Motor de Reglas y Parámetros)
-- --------------------------------------------------------
CREATE TABLE `reglas_fiscales` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `clave` VARCHAR(50) NOT NULL UNIQUE,
    `nombre` VARCHAR(100) NOT NULL,
    `valor` DECIMAL(10,4) NOT NULL,
    `unidad` VARCHAR(20) NOT NULL DEFAULT '%',
    `descripcion` VARCHAR(255) NULL,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================================
-- SEEDERS (Datos Iniciales de Prueba)
-- Contraseña general por defecto: 123456
-- ========================================================

-- Inserción de Parámetros Fiscales
INSERT INTO `reglas_fiscales` (`clave`, `nombre`, `valor`, `unidad`, `descripcion`) VALUES
('ISR_TASA_BASE', 'Tasa Media Retención ISR', 10.0000, '%', 'Porcentaje de retención mensual/quincenal sobre percepciones gravables'),
('IMSS_CUOTA_OBRERA', 'Cuota Obrera IMSS', 2.5000, '%', 'Aportación de seguridad social retenida al trabajador'),
('IMSS_CUOTA_PATRONAL', 'Cuota Patronal IMSS', 20.5000, '%', 'Aportación patronal a la seguridad social'),
('INFONAVIT_PORCENTAJE', 'Aportación Patronal Infonavit', 5.0000, '%', 'Fondo de vivienda de los trabajadores'),
('SALARIO_MINIMO_GENERAL', 'Salario Mínimo General Diario', 248.9300, 'MXN', 'Salario mínimo diario oficial vigente'),
('PRIMA_RIESGO_TRABAJO', 'Prima de Riesgo de Trabajo', 0.5435, '%', 'Factor de riesgo de trabajo clase I oficina'),
('SUBSIDIO_EMPLEO_TOPE', 'Tope Ingreso Subsidio Empleo', 9081.0000, 'MXN', 'Límite de percepción mensual para subsidio acreditado');

-- Inserción de Empleados
INSERT INTO `empleados` (`id`, `nombre`, `rfc`, `curp`, `puesto`, `departamento`, `tipo_contrato`, `salario_diario`, `salario_diario_integrado`, `cuenta_clabe`, `banco`, `fecha_ingreso`, `dias_vacaciones_disponibles`, `estatus`) VALUES
(1, 'Carlos Eduardo Mendoza Rivas', 'MERC880315HJ2', 'MERC880315HDFRVR01', 'Director General', 'Dirección General', 'TIEMPO INDETERMINADO', 1250.00, 1311.63, '012180004567891234', 'BBVA MEXICO', '2021-01-15', 20, 'ACTIVO'),
(2, 'Ana Sofía Garza Valdés', 'GAVA920722M41', 'GAVA920722MDFRNS02', 'Gerente de Recursos Humanos', 'Recursos Humanos', 'TIEMPO INDETERMINADO', 850.00, 891.91, '014180007891234567', 'SANTANDER', '2022-03-01', 18, 'ACTIVO'),
(3, 'Juan Manuel Pérez López', 'PELJ940510KN5', 'PELJ940510HDFRLN03', 'Desarrollador Senior Backend', 'Tecnología', 'TIEMPO INDETERMINADO', 720.00, 755.50, '002180003216549870', 'BANAMEX', '2023-02-15', 16, 'ACTIVO'),
(4, 'Valeria Torres Fuentes', 'TOFV9511306T8', 'TOFV951130MDFRFV04', 'Especialista en Nóminas y Fiscal', 'Recursos Humanos', 'TIEMPO INDETERMINADO', 650.00, 682.05, '021180009874563211', 'HSBC', '2023-06-10', 16, 'ACTIVO'),
(5, 'Roberto Gómez Hernández', 'GOHR900805LP9', 'GOHR900805HDFRRG05', 'Diseñador UI/UX & Frontend', 'Tecnología', 'TIEMPO INDETERMINADO', 600.00, 629.58, '072180006549873214', 'BANORTE', '2023-09-01', 14, 'ACTIVO'),
(6, 'Elena Beatriz Navarro Solís', 'NASE9304184S3', 'NASE930418MDFRLN06', 'Contadora General', 'Finanzas', 'TIEMPO INDETERMINADO', 680.00, 713.52, '127180001239874562', 'BANCO AZTECA', '2022-11-20', 18, 'ACTIVO');

-- Inserción de Usuarios del Sistema (Password: 123456)
INSERT INTO `usuarios` (`id`, `email`, `password`, `rol`, `empleado_id`) VALUES
(1, 'admin@nominas.com', '$2y$10$tZ8n8gUo0KsqXm0wXNn6seW7gUeR9kS4Xq2fUfWzGk6nK4O0E2dGy', 'ADMIN', 1),
-- password '123456'
(2, 'rh@nominas.com', '$2y$10$tZ8n8gUo0KsqXm0wXNn6seW7gUeR9kS4Xq2fUfWzGk6nK4O0E2dGy', 'RH', 2),
(3, 'empleado@nominas.com', '$2y$10$tZ8n8gUo0KsqXm0wXNn6seW7gUeR9kS4Xq2fUfWzGk6nK4O0E2dGy', 'EMPLEADO', 3),
(4, 'auditor@nominas.com', '$2y$10$tZ8n8gUo0KsqXm0wXNn6seW7gUeR9kS4Xq2fUfWzGk6nK4O0E2dGy', 'AUDITOR', 6);

-- Inserción de Incidencias de Prueba
INSERT INTO `incidencias` (`empleado_id`, `fecha`, `tipo`, `modalidad`, `horas_extra`, `estatus`, `observaciones`) VALUES
(3, '2026-10-01', 'ASISTENCIA', 'PRESENCIAL', 0.00, 'APROBADO', 'Registro puntual de entrada reloj central'),
(3, '2026-10-02', 'HORA_EXTRA', 'PRESENCIAL', 3.00, 'APROBADO', 'Despliegue de actualización en servidores'),
(3, '2026-10-03', 'ASISTENCIA', 'TELETRABAJO', 0.00, 'APROBADO', 'Marcaje checador remoto IP autorizada'),
(5, '2026-10-02', 'HORA_EXTRA', 'PRESENCIAL', 2.50, 'APROBADO', 'Ajuste urgente de maquetación en release'),
(5, '2026-10-04', 'ASISTENCIA', 'PRESENCIAL', 0.00, 'APROBADO', 'Marcaje checador regular'),
(6, '2026-10-03', 'FALTA', 'PRESENCIAL', 0.00, 'APROBADO', 'Permiso personal sin goce de sueldo'),
(4, '2026-10-04', 'VACACIONES', 'PRESENCIAL', 0.00, 'APROBADO', 'Día de vacaciones autorizado por RH'),
(1, '2026-10-01', 'ASISTENCIA', 'PRESENCIAL', 0.00, 'APROBADO', 'Marcaje en sede central');

-- Inserción de Nómina Inicial
INSERT INTO `nominas` (`id`, `periodo`, `fecha_inicio`, `fecha_fin`, `estatus`, `total_percepciones`, `total_deducciones`, `total_neto`) VALUES
(1, '2026-Q1', '2026-10-01', '2026-10-15', 'BORRADOR', 0.00, 0.00, 0.00);

-- --------------------------------------------------------
-- 9. TABLA: configuracion_pac (ProFact PAC Integración)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `configuracion_pac` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `pac_nombre` VARCHAR(50) NOT NULL DEFAULT 'PROFACT',
    `ambiente` ENUM('SANDBOX', 'PRODUCCION', 'SIMULADO') NOT NULL DEFAULT 'SANDBOX',
    `usuario_integrador` VARCHAR(255) NULL,
    `rfc_emisor` VARCHAR(13) NOT NULL DEFAULT 'NOM210315AB1',
    `razon_social_emisor` VARCHAR(255) NOT NULL DEFAULT 'SISTEMA DE NOMINAS Y PERSONAL S.A. DE C.V.',
    `regimen_fiscal_emisor` VARCHAR(10) NOT NULL DEFAULT '601',
    `lugar_expedicion` VARCHAR(10) NOT NULL DEFAULT '06600',
    `wsdl_url` VARCHAR(255) NOT NULL DEFAULT 'https://pruebas.timbracfdi33.mx/Timbrado.asmx?wsdl',
    `timbres_disponibles` INT NOT NULL DEFAULT 10000,
    `ultima_verificacion` DATETIME NULL,
    `activo` TINYINT(1) NOT NULL DEFAULT 1,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `configuracion_pac` 
(`pac_nombre`, `ambiente`, `usuario_integrador`, `rfc_emisor`, `razon_social_emisor`, `regimen_fiscal_emisor`, `lugar_expedicion`, `wsdl_url`, `timbres_disponibles`, `ultima_verificacion`, `activo`)
VALUES 
('PROFACT', 'SANDBOX', 'DEMO_PROFACT_INTEGRADOR', 'NOM210315AB1', 'SISTEMA DE NOMINAS Y PERSONAL S.A. DE C.V.', '601', '06600', 'https://pruebas.timbracfdi33.mx/Timbrado.asmx?wsdl', 10000, NOW(), 1);

-- --------------------------------------------------------
-- 10. TABLA: facturas (CFDI 4.0 Ingreso con ProFact)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `facturas` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `serie` VARCHAR(10) NOT NULL DEFAULT 'F',
    `folio` INT NOT NULL DEFAULT 1,
    `tipo_comprobante` VARCHAR(5) NOT NULL DEFAULT 'I',
    `cliente_nombre` VARCHAR(255) NOT NULL,
    `cliente_rfc` VARCHAR(13) NOT NULL,
    `cliente_uso_cfdi` VARCHAR(10) NOT NULL DEFAULT 'G03',
    `cliente_regimen_fiscal` VARCHAR(10) NOT NULL DEFAULT '601',
    `cliente_domicilio_fiscal` VARCHAR(10) NOT NULL DEFAULT '06600',
    `fecha_emision` DATETIME NOT NULL,
    `moneda` VARCHAR(5) NOT NULL DEFAULT 'MXN',
    `forma_pago` VARCHAR(5) NOT NULL DEFAULT '03',
    `metodo_pago` VARCHAR(5) NOT NULL DEFAULT 'PUE',
    `subtotal` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `iva` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `retencion_isr` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `retencion_iva` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `total` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `folio_fiscal` VARCHAR(50) NULL,
    `sello_sat` TEXT NULL,
    `sello_cfd` TEXT NULL,
    `cadena_original` TEXT NULL,
    `codigo_qr` TEXT NULL,
    `xml_cfdi` LONGTEXT NULL,
    `estatus` ENUM('BORRADOR', 'TIMBRADA', 'CANCELADA') NOT NULL DEFAULT 'BORRADOR',
    `fecha_timbrado` DATETIME NULL,
    `pac_utilizado` VARCHAR(50) NOT NULL DEFAULT 'PROFACT',
    `motivo_cancelacion` VARCHAR(5) NULL,
    `creado_por` INT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 11. TABLA: detalle_factura (Conceptos de Factura)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `detalle_factura` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `factura_id` INT NOT NULL,
    `clave_prod_serv` VARCHAR(20) NOT NULL DEFAULT '84111500',
    `descripcion` VARCHAR(255) NOT NULL,
    `cantidad` DECIMAL(10,2) NOT NULL DEFAULT 1.00,
    `clave_unidad` VARCHAR(10) NOT NULL DEFAULT 'E48',
    `valor_unitario` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `importe` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `objeto_imp` VARCHAR(5) NOT NULL DEFAULT '02',
    `tasa_iva` DECIMAL(6,4) NOT NULL DEFAULT 0.1600,
    `iva` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    INDEX (`factura_id`),
    FOREIGN KEY (`factura_id`) REFERENCES `facturas`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
