FROM php:8.5.10-apache

RUN apt-get update \
    && apt-get install -y libcurl4-openssl-dev libssl-dev libzip-dev pkg-config \
    && docker-php-ext-install curl zip \
    && pecl install mongodb \
    && docker-php-ext-enable mongodb \
    && rm -rf /var/lib/apt/lists/*

COPY . /var/www/html/

RUN mkdir -p /data/db \
    && chown -R www-data:www-data /var/www/html /data \
    && chmod +x /var/www/html/docker/entrypoint.sh \
    && printf '%s\n' \
        '<Directory /var/www/html/src>' \
        '    Require all denied' \
        '</Directory>' \
        '<Directory /var/www/html/config>' \
        '    Require all denied' \
        '</Directory>' \
        '<Directory /var/www/html/scripts>' \
        '    Require all denied' \
        '</Directory>' \
        '<Directory /var/www/html/data>' \
        '    Require all denied' \
        '</Directory>' \
        > /etc/apache2/conf-available/protect-app.conf \
    && a2enconf protect-app

ENV MONGO_URI=mongodb://mongo:27017
ENV MONGO_DB=viagem
ENV DATA_DIR=/data/db

ENTRYPOINT ["/var/www/html/docker/entrypoint.sh"]
