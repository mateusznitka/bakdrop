FROM php:8.3-apache
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
RUN a2enmod rewrite
RUN mkdir -p /var/lib/bakdrop && chown www-data:www-data /var/lib/bakdrop && mkdir -p /fsr
RUN curl -L -o /fsr/protocols.tar.gz https://github.com/mateusznitka/protocolsmanager/releases/download/v1.4.2/protocolsmanager.tar.gz
