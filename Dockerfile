FROM serversideup/php:8.2-fpm-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends curl unzip tzdata php8.2-mysqli php8.2-pdo php8.2-pdo-mysql \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/* /usr/share/doc/*

ENV TZ="UTC"

# Without this, mail() specifying a From header errors out with "msmtp: cannot use both --from and --read-envelope-from"
RUN sed -i -e 's/ --read-envelope-from//' /etc/php/8.2/fpm/pool.d/z-fpm-with-overrides.conf

# Setup document root
RUN mkdir -p /var/www/html
WORKDIR /var/www/html

# This ADD will force 'docker build' to skip the cache starting from this line, when the Github repo changes
ADD "https://api.github.com/repos/gboudreau/dropinambour/commits?per_page=1" latest_commit
RUN curl -sLO "https://github.com/gboudreau/dropinambour/archive/main.zip" && unzip main.zip && rm main.zip && mv dropinambour-main public

WORKDIR /var/www/html/public/
RUN curl -sLo /usr/bin/composer https://getcomposer.org/download/2.6.6/composer.phar && chmod +x /usr/bin/composer \
	&& composer install

# Use port 8080
RUN sed -ri -e 's!80!8080!g' /etc/apache2/sites-available/*.conf /etc/apache2/ports.conf

# Use production php.ini
RUN mv /usr/lib/php/8.2/php.ini-production /usr/lib/php/8.2/php.ini

# Make the config folder a volume
VOLUME /config

CMD sh -c "if [ ! -f /config/config.php ]; then cp /var/www/html/public/_config/config.example.php /config/config.php; fi" \
	&& rm -f /var/www/html/public/_config/config.example.php || true \
	&& rmdir /var/www/html/public/_config || true \
	&& ln -s /config /var/www/html/public/_config || true \
	&& echo "date.timezone=$TZ" >> /usr/lib/php/8.2/php.ini \
	&& /init
