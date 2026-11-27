# Changelog

Formato basado en [Keep a Changelog](https://keepachangelog.com/es-ES/1.1.0/); versionado [SemVer](https://semver.org/lang/es/).

## [1.0.0] - 2026-11-27

Primera versión pública.

### Incluye
- Dashboard con estado general, jobs fallidos/atrasados/colgados y uso de disco.
- Jobs programados con cron: copia de bases de datos (`mysqldump`), archivos por SSH (`tar.gz`) y scripts remotos con log por ejecución. Retención por job.
- Módulo Database: conexiones, migraciones entre servidores, comparación y sincronización de esquemas y datos.
- Almacenamiento multi-disco: explorador de solo lectura con descarga.
- Lab: tarjetas de servicios por máquina, con autodescubrimiento desde Portainer.
- Ajustes en la web (Portainer, ntfy, zona horaria, rutas y timeouts) sin reiniciar el contenedor.
- Avisos de jobs por ntfy.
- API v1 con Sanctum (dashboard, jobs, runs y logs).
- Imagen Docker `ghcr.io/tonyslabs/bakman` (`linux/amd64`, `linux/arm64`) y `docker-compose.yml` genérico: usa tu propia MariaDB/MySQL y Redis.

[1.0.0]: https://github.com/tonyslabs/bakman/releases/tag/v1.0.0
