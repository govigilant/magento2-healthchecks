FROM php:8.2-fpm

ENV COMPOSER_MEMORY_LIMIT=-1 \
    COMPOSER_ALLOW_SUPERUSER=1

WORKDIR /var/www/html

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        curl \
        git \
        unzip \
        rsync \
        libzip-dev \
        libicu-dev \
        libxslt1-dev \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
        libonig-dev \
        libxml2-dev \
        libcurl4-openssl-dev \
        libssl-dev \
        libgmp-dev \
        libsodium-dev \
        default-mysql-client \
        netcat-openbsd \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        gd \
        intl \
        opcache \
        pcntl \
        pdo_mysql \
        soap \
        sockets \
        sodium \
        xsl \
        zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY devenv/setup.sh /usr/local/bin/magento-setup.sh
COPY devenv/entrypoint.sh /usr/local/bin/magento-entrypoint.sh

RUN chmod +x /usr/local/bin/magento-setup.sh /usr/local/bin/magento-entrypoint.sh

EXPOSE 9000

ENTRYPOINT ["/usr/local/bin/magento-entrypoint.sh"]
CMD ["php-fpm"]
