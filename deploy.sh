#!/bin/bash

# Exit on any error
set -e

echo "🚀 Starting deployment..."

# Navigate to project directory
cd /home/master/applications/jouw-app-id/public_html

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

# Run database migrations
echo "🗄️ Running database migrations..."
php artisan migrate --force

# Set proper permissions
echo "🔐 Setting permissions..."
chmod -R 755 storage bootstrap/cache
chown -R master:master storage bootstrap/cache

echo "✅ Deployment completed successfully!"
