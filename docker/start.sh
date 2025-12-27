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
    echo "${GREEN}.env file found. Running optimizations...${NC}"
    
    # Run Laravel optimizations
    php artisan config:cache || echo "${YELLOW}Warning: config:cache failed${NC}"
    php artisan route:cache || echo "${YELLOW}Warning: route:cache failed${NC}"
    php artisan view:cache || echo "${YELLOW}Warning: view:cache failed${NC}"
    
    echo "${GREEN}Optimizations completed.${NC}"
else
    echo "${YELLOW}Warning: .env file not found. Skipping optimizations.${NC}"
    echo "${YELLOW}Make sure environment variables are set in Dokploy.${NC}"
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
echo "${GREEN}Application is ready!${NC}"

# Run PHP built-in server (this will block)
exec php -S 0.0.0.0:9000 -t public

