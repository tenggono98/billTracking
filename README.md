# Bill Tracking Application

A modern, feature-rich bill tracking system built with Laravel 12, Livewire 3, Flux UI, and Tailwind CSS. This application helps businesses and organizations manage bills, branches, and financial tracking with ease.

## 🎯 Features

- **Bill Management**: Create, track, and manage bills with detailed information
- **Branch Management**: Organize bills and operations across multiple branches
- **User Authentication**: Secure authentication with Google OAuth integration
- **PDF Export**: Generate professional PDF reports for bills and records
- **AI-Powered Extraction**: Intelligent data extraction from bill images using AI
- **Settings Management**: Configurable application settings and user preferences
- **Responsive UI**: Modern, mobile-friendly interface with Tailwind CSS and Flux components
- **Real-time Updates**: Live component updates with Livewire
- **Two-Factor Authentication**: Enhanced security with 2FA support

## 📋 Prerequisites

### System Requirements
- **PHP**: 8.2 or higher
- **Node.js**: 18.0 or higher
- **Composer**: Latest version
- **npm**: 9.0 or higher
- **Database**: MySQL, PostgreSQL, or SQLite
- **Git**: For version control

### Optional Requirements
- **Docker**: For containerized deployment
- **Redis**: For caching (optional but recommended)

## 🚀 Installation

### 1. Clone the Repository

```bash
git clone <repository-url>
cd billTracking
```

### 2. Install PHP Dependencies

```bash
composer install
```

### 3. Setup Environment Variables

```bash
# Copy the example environment file
cp .env.example .env

# Generate application key
php artisan key:generate
```

### 4. Configure Environment File

Edit `.env` file with your configuration:

```env
# Application Settings
APP_NAME="Bill Tracking"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000

# Database Configuration
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=bill_tracking
DB_USERNAME=root
DB_PASSWORD=

# Mail Configuration (if needed)
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=465
MAIL_USERNAME=your_username
MAIL_PASSWORD=your_password

# Google OAuth (Optional)
GOOGLE_CLIENT_ID=your_google_client_id
GOOGLE_CLIENT_SECRET=your_google_client_secret
GOOGLE_CLIENT_REDIRECT_URI=http://localhost:8000/auth/google/callback
```

### 5. Install Node Dependencies

```bash
npm install
```

### 6. Build Frontend Assets

```bash
# Development build with hot module replacement
npm run dev

# Production build (minified)
npm run build
```

### 7. Run Database Migrations

```bash
php artisan migrate
```

### 8. Seed Database (Optional)

```bash
php artisan db:seed
```

### 9. Start the Development Server

```bash
# In one terminal - Start PHP development server
php artisan serve

# In another terminal - Start Vite development server
npm run dev
```

Visit `http://localhost:8000` in your browser.

## 🔧 Configuration

### Google OAuth Setup

1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Create a new project
3. Enable Google+ API
4. Create OAuth 2.0 credentials (Web application)
5. Add authorized redirect URIs:
   - Development: `http://localhost:8000/auth/google/callback`
   - Production: `https://yourdomain.com/auth/google/callback`
6. Copy the Client ID and Client Secret to your `.env` file

### Database Configuration

The application supports multiple databases. Configure in your `.env`:

**MySQL**:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=bill_tracking
DB_USERNAME=root
DB_PASSWORD=secret
```

**PostgreSQL**:
```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=bill_tracking
DB_USERNAME=postgres
DB_PASSWORD=secret
```

**SQLite**:
```env
DB_CONNECTION=sqlite
DB_DATABASE=/path/to/database.sqlite
```

### Filesystem Configuration

Configure file storage in `.env`:

```env
FILESYSTEM_DISK=local
# or for S3
FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=your_access_key
AWS_SECRET_ACCESS_KEY=your_secret_key
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=your_bucket
```

## 📁 Project Structure

```
billTracking/
├── app/
│   ├── Actions/              # Business logic actions
│   ├── Helpers/              # Helper functions (CurrencyHelper.php)
│   ├── Http/
│   │   ├── Controllers/      # HTTP controllers
│   │   └── Requests/         # Form request validation
│   ├── Livewire/             # Livewire components
│   │   └── Actions/          # Component actions
│   ├── Models/               # Eloquent models (Bill, Branch, User, Setting)
│   ├── Providers/            # Service providers
│   └── Services/             # Business services (AiExtractionService, PdfExportService)
├── bootstrap/                # Bootstrap files
├── config/                   # Configuration files
├── database/
│   ├── factories/            # Factory classes for testing
│   ├── migrations/           # Database migrations
│   └── seeders/              # Database seeders
├── docker/                   # Docker configuration
├── public/                   # Public assets
├── resources/
│   ├── css/                  # CSS files (Tailwind)
│   ├── js/                   # JavaScript files
│   └── views/                # Blade templates
├── routes/                   # Route definitions
├── storage/                  # File storage
├── tests/                    # Unit and feature tests
├── vendor/                   # Composer dependencies
├── composer.json             # PHP dependencies
├── package.json              # Node dependencies
└── vite.config.js            # Vite configuration
```

## 💾 Database Schema

### Users Table
- `id`: Primary key
- `name`: User's full name
- `email`: Unique email address
- `google_id`: Google OAuth ID
- `two_factor_confirmed_at`: 2FA status
- Timestamps

### Bills Table
- `id`: Primary key
- `user_id`: Foreign key to users
- `branch_id`: Foreign key to branches
- `bill_image`: Path to bill image
- `payment_proof_image_path`: Path to payment proof
- `amount`: Bill amount
- `status`: Bill status (pending, completed, etc.)
- Timestamps

### Branches Table
- `id`: Primary key
- `user_id`: Foreign key to users
- `name`: Branch name
- `location`: Branch location
- Timestamps

### Settings Table
- `id`: Primary key
- `user_id`: Foreign key to users
- `key`: Setting key
- `value`: Setting value
- Timestamps

## 🧪 Testing

Run the test suite:

```bash
# Run all tests
php artisan test

# Run specific test class
php artisan test tests/Feature/BillTest

# Run with code coverage
php artisan test --coverage
```

## 🐛 Debugging

### Enable Debug Mode

Set in `.env`:
```env
APP_DEBUG=true
```

### View Logs

```bash
# Real-time log viewer
php artisan pail

# Manual log inspection
tail -f storage/logs/laravel.log
```

### Database Query Debugging

Enable query logging in `.env`:
```env
DB_LOG=true
```

## 📦 Building for Production

### 1. Optimize Application

```bash
# Cache configuration
php artisan config:cache

# Cache routes
php artisan route:cache

# Cache views
php artisan view:cache

# Optimize autoloader
composer dump-autoload --optimize
```

### 2. Build Frontend Assets

```bash
npm run build
```

### 3. Set Production Environment

```env
APP_ENV=production
APP_DEBUG=false
```

## 🚀 Deployment Guide

### Manual Server Deployment

#### 1. Prepare Server

```bash
# SSH into your server
ssh user@your-server.com

# Install required packages
sudo apt-get update
sudo apt-get install -y php8.2 php8.2-{fpm,mysql,xml,curl,gd,zip} composer nodejs npm nginx mysql-server

# Create application directory
sudo mkdir -p /var/www/bill-tracking
cd /var/www/bill-tracking
```

#### 2. Clone Repository

```bash
git clone <repository-url> .
```

#### 3. Install Dependencies

```bash
composer install --optimize-autoloader --no-dev
npm install
npm run build
```

#### 4. Configure Environment

```bash
# Copy environment file
cp .env.example .env

# Edit .env with production values
nano .env

# Generate application key
php artisan key:generate

# Set permissions
sudo chown -R www-data:www-data /var/www/bill-tracking
sudo chmod -R 755 /var/www/bill-tracking
sudo chmod -R 775 /var/www/bill-tracking/storage
sudo chmod -R 775 /var/www/bill-tracking/bootstrap/cache
```

#### 5. Setup Database

```bash
# Create database
mysql -u root -p
CREATE DATABASE bill_tracking;
EXIT;

# Run migrations
php artisan migrate --force
```

#### 6. Configure Web Server (Nginx)

Create `/etc/nginx/sites-available/bill-tracking`:

```nginx
server {
    listen 80;
    server_name your-domain.com www.your-domain.com;
    
    root /var/www/bill-tracking/public;
    index index.php index.html index.htm;
    
    # Redirect HTTP to HTTPS
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name your-domain.com www.your-domain.com;
    
    root /var/www/bill-tracking/public;
    index index.php index.html index.htm;
    
    # SSL configuration
    ssl_certificate /etc/letsencrypt/live/your-domain.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/your-domain.com/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;
    
    # Gzip compression
    gzip on;
    gzip_types text/plain text/css text/xml text/javascript application/x-javascript application/xml+rss;
    
    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    
    # Log files
    access_log /var/log/nginx/bill-tracking-access.log;
    error_log /var/log/nginx/bill-tracking-error.log;
    
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
    
    location ~ /\.ht {
        deny all;
    }
}
```

Enable the site:

```bash
sudo ln -s /etc/nginx/sites-available/bill-tracking /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl restart nginx
```

#### 7. Setup SSL Certificate (Let's Encrypt)

```bash
sudo apt-get install -y certbot python3-certbot-nginx
sudo certbot certonly --nginx -d your-domain.com -d www.your-domain.com
```

#### 8. Setup Process Manager (Supervisor)

Create `/etc/supervisor/conf.d/bill-tracking.conf`:

```ini
[program:bill-tracking-queue]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/bill-tracking/artisan queue:work --sleep=3 --tries=3 --timeout=90
autostart=true
autorestart=true
numprocs=2
redirect_stderr=true
stdout_logfile=/var/log/supervisor/bill-tracking-queue.log
```

Reload supervisor:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start bill-tracking-queue:*
```

#### 9. Setup Cron Jobs

Add to crontab:

```bash
sudo crontab -e

# Add the following line
* * * * * cd /var/www/bill-tracking && php artisan schedule:run >> /dev/null 2>&1
```

### Docker Deployment

#### 1. Build Docker Image

```bash
docker build -t bill-tracking:latest .
```

#### 2. Run Container

```bash
docker run -d \
  --name bill-tracking \
  -p 80:80 \
  -p 443:443 \
  -e APP_ENV=production \
  -e APP_DEBUG=false \
  -e DB_HOST=mysql \
  -e DB_DATABASE=bill_tracking \
  -e DB_USERNAME=root \
  -e DB_PASSWORD=secret \
  -v /var/www/bill-tracking/storage:/app/storage \
  bill-tracking:latest
```

#### 3. Docker Compose Setup

Create `docker-compose.yml`:

```yaml
version: '3.8'

services:
  app:
    build: .
    ports:
      - "80:80"
      - "443:443"
    environment:
      APP_ENV: production
      APP_DEBUG: false
      DB_HOST: mysql
      DB_DATABASE: bill_tracking
      DB_USERNAME: root
      DB_PASSWORD: ${DB_PASSWORD}
    volumes:
      - ./storage:/app/storage
      - ./bootstrap/cache:/app/bootstrap/cache
    depends_on:
      - mysql
    networks:
      - bill-tracking

  mysql:
    image: mysql:8.0
    environment:
      MYSQL_ROOT_PASSWORD: ${DB_PASSWORD}
      MYSQL_DATABASE: bill_tracking
    volumes:
      - mysql-data:/var/lib/mysql
    networks:
      - bill-tracking

  nginx:
    image: nginx:alpine
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - ./docker/nginx/nginx.conf:/etc/nginx/nginx.conf:ro
      - ./public:/app/public:ro
    depends_on:
      - app
    networks:
      - bill-tracking

volumes:
  mysql-data:

networks:
  bill-tracking:
```

Run with:
```bash
docker-compose up -d
```

### Deployment with Dokploy

1. Create a new application in Dokploy dashboard
2. Connect your Git repository
3. Set environment variables in Dokploy UI
4. Configure deployment settings:
   - Build command: `docker build -t app .`
   - Start command: Uses `docker/start.sh`
5. Deploy

The `docker/start.sh` script handles:
- APP_KEY generation
- Configuration caching
- Database migrations
- Storage directory setup

## 🔐 Security Considerations

1. **Environment Variables**: Keep sensitive data in `.env` (never commit to Git)
2. **HTTPS**: Always use HTTPS in production
3. **CORS**: Configure CORS properly in `config/cors.php`
4. **CSRF Protection**: Enabled by default on all POST requests
5. **SQL Injection**: Use Eloquent ORM to prevent SQL injection
6. **File Upload**: Validate and sanitize file uploads
7. **Rate Limiting**: Implement rate limiting for API endpoints
8. **Dependency Updates**: Regularly update dependencies

```bash
# Check for vulnerabilities
composer audit
npm audit
```

## 📊 Monitoring

### Application Monitoring

```bash
# Check application health
php artisan route:cache
php artisan config:cache

# Monitor queue jobs
php artisan queue:monitor
```

### Server Monitoring

Use tools like:
- **New Relic**: Application performance monitoring
- **DataDog**: Infrastructure monitoring
- **Sentry**: Error tracking
- **LogRocket**: User session recording

## 🆘 Troubleshooting

### Common Issues

#### 1. "No application encryption key has been specified"

```bash
php artisan key:generate
```

#### 2. Database Connection Error

Check `.env` database credentials:
```env
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=bill_tracking
DB_USERNAME=root
DB_PASSWORD=secret
```

#### 3. Permission Denied Errors

```bash
sudo chown -R www-data:www-data /var/www/bill-tracking
sudo chmod -R 775 /var/www/bill-tracking/storage
sudo chmod -R 775 /var/www/bill-tracking/bootstrap/cache
```

#### 4. Storage Symlink Missing

```bash
php artisan storage:link
```

#### 5. Vite Asset Compilation Failed

```bash
rm -rf node_modules package-lock.json
npm install
npm run build
```

#### 6. Google OAuth Not Working

- Verify Google Client ID and Secret in `.env`
- Check authorized redirect URIs in Google Console
- Ensure domain is properly configured

#### 7. Queue Jobs Not Processing

```bash
# Check queue status
php artisan queue:failed

# Retry failed jobs
php artisan queue:retry all

# Clear queue
php artisan queue:clear
```

## 📚 Additional Resources

- [Laravel Documentation](https://laravel.com/docs)
- [Livewire Documentation](https://livewire.laravel.com)
- [Tailwind CSS Documentation](https://tailwindcss.com)
- [Flux UI Documentation](https://fluxui.dev)
- [Vite Documentation](https://vitejs.dev)

## 📝 License

This project is licensed under the MIT License - see the LICENSE file for details.

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## 📞 Support

For support, email support@example.com or open an issue in the repository.

---

**Last Updated**: January 2026
**Version**: 1.0.0
**Maintainer**: Development Team
