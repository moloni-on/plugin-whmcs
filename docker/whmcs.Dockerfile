# PHP/Apache runtime for running WHMCS locally (used by the "whmcs" compose profile).
#
# This image provides only the runtime. WHMCS itself is proprietary — download
# your licensed release into ./whmcs (mounted at /var/www/html).
#
# NOTE: WHMCS also requires the ionCube Loader. It is architecture- and
# PHP-version-specific, so it is left as an explicit opt-in step below rather
# than baked in blindly. Match the loader to this image's PHP version (8.1).
FROM php:8.1-apache

RUN apt-get update && apt-get install -y --no-install-recommends \
        libpng-dev libjpeg-dev libfreetype6-dev libzip-dev libicu-dev \
        libxml2-dev libonig-dev unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        mysqli pdo_mysql gd zip intl bcmath soap opcache \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

# --- ionCube Loader (required by WHMCS) ---------------------------------
# Uncomment and adjust for your platform, then rebuild:
#
# RUN curl -fsSL https://downloads.ioncube.com/loader_downloads/ioncube_loaders_lin_x86-64.tar.gz \
#       -o /tmp/ioncube.tar.gz \
#     && tar -xzf /tmp/ioncube.tar.gz -C /tmp \
#     && cp /tmp/ioncube/ioncube_loader_lin_8.1.so "$(php -r 'echo ini_get("extension_dir");')/" \
#     && printf 'zend_extension=ioncube_loader_lin_8.1.so\n' > /usr/local/etc/php/conf.d/00-ioncube.ini \
#     && rm -rf /tmp/ioncube*
#
# See https://docs.whmcs.com/System_Requirements for supported PHP + loader versions.

WORKDIR /var/www/html
