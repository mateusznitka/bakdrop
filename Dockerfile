FROM php:8.3-apache

# UID/GID the app runs as - set to the host user who owns the files directory,
# so files copied on the host are fully accessible in the container (and vice versa)
ARG PUID=33
ARG PGID=33

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
RUN a2enmod rewrite ssl
RUN echo "display_errors=Off\nerror_log=/var/log/php_errors.log" >> /usr/local/etc/php/php.ini

RUN apt-get update && apt-get install -y git unzip libzip-dev openssl \
    && docker-php-ext-install zip \
    && rm -rf /var/lib/apt/lists/*

# Remap www-data to the requested UID/GID (must happen before any chown below)
RUN groupmod -o -g ${PGID} www-data && usermod -o -u ${PUID} -g ${PGID} www-data

RUN mkdir -p /var/lib/bakdrop && chown www-data:www-data /var/lib/bakdrop \
    && mkdir -p /bakdrop && chown www-data:www-data /bakdrop \
    && mkdir -p /var/log/bakdrop && chown www-data:www-data /var/log/bakdrop

RUN openssl req -x509 -nodes -days 3650 -newkey rsa:2048 \
    -keyout /etc/ssl/private/bakdrop.key \
    -out /etc/ssl/certs/bakdrop.crt \
    -subj "/CN=bakdrop"

RUN printf '%s\n' \
    '<VirtualHost *:80>' \
    '    RewriteEngine On' \
    '    RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [R=301,L]' \
    '</VirtualHost>' \
    > /etc/apache2/sites-available/000-default.conf && \
    printf '%s\n' \
    '<IfModule mod_ssl.c>' \
    '    <VirtualHost *:443>' \
    '        DocumentRoot /var/www/html' \
    '        SSLEngine on' \
    '        SSLCertificateFile /etc/ssl/certs/bakdrop.crt' \
    '        SSLCertificateKeyFile /etc/ssl/private/bakdrop.key' \
    '        <Directory /var/www/html>' \
    '            AllowOverride All' \
    '            Require all granted' \
    '        </Directory>' \
    '        # Internal scripts must never be reachable over HTTP' \
    '        <FilesMatch "\.view\.php$">' \
    '            Require all denied' \
    '        </FilesMatch>' \
    '        <FilesMatch "^(manage|cleanup|config)\.php$">' \
    '            Require all denied' \
    '        </FilesMatch>' \
    '    </VirtualHost>' \
    '</IfModule>' \
    > /etc/apache2/sites-available/default-ssl.conf && \
    a2ensite default-ssl

COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader

COPY . .

# Maintenance scripts belong to root and must not sit in the web root.
# The sidecar container keeps the files directory accessible to the app, so a
# restore that lands root-owned does not stay invisible - see bakdrop-fixperms.
# In the container the app's group is www-data, remapped to PGID above.
ENV BAKDROP_GROUP=www-data
RUN install -m 0755 -o root -g root bakdrop-fixperms /usr/local/sbin/bakdrop-fixperms \
    && install -m 0755 -o root -g root bakdrop-sidecar /usr/local/sbin/bakdrop-sidecar \
    && rm bakdrop-fixperms bakdrop-sidecar

RUN chown -R www-data:www-data /var/www/html