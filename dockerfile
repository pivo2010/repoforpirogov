FROM php:8.3-apache

RUN apt-get update && apt-get install -y \
    libpq-dev \
  && docker-php-ext-install pdo pdo_pgsql pgsql \
  && a2enmod rewrite \
  && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html