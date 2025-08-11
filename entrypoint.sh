#!/bin/bash
# Start monitor in the background
php /app/monitor.php &

# Start PHP built-in server for index page
php -S 0.0.0.0:8000 -t /app/public