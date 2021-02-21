FROM alpine:3.13

# Install packages
RUN apk --no-cache add php8 php8-cli php8-pdo php8-pdo_mysql php8-mysqli php8-json php8-openssl php8-curl \
    php8-xml php8-phar php8-simplexml php8-mbstring php8-session curl unzip tzdata \
    && ln -s /usr/bin/php8 /usr/bin/php

ENV TZ="UTC"

# Setup document root
RUN mkdir -p /var/www/

# Install application
WORKDIR /var/www/
# This ADD will force 'docker build' to skip the cache starting from this line, when the Github repo changes
ADD "https://api.github.com/repos/gboudreau/dropinambour/commits?per_page=1" latest_commit
RUN curl -sLO "https://github.com/gboudreau/dropinambour/archive/main.zip" && unzip main.zip && rm main.zip && mv dropinambour-main html
WORKDIR /var/www/html/
RUN curl -sLo /usr/bin/composer https://getcomposer.org/download/2.0.9/composer.phar && chmod +x /usr/bin/composer \
	&& composer install

# Make the config folder a volume
VOLUME /config

# Expose the port PHP will be reachable on
EXPOSE 8080

# Allow up to 4 parallel requests
ENV PHP_CLI_SERVER_WORKERS=4

CMD sh -c "if [ ! -f /config/config.php ]; then cp /var/www/html/_config/config.example.php /config/config.php; fi" \
	&& rm -f /var/www/html/_config/config.example.php || true \
	&& rmdir /var/www/html/_config || true \
	&& ln -s /config /var/www/html/_config || true \
	&& echo "date.timezone=$TZ" >> /etc/php8/conf.d/zzz_custom.ini \
	&& /usr/bin/php -S 0.0.0.0:8080 /var/www/html/index.php
