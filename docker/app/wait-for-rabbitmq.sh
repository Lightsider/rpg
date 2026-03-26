#!/bin/sh
set -e

# Wait until RabbitMQ is reachable to avoid connection refused on container start.
php -r 'while (!@fsockopen("rabbitmq", 5672)) { sleep(1); }'

exec php /var/www/html/artisan queue:work --sleep=3 --tries=3 --max-time=3600
