#!/bin/bash
# Instalador - Sistema de Gestion Veterinaria Los Angeles
set -e

REPO_URL="https://github.com/UTVAM-Software/2026-III-Veterinaria-Los-Angeles.git"
DIR="2026-III-Veterinaria-Los-Angeles"
DB_NAME="veterinaria"
DB_USER="vet_user"
DB_PASS="vet_pass_2026"
PORT=8000

echo "== 1. Ambiente virtual: no aplica (PHP) =="
for cmd in git php mysql; do
  command -v $cmd >/dev/null || { echo "Falta '$cmd'. Instala: sudo apt install git php php-mysql mariadb-server"; exit 1; }
done
php -m | grep -qi pdo_mysql || { echo "Falta php-mysql: sudo apt install php-mysql"; exit 1; }
sudo service mariadb start 2>/dev/null || sudo service mysql start 2>/dev/null || true

echo "== 2. Descargando codigo fuente =="
if [ ! -d .git ]; then
  git clone "$REPO_URL" "$DIR"
  cd "$DIR"
fi

echo "== 3. Configurando base de datos y conexion =="
sudo mysql <<SQL
DROP DATABASE IF EXISTS ${DB_NAME};
CREATE DATABASE ${DB_NAME} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON ${DB_NAME}.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
SQL
mysql --default-character-set=utf8mb4 -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" < base_datos/veterinaria.sql

cp config/conexion.example.php config/conexion.php
sed -i "s/TU_USUARIO/${DB_USER}/; s/TU_PASSWORD/${DB_PASS}/" config/conexion.php
chmod -R 775 uploads

echo "== 4. Herramientas de Django: no aplica =="

echo "== 5. Activando servicio web =="
echo "Sistema listo en http://localhost:${PORT}"
php -S 0.0.0.0:${PORT}
