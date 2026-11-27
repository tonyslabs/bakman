# bakman

**bakman** (*BAcKend MANager*) es una navaja suiza para administrar la parte de backend de un homelab o de un pequeño conjunto de servidores desde una sola interfaz web: backups programados, scripts remotos, bases de datos y un panel con el estado de los servicios de cada máquina.

Está hecho con Laravel y corre en un único contenedor Docker.

---

## Funciones

| Módulo | Qué hace |
|---|---|
| **Dashboard** | Resumen: estado general, ejecuciones de las últimas 24 h, último backup de base de datos, uso del disco, próximas ejecuciones y lo que requiere atención (jobs fallidos, atrasados o colgados). |
| **Jobs → Monitor** | Estado de cada job: última ejecución, último éxito, próxima ejecución, duración y tamaño. |
| **Jobs** | Tareas programadas con expresiones cron (admite varias separadas por `\|` para ritmos que un solo cron no expresa, p. ej. cada 40 min exactos). Tipos: <br>• **Base de datos**: `mysqldump` comprimido (`.sql.gz`), completo, solo estructura o por tablas. <br>• **Archivos / sistema**: `tar.gz` de rutas remotas por SSH. <br>• **Script**: ejecuta un comando en un servidor por SSH (p. ej. un script Python con su venv) y guarda un log por ejecución. |
| **Database** | Conexiones reutilizables a MySQL/MariaDB, migraciones (dump → restore entre servidores), comparación de esquemas y datos entre dos bases y sincronización. |
| **Almacenamiento** | Explorador de archivos de solo lectura del disco de backups, con descarga. |
| **Lab** | Tarjetas de acceso a los servicios, agrupadas por máquina, con su estado en vivo. Opcionalmente se autodescubren desde Portainer (contenedores de todas las máquinas y su estado). |
| **SSH** | Servidores accesibles por SSH (*targets*) y la clave pública que usa bakman para entrar en ellos. |

### Cómo se organizan los backups

```
<ruta de backups>/
├── <conexión>/<base de datos>/<job>_<AAAA-MM-DD_HHMMSS>.sql.gz
└── <target>/<job>/<job>_<AAAA-MM-DD_HHMMSS>.tar.gz
```

Los logs de los jobs de tipo script van aparte: `<ruta de logs>/<target>/<job>/<job>_<fecha>.log`.
La retención (conservar los últimos *N*) se aplica por job.

---

## Arquitectura

Un solo contenedor con tres procesos, gestionados por `supervisord`:

- **web**: `php artisan serve` con varios workers (`PHP_CLI_SERVER_WORKERS`).
- **scheduler**: `php artisan schedule:work`, que lanza los jobs según su cron.
- **queue**: `php artisan queue:work`, que los ejecuta uno a uno.

Al detener el contenedor, la cola termina el job en curso antes de salir (hasta 10 min), así que un redeploy no corta un backup a la mitad.

Datos que necesita fuera del contenedor:

- Una base **MySQL/MariaDB** para su propia información (jobs, historial, conexiones…).
- Un directorio para los **backups** y otro para los **logs** de scripts.
- Un volumen para la **clave SSH** (se genera sola la primera vez si no existe).
- Opcional: el disco que se quiere explorar desde *Almacenamiento*, montado en solo lectura.

---

## Requisitos

- **Docker** y **Docker Compose**.
- Un servidor **MySQL 8 / MariaDB 10.6+** que ya tengas levantado, y un **Redis 6+** (cola de jobs, caché y locks). bakman **no los incluye**: tú indicas host y credenciales en el `.env`.
- Acceso **SSH por clave** a los servidores de los que se harán backups de archivos o donde se ejecutarán scripts.
- Opcional: **Portainer** (2.x) con un *access token* para el autodescubrimiento de servicios en *Lab*.

---

## Puesta en marcha

### 1. Clonar

```bash
git clone https://github.com/<usuario>/bakman.git
cd bakman
```

### 2. Crear la base de datos

En tu servidor MySQL/MariaDB crea la base y un usuario para bakman:

```sql
CREATE DATABASE backend_manager CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'bakman'@'%' IDENTIFIED BY '<contraseña>';
GRANT ALL ON backend_manager.* TO 'bakman'@'%';
```

### 3. Crear el `.env` de despliegue

```bash
cp deploy.env.example .env
```

Rellena al menos `APP_KEY`, `DB_HOST`, `DB_USERNAME`, `DB_PASSWORD`, `REDIS_HOST`, `ADMIN_EMAIL` y `ADMIN_PASSWORD` (el compose se niega a arrancar si falta alguno). Para generar la `APP_KEY` sin tener PHP instalado:

```bash
docker run --rm php:8.4-cli php -r "echo 'base64:'.base64_encode(random_bytes(32)).PHP_EOL;"
```

> ⚠️ Guarda la `APP_KEY`: con ella se cifran las contraseñas de las conexiones de base de datos y los tokens de Ajustes. Si se pierde, habrá que volver a introducirlos.

### 4. Conectar con tu base de datos y Redis

`DB_HOST` y `REDIS_HOST` son los que ve el **contenedor**. Dos casos habituales:

- **Están en otra máquina o publican su puerto en el host:** usa su IP o nombre DNS. No hace falta nada más.
- **Son contenedores en una red de Docker propia:** conecta bakman a esa red con un `docker-compose.override.yml` junto al compose (Docker Compose lo lee solo; no lo subas a git):

```yaml
services:
  backend-manager:
    networks: [mi_red]
networks:
  mi_red:
    external: true
```

y usa en el `.env` los nombres de esos contenedores (`DB_HOST=mariadb`, `REDIS_HOST=redis`).

Para el explorador de archivos, monta tus discos en `docker-compose.yml` (sección `volumes`, **en solo lectura**) y regístralos después desde la web (**Almacenamiento → +**).

### 5. Levantar

```bash
docker compose up -d --build
docker compose logs -f
```

En el arranque, el contenedor espera a la base de datos (y se detiene con un mensaje claro si no consigue conectar), aplica las migraciones y, **si todavía no existe ningún usuario**, crea el administrador con `ADMIN_EMAIL` / `ADMIN_PASSWORD`. Después entra por el navegador con ese usuario.

> Cambiar `ADMIN_PASSWORD` más adelante no modifica la contraseña de un usuario ya creado; cámbiala desde *Profile*.

### 6. Dar acceso SSH a tus servidores

En **SSH → Clave SSH** está la clave pública de bakman. Agrégala al `~/.ssh/authorized_keys` del usuario con el que se conectará en cada servidor y registra ese servidor en **SSH → Targets**.

---

## Variables de entorno

Las del bloque "obligatorio" de `deploy.env.example` hay que definirlas; el resto tiene valor por defecto.

| Variable | Uso |
|---|---|
| `APP_KEY` | Clave de cifrado de Laravel (obligatoria). |
| `DB_HOST` / `DB_PORT` / `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD` | Base de datos propia de bakman (la tuya; no se incluye). |
| `REDIS_HOST` / `REDIS_PORT` / `REDIS_USERNAME` / `REDIS_PASSWORD` / `REDIS_PREFIX` | Tu Redis para la cola, la caché y los locks (no se incluye). |
| `ADMIN_EMAIL` / `ADMIN_PASSWORD` | Usuario administrador que se crea en el primer arranque (solo si no hay usuarios). |
| `BAKMAN_BIND` / `BAKMAN_PORT` | IP y puerto en que se publica la web (por defecto `127.0.0.1:8091`). |
| `APP_URL` / `APP_LOCALE` / `TZ` | URL de acceso, idioma (`es`) y zona horaria del contenedor. |
| `BACKUPS_DIR` / `LOGS_DIR` | Carpetas del host donde se guardan los backups y los logs de scripts. |
| `SSH_VOLUME` | Nombre del volumen con la clave SSH (por defecto `bakman_ssh`). |
| `PORTAINER_URL` / `PORTAINER_TOKEN` | API de Portainer para el autodescubrimiento de *Lab*. |
| `NTFY_URL` / `NTFY_TOKEN` | Servidor ntfy para los avisos de jobs. |

Portainer, ntfy, la zona horaria de los cron, las rutas del contenedor, los puertos y nombres de *Lab* y los timeouts de jobs también se editan en la web, en **Ajustes**, y ahí mandan sobre el `.env` sin reiniciar el contenedor. Los discos del explorador se gestionan en **Almacenamiento → +**.

---|---|
| `APP_KEY` | Clave de cifrado de Laravel (obligatoria). |
| `APP_URL` | URL con la que se accede a bakman. |
| `APP_LOCALE` | Idioma (`es`). |
| `TZ` | Zona horaria del contenedor. |
| `DB_*` | Conexión a la base de datos propia de bakman. |
| `ADMIN_EMAIL` / `ADMIN_PASSWORD` | Usuario administrador que se crea en el primer arranque (solo si no hay usuarios). |
| `BACKUPS_PATH` | Ruta de los backups dentro del contenedor (por defecto `/data/backups`). |
| `LOGS_PATH` | Ruta de los logs de scripts (por defecto `/data/logs`). |
| `BACKUPS_TIMEZONE` | Zona horaria en la que se interpretan los cron y se nombran los archivos. |
| `SSH_KEY_PATH` | Clave privada SSH (por defecto `/data/ssh/id_ed25519`). |
| `FILES_ROOT` / `FILES_LABEL` | Raíz y nombre del explorador de archivos. |
| `PORTAINER_URL` / `PORTAINER_TOKEN` | API de Portainer para el autodescubrimiento de *Lab*. |
| `LAB_LOCAL_HOST` | Host con el que se enlazan los servicios del endpoint local de Portainer. |
| `PHP_CLI_SERVER_WORKERS` | Workers del servidor web (requiere `artisan serve --no-reload`, ya configurado). |

La asignación de cada máquina a su sección de *Lab*, los puertos que se consideran "no web" y los nombres a mostrar se definen en `config/lab.php`.

---

## Desarrollo

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve
```

En otra terminal, para que corran los jobs:

```bash
php artisan schedule:work
php artisan queue:work
```

### Tests

Usan SQLite en memoria, así que no hace falta base de datos:

```bash
php vendor/bin/phpunit
```

Si no tienes PHP en la máquina, con Docker:

```bash
docker run --rm -v "$PWD":/app -w /app php:8.4-cli-bookworm php vendor/bin/phpunit
```

> Los tests de SQLite no validan los `ENUM` de MySQL: si cambias el esquema, pruébalo también contra MariaDB.

---

## Operación

- **Redeploy sin cortar jobs**: `docker compose build` primero y `docker compose up -d` después. El contenedor espera a que termine el job en curso antes de detenerse.
- **Restaurar un backup de base de datos**:
  ```bash
  zcat <archivo>.sql.gz | mysql -h <servidor> -u <usuario> -p <base>
  ```
- **Jobs interrumpidos**: si el worker muere a mitad de un job, la ejecución queda registrada como fallida ("Interrumpido") y aparece en *Requiere atención*.

---

## Seguridad

- bakman guarda credenciales (conexiones a bases de datos, clave SSH, token de Portainer): publícalo **solo en una red privada** o detrás de una VPN, nunca directamente en internet.
- Las contraseñas de las conexiones se guardan cifradas con la `APP_KEY`.
- El *token* de Portainer tiene los permisos de su usuario; bakman solo lo usa para leer.
- El explorador de archivos es de solo lectura y no permite salir de su raíz.

---

## Licencia

[MIT](LICENSE)
