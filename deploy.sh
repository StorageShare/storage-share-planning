#!/bin/bash

# Exit on any error
set -e

echo "🚀 Starting deployment..."

# Navigate to project directory
cd /home/master/applications/planning/public_html

# Pull latest changes
echo "📥 Pulling latest changes from Bitbucket..."
git pull origin main

# Install composer dependencies
echo "📦 Installing composer dependencies..."
composer install --optimize-autoloader --no-dev

# Install npm dependencies and build assets
echo "🎨 Building frontend assets..."
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

# Set proper permissions
echo "🔐 Setting permissions..."
chmod -R 755 storage bootstrap/cache

# Post-deployment permission fix
chown -R cpbwahmrsn:cpbwahmrsn storage/ bootstrap/cache/
chmod -R 755 storage/ bootstrap/cache/
chmod -R 775 storage/logs/ storage/framework/
php artisan config:clear && php artisan cache:clear && php artisan view:clear

echo "✅ Deployment completed successfully!"
