#!/bin/sh
set -e

php /var/www/html/scripts/import.php

exec apache2-foreground
