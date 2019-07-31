# Dockerfile
FROM circleci/php:7.0-apache-node-browsers-legacy

USER root
ENV LANG=C.UTF-8

RUN MAGENTO_VERSION=2.2.0

RUN apt-get update && apt-get -y install curl mysql-client libmcrypt-dev mcrypt libpng-dev libjpeg-dev libxml2-dev libxslt-dev
RUN pecl channel-update pecl.php.net
RUN pecl install zip &&  docker-php-ext-enable zip
RUN pecl install xdebug &&  docker-php-ext-enable xdebug
RUN docker-php-ext-configure gd --with-jpeg-dir=/usr/include/
RUN docker-php-ext-install gd
RUN docker-php-ext-install soap
RUN docker-php-ext-install xsl
RUN docker-php-ext-install mcrypt && docker-php-ext-enable mcrypt
RUN docker-php-ext-install bcmath && docker-php-ext-enable bcmath
RUN docker-php-ext-install pdo_mysql && docker-php-ext-enable pdo_mysql

RUN  composer self-update -q

COPY auth.json /home/circleci/.composer/auth.json
USER circleci
WORKDIR /home/circleci
RUN composer create-project --repository-url=https://repo.magento.com/ magento/project-community-edition=2.2.0 magento/
WORKDIR /home/circleci/magento
RUN composer install

RUN composer require "bugsnag/bugsnag:^3.0"

WORKDIR /home/circleci