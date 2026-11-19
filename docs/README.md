# Documentación

Todo lo que hace la app, cómo se usa, cómo se opera y cómo se extiende. El [README](../README.md) es el resumen; las
decisiones y el estado de cada fase están en el [ROADMAP](../ROADMAP.md).

| Guía | De qué trata |
| --- | --- |
| [Primeros pasos](getting-started.md) | Arrancar en local: HTTPS con mkcert, Docker, migraciones, superadmin, datos de demo |
| [Arquitectura](architecture.md) | Capas (DDD-lite), dónde está cada cosa y **cómo añadir un módulo nuevo** |
| [Cuentas, usuarios y roles](accounts-and-roles.md) | Login, registro, reset, enlace mágico, «recordarme», perfil, CRUD de usuarios, roles |
| [Permisos](permissions.md) | RBAC en base de datos: descubrimiento de acciones, matriz, reglas de sistema, histórico |
| [Verificación en dos pasos y passkeys](two-factor.md) | TOTP, llaves de seguridad, passkeys, reset, códigos de un solo uso |
| [Actividad de la cuenta](account-activity.md) | Qué se registra de cada cuenta, quién lo ve y cuánto se guarda |
| [Cola de trabajos](queue.md) | Emails en segundo plano, el worker, la pantalla *System › Queue* y **cómo crear tareas propias** |
| [Subida de archivos](uploads.md) | Convención `<tabla>/<id>/<campo>/<uuid>.<ext>`, almacenamiento privado, foto de usuario, cómo añadir archivos a otra entidad |
| [Emails y PDF](emails-and-pdf.md) | Layout de email compatible, CSS en línea, informe en PDF |
| [Seguridad](security.md) | CSP, cabeceras, HTTPS, CSRF, sesiones, límites por IP, proxies; **reglas para escribir plantillas** |
| [htmx](htmx.md) | Fragmentos sin recargar compatibles con la CSP |
| [Configuración](configuration.md) | Todas las variables de entorno y claves de config |
| [Despliegue en Coolify](deploy-coolify.md) | Paso a paso: base de datos, dominio, SMTP, worker, cron |
| [Operación y crons](operations.md) | **Los crons que hay que ejecutar siempre**, comandos, logs, copias, actualizaciones |
