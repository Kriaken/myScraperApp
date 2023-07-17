FROM php:8.2-fpm

RUN apt-get update && apt-get install -y zlib1g-dev g++ git libzip-dev zip
RUN docker-php-ext-install mysqli pdo pdo_mysql
RUN pecl install redis \
    && docker-php-ext-enable redis

RUN apt-get update && apt-get install -y gcc make librabbitmq-dev


RUN pecl install amqp \
    && docker-php-ext-enable amqp

WORKDIR /var/www/project

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

RUN curl -sS https://get.symfony.com/cli/installer | bash

EXPOSE 9000

