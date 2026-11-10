# 💼 NóminaPay - Sistema Integral de Nóminas, Facturación CFDI 4.0 & Portal del Empleado

Sistema modular empresarial de gestión de capital humano, cálculo automatizado de nóminas mexicanas (ISR, IMSS, Infonavit), timbrado fiscal digital CFDI 4.0 con PAC ProFact, facturación electrónica, dispersión bancaria SPEI, auditoría de cambios y portal de autoconsulta para empleados con checador GPS y clima en tiempo real (OpenWeatherMap).

![PHP](https://img.shields.io/badge/PHP-8.2-777BB4?style=flat&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-8.0-4479A1?style=flat&logo=mysql&logoColor=white)
![Bootstrap](https://img.shields.io/badge/Bootstrap-5.3-7952B3?style=flat&logo=bootstrap&logoColor=white)
![CFDI 4.0](https://img.shields.io/badge/SAT-CFDI%204.0%20%2F%20Nómina%201.2-green?style=flat)
![ProFact PAC](https://img.shields.io/badge/PAC-ProFact-007ACC?style=flat)
![OpenWeatherMap](https://img.shields.io/badge/Weather-OpenWeatherMap-orange?style=flat)

---

## 🚀 Características y Servicios Principales

1. **HR Core & Expedientes Laborales**:
   - Catálogo de colaboradores, puestos, departamentos, RFC, CURP, NSS, cuentas CLABE y salarios diarios/integrados.
   - Historial de contratos y cálculo de días de vacaciones según LFT.
   - Emisión y descarga de Constancias Laborales oficiales en PDF.

2. **Control de Asistencias & Checador Digital**:
   - Registro digital de entradas/salidas con geolocalización GPS, dirección IP y sello de tiempo.
   - Detección de modalidad Presencial vs Teletrabajo.
   - Gestión y aprobación de solicitudes de incidencias (vacaciones, permisos con/sin goce, incapacidades IMSS).

3. **Motor de Cálculo Fiscal de Nómina (Reglas de Negocio)**:
   - Procesamiento de sueldos base proporcionales a días trabajados (quincenales/mensuales).
   - Cálculo automático de Horas Extra dobles y triples.
   - Bonos de puntualidad, productividad y compensaciones variables.
   - Tablas progresivas de retención de **ISR** (Tarifa SAT Art. 96 LISR).
   - Retención de cuotas obreras de seguridad social **IMSS** (Enfermedad, Maternidad, Invalidez y Vida, Cesantía y Vejez).
   - Descuentos de amortización **Infonavit** y créditos/préstamos internos de empresa.
   - Ajustes manuales justificados con auditoría estricta.

4. **Timbrado Fiscal CFDI 4.0 & PAC ProFact (SOAP)**:
   - Integración nativa con el PAC autorizado por el SAT **ProFact** (`https://pruebas.timbracfdi33.mx/Timbrado.asmx?wsdl`).
   - Generación de comprobantes CFDI 4.0 con **Complemento Nómina 1.2**.
   - Timbrado por lotes y re-timbrado en Sandbox de pruebas.
   - Entrega inmediata de **XML** certificado (`tfd:TimbreFiscalDigital`) con descarga automática.
   - Representación impresa del CFDI con código QR oficial del SAT y sellos digitales criptográficos.

5. **Facturación Electrónica de Ingreso (CFDI 4.0)**:
   - Módulo para generación y emisión de facturas electrónicas comerciales.
   - Claves de productos y servicios SAT (ej. `84111500`), catálogo de unidades (`E48`, `ACT`).
   - Timbrado directo con ProFact, consulta de sellos y cancelación fiscal ante el SAT (`CancelaCFDI40`).

6. **Dispersión Bancaria & Layouts Masivos (SPEI)**:
   - Generación de transferencias bancarias masivas.
   - Exportación de layouts bancarios oficiales (`.txt`) listos para banca electrónica empresarial (BBVA, Banorte, Santander, Banamex, etc.).
   - Validación de cuentas CLABE interbancarias a 18 dígitos.

7. **Portal de Autoconsulta del Colaborador (Employee Self-Service)**:
   - Acceso personal para consulta y descarga de recibos CFDI en PDF y XML.
   - Reloj checador interactivo.
   - **Clima en Tiempo Real (OpenWeatherMap)**: Monitoreo meteorológico en vivo con selector de ciudades de México y detección automática por coordenadas GPS.

8. **Auditoría (Audit Trail) & Message Broker**:
   - Registro inmutable de cada acción, cálculo, modificación de salario, ajuste manual o timbrado.
   - Arquitectura orientada a eventos (`ReciboTimbrado`, `IncidenciaAprobada`, `DispersionCompletada`).
   - Consola GraphQL y catálogo de metadatos de integración.

---

## 📂 Estructura del Proyecto

```text
nomina/
├── api/
│   ├── auditoria.php         # Logs inmutables y trazabilidad
│   ├── auth.php              # Autenticación, sesión y RBAC (Admin, RH, Auditor, Empleado)
│   ├── calculo.php           # Motor fiscal de nómina (ISR, IMSS, bonos, ajustes)
│   ├── clima.php             # Servicio de clima OpenWeatherMap API
│   ├── dispersion.php        # Dispersión bancaria SPEI y layouts TXT
│   ├── empleados.php         # HR Core y expedientes
│   ├── facturacion.php       # Ciclo de vida de facturas comerciales CFDI 4.0
│   ├── graphql.php           # Capa de consulta unificada GraphQL
│   ├── incidencias.php       # Checador GPS y solicitudes de incidencias
│   ├── notificaciones.php    # Centro de notificaciones y alertas
│   ├── profact.php           # Configuración y pruebas de conexión con PAC ProFact
│   └── timbrado.php          # Timbrado fiscal CFDI 4.0 y descarga de XML
├── config/
│   └── database.php          # Conexión PDO a MySQL (utf8mb4)
├── css/
│   └── styles.css            # Estilos modernos y responsivos
├── js/
│   └── app.js                # Frontend SPA en Vanilla JavaScript
├── services/
│   └── ProfactService.php    # Cliente Web Service SOAP y Sandbox de ProFact
├── sql/
│   └── schema.sql            # Estructura de BD relacional y semillas de prueba
├── index.php                 # Entrada principal del sistema
├── logout.php                # Cierre de sesión seguro
└── README.md
```

---

## 🛠️ Instalación y Configuración Local

### Requisitos
- **PHP** >= 8.1 (con extensiones `pdo_mysql`, `curl`, `json`, `mbstring`, `soap` habilitadas).
- **MySQL** o **MariaDB** >= 5.7 / 8.0.
- Servidor Web (**Apache** a través de XAMPP, WampServer o nativo).

### Pasos de Instalación

1. **Clonar el repositorio**:
   ```bash
   git clone https://github.com/LF4bi4n/nomina.git c:\xampp\htdocs\nomina
   ```

2. **Configurar la Base de Datos**:
   - Inicia los servicios de Apache y MySQL en el Panel de Control de XAMPP.
   - Crea la base de datos en phpMyAdmin o consola MySQL:
     ```sql
     CREATE DATABASE sistema_nominas CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
     ```
   - Importa el archivo `sql/schema.sql`:
     ```bash
     mysql -u root -p sistema_nominas < c:\xampp\htdocs\nomina\sql\schema.sql
     ```

3. **Verificar Configuración de Conexión**:
   Revisa el archivo `config/database.php`:
   ```php
   define('DB_HOST', '127.0.0.1');
   define('DB_PORT', '3306');
   define('DB_NAME', 'sistema_nominas');
   define('DB_USER', 'root');
   define('DB_PASS', '');
   ```

4. **Abrir la Aplicación**:
   Abre tu navegador web y entra a:
   ```text
   http://localhost/nomina/
   ```

---

## 🔑 Credenciales de Acceso Demo

| Rol | Correo Electrónico | Contraseña | Permisos |
| :--- | :--- | :--- | :--- |
| **Administrador** | `admin@nominas.com` | `123456` | Control total, timbrado, dispersión, auditoría y reglas |
| **Recursos Humanos** | `rh@nominas.com` | `123456` | Altas/bajas, incidencias, cálculo de nómina y ProFact |
| **Auditor Fiscal** | `auditor@nominas.com` | `123456` | Supervisión de cálculos, auditoría y trazabilidad SAT |
| **Colaborador** | `empleado@nominas.com` | `123456` | Portal personal, recibos CFDI, checador GPS y clima |

---

## 👨‍💻 Autor

- **LF4bi4n** - [Perfil de GitHub](https://github.com/LF4bi4n)

