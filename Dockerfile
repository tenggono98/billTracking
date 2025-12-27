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

# Set proper permissions
RUN chown -R www-data:www-data /var/www/html && \
    chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Expose port 9000 for PHP-FPM
EXPOSE 9000

# Start PHP-FPM
CMD ["php-fpm"]

