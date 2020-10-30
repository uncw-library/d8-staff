# from https://github.com/docker-library/drupal/blob/master/8.9/apache/Dockerfile
# Starting from php image was necessary for adding ldap php extension

# from https://www.drupal.org/docs/8/system-requirements/drupal-8-php-requirements
FROM php:7.4-apache-buster
# TODO switch to buster once https://github.com/docker-library/php/issues/865 is resolved in a clean way (either in the PHP image or in PHP itself)

# install the PHP extensions we need
RUN set -eux; \
	\
	if command -v a2enmod; then \
		a2enmod rewrite; \
	fi; \
	\
	savedAptMark="$(apt-mark showmanual)"; \
	\
	apt-get update; \
	apt-get install -y --no-install-recommends \
		libfreetype6-dev \
		libjpeg-dev \
		libldap2-dev \
		libpng-dev \
		libpq-dev \
		libzip-dev \
	; \
	\
	docker-php-ext-configure gd \
		--with-freetype \
		--with-jpeg=/usr \
	; \
	\
    docker-php-ext-configure ldap --with-libdir=lib/x86_64-linux-gnu/ \
    ; \
    \
	docker-php-ext-install -j "$(nproc)" \
		gd \
		opcache \
		ldap \
		pdo_mysql \
		pdo_pgsql \
		zip \
	; \
	\
	# reset apt-mark's "manual" list so that "purge --auto-remove" will remove all build dependencies
	apt-mark auto '.*' > /dev/null; \
	apt-mark manual $savedAptMark; \
	ldd "$(php -r 'echo ini_get("extension_dir");')"/*.so \
		| awk '/=>/ { print $3 }' \
		| sort -u \
		| xargs -r dpkg-query -S \
		| cut -d: -f1 \
		| sort -u \
		| xargs -rt apt-mark manual; \
	\
	apt-get purge -y --auto-remove -o APT::AutoRemove::RecommendsImportant=false; \
	rm -rf /var/lib/apt/lists/*

# set recommended PHP.ini settings
# see https://secure.php.net/manual/en/opcache.installation.php
RUN { \
		echo 'opcache.memory_consumption=128'; \
		echo 'opcache.interned_strings_buffer=8'; \
		echo 'opcache.max_accelerated_files=4000'; \
		echo 'opcache.revalidate_freq=60'; \
		echo 'opcache.fast_shutdown=1'; \
	} > /usr/local/etc/php/conf.d/opcache-recommended.ini

# installing extra programs we need, including composer & drush-launcher

RUN apt update \
    && apt install wget git curl nano tree unzip default-mysql-client -y

RUN cd /tmp \
    && php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" \
    && php composer-setup.php --version=1.10.16 \
    && mv composer.phar /usr/bin/composer \
    && php -r "unlink('composer-setup.php');"

RUN wget -O drush.phar https://github.com/drush-ops/drush-launcher/releases/download/0.6.0/drush.phar \
    && chmod +x drush.phar \
    && mv drush.phar /usr/bin/drush

# creating a bare drupal8 project, then using composer to install drupal8 + modules

WORKDIR /var/www/html
RUN composer create-project drupal/recommended-project:8.9.7 /var/www/html --stability dev --no-interaction

RUN php -d memory_limit=-1 /usr/bin/composer update 

RUN cp web/sites/default/default.settings.php web/sites/default/settings.php \
	&& cp web/sites/default/default.services.yml web/sites/default/services.yml \
	&& chown www-data:www-data web/sites/default/settings.php \
	&& chown www-data:www-data web/sites/default/services.yml

RUN php -d memory_limit=-1 /usr/bin/composer require \
        "cweagans/composer-patches:>=1" \
        "drupal/backup_migrate:^4.1" \
        "drupal/bootstrap_barrio:>=4" \
        "drupal/ctools:^3.4" \
        "drupal/imce:^2.3" \
        "drupal/ldap:^3.0@beta" \
        "drupal/menu_block:^1.6" \
        "drupal/pathauto:^1.8" \
        "drupal/search_api:^1.18" \
        "drupal/smtp:^1.0" \
        "drupal/token:^1.6" \
        "drupal/webform:^5.22" \
        "drush/drush:>=9"

# injecting our additions to settings.php

COPY ./config/drupal/settings.php /tmp/drupal_settings.php
RUN cat /tmp/drupal_settings.php >> /var/www/html/web/sites/default/settings.php

# creating a writable folder outside of webroot for private files

COPY --chown=www-data:www-data ./config/drupal/private_htaccess /var/www/html/private/.htaccess

# injecting our apache conf, custom php.ini, drupal sync, drupal custom subtheme, drupal custom modules 

COPY --chown=www-data:www-data ./config/apache/000-default.conf /etc/apache2/sites-enabled/000-default.conf
COPY --chown=www-data:www-data ./config/drupal/php.ini /usr/local/etc/php/php.ini
COPY --chown=www-data:www-data ./sync /drupal_sync
COPY --chown=www-data:www-data ./themes/custom /var/www/html/web/themes/custom
COPY --chown=www-data:www-data ./modules/custom /var/www/html/web/modules/custom

RUN chown -R www-data:www-data /var/www/html/