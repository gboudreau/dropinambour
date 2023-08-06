FROM php:8.2-apache

# Install packages
RUN apt-get update && apt-get install -y curl unzip tzdata && rm -rf /var/lib/apt/lists/*

# Add pho_mysql extension
RUN docker-php-ext-install pdo_mysql

ENV TZ="UTC"

# Setup document root
RUN mkdir -p /var/www/

# Install application
WORKDIR /var/www/
RUN rm -rf html

# This ADD will force 'docker build' to skip the cache starting from this line, when the Github repo changes
ADD "https://api.github.com/repos/gboudreau/dropinambour/commits?per_page=1" latest_commit
RUN curl -sLO "https://github.com/gboudreau/dropinambour/archive/main.zip" && unzip main.zip && rm main.zip && mv dropinambour-main html

WORKDIR /var/www/html/
RUN curl -sLo /usr/bin/composer https://getcomposer.org/download/2.4.4/composer.phar && chmod +x /usr/bin/composer \
	&& composer install

# Use port 8080
RUN sed -ri -e 's!80!8080!g' /etc/apache2/sites-available/*.conf /etc/apache2/ports.conf

# Use production php.ini
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

# Make the config folder a volume
VOLUME /config

CMD sh -c "if [ ! -f /config/config.php ]; then cp /var/www/html/_config/config.example.php /config/config.php; fi" \
	&& rm -f /var/www/html/_config/config.example.php || true \
	&& rmdir /var/www/html/_config || true \
	&& ln -s /config /var/www/html/_config || true \
	&& echo "date.timezone=$TZ" >> $PHP_INI_DIR/php.ini \
	&& apache2-foreground
