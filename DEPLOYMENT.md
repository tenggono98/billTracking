# Advanced Deployment Guide - Bill Tracking Application

This document provides detailed deployment instructions for various hosting environments and scenarios.

## Table of Contents

1. [Server Requirements](#server-requirements)
2. [Local Development](#local-development)
3. [Shared Hosting Deployment](#shared-hosting-deployment)
4. [VPS Deployment (Ubuntu 22.04)](#vps-deployment-ubuntu-2204)
5. [Docker & Container Deployment](#docker--container-deployment)
6. [Cloud Platform Deployments](#cloud-platform-deployments)
7. [CI/CD Pipeline Setup](#cicd-pipeline-setup)
8. [Post-Deployment Checklist](#post-deployment-checklist)

---

## Server Requirements

### Minimum Requirements
- **PHP**: 8.2+ with extensions: curl, gd, json, mbstring, openssl, xml, zip, pdo, pdo_mysql
- **Web Server**: Nginx or Apache 2.4+
- **Database**: MySQL 8.0+ / PostgreSQL 12+ / SQLite
- **Node.js**: 18+ (for asset compilation)

### Recommended Requirements
- **RAM**: 2GB minimum (4GB+ for production)
- **Storage**: 10GB+ free space
- **CPU**: 2+ cores (4+ for high traffic)
- **SSL/TLS**: HTTPS certificate

### Recommended PHP Extensions
```bash
php8.2-{
  bcmath,
  cli,
  common,
  curl,
  fpm,
  gd,
  iconv,
  intl,
  json,
  mbstring,
  mysql,
  opcache,
  openssl,
  pdo,
  pdo-mysql,
  pdo-pgsql,
  pdo-sqlite,
  readline,
  sqlite3,
  tokenizer,
  xml,
  zip
}
```

---

## Local Development

### Setup with Laragon

1. **Extract to htdocs**:
   ```bash
   cd C:\laragon\www\billTracking
   ```

2. **Quick Setup with Composer**:
   ```bash
   composer run-script setup
   ```

3. **Manual Setup**:
   ```bash
   # Install dependencies
   composer install
   npm install

   # Generate key
   php artisan key:generate

   # Setup database
   php artisan migrate:fresh --seed

   # Build assets
   npm run dev
   ```

4. **Start Development**:
   ```bash
   php artisan serve
   npm run dev
   ```

5. **Access Application**:
   - URL: `http://localhost:8000`
   - Gmail credentials for OAuth (if configured)

---

## Shared Hosting Deployment

### Limitations
- No SSH access in basic plans
- Limited command execution
- Shared PHP-FPM pool
- Limited file permissions

### Deployment Steps

#### 1. Prepare Local Assets

```bash
# Build all assets locally
npm run build

# Clean autoloader
composer dump-autoload --optimize

# Create deployment archive
zip -r -x "vendor/*" "node_modules/*" ".git/*" -R billTracking.zip .
```

#### 2. Upload to Hosting

1. Upload `billTracking.zip` via FTP/cPanel File Manager
2. Extract on server
3. Upload `vendor/` separately via FTP (if > 100MB limit)

#### 3. Configure .env

Use cPanel File Manager or SSH:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://yourdomain.com

DB_CONNECTION=mysql
DB_HOST=localhost
DB_DATABASE=cpanel_username_dbname
DB_USERNAME=cpanel_username_user
DB_PASSWORD=your_db_password

CACHE_DRIVER=file
SESSION_DRIVER=file
QUEUE_CONNECTION=database
```

#### 4. Fix Permissions

```bash
# Via SSH if available
chmod -R 755 .
chmod -R 755 storage
chmod -R 755 bootstrap/cache
```

#### 5. Setup Cron Job

In cPanel Cron Jobs:

```bash
* * * * * /usr/bin/php /home/username/public_html/artisan schedule:run >> /dev/null 2>&1
```

#### 6. Verify Installation

- Visit `https://yourdomain.com`
- Check browser console for JS errors
- Review error logs in `storage/logs/`

### Known Hosting Issues

**Issue**: PHP files not executing
- **Solution**: Check PHP handler in .htaccess or cPanel

**Issue**: Storage folder permission denied
- **Solution**: Use cPanel File Manager to chmod 777

**Issue**: Database connection refused
- **Solution**: Use `localhost` not `127.0.0.1`

---

## VPS Deployment (Ubuntu 22.04)

### 1. Initial Server Setup

```bash
# Update system
sudo apt update && sudo apt upgrade -y

# Install dependencies
sudo apt install -y curl wget git zip unzip

# Install PHP
sudo apt install -y php8.2 php8.2-{fpm,mysql,xml,curl,gd,zip,bcmath,mbstring}

# Install Node.js
curl -sL https://deb.nodesource.com/setup_18.x | sudo -E bash -
sudo apt install -y nodejs

# Install Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Install Nginx
sudo apt install -y nginx

# Install MySQL
sudo apt install -y mysql-server
```

### 2. Create Application User

```bash
# Create dedicated user
sudo useradd -m -s /bin/bash billtracking

# Create application directory
sudo mkdir -p /home/billtracking/app
sudo chown -R billtracking:billtracking /home/billtracking/app

# Switch to user
sudo -u billtracking -s
```

### 3. Clone Repository

```bash
cd /home/billtracking/app
git clone <repository-url> .
```

### 4. Install PHP Dependencies

```bash
composer install --optimize-autoloader --no-dev --no-progress --prefer-dist
```

### 5. Install Node Dependencies & Build

```bash
npm install --production
npm run build
```

### 6. Configure Environment

```bash
cp .env.example .env
php artisan key:generate

# Edit .env with production values
nano .env
```

Minimum .env configuration:

```env
APP_NAME="Bill Tracking"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://yourdomain.com

DB_CONNECTION=mysql
DB_HOST=localhost
DB_DATABASE=bill_tracking
DB_USERNAME=billtracking_user
DB_PASSWORD=$(openssl rand -base64 32)

MAIL_FROM_ADDRESS=noreply@yourdomain.com

FILESYSTEM_DISK=local
```

### 7. Setup MySQL Database

```bash
# Connect to MySQL
sudo mysql

# Create user and database
CREATE DATABASE bill_tracking;
CREATE USER 'billtracking_user'@'localhost' IDENTIFIED BY 'secure_password_here';
GRANT ALL PRIVILEGES ON bill_tracking.* TO 'billtracking_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;

# Run migrations
php artisan migrate --force
```

### 8. Set File Permissions

```bash
# Storage and bootstrap
sudo chmod -R 775 /home/billtracking/app/storage
sudo chmod -R 775 /home/billtracking/app/bootstrap/cache
sudo chown -R www-data:www-data /home/billtracking/app/storage
sudo chown -R www-data:www-data /home/billtracking/app/bootstrap/cache

# Public directory
sudo chmod -R 755 /home/billtracking/app/public
```

### 9. Configure Nginx

Create `/etc/nginx/sites-available/bill-tracking`:

```nginx
# HTTP to HTTPS redirect
server {
    listen 80;
    listen [::]:80;
    server_name yourdomain.com www.yourdomain.com;
    return 301 https://$server_name$request_uri;
}

# Main HTTPS server block
server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name yourdomain.com www.yourdomain.com;

    # Root directory
    root /home/billtracking/app/public;
    index index.php index.html;

    # SSL certificates (Let's Encrypt)
    ssl_certificate /etc/letsencrypt/live/yourdomain.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/yourdomain.com/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;
    ssl_prefer_server_ciphers on;

    # Security headers
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "no-referrer-when-downgrade" always;

    # Gzip compression
    gzip on;
    gzip_vary on;
    gzip_min_length 1024;
    gzip_types text/plain text/css text/xml text/javascript application/x-javascript application/xml+rss application/atom+xml image/svg+xml;

    # Logging
    access_log /var/log/nginx/bill-tracking-access.log combined;
    error_log /var/log/nginx/bill-tracking-error.log;

    # Client upload size
    client_max_body_size 100M;

    # Laravel routing
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # PHP execution
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
        
        # Timeout settings for long operations
        fastcgi_connect_timeout 300;
        fastcgi_send_timeout 300;
        fastcgi_read_timeout 300;
    }

    # Static assets cache
    location ~* \.(jpg|jpeg|png|gif|ico|css|js|svg|woff|woff2|ttf|eot)$ {
        expires 365d;
        add_header Cache-Control "public, immutable";
    }

    # Hide sensitive files
    location ~ /\. {
        deny all;
    }
    
    location ~ /\.ht {
        deny all;
    }
}
```

Enable site:

```bash
sudo ln -s /etc/nginx/sites-available/bill-tracking /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl restart nginx
```

### 10. Setup SSL Certificate (Let's Encrypt)

```bash
# Install Certbot
sudo apt install -y certbot python3-certbot-nginx

# Obtain certificate
sudo certbot certonly --nginx -d yourdomain.com -d www.yourdomain.com

# Auto-renewal (runs automatically)
sudo systemctl status certbot.timer
```

### 11. Configure PHP-FPM

Edit `/etc/php/8.2/fpm/pool.d/www.conf`:

```ini
; Set to dedicated user
user = www-data
group = www-data

; Connection limit
pm = dynamic
pm.max_children = 20
pm.start_servers = 5
pm.min_spare_servers = 2
pm.max_spare_servers = 10

; Process timeout
request_terminate_timeout = 300

; Listen socket
listen = /var/run/php/php8.2-fpm.sock
listen.owner = www-data
listen.group = www-data
listen.mode = 0660
```

Restart PHP-FPM:

```bash
sudo systemctl restart php8.2-fpm
```

### 12. Setup Background Jobs (Supervisor)

Install Supervisor:

```bash
sudo apt install -y supervisor
```

Create `/etc/supervisor/conf.d/bill-tracking.conf`:

```ini
[program:bill-tracking-queue]
process_name=%(program_name)s_%(process_num)02d
command=php /home/billtracking/app/artisan queue:work --sleep=3 --tries=3 --timeout=90
autostart=true
autorestart=true
numprocs=2
user=www-data
redirect_stderr=true
stdout_logfile=/var/log/supervisor/bill-tracking-queue.log
stdout_logfile_maxbytes=10MB
stdout_logfile_backups=10

[program:bill-tracking-scheduler]
command=php /home/billtracking/app/artisan schedule:work
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/log/supervisor/bill-tracking-scheduler.log
stdout_logfile_maxbytes=10MB
stdout_logfile_backups=10
```

Start Supervisor:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start all
```

### 13. Optimization Commands

```bash
# Cache optimization
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# Optimize autoloader
composer dump-autoload --optimize --no-dev

# Clear unnecessary files
php artisan optimize:clear
```

---

## Docker & Container Deployment

### Docker Setup

Create `Dockerfile`:

```dockerfile
FROM php:8.2-fpm-alpine

# Install system dependencies
RUN apk add --no-cache \
    nginx \
    sqlite \
    mysql-client \
    postgresql-client \
    nodejs \
    npm \
    curl \
    git \
    zip \
    unzip \
    supervisor

# Install PHP extensions
RUN docker-php-ext-install \
    pdo \
    pdo_mysql \
    pdo_pgsql \
    pdo_sqlite \
    bcmath \
    gd \
    mbstring \
    zip

# Copy application
WORKDIR /app
COPY . .

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader

# Install Node dependencies and build
RUN npm install && npm run build

# Copy configuration files
COPY docker/nginx/nginx.conf /etc/nginx/nginx.conf
COPY docker/supervisor/supervisord.conf /etc/supervisord.conf

# Set permissions
RUN chown -R nobody:nobody /app && \
    chmod -R 755 /app && \
    chmod -R 775 /app/storage /app/bootstrap/cache

# Expose ports
EXPOSE 80 443

# Start services
CMD ["/docker/start.sh"]
```

### Docker Compose Setup

`docker-compose.yml`:

```yaml
version: '3.8'

services:
  # PHP-FPM Application
  app:
    build:
      context: .
      dockerfile: Dockerfile
    container_name: bill-tracking-app
    restart: unless-stopped
    working_dir: /app
    environment:
      APP_ENV: production
      APP_DEBUG: false
      APP_KEY: ${APP_KEY}
      DB_CONNECTION: mysql
      DB_HOST: mysql
      DB_PORT: 3306
      DB_DATABASE: ${DB_DATABASE:-bill_tracking}
      DB_USERNAME: ${DB_USERNAME:-bill_user}
      DB_PASSWORD: ${DB_PASSWORD}
    volumes:
      - ./:/app
      - ./storage:/app/storage
      - ./bootstrap/cache:/app/bootstrap/cache
    networks:
      - bill-network
    depends_on:
      - mysql
      - redis

  # MySQL Database
  mysql:
    image: mysql:8.0
    container_name: bill-tracking-mysql
    restart: unless-stopped
    environment:
      MYSQL_DATABASE: ${DB_DATABASE:-bill_tracking}
      MYSQL_ROOT_PASSWORD: ${MYSQL_ROOT_PASSWORD}
      MYSQL_PASSWORD: ${DB_PASSWORD}
      MYSQL_USER: ${DB_USERNAME:-bill_user}
    volumes:
      - mysql-data:/var/lib/mysql
    networks:
      - bill-network
    ports:
      - "3306:3306"

  # Redis Cache
  redis:
    image: redis:7-alpine
    container_name: bill-tracking-redis
    restart: unless-stopped
    networks:
      - bill-network
    ports:
      - "6379:6379"

  # Nginx Web Server
  nginx:
    image: nginx:alpine
    container_name: bill-tracking-nginx
    restart: unless-stopped
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - ./public:/app/public:ro
      - ./docker/nginx/nginx.conf:/etc/nginx/nginx.conf:ro
      - ./storage/logs/nginx:/var/log/nginx
      - ./docker/ssl:/etc/nginx/ssl:ro
    networks:
      - bill-network
    depends_on:
      - app

  # Mailhog (for email testing)
  mailhog:
    image: mailhog/mailhog:latest
    container_name: bill-tracking-mailhog
    restart: unless-stopped
    ports:
      - "1025:1025"
      - "8025:8025"
    networks:
      - bill-network

volumes:
  mysql-data:

networks:
  bill-network:
    driver: bridge
```

Deploy with Docker Compose:

```bash
# Build images
docker-compose build

# Start services
docker-compose up -d

# Run migrations
docker-compose exec app php artisan migrate --force

# View logs
docker-compose logs -f app
```

---

## Cloud Platform Deployments

### AWS Deployment (EC2)

1. **Launch EC2 Instance**:
   - Ubuntu 22.04 LTS
   - t3.medium or larger
   - Security group: Allow 80, 443, 22

2. **Connect and Setup**:
   ```bash
   ssh -i your-key.pem ubuntu@your-instance-ip
   # Follow VPS deployment steps above
   ```

### Heroku Deployment

1. **Install Heroku CLI**:
   ```bash
   curl https://cli-assets.heroku.com/install.sh | sh
   ```

2. **Create Procfile**:
   ```
   web: heroku-php-nginx -C docker/nginx/nginx.conf public/
   release: php artisan migrate --force
   ```

3. **Deploy**:
   ```bash
   heroku create bill-tracking
   heroku config:set APP_KEY=$(php artisan key:generate --show)
   git push heroku main
   ```

### DigitalOcean App Platform

1. **Create App**:
   - Connect GitHub repository
   - Configure build: `npm run build`
   - Configure run: `php artisan serve`

2. **Environment**:
   - Set in App Platform dashboard
   - Enable auto-deploy from main branch

### Azure App Service

1. **Create Resource Group**:
   ```bash
   az group create -n bill-tracking -l eastus
   ```

2. **Create App Service**:
   ```bash
   az appservice plan create -n bill-tracking-plan -g bill-tracking --sku B2 --is-linux
   az webapp create -n bill-tracking -g bill-tracking -p bill-tracking-plan --runtime "PHP|8.2"
   ```

3. **Deploy**:
   ```bash
   az webapp deployment source config-zip --resource-group bill-tracking --name bill-tracking --src billTracking.zip
   ```

---

## CI/CD Pipeline Setup

### GitHub Actions

Create `.github/workflows/deploy.yml`:

```yaml
name: Deploy

on:
  push:
    branches: [main, production]

jobs:
  deploy:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
          extensions: curl,gd,mbstring,mysql,zip
      
      - name: Install Dependencies
        run: composer install --no-dev --optimize-autoloader
      
      - name: Setup Node
        uses: actions/setup-node@v3
        with:
          node-version: '18'
      
      - name: Build Assets
        run: npm install && npm run build
      
      - name: Run Tests
        run: php artisan test
      
      - name: Deploy to Server
        uses: appleboy/ssh-action@master
        with:
          host: ${{ secrets.SSH_HOST }}
          username: ${{ secrets.SSH_USER }}
          key: ${{ secrets.SSH_KEY }}
          script: |
            cd /home/billtracking/app
            git pull origin ${{ github.ref }}
            composer install --no-dev --optimize-autoloader
            npm install && npm run build
            php artisan migrate --force
            php artisan config:cache
            php artisan route:cache
            php artisan view:cache
            sudo systemctl restart php8.2-fpm nginx
```

---

## Post-Deployment Checklist

- [ ] Database migrations completed successfully
- [ ] Environment variables configured correctly
- [ ] File permissions set properly (storage, bootstrap/cache)
- [ ] SSL certificate installed and valid
- [ ] Email configuration tested
- [ ] Google OAuth configured and tested
- [ ] Error logs reviewed
- [ ] Backup strategy implemented
- [ ] Monitoring and alerting configured
- [ ] Database backups automated
- [ ] Application caching enabled
- [ ] Performance optimizations applied
- [ ] Security headers configured
- [ ] Rate limiting implemented
- [ ] Queue jobs tested
- [ ] Scheduler tested
- [ ] User authentication tested
- [ ] File upload functionality tested

---

## Troubleshooting Deployment Issues

### Database Connection Failed
```bash
# Check database credentials
cat .env | grep DB_

# Test connection
php artisan tinker
DB::connection()->getPDO()
```

### Permission Denied on Storage
```bash
sudo chown -R www-data:www-data /app/storage
sudo chmod -R 755 /app/storage
```

### Composer Out of Memory
```bash
composer install --no-dev -o --memory-limit=-1
```

### Node Build Fails
```bash
rm -rf node_modules package-lock.json
npm cache clean --force
npm install
npm run build
```

### Migrations Fail
```bash
# Check migration status
php artisan migrate:status

# Rollback last batch
php artisan migrate:rollback

# Fresh migration (WARNING: deletes all data)
php artisan migrate:fresh
```

---

**Last Updated**: January 2026
