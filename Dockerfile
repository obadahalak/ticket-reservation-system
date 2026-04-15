
FROM php:8.4-fpm


RUN apt-get update && apt-get install -y \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    git \
    curl


RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd


RUN pecl install redis && docker-php-ext-enable redis


COPY www.conf /usr/local/etc/php-fpm.d/www.conf

WORKDIR /var/www