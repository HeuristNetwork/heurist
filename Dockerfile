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
        bzip2 \
        default-mysql-client \
        git \
        libfreetype6-dev \
        libjpeg62-turbo-dev \
        libonig-dev \
        libpng-dev \
        libzip-dev \
        unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" gd mbstring mysqli pdo_mysql zip \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

# PHP settings for a development instance (uploads, memory, errors)
COPY docker/heurist-php.ini /usr/local/etc/php/conf.d/heurist.ini

# Apache vhost: DocumentRoot on the HEURIST parent (switchboard),
# AllowOverride All so the codebase .htaccess rules apply.
COPY docker/apache-heurist.conf /etc/apache2/sites-available/000-default.conf

# Composer binary used to install the repository's locked dependencies.
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Codebase at the canonical location. Keep this allowlist explicit so local
# credentials and development artefacts can never become part of the image.
WORKDIR /var/www/html/HEURIST/heurist
COPY admin admin
COPY export export
COPY hclient hclient
COPY hserv hserv
COPY import import
COPY movetoparent movetoparent
COPY redirects redirects
COPY scripts scripts
COPY server_management server_management
COPY srv srv
COPY startup startup
COPY viewers viewers
COPY .htaccess LICENSE_gnuGPL3.txt README.md autoload.php composer.json composer.lock configIni.php favicon.ico h4styles.css h6styles.css index.php layout_default.js mbtiles.php ./

# Bake composer dependencies into the image. At runtime the repo is
# bind-mounted over this directory, so the entrypoint copies this vendor
# tree into the mount if the checked-out repo does not have vendor/ yet.
# composer.lock makes the installed dependency set reproducible.
RUN composer install --no-dev --no-interaction --prefer-dist --no-progress \
    && cp -a vendor /opt/heurist_vendor

# Support bundles (external JS libs + help system) from the Heurist
# distribution server, same as install_heurist.sh does. Best-effort:
# each download may fail without breaking the build.
WORKDIR /var/www/html/HEURIST/HEURIST_SUPPORT
RUN { curl -fsSL --proto '=https' --proto-redir '=https' --retry 3 --retry-delay 3 -o ext.tar.bz2 https://heuristref.net/HEURIST/DISTRIBUTION/HEURIST_SUPPORT/external_h5.tar.bz2 \
         && tar -xjf ext.tar.bz2 && rm -f ext.tar.bz2 \
         || echo "WARNING: external_h5 bundle not downloaded"; } \
    && { curl -fsSL --proto '=https' --proto-redir '=https' --retry 3 --retry-delay 3 -o help.tar.bz2 https://heuristref.net/HEURIST/DISTRIBUTION/HEURIST_SUPPORT/help.tar.bz2 \
         && tar -xjf help.tar.bz2 && rm -f help.tar.bz2 \
         || echo "WARNING: help bundle not downloaded"; }

COPY docker/apache-servername.conf /etc/apache2/conf-available/servername.conf
COPY docker/entrypoint.sh /usr/local/bin/heurist-entrypoint.sh
RUN a2enconf servername \
    && sed -i 's/^Listen 80$/Listen 8080/' /etc/apache2/ports.conf \
    && chmod +x /usr/local/bin/heurist-entrypoint.sh \
    && mkdir -p /var/run/apache2 /var/lock/apache2 /var/log/apache2 \
        /var/www/html/HEURIST/HEURIST_FILESTORE \
    && find /var/www/html/HEURIST/heurist/movetoparent -maxdepth 1 -type f ! -name 'heuristConfigIni.php' \
        -exec cp -n {} /var/www/html/HEURIST/ \; \
    && ln -s ../HEURIST_SUPPORT/external_h5 /var/www/html/HEURIST/heurist/external \
    && ln -s ../HEURIST_SUPPORT/help /var/www/html/HEURIST/heurist/help \
    && chown -R www-data:www-data /var/run/apache2 /var/lock/apache2 /var/log/apache2 \
        /var/www/html/HEURIST/HEURIST_FILESTORE /var/www/html/HEURIST/HEURIST_SUPPORT

WORKDIR /var/www/html/HEURIST/heurist
USER www-data
EXPOSE 8080
ENTRYPOINT ["heurist-entrypoint.sh"]
CMD ["apache2-foreground"]
