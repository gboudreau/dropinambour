FROM alpine:edge

# Install packages
RUN apk --no-cache add php7 php7-pdo php7-pdo_mysql php7-mysqli php7-json php7-openssl php7-curl \
    php7-xml php7-phar php7-simplexml php7-mbstring php7-session curl unzip tzdata

ENV TZ="UTC"

# Configure PHP
RUN echo 'date.timezone="$TZ"' >> /etc/php7/conf.d/zzz_custom.ini

# Setup document root
RUN mkdir -p /var/www/

# Install application
WORKDIR /var/www/
# This ADD will force 'docker build' to skip the cache starting from this line, when the Github repo changes
ADD "https://api.github.com/repos/gboudreau/dropinambour/commits?per_page=1" latest_commit
RUN curl -sLO "https://github.com/gboudreau/dropinambour/archive/main.zip" && unzip main.zip && rm main.zip && mv dropinambour-main html
WORKDIR /var/www/html/
RUN curl -sLo /usr/bin/composer https://getcomposer.org/download/2.0.7/composer.phar && chmod +x /usr/bin/composer \
	&& composer install

# Make the config folder a volume
VOLUME /config

# Expose the port PHP will be reachable on
EXPOSE 8080

CMD sh -c "if [ ! -f /config/config.php ]; then cp /var/www/html/_config/config.example.php /config/config.php; fi" ; rm /var/www/html/_config/config.example.php ; rmdir /var/www/html/_config ; ln -s /config /var/www/html/_config ; /usr/bin/php -S 0.0.0.0:8080 /var/www/html/index.php
