FROM devcto/caddy-php:8.0

# Install packages
RUN apk --no-cache add curl unzip tzdata \
    && ln -s /usr/bin/php8 /usr/bin/php

ENV TZ="UTC"

# Setup document root
RUN mkdir -p /var/www/

# Install application
WORKDIR /var/www/
# This ADD will force 'docker build' to skip the cache starting from this line, when the Github repo changes
ADD "https://api.github.com/repos/gboudreau/dropinambour/commits?per_page=1" latest_commit
RUN curl -sLO "https://github.com/gboudreau/dropinambour/archive/main.zip" && unzip main.zip && rm main.zip && mv dropinambour-main html2
WORKDIR /var/www/html2/
RUN curl -sLo /usr/bin/composer https://getcomposer.org/download/2.4.4/composer.phar && chmod +x /usr/bin/composer \
	&& composer install

# Configure PHP-FPM to send error_log() to docker logs
RUN echo '[global]' > /usr/local/etc/php-fpm.d/www.conf.new \
	&& echo 'error_log = /proc/self/fd/2' >> /usr/local/etc/php-fpm.d/www.conf.new \
	&& echo 'log_limit = 8192' >> /usr/local/etc/php-fpm.d/www.conf.new \
	&& cat /usr/local/etc/php-fpm.d/www.conf >> /usr/local/etc/php-fpm.d/www.conf.new \
	&& echo 'php_admin_value[error_log] = /dev/stderr' >> /usr/local/etc/php-fpm.d/www.conf.new \
    && echo 'catch_workers_output = yes' >> /usr/local/etc/php-fpm.d/www.conf.new \
    && echo 'decorate_workers_output = no' >> /usr/local/etc/php-fpm.d/www.conf.new \
	&& mv /usr/local/etc/php-fpm.d/www.conf.new /usr/local/etc/php-fpm.d/www.conf

# Make the config folder a volume
VOLUME /config

CMD sh -c "if [ ! -f /config/config.php ]; then cp /var/www/html2/_config/config.example.php /config/config.php; fi" \
	&& sh -c "if [ ! -f /var/www/html/init.inc.php ]; then sed -e 's/html/html2/' -i /etc/caddy/Caddyfile; fi" \
	&& rm -f /var/www/html2/_config/config.example.php || true \
	&& rmdir /var/www/html2/_config || true \
	&& ln -s /config /var/www/html2/_config || true \
	&& echo "date.timezone=$TZ" >> /usr/local/etc/php/conf.d/zzz_custom.ini \
	&& "/app/entry.sh" -D
