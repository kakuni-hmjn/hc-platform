FROM php:8.3-apache

RUN apt-get update \
    && apt-get install -y \
        libpq-dev \
        libonig-dev \
        git \
        unzip \
        curl \
        netcat-openbsd \
    && docker-php-ext-install \
        pdo \
        pdo_pgsql \
        pgsql \
        mbstring \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

COPY apache.conf /etc/apache2/sites-available/000-default.conf

WORKDIR /var/www/html