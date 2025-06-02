# Dockerfile
FROM cimg/php:8.4.7

USER root
ENV LANG=C.UTF-8
ENV OPENSEARCH_VERSION=2.5.0

RUN cd /usr/local/etc/php/conf.d/ && echo 'memory_limit = -1' >> /usr/local/etc/php/conf.d/docker-php-memlimit.ini

RUN MAGENTO_VERSION=2.4.8

RUN curl -fsSL https://deb.nodesource.com/setup_18.x | bash -

RUN apt-get update && apt-get install -y \
    cron \
    default-mysql-client \
    git \
    gnupg \
    gzip \
    libbz2-dev \
    libfreetype6-dev \
    libicu-dev \
    libmagickwand-dev \
    libmcrypt-dev \
    libonig-dev \
    libpng-dev \
    libsodium-dev \
    libssh2-1-dev \
    libwebp-dev \
    libxslt1-dev \
    libzip-dev \
    lsof \
    mailutils \
    msmtp \
    nodejs \
    procps \
    vim \
    zip \
    curl \
    gnupg \
    lsb-release \
    apt-transport-https \
    ca-certificates \
    openjdk-11-jdk \
    unzip \
  && rm -rf /var/lib/apt/lists/*

RUN pecl channel-update pecl.php.net && pecl install \
    imagick \
    redis \
    ssh2-1.3.1 \
    xdebug \
  && pecl clear-cache \
  && rm -rf /tmp/pear

RUN docker-php-ext-configure \
    gd --with-freetype --with-jpeg --with-webp \
  && docker-php-ext-install \
    bcmath \
    bz2 \
    calendar \
    exif \
    gd \
    gettext \
    intl \
    mbstring \
    mysqli \
    opcache \
    pcntl \
    pdo_mysql \
    soap \
    sockets \
    sodium \
    sysvmsg \
    sysvsem \
    sysvshm \
    xsl \
    zip \
    ftp \
  && docker-php-ext-enable \
    imagick \
    redis \
    ssh2 \
    xdebug

COPY auth.json /home/circleci/.composer/auth.json
USER circleci
WORKDIR /home/circleci
RUN composer -vvv create-project --repository-url=https://repo.magento.com/ magento/project-community-edition=2.4.8 magento/
WORKDIR /home/circleci/magento
RUN composer install
RUN composer -vvv require "bugsnag/bugsnag:^3.0"

USER circleci

WORKDIR /home/circleci
