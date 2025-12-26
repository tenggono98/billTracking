FROM php:8.2-fpm

# Set working directory
WORKDIR /var/www/html

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

# Set proper permissions
RUN chown -R www-data:www-data /var/www/html

# Expose port 9000 and start php-fpm server
EXPOSE 9000
CMD ["php-fpm"]

