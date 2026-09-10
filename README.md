Claro, aquí tienes una propuesta completa y profesional para el archivo `README.md` de tu proyecto **SERVIMOTOS FRUNA**, basada en el prompt de actualización que proporcionaste.

Este README está diseñado para ser la cara pública de tu repositorio en GitHub, sirviendo como documentación central para desarrolladores, gerentes de proyecto y cualquier persona que necesite entender el alcance y las reglas del sistema.

---

# SERVIMOTOS FRUNA - Sistema de Gestión para Taller

![Estado del Proyecto](https://img.shields.io/badge/estado-en%20desarrollo-yellow)
![Licencia](https://img.shields.io/badge/licencia-privada-red)

Sistema integral de gestión para talleres de motocicletas, diseñado para optimizar el flujo de trabajo, la comunicación con el cliente y la gestión de inventario, con un enfoque especial en la automatización y la reducción de costos operativos a través de la API de WhatsApp Business.

## Tabla de Contenidos

- [Visión General](#-visión-general)
- [Características Principales](#-características-principales)
- [Flujo de Trabajo del Taller](#-flujo-de-trabajo-del-taller)
- [Arquitectura Técnica](#-arquitectura-técnica)
- [Estructura de la Base de Datos](#-estructura-de-la-base-de-datos)
- [Configuración de WhatsApp Business API](#-configuración-de-whatsapp-business-api)
- [Seguridad y Roles (RBAC)](#-seguridad-y-roles-rbac)
- [Roadmap y Prioridades](#-roadmap-y-prioridades)
- [Acciones Inmediatas para el Desarrollador](#-acciones-inmediatas-para-el-desarrollador)
- [Stack Tecnológico Sugerido](#-stack-tecnológico-sugerido)
- [Instalación y Configuración](#-instalación-y-configuración)
- [Contribuciones](#-contribuciones)
- [Contacto](#-contacto)

## 📝 Visión General

**SERVIMOTOS FRUNA** es un sistema de gestión "todo en uno" para talleres de motos. Nace de la necesidad de resolver problemas críticos del día a día, como la pérdida de ventas por falta de stock, la comunicación ineficiente con los clientes y la gestión desordenada de las reparaciones.

Este proyecto implementa un flujo de trabajo realista, donde las motos se reciben y esperan su turno para revisión, y utiliza la API de WhatsApp para mantener a los clientes informados, optimizando cada interacción para garantizar un costo cero o mínimo en mensajería.

## ✨ Características Principales

- **Tablero Kanban de Reparaciones:** Visualización clara del estado de cada moto en el taller.
- **Gestión de Inventario Inteligente:** Alertas automáticas de stock bajo con cálculo de tiempo de reabastecimiento.
- **Facturación Automatizada:** Generación y envío de facturas por WhatsApp al finalizar una reparación.
- **Gestión del Ciclo de Vida del Cliente:** Sistema de hibernación y archivado automático para mantener la base de datos optimizada.
- **Comunicación por WhatsApp Optimizada:**
    - **Asistencia en Carretera (Gratuito):** Flujo iniciado por el cliente para solicitar ayuda con ubicación y video.
    - **Recordatorios de Mantenimiento (Pago Inteligente):** Uso de plantillas UTILITY con botones de respuesta rápida para fomentar la interacción gratuita posterior.
- **Control de Acceso Basado en Roles (RBAC):** Permisos estrictos para Administradores y Mecánicos.
- **Arquitectura Multi-Tenant (SaaS):** Preparado para escalar y dar servicio a múltiples talleres desde una sola instancia.

## 🔄 Flujo de Trabajo del Taller

El sistema sigue un flujo de trabajo dinámico y realista, definido en las siguientes etapas:

1.  **Recepción:** La moto llega al taller y se registra en el sistema.
    - **Estado Inicial Obligatorio:** `En espera de revisión`.
    - **Acción Automática:** Se envía un WhatsApp al cliente: `"[Nombre] Moto con placa [PLACA] recibida y en taller, en espera de que el mecánico la revise"`.
2.  **Revisión y Diagnóstico:** El mecánico, cuando tiene disponibilidad, revisa la moto y actualiza su estado.
    - **Estados Permitidos:** `En revisión`, `En reparación`, `Espera de repuestos`, `Reparación finalizada`.
3.  **Finalización y Facturación:**
    - Al pasar a `Reparación finalizada`, el sistema **genera una factura y la envía automáticamente** al cliente por WhatsApp.
    - El estado cambia a `Lista para reclamar`.
4.  **Pago y Entrega:**
    - **Solo un usuario con rol `Administradora`** puede marcar la orden como `Pagada`.
    - El mecánico no tiene permiso para realizar esta acción.

## 🛠️ Arquitectura Técnica

El proyecto está diseñado para ser robusto, escalable y eficiente en costos.

- **Backend:** API REST para la lógica de negocio.
- **Frontend:** Aplicación web para administradores y panel Kanban para mecánicos.
- **Base de Datos:** Relacional (PostgreSQL/MySQL) con soporte multi-tenant.
- **Mensajería:** Integración con la API de WhatsApp Cloud de Meta.
- **Automatización:** Cron Jobs para tareas programadas (recordatorios, depuración de clientes).

### Optimización de Costos en WhatsApp

Un pilar fundamental del proyecto es el uso eficiente de la API de WhatsApp para minimizar costos.

- **Ventana de 24 Horas:** Toda interacción dentro de las 24 horas posteriores a un mensaje del cliente es gratuita. El código debe garantizar que los flujos conversacionales (ej. agendar una cita) ocurran dentro de este período.
- **Flujo de Asistencia en Carretera:** Iniciado por el cliente, es 100% gratuito. Se debe **descargar el video inmediatamente** al servidor y eliminarlo del CDN de Meta para evitar cargos por almacenamiento.
- **Flujo de Mantenimiento Preventivo:** Iniciado por el taller, es de pago (Plantilla UTILITY). Para optimizar costos, la plantilla incluye **Quick Replies** ("Sí, quiero agendar"). Cuando el cliente responde, se inicia una nueva ventana de 24 horas gratuita, permitiendo que toda la conversación de agendamiento sea sin costo.

## 🗄️ Estructura de la Base de Datos

Se han añadido y modificado tablas para soportar la nueva lógica de negocio y la auditoría.

### Tabla: `ordenes_servicio`

| Campo | Tipo | Descripción |
| :--- | :--- | :--- |
| `estado` | `VARCHAR` | Valores: `en_espera_revision`, `en_revision`, `reparacion`, `espera_repuesto`, `terminada`, `listo_para_reclamar`, `pagada`. |
| `fecha_hora_ingreso` | `TIMESTAMP` | Fecha y hora de recepción de la moto. |
| `ultima_interaccion_cliente` | `TIMESTAMP` | Para el control del ciclo de vida del cliente. |
| `taller_id` | `INT` | **(Multi-Tenant)** Identificador del taller. |

### Tabla: `historial_mantenimiento`

| Campo | Tipo | Descripción |
| :--- | :--- | :--- |
| `fecha_cambio_repuesto` | `DATE` | Fecha del último cambio. |
| `vida_util_km` | `INT` | Vida útil del repuesto en kilómetros. |
| `vida_util_meses` | `INT` | Vida útil del repuesto en meses. |
| `plantilla_whatsapp_enviada` | `BOOLEAN` | **Importante:** Evita el envío de recordatorios duplicados. |

### Tabla: `clientes`

| Campo | Tipo | Descripción |
| :--- | :--- | :--- |
| `fecha_ultima_visita` | `TIMESTAMP` | Fecha de la última interacción o visita. |
| `estado_cliente` | `VARCHAR` | Valores: `activo`, `hibernando`, `archivado`. |
| `taller_id` | `INT` | **(Multi-Tenant)** Identificador del taller. |

> **Nota:** Las tablas `users`, `motos`, `inventario` también deben incluir la columna `taller_id`.

## 📱 Configuración de WhatsApp Business API

### Plantilla: `recordatorio_mantenimiento`

- **Categoría:** `UTILITY`
- **Idioma:** `es`
- **Cuerpo del Mensaje:**
    > "Hola {{1}}, te recordamos que tu moto {{2}} (Placa: {{3}}) ya cumplió {{4}} meses desde el último cambio de {{5}}. Es momento de agendar su mantenimiento para evitar desgastes. ¿Te ayudamos a reservar una hora?"
- **Botones (Quick Replies):**
    - **Botón 1:** `Sí, quiero agendar` (Payload: `SI_AGENDAR`)
    - **Botón 2:** `Lo haré después` (Payload: `DESPUES`)

## 🔐 Seguridad y Roles (RBAC)

El sistema implementa un control de acceso basado en roles para garantizar la seguridad y la integridad de los procesos.

- **SuperAdmin:** Acceso total al sistema, gestión de múltiples talleres.
- **Administradora:** Gestión completa de **su taller**. Único rol con permiso para **marcar una orden como "Pagada"**.
- **Mecánico:** Acceso limitado al **Tablero Kanban**. Puede visualizar y actualizar los estados técnicos de las órdenes, pero **no puede** gestionar pagos, clientes o inventario.

## 🗺️ Roadmap y Prioridades

El desarrollo se centra en resolver los dolores más críticos del negocio.

1.  **Prioridad #1: Módulo de Inventario**
    - Implementar alertas de "Stock Bajo".
    - La alerta debe activarse con **al menos 5 días hábiles** de anticipación, considerando el tiempo de envío de repuestos (2-5 días).
2.  **Prioridad #2: Recepción y Tablero Kanban**
    - Asegurar que la columna inicial sea `En espera de revisión`.
3.  **Prioridad #3: Facturación y WhatsApp**
    - Automatizar el envío de la factura al finalizar la reparación.

## 🚀 Acciones Inmediatas para el Desarrollador

1.  **Cambiar Estado Inicial:** Modificar el formulario de "Recepción" para que el estado por defecto sea `En espera de revisión`.
2.  **Ocultar Botón de Pago:** Asegurar que el botón "Marcar como Pagada" solo sea visible para el rol `Administradora`.
3.  **Script de Depuración de Clientes:**
    - Crear un Cron Job mensual que revise `fecha_ultima_visita`.
    - Si > 5 meses -> cambiar `estado_cliente` a `hibernando`.
    - Si > 10 meses -> cambiar `estado_cliente` a `archivado`.
4.  **Configurar Alertas de Stock:** Añadir un umbral configurable en el módulo de inventario que dispare una alerta basada en el tiempo de reabastecimiento.
5.  **Middleware de WhatsApp:** En el webhook, reiniciar correctamente el contador de la ventana de 24 horas al recibir respuestas de botones.

## 💻 Stack Tecnológico Sugerido

- **Backend:** Node.js (con Express) o Python (con Django/FastAPI).
- **Frontend:** React, Vue.js o Angular.
- **Base de Datos:** PostgreSQL.
- **ORM:** Prisma, Sequelize o SQLAlchemy.
- **Mensajería:** SDK oficial de Meta para WhatsApp Cloud API.
- **Infraestructura:** Docker, AWS/Google Cloud.

## ⚙️ Instalación y Configuración

_(Esta sección se completará una vez que el proyecto tenga un esqueleto de código)_

1.  Clonar el repositorio:
    ```bash
    git clone https://github.com/tu-usuario/servimotos-fruna.git
    ```
2.  Instalar dependencias:
    ```bash
    cd servimotos-fruna
    npm install
    ```
3.  Configurar variables de entorno (`.env`):
    ```
    DATABASE_URL="postgresql://user:password@localhost:5432/servimotos"
    WHATSAPP_API_TOKEN="tu_token_de_meta"
    WHATSAPP_PHONE_NUMBER_ID="tu_id_de_telefono"
    ```
4.  Ejecutar migraciones de la base de datos:
    ```bash
    npx prisma migrate dev
    ```
5.  Iniciar el servidor:
    ```bash
    npm run dev
    ```

## 🤝 Contribuciones

Las contribuciones son bienvenidas. Por favor, sigue estos pasos:

1.  Haz un fork del proyecto.
2.  Crea una nueva rama (`git checkout -b feature/nueva-funcionalidad`).
3.  Realiza tus cambios y haz commit (`git commit -m 'Añade nueva funcionalidad'`).
4.  Haz push a la rama (`git push origin feature/nueva-funcionalidad`).
5.  Abre un Pull Request.

## 📧 Contacto

Para cualquier consulta o soporte, por favor contacta a:

- **Nombre:** Dana Pacheco y Juan Pulido
- **Email:** dvalentinapacheco@uts.edu.co y jdavidpulido@uts.edu.co
