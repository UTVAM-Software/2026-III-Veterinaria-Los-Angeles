# Sistema de Gestión Veterinaria - Los Ángeles

Aplicación web multi-rol para la administración de una clínica veterinaria: pacientes (mascotas y
dueños), hojas de estética canina, citas médicas con diagnóstico, y administración de usuarios,
roles y permisos.

## Equipo de desarrollo

| Integrante | Responsabilidad |
|---|---|
| Ana Karen Beltrán Montañez | Arquitectura, seguridad del repositorio, backend y frontend |
| Cesar Daniel Martinez Bertadillo | Base de datos y documentación |
| Abril Acosta Zamora| Desarrollo de módulos |
| Alondra Quezada Mejia | Control de calidad (QA) y pruebas de instalación limpia |

Organización: **UTVAM-Software**

Repositorio: https://github.com/UTVAM-Software/2026-III-Veterinaria-Los-Angeles

## Tecnologías

- **Frontend:** HTML5, CSS3, Bootstrap 5.3, JavaScript
- **Backend:** PHP 8.x con consultas preparadas (PDO)
- **Base de datos:** MySQL / MariaDB
- **Control de versiones:** Git y GitHub (rama `main`)

## Instalación en Linux (instalador automático)

### Requisitos

Un sistema Linux con Ubuntu/Debian (o similar), acceso a `sudo` e internet. El instalador revisa
que existan estos programas y, si falta alguno, indica cómo instalarlo:

```bash
sudo apt update
sudo apt install -y git php php-mysql mariadb-server
```

### Pasos

```bash
git clone https://github.com/UTVAM-Software/2026-III-Veterinaria-Los-Angeles.git
cd 2026-III-Veterinaria-Los-Angeles
bash instalar.sh
```

Al terminar, el sistema queda funcionando en:

**http://localhost:8000/index.php?p=login**

Para detener el servidor, presionar `Ctrl + C` en la terminal.

### Qué hace el instalador

1. **Crear el ambiente virtual:** NO APLICA. El proyecto es PHP y no usa ambientes virtuales.
2. **Descargar el código fuente:** revisa que existan `git`, `php` y `mysql`. Si se ejecuta fuera
   del repositorio, lo descarga con `git clone`.
3. **Configurar base de datos y usuarios:** crea la base `veterinaria` y un usuario de MySQL
   propio, importa `base_datos/veterinaria.sql` y genera `config/conexion.php` a partir de la
   plantilla.
4. **Herramientas propias de la aplicación:** NO APLICA (solo para proyectos Django).
5. **Activar el servicio web:** inicia el servidor con `php -S 0.0.0.0:8000`.

Si cualquier paso falla, el instalador se detiene y muestra el motivo.

> Importante: reinstalar borra los datos. Cada vez que se ejecuta `instalar.sh` se elimina y se
> vuelve a crear la base `veterinaria`.

### Credenciales

**Usuarios del sistema (contraseña de todos: `123456`):**

| Usuario | Rol | Acceso |
|---|---|---|
| `admin` | Administrador | Control total del sistema |
| `veterinario` | Veterinario | Pacientes, estética, citas y diagnósticos |
| `asistente` | Asistente | Pacientes, estética y citas (sin eliminar ni diagnosticar) |

**Base de datos creada por el instalador (solo para uso local):**

| Dato | Valor |
|---|---|
| Base de datos | `veterinaria` |
| Usuario MySQL | `vet_user` |
| Contraseña MySQL | `vet_pass_2026` |

## Instalación manual en Windows (WAMP)

1. Copiar la carpeta del proyecto a `C:\wamp64\www\`.
2. Copiar `config/conexion.example.php` como `config/conexion.php` y poner los datos de tu MySQL
   local (en WAMP normalmente usuario `root` y contraseña vacía; la base debe llamarse
   `veterinaria`).
3. En phpMyAdmin, ir a **Importar** y subir `base_datos/veterinaria.sql` (crea la base y las
   tablas desde cero).
4. Abrir `http://localhost/2026-III-Veterinaria-Los-Angeles/index.php?p=login`.

## Seguridad

- El archivo real de conexión `config/conexion.php` **nunca se sube a GitHub**: está en
  `.gitignore`.
- En el repositorio solo existe la plantilla `config/conexion.example.php`, con valores genéricos.
- Las contraseñas de los usuarios se guardan encriptadas (BCRYPT).
- Todas las consultas a la base de datos usan sentencias preparadas (PDO).
- Cada pantalla y botón verifican el permiso del usuario según su rol.

## Estructura del proyecto

```
index.php                  Aplicación y enrutador (index.php?p=<pantalla>)
app.js / styles.css        Comportamiento y estilos
config/
  conexion.example.php     Plantilla de conexión (la real no se sube)
base_datos/
  veterinaria.sql          Estructura y datos iniciales de la base
uploads/                   Fotos de las mascotas
documentacion/             Modelos conceptual y lógico de la base de datos
instalar.sh                Instalador automático para Linux
README.md                  Este archivo
```

## Solución de problemas

- **Falta 'php' (o 'git', 'mysql'):** instalar los requisitos con el comando `apt install` de la
  sección de requisitos.
- **Falta php-mysql:** ejecutar `sudo apt install php-mysql`.
- **Address already in use:** el puerto 8000 está ocupado. Cerrar el otro servidor o cambiar
  `PORT` en `instalar.sh`.
- **Error de conexión al servidor de base de datos:** verificar que MariaDB esté activo con
  `sudo service mariadb start`.
- **No se pueden subir fotos:** dar permisos a la carpeta con `chmod -R 775 uploads`.
