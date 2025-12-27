ARG PHP_VERSION=${PHP_VERSION:-8.3}
FROM php:${PHP_VERSION}-fpm-alpine AS php-system-setup
# Install system dependencies
RUN apk add --no-cache dcron busybox-suid libcap curl zip unzip git
RUN apk add --update nodejs npm

# Install PHP extensions
COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/bin/
RUN install-php-extensions intl bcmath gd pdo_mysql pdo_pgsql opcache redis uuid exif pcntl zip pdo_sqlsrv

# Install supervisord implementation
COPY --from=ochinchina/supervisord:latest /usr/local/bin/supervisord /usr/local/bin/supervisord

# Install caddy
COPY --from=caddy:2.2.1 /usr/bin/caddy /usr/local/bin/caddy
RUN setcap 'cap_net_bind_service=+ep' /usr/local/bin/caddy

# Install composer
COPY --from=composer/composer:2 /usr/bin/composer /usr/local/bin/composer

FROM php-system-setup AS app-setup

# Set working directory
ENV LARAVEL_PATH=/srv/app
WORKDIR $LARAVEL_PATH

# Add non-root user: 'app'
ARG NON_ROOT_GROUP=${NON_ROOT_GROUP:-app}
ARG NON_ROOT_USER=${NON_ROOT_USER:-app}
RUN addgroup -S $NON_ROOT_GROUP && adduser -S $NON_ROOT_USER -G $NON_ROOT_GROUP
RUN addgroup $NON_ROOT_USER wheel

# IMPORTANT: This Dockerfile works with Docker Context Path = '.deploy'
# All paths are relative to .deploy folder
# composer.json and composer.lock must be copied to .deploy folder first

# Set cron job (context is .deploy, so use relative path)
COPY config/crontab /etc/crontabs/$NON_ROOT_USER
RUN chmod 777 /usr/sbin/crond
RUN chown -R $NON_ROOT_USER:$NON_ROOT_GROUP /etc/crontabs/$NON_ROOT_USER && setcap cap_setgid=ep /usr/sbin/crond

# Switch to non-root 'app' user & install app dependencies
# composer.json and composer.lock should be in .deploy folder
COPY composer.json composer.lock ./
RUN chown -R $NON_ROOT_USER:$NON_ROOT_GROUP $LARAVEL_PATH
USER $NON_ROOT_USER
RUN composer install --prefer-dist --no-scripts --no-autoloader
RUN rm -rf /home/$NON_ROOT_USER/.composer

# Copy all .deploy contents first
COPY --chown=$NON_ROOT_USER:$NON_ROOT_GROUP . $LARAVEL_PATH/.deploy/
COPY config/php/local.ini /usr/local/etc/php/conf.d/local.ini

# Copy entrypoint.sh from the copied .deploy directory
# After copying all files, entrypoint.sh should be at $LARAVEL_PATH/.deploy/entrypoint.sh
RUN cp $LARAVEL_PATH/.deploy/entrypoint.sh /entrypoint.sh && chmod +x /entrypoint.sh

# NOTE: The rest of the application files need to be copied from parent directory
# This requires Docker Context Path to be '.' (root), not '.deploy'
# If context path is '.deploy', you need to copy all app files to .deploy first
# OR change Docker Context Path in Dokploy to '.' (root project)

#install NPM and run production
# This will fail if package.json is not in .deploy
RUN npm install || echo "Warning: npm install failed - package.json may not be in .deploy"
RUN npm run build || echo "Warning: npm build failed"

# Set any ENVs
ARG APP_KEY=${APP_KEY}
ARG APP_NAME=${APP_NAME}
ARG APP_URL=${APP_URL}
ARG APP_ENV=${APP_ENV}
ARG APP_DEBUG=${APP_DEBUG}

ARG LOG_CHANNEL=${LOG_CHANNEL}

ARG DB_CONNECTION=${DB_CONNECTION}
ARG DB_HOST=${DB_HOST}
ARG DB_PORT=${DB_PORT}
ARG DB_DATABASE=${DB_DATABASE}
ARG DB_USERNAME=${DB_USERNAME}
ARG DB_PASSWORD=${DB_PASSWORD}

ARG BROADCAST_DRIVER=${BROADCAST_DRIVER}
ARG CACHE_DRIVER=${CACHE_DRIVER}
ARG QUEUE_CONNECTION=${QUEUE_CONNECTION}
ARG SESSION_DRIVER=${SESSION_DRIVER}
ARG SESSION_LIFETIME=${SESSION_LIFETIME}

ARG REDIS_HOST=${REDIS_HOST}
ARG REDIS_PASSWORD=${REDIS_PASSWORD}
ARG REDIS_PORT=${REDIS_PORT}

ARG MAIL_MAILER=${MAIL_MAILER}
ARG MAIL_HOST=${MAIL_HOST}
ARG MAIL_PORT=${MAIL_PORT}
ARG MAIL_USERNAME=${MAIL_USERNAME}
ARG MAIL_PASSWORD=${MAIL_PASSWORD}
ARG MAIL_ENCRYPTION=${MAIL_ENCRYPTION}
ARG MAIL_FROM_ADDRESS=${MAIL_FROM_ADDRESS}
ARG MAIL_FROM_NAME=${APP_NAME}

ARG PUSHER_APP_ID=${PUSHER_APP_ID}
ARG PUSHER_APP_KEY=${PUSHER_APP_KEY}
ARG PUSHER_APP_SECRET=${PUSHER_APP_SECRET}
ARG PUSHER_APP_CLUSTER=${PUSHER_APP_CLUSTER}

ARG LINODE_KEY=${PUSHER_APP_ID}
ARG LINODE_SECRET=${LINODE_SECRET}
ARG LINODE_ENDPOINT=${LINODE_ENDPOINT}
ARG LINODE_REGION=${LINODE_REGION}
ARG LINODE_BUCKET=${LINODE_BUCKET}
ARG BEAMS_ID=${BEAMS_ID}
ARG WK_NOTIF_TIME_TO_SEND_MIN=${WK_NOTIF_TIME_TO_SEND_MIN}
ARG FIREBASE_CREDENTIALS=${FIREBASE_CREDENTIALS}

# Start app
EXPOSE 80

ENTRYPOINT ["sh", "/entrypoint.sh"]
