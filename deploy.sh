#!/bin/bash

# Exit on any error
set -e

echo "🚀 Starting deployment..."

# Navigate to project directory
cd /home/master/applications/planning/public_html

# Pull latest changes
echo "📥 Pulling latest changes from GitHub..."
git pull origin main

# Install composer dependencies
echo "📦 Installing composer dependencies..."
composer install --optimize-autoloader --no-dev

# Install npm dependencies and build assets
echo "🎨 Building frontend assets..."
# De home van de applicatie-user is read-only; stuur de npm-cache (en logs)
# naar een wel-schrijfbare map zodat npm niet faalt op ~/.npm.
export npm_config_cache="$HOME/private_html/.npm"
mkdir -p "$npm_config_cache"
npm ci
npm run build

# Clear and cache Laravel configurations
echo "⚙️ Optimizing Laravel..."
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

php artisan config:cache
php artisan route:cache
php artisan view:cache

# Ensure public/storage symlink exists for serving uploaded files
php artisan storage:link || true

# Run database migrations
echo "🗄️ Running database migrations..."
php artisan migrate --force

# Set proper permissions (best-effort).
# Bij een geautomatiseerde deploy draaien we mogelijk als een gebruiker die
# niet alle bestanden mag chown/chmod-en (eigendom van een andere user).
# Daarom faalt dit blok de deploy niet langer.
echo "🔐 Setting permissions (best-effort)..."
set +e
chmod -R 755 storage bootstrap/cache 2>/dev/null
chown -R cpbwahmrsn:cpbwahmrsn storage/ bootstrap/cache/ 2>/dev/null
chmod -R 755 storage/ bootstrap/cache/ 2>/dev/null
chmod -R 775 storage/logs/ storage/framework/ 2>/dev/null
set -e

php artisan config:clear && php artisan cache:clear && php artisan view:clear

echo "✅ Deployment completed successfully!"
