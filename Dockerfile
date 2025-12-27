# Build stage
FROM php:8.2-cli-alpine AS builder

# Install system dependencies and PHP extensions
RUN apk add --no-cache \
    git \
    curl \
    libpng-dev \
    libzip-dev \
    zip \
    unzip \
    oniguruma-dev \
    freetype-dev \
    libjpeg-turbo-dev \
    nodejs \
    npm \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
    pdo \
    pdo_sqlite \
    pdo_mysql \
    mbstring \
    xml \
    curl \
    zip \
    gd \
    bcmath \
    opcache

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy dependency files
COPY composer.json composer.lock ./
COPY package.json package-lock.json* ./

# Install PHP dependencies (production only)
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --optimize-autoloader

# Install Node dependencies (all dependencies needed for build)
RUN npm ci

# Copy application files
COPY . .

# Complete Composer autoloader
RUN composer dump-autoload --optimize --classmap-authoritative --no-dev

# Build Vite assets
RUN npm run build

# Remove Node.js and npm to reduce image size
RUN apk del nodejs npm

# Production stage
FROM php:8.2-cli-alpine

# Install PHP extensions and system dependencies
RUN apk add --no-cache \
    libpng \
    libzip \
    oniguruma \
    freetype \
    libjpeg-turbo \
    curl \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
    pdo \
    pdo_sqlite \
    pdo_mysql \
    mbstring \
    xml \
    curl \
    zip \
    gd \
    bcmath \
    opcache

# Configure PHP for production
RUN echo "opcache.enable=1" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.memory_consumption=256" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.interned_strings_buffer=16" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.max_accelerated_files=20000" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.validate_timestamps=0" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.save_comments=1" >> /usr/local/etc/php/conf.d/opcache.ini

# Create non-root user
RUN addgroup -g 1000 www && \
    adduser -u 1000 -G www -s /bin/sh -D www

# Set working directory
WORKDIR /var/www/html

# Copy built application from builder stage
COPY --from=builder --chown=www:www /var/www/html /var/www/html

# Copy startup script
COPY --chown=www:www docker/start.sh /usr/local/bin/start.sh
RUN chmod +x /usr/local/bin/start.sh

# Create necessary directories and set permissions
RUN mkdir -p storage/framework/{sessions,views,cache} \
    storage/logs \
    bootstrap/cache \
    database \
    && chown -R www:www storage bootstrap/cache database \
    && chmod -R 775 storage bootstrap/cache

# Ensure SQLite database file exists and has correct permissions
RUN touch database/database.sqlite && \
    chown www:www database/database.sqlite && \
    chmod 664 database/database.sqlite

# Switch to non-root user
USER www

# Expose port 9000
EXPOSE 9000

# Health check
HEALTHCHECK --interval=30s --timeout=3s --start-period=40s --retries=3 \
    CMD curl -f http://localhost:9000 || exit 1

# Start application
CMD ["/usr/local/bin/start.sh"]

