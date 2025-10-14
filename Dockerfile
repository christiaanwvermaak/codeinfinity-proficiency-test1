FROM php:8.4-apache

RUN apt-get update && apt-get install -y \
    git \
    libssl-dev \
    pkg-config \
    unzip \
    zip \
    zlib1g-dev \
    libpng-dev \
    libzip-dev \
    libxml2-dev \
    libpq-dev \
    curl \
    --no-install-recommends && rm -rf /var/lib/apt/lists/*

RUN pecl install mongodb && docker-php-ext-enable mongodb

RUN docker-php-ext-install pdo pdo_mysql opcache zip gd

RUN a2enmod rewrite

WORKDIR /var/www/html

COPY app /var/www/html

# The base image handles the Apache startup command
# CMD ["apache2-foreground"]
