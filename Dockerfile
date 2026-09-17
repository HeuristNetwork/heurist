# Heurist 7.x development stack
#
# Reproduces the layout created by server_management/code_setup/install_heurist7.sh:
#   /var/www/html/HEURIST/heurist            <- this repository (codebase)
#   /var/www/html/HEURIST/heuristConfigIni.php  <- mounted from docker/heuristConfigIni.php
#   /var/www/html/HEURIST/HEURIST_FILESTORE  <- uploaded files (named volume)
#   /var/www/html/HEURIST/HEURIST_SUPPORT    <- external_h5 / help / vendor bundles
FROM php:8.2-apache

ENV DEBIAN_FRONTEND=noninteractive

# System packages: GD/zip/mbstring build deps, MySQL client (for mysqldump
# modes), bzip2 for the support bundles, git+unzip for composer.
# Note: mbstring requires libonig-dev (oniguruma); json/session/dom/simplexml/xml
# are already compiled into PHP 8.
RUN apt-get update && apt-get install -y --no-install-recommends \
        libfreetype6-dev libjpeg62-turbo-dev libpng-dev libzip-dev libonig-dev \
        bzip2 unzip git default-mysql-client \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" gd mbstring mysqli pdo_mysql zip \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

# PHP settings for a development instance (uploads, memory, errors)
COPY docker/heurist-php.ini /usr/local/etc/php/conf.d/heurist.ini

# Apache vhost: DocumentRoot on the HEURIST parent (switchboard),
# AllowOverride All so the codebase .htaccess rules apply.
COPY docker/apache-heurist.conf /etc/apache2/sites-available/000-default.conf

# Composer (the repo ships no composer.lock, so we resolve at build time)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Codebase at the canonical location
COPY . /var/www/html/HEURIST/heurist

# Bake composer dependencies into the image. At runtime the repo is
# bind-mounted over this directory, so the entrypoint copies this vendor
# tree into the mount if the checked-out repo does not have vendor/ yet.
# NOTE: Heurist pins smarty/smarty ~5.4.1, whose versions all carry known
# security advisories; Composer 2.7+ blocks those by default. We disable the
# block for this local dev build instead of altering upstream constraints.
RUN cd /var/www/html/HEURIST/heurist \
    && composer config policy.advisories.block false \
    && composer update --no-interaction --prefer-dist --no-progress \
    && cp -a vendor /opt/heurist_vendor

# Support bundles (external JS libs + help system) from the Heurist
# distribution server, same as install_heurist.sh does. Best-effort:
# each download may fail without breaking the build.
RUN mkdir -p /var/www/html/HEURIST/HEURIST_SUPPORT \
    && cd /var/www/html/HEURIST/HEURIST_SUPPORT \
    && { curl -fsSL --retry 3 --retry-delay 3 -o ext.tar.bz2 https://heuristref.net/HEURIST/DISTRIBUTION/HEURIST_SUPPORT/external_h5.tar.bz2 \
         && tar -xjf ext.tar.bz2 && rm -f ext.tar.bz2 \
         || echo "WARNING: external_h5 bundle not downloaded"; } \
    && { curl -fsSL --retry 3 --retry-delay 3 -o help.tar.bz2 https://heuristref.net/HEURIST/DISTRIBUTION/HEURIST_SUPPORT/help.tar.bz2 \
         && tar -xjf help.tar.bz2 && rm -f help.tar.bz2 \
         || echo "WARNING: help bundle not downloaded"; }

COPY docker/apache-servername.conf /etc/apache2/conf-available/servername.conf
RUN a2enconf servername

COPY docker/entrypoint.sh /usr/local/bin/heurist-entrypoint.sh
RUN chmod +x /usr/local/bin/heurist-entrypoint.sh

EXPOSE 80
ENTRYPOINT ["heurist-entrypoint.sh"]
CMD ["apache2-foreground"]
