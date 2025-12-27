#!/bin/sh
set -e

# Colors for output
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

echo "${GREEN}Starting Laravel application...${NC}"

# Check if .env file exists
if [ -f .env ]; then
    echo "${GREEN}.env file found.${NC}"
    
    # Check if APP_KEY is set
    if ! grep -q "APP_KEY=base64:" .env 2>/dev/null && ! grep -q "^APP_KEY=" .env 2>/dev/null; then
        echo "${YELLOW}Warning: APP_KEY not found in .env${NC}"
        echo "${YELLOW}Generating APP_KEY...${NC}"
        php artisan key:generate --force || echo "${RED}Error: Failed to generate APP_KEY${NC}"
    fi
    
    # Run Laravel optimizations
    echo "${GREEN}Running optimizations...${NC}"
    php artisan config:cache || echo "${YELLOW}Warning: config:cache failed${NC}"
    php artisan route:cache || echo "${YELLOW}Warning: route:cache failed${NC}"
    php artisan view:cache || echo "${YELLOW}Warning: view:cache failed${NC}"
    
    echo "${GREEN}Optimizations completed.${NC}"
else
    echo "${RED}ERROR: .env file not found!${NC}"
    echo "${YELLOW}Please configure environment variables in Dokploy.${NC}"
    echo "${YELLOW}Required variables: APP_KEY, APP_ENV, APP_DEBUG, DB_CONNECTION, etc.${NC}"
    echo "${YELLOW}Application may not work correctly without .env file.${NC}"
fi

# Ensure storage directories exist and have correct permissions
mkdir -p storage/framework/{sessions,views,cache}
mkdir -p storage/logs
mkdir -p bootstrap/cache
chmod -R 775 storage bootstrap/cache

# Ensure SQLite database exists
if [ ! -f database/database.sqlite ]; then
    echo "${GREEN}Creating SQLite database...${NC}"
    touch database/database.sqlite
    chmod 664 database/database.sqlite
fi

# Run migrations if .env exists
if [ -f .env ]; then
    echo "${GREEN}Running database migrations...${NC}"
    php artisan migrate --force || echo "${YELLOW}Warning: migrations failed${NC}"
fi

# Start queue worker in background (only if .env exists)
if [ -f .env ]; then
    echo "${GREEN}Starting queue worker...${NC}"
    php artisan queue:work --tries=3 --timeout=90 --max-time=3600 > storage/logs/queue.log 2>&1 &
    QUEUE_PID=$!
    echo "${GREEN}Queue worker started (PID: $QUEUE_PID)${NC}"
else
    echo "${YELLOW}Skipping queue worker (no .env file)${NC}"
    QUEUE_PID=""
fi

# Function to cleanup on exit
cleanup() {
    echo "${YELLOW}Shutting down...${NC}"
    if [ ! -z "$QUEUE_PID" ]; then
        kill $QUEUE_PID 2>/dev/null || true
        wait $QUEUE_PID 2>/dev/null || true
    fi
    exit 0
}

# Trap signals for graceful shutdown
trap cleanup SIGTERM SIGINT

# Start PHP built-in server
echo "${GREEN}Starting PHP built-in server on port 9000...${NC}"

# Enable error display for debugging (will be overridden by APP_DEBUG in .env if set)
# This helps see errors when .env is not properly configured
export PHP_INI_SCAN_DIR="/usr/local/etc/php/conf.d:/tmp"
echo "display_errors=On" > /tmp/error-display.ini
echo "display_startup_errors=On" >> /tmp/error-display.ini
echo "error_reporting=E_ALL" >> /tmp/error-display.ini

echo "${GREEN}Application is ready!${NC}"
echo "${YELLOW}Check storage/logs/laravel.log for detailed error messages.${NC}"

# Run PHP built-in server (this will block)
# Use -d to set php.ini values for error display
exec php -d display_errors=1 -d display_startup_errors=1 -d error_reporting=E_ALL -S 0.0.0.0:9000 -t public

