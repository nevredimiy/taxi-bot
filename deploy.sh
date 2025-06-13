
text/x-generic deploy.sh ( Bourne-Again shell script, ASCII text executable )
#!/bin/bash

set -e

echo "Starting deployment..."
git pull origin main
php artisan down
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan up
echo "Deployment completed successfully."