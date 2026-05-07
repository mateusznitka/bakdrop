FROM php:8.3-apache

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
RUN a2enmod rewrite
RUN echo "display_errors=Off\nerror_log=/var/log/php_errors.log" >> /usr/local/etc/php/php.ini

RUN apt-get update && apt-get install -y git unzip libzip-dev \
    && docker-php-ext-install zip \
    && rm -rf /var/lib/apt/lists/*

RUN mkdir -p /var/lib/bakdrop && chown www-data:www-data /var/lib/bakdrop \
    && mkdir -p /fsr && chown www-data:www-data /fsr

COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader

COPY . .
RUN chown -R www-data:www-data /var/www/html

RUN curl -L -o /fsr/protocols.tar.gz https://github.com/mateusznitka/protocolsmanager/releases/download/v1.4.2/protocolsmanager.tar.gz \
    && chown www-data:www-data /fsr/protocols.tar.gz