# Dockerfile
FROM circleci/php:7.4-apache-node-browsers-legacy

USER root
ENV LANG=C.UTF-8

RUN cd /usr/local/etc/php/conf.d/ && echo 'memory_limit = -1' >> /usr/local/etc/php/conf.d/docker-php-memlimit.ini

RUN MAGENTO_VERSION=2.4.2

RUN sudo apt install openjdk-11-jdk -y
RUN curl -fsSL https://artifacts.elastic.co/GPG-KEY-elasticsearch | sudo apt-key add -
RUN sudo echo "deb https://artifacts.elastic.co/packages/7.x/apt stable main" | sudo tee -a /etc/apt/sources.list.d/elastic-7.x.list
RUN sudo apt update
RUN sudo apt install elasticsearch -y
RUN sudo sed -i '/network.host/c\network.host: localhost' /etc/elasticsearch/elasticsearch.yml

RUN apt-get update && apt-get -y install curl default-mysql-client libmcrypt-dev mcrypt libpng-dev libjpeg-dev libxml2-dev libxslt-dev
RUN pecl channel-update pecl.php.net
RUN pecl install zip && docker-php-ext-enable zip
RUN docker-php-ext-enable xdebug
RUN docker-php-ext-configure gd --with-jpeg=/usr/include/
RUN docker-php-ext-install gd
RUN docker-php-ext-install soap
RUN docker-php-ext-install xsl
RUN docker-php-ext-install sockets
RUN apt-get install -y libmcrypt-dev
RUN pecl install mcrypt-1.0.4
RUN docker-php-ext-enable mcrypt
RUN docker-php-ext-install bcmath && docker-php-ext-enable bcmath
RUN docker-php-ext-install pdo_mysql && docker-php-ext-enable pdo_mysql
RUN composer self-update --1

COPY auth.json /home/circleci/.composer/auth.json
USER circleci
WORKDIR /home/circleci
RUN composer -vvv create-project --repository-url=https://repo.magento.com/ magento/project-community-edition=2.4.2 magento/
WORKDIR /home/circleci/magento
RUN composer install
RUN composer -vvv require "bugsnag/bugsnag:^3.0"

WORKDIR /home/circleci