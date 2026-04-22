#!/bin/bash
# ============================================================
#  LGPAS — VPS/SSH Deployment Script
#  Local Government Profile & Analytics System
# ============================================================
#  Usage:
#    chmod +x deploy.sh
#    ./deploy.sh
#
#  Requirements:
#    - SSH access to your server
#    - PHP 8.2+, Composer, MySQL
#    - Git (optional, or upload zip manually)
# ============================================================

set -e  # Exit immediately on error

# ── CONFIGURE THESE ─────────────────────────────────────────
APP_DIR="/var/www/apprmp"           # Where to install the app
WEB_USER="www-data"                 # Web server user (www-data for Apache/Nginx on Ubuntu)
PHP_BIN="php"                       # PHP binary (use full path if needed: /usr/bin/php8.2)
COMPOSER_BIN="composer"             # Composer binary

DB_HOST="localhost"
DB_PORT="3306"
DB_NAME="apprmp"
DB_USER="apprmp_user"
DB_PASS=""                          # Set your DB password here
# ────────────────────────────────────────────────────────────

GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

log()  { echo -e "${GREEN}[+]${NC} $1"; }
warn() { echo -e "${YELLOW}[!]${NC} $1"; }
err()  { echo -e "${RED}[✗]${NC} $1"; exit 1; }

echo "============================================================"
echo "  LGPAS Deployment Script"
echo "============================================================"
echo

# ── Step 1: Check prerequisites ──────────────────────────────
log "Checking prerequisites..."

command -v $PHP_BIN    >/dev/null 2>&1 || err "PHP not found. Install PHP 8.2+"
command -v $COMPOSER_BIN >/dev/null 2>&1 || err "Composer not found. Install Composer."
command -v mysql       >/dev/null 2>&1 || warn "mysql client not found — skipping DB check."

PHP_VER=$($PHP_BIN -r "echo PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;")
log "PHP version: $PHP_VER"

# ── Step 2: Create app directory ─────────────────────────────
log "Setting up directory: $APP_DIR"
mkdir -p "$APP_DIR"

# ── Step 3: Upload / extract files ───────────────────────────
log "Checking for application files..."
if [ ! -f "$APP_DIR/artisan" ]; then
    warn "artisan not found in $APP_DIR."
    echo "  Please upload your application files to $APP_DIR first:"
    echo "  Option A (Git): git clone https://github.com/yourrepo/apprmp.git $APP_DIR"
    echo "  Option B (Zip): Upload apprmp.zip and run: unzip apprmp.zip -d $APP_DIR"
    echo
    read -p "  Press ENTER once files are in place..."
fi

cd "$APP_DIR"
log "Working in: $(pwd)"

# ── Step 4: Install Composer dependencies ────────────────────
log "Installing Composer dependencies..."
$COMPOSER_BIN install --no-dev --optimize-autoloader --no-interaction

# ── Step 5: Write .env file ───────────────────────────────────
if [ ! -f ".env" ]; then
    log "Creating .env file..."
    if [ ! -f ".env.example" ]; then
        err ".env.example not found. Cannot create .env."
    fi
    cp .env.example .env

    # Update database settings
    sed -i "s|^DB_CONNECTION=.*|DB_CONNECTION=mysql|"    .env
    sed -i "s|^# DB_HOST=.*|DB_HOST=$DB_HOST|"          .env
    sed -i "s|^DB_HOST=.*|DB_HOST=$DB_HOST|"            .env
    sed -i "s|^# DB_PORT=.*|DB_PORT=$DB_PORT|"          .env
    sed -i "s|^DB_PORT=.*|DB_PORT=$DB_PORT|"            .env
    sed -i "s|^# DB_DATABASE=.*|DB_DATABASE=$DB_NAME|"  .env
    sed -i "s|^DB_DATABASE=.*|DB_DATABASE=$DB_NAME|"    .env
    sed -i "s|^# DB_USERNAME=.*|DB_USERNAME=$DB_USER|"  .env
    sed -i "s|^DB_USERNAME=.*|DB_USERNAME=$DB_USER|"    .env
    sed -i "s|^# DB_PASSWORD=.*|DB_PASSWORD=$DB_PASS|"  .env
    sed -i "s|^DB_PASSWORD=.*|DB_PASSWORD=$DB_PASS|"    .env

    # Set production values
    sed -i "s|^APP_ENV=.*|APP_ENV=production|"  .env
    sed -i "s|^APP_DEBUG=.*|APP_DEBUG=false|"   .env
    sed -i "s|^APP_LOCALE=.*|APP_LOCALE=ne|"    .env

    log ".env file created."
else
    warn ".env already exists — skipping creation."
fi

# ── Step 6: Generate app key ──────────────────────────────────
log "Generating application key..."
$PHP_BIN artisan key:generate --force

# ── Step 7: Set permissions ───────────────────────────────────
log "Setting file permissions..."
chmod -R 755 "$APP_DIR"
chmod -R 775 storage bootstrap/cache
find storage -type f -exec chmod 664 {} \;
find bootstrap/cache -type f -exec chmod 664 {} \;
chown -R "$WEB_USER:$WEB_USER" storage bootstrap/cache 2>/dev/null || \
    warn "Could not chown to $WEB_USER — run manually if needed."

# ── Step 8: Run database migrations ──────────────────────────
log "Running database migrations..."
$PHP_BIN artisan migrate --force

log "Running database seeders..."
$PHP_BIN artisan db:seed --force

# ── Step 9: Storage link ──────────────────────────────────────
log "Creating storage symlink..."
$PHP_BIN artisan storage:link

# ── Step 10: Clear and rebuild caches ────────────────────────
log "Building caches..."
$PHP_BIN artisan config:cache
$PHP_BIN artisan route:cache
$PHP_BIN artisan view:cache

# ── Step 11: Create super admin ───────────────────────────────
echo
echo "────────────────────────────────────────────"
echo "  Create Super Admin Account"
echo "────────────────────────────────────────────"
read -p "  Admin name:     " ADMIN_NAME
read -p "  Admin email:    " ADMIN_EMAIL
read -s -p "  Admin password: " ADMIN_PASS
echo

HASHED_PASS=$(php -r "echo password_hash('$ADMIN_PASS', PASSWORD_BCRYPT, ['cost' => 12]);")

mysql -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" <<SQL
INSERT INTO users (name, email, password, role, email_verified_at, created_at, updated_at)
VALUES ('$ADMIN_NAME', '$ADMIN_EMAIL', '$HASHED_PASS', 'super_admin', NOW(), NOW(), NOW())
ON DUPLICATE KEY UPDATE
  name = VALUES(name), password = VALUES(password), role = 'super_admin', updated_at = NOW();
SQL

log "Admin user created: $ADMIN_EMAIL"

# ── Done ──────────────────────────────────────────────────────
echo
echo -e "${GREEN}============================================================${NC}"
echo -e "${GREEN}  ✓ Deployment Complete!${NC}"
echo -e "${GREEN}============================================================${NC}"
echo
echo "  Next steps:"
echo "  1. Configure your web server (Apache/Nginx) to serve from: $APP_DIR/public"
echo "  2. Set APP_URL in .env to your domain"
echo "  3. Login at: https://yourdomain.com/login"
echo "  4. Delete this script from the server!"
echo
