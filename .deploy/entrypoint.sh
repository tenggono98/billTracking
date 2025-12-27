#!/bin/sh

echo "🎬 entrypoint.sh: [$(whoami)] [PHP $(php -r 'echo phpversion();')]"

composer dump-autoload --no-interaction --optimize

echo "🎬 artisan commands"

# 💡 Group into a custom command e.g. php artisan app:on-deploy
#php artisan migrate --no-interaction --force
php artisan storage:link

#php artisan app:migrate-if-in-test-server

#php artisan migrate:refresh --seed

php artisan optimize

php artisan config:clear

#php artisan livewire:configure-s3-upload-cleanup

echo "🎬 start supervisord"

supervisord -c $LARAVEL_PATH/.deploy/config/supervisor.conf

