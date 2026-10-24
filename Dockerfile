FROM php:8.4-apache

ENV TZ=UTC

# System dependencies
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        curl unzip tzdata \
        libonig-dev libxml2-dev \
    && rm -rf /var/lib/apt/lists/*

# PHP extensions
RUN docker-php-ext-install -j"$(nproc)" \
    mysqli pdo_mysql mbstring simplexml

# Production PHP configuration
RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && echo "date.timezone=${TZ}" > "$PHP_INI_DIR/conf.d/timezone.ini"

# Apache: port 8080, document root, .htaccess
RUN a2enmod rewrite \
    && sed -ri 's/Listen 80/Listen 8080/' /etc/apache2/ports.conf \
    && sed -ri 's/:80>/:8080>/' /etc/apache2/sites-available/*.conf \
    && sed -ri 's!/var/www/html!/var/www/html/public!g' \
        /etc/apache2/sites-available/*.conf \
    && printf '%s\n' \
        '<Directory /var/www/html/public>' \
        '    AllowOverride All' \
        '    Require all granted' \
        '</Directory>' \
        > /etc/apache2/conf-available/app.conf \
    && a2enconf app

WORKDIR /var/www/html

# Bust cache when GitHub repository changes
ADD https://api.github.com/repos/gboudreau/dropinambour/commits?per_page=1 latest_commit

# Download application
RUN curl -fsSL -o main.zip \
        https://github.com/gboudreau/dropinambour/archive/refs/heads/main.zip \
    && unzip -q main.zip \
    && rm main.zip \
    && mv dropinambour-main public

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

WORKDIR /var/www/html/public
RUN composer install --no-interaction --prefer-dist --optimize-autoloader

# Preserve example config and replace _config with symlink
RUN cp _config/config.example.php /tmp/config.example.php \
    && rm -rf _config \
    && mkdir -p /config \
    && ln -s /config _config \
    && touch /var/log/dropinambour.log \
    && chown www-data:www-data /var/log/dropinambour.log

# Initialize config at startup
RUN printf '%s\n' \
    '#!/bin/sh' \
    'set -e' \
    'if [ ! -f /config/config.php ]; then' \
    '    cp /tmp/config.example.php /config/config.php' \
    'fi' \
    'exec apache2-foreground' \
    > /usr/local/bin/start-app \
    && chmod +x /usr/local/bin/start-app

VOLUME /config

EXPOSE 8080

CMD ["start-app"]
