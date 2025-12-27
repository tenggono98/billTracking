# Base stage - PHP with extensions
FROM php:8.2-fpm AS base

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    libzip-dev \
    nginx \
    supervisor \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip

# Install Redis extension
RUN pecl install redis && docker-php-ext-enable redis

# Configure PHP settings
RUN echo "upload_max_filesize=40M" >> /usr/local/etc/php/conf.d/local.ini && \
    echo "post_max_size=40M" >> /usr/local/etc/php/conf.d/local.ini && \
    echo "memory_limit=256M" >> /usr/local/etc/php/conf.d/local.ini && \
    echo "max_execution_time=300" >> /usr/local/etc/php/conf.d/local.ini && \
    echo "max_input_time=300" >> /usr/local/etc/php/conf.d/local.ini

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Configure Composer to use cache directory
ENV COMPOSER_CACHE_DIR=/tmp/composer-cache

# Build stage - Install dependencies and build assets
FROM base AS build

# Install Node.js
RUN curl -fsSL https://deb.nodesource.com/setup_22.x | bash - && \
    apt-get install -y nodejs && \
    rm -rf /var/lib/apt/lists/*

# Set working directory
WORKDIR /var/www/html

# Copy dependency files
COPY composer.json composer.lock ./
COPY package.json package-lock.json ./

# Install PHP dependencies (with dev for building)
RUN composer install --no-interaction --prefer-dist --optimize-autoloader --no-scripts

# Install Node dependencies (with dev for building assets)
RUN npm ci || npm install

# Copy application files
COPY . .

# Build frontend assets
RUN npm run build

# Production stage - Final optimized image
FROM base AS dokploy

# Set working directory
WORKDIR /var/www/html

# Copy dependency files for production install
COPY composer.json composer.lock ./

# Copy minimal files needed for composer scripts (artisan needs bootstrap/app.php and routes)
COPY artisan ./
COPY bootstrap ./bootstrap
COPY app ./app
COPY config ./config
COPY routes ./routes

# Create cache and storage directories required by Laravel
RUN mkdir -p \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/app/private \
    storage/app/public \
    storage/logs \
    bootstrap/cache \
    public

# Install only production PHP dependencies
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-scripts && \
    composer dump-autoload --optimize --classmap-authoritative

# Copy built assets from build stage
COPY --from=build /var/www/html/public/build ./public/build

# Copy application files
COPY . .

# Copy Nginx configuration
COPY docker/nginx/production.conf /etc/nginx/sites-available/default
RUN rm -rf /etc/nginx/sites-enabled/* && \
    ln -sf /etc/nginx/sites-available/default /etc/nginx/sites-enabled/default

# Copy Supervisor configuration
COPY docker/supervisor/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
RUN mkdir -p /var/log/supervisor /var/run

# Set proper permissions
RUN chown -R www-data:www-data /var/www/html && \
    chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Optimize Laravel for production (cache config, routes, views)
# Note: These will be run at container startup if .env exists
# We create a startup script that handles this
RUN echo '#!/bin/bash' > /usr/local/bin/laravel-optimize.sh && \
    echo 'set -e' >> /usr/local/bin/laravel-optimize.sh && \
    echo 'if [ -f .env ]; then' >> /usr/local/bin/laravel-optimize.sh && \
    echo '  php artisan config:cache || true' >> /usr/local/bin/laravel-optimize.sh && \
    echo '  php artisan route:cache || true' >> /usr/local/bin/laravel-optimize.sh && \
    echo '  php artisan view:cache || true' >> /usr/local/bin/laravel-optimize.sh && \
    echo 'fi' >> /usr/local/bin/laravel-optimize.sh && \
    chmod +x /usr/local/bin/laravel-optimize.sh

# Create entrypoint script
RUN echo '#!/bin/bash' > /usr/local/bin/docker-entrypoint.sh && \
    echo 'set -e' >> /usr/local/bin/docker-entrypoint.sh && \
    echo '' >> /usr/local/bin/docker-entrypoint.sh && \
    echo '# Run Laravel optimizations' >> /usr/local/bin/docker-entrypoint.sh && \
    echo '/usr/local/bin/laravel-optimize.sh' >> /usr/local/bin/docker-entrypoint.sh && \
    echo '' >> /usr/local/bin/docker-entrypoint.sh && \
    echo '# Start Supervisor' >> /usr/local/bin/docker-entrypoint.sh && \
    echo 'exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf' >> /usr/local/bin/docker-entrypoint.sh && \
    chmod +x /usr/local/bin/docker-entrypoint.sh

# Expose port 80 for HTTP
EXPOSE 80

# Health check
HEALTHCHECK --interval=30s --timeout=3s --start-period=40s --retries=3 \
    CMD curl -f http://localhost/up || exit 1

# Start Supervisor which manages both Nginx and PHP-FPM
CMD ["/usr/local/bin/docker-entrypoint.sh"]

