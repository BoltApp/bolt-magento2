#!/usr/bin/env bash

#/**
#* Bolt magento2 plugin
#*
#* NOTICE OF LICENSE
#*
#* This source file is subject to the Open Software License (OSL 3.0)
#* that is bundled with this package in the file LICENSE.txt.
#* It is also available through the world-wide-web at this URL:
#* http://opensource.org/licenses/osl-3.0.php
#*
#* @category   Bolt
#* @package    Bolt_Boltpay
#* @copyright  Copyright (c) 2018 Bolt Financial, Inc (https://www.bolt.com)
#* @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
#*/

set -e
set -u
set -x

trap '>&2 echo Error: Command \`$BASH_COMMAND\` on line $LINENO failed with exit code $?' ERR

echo "Installing dependencies..."
sudo apt-get update && sudo apt-get -y install curl mysql-client libmcrypt-dev mcrypt libpng-dev libjpeg-dev libxml2-dev libxslt-dev
sudo pecl channel-update pecl.php.net
sudo pecl install zip && sudo docker-php-ext-enable zip
sudo pecl install xdebug && sudo docker-php-ext-enable xdebug
sudo docker-php-ext-configure gd --with-jpeg-dir=/usr/include/
sudo docker-php-ext-install gd
sudo docker-php-ext-install soap
sudo docker-php-ext-install xsl
sudo docker-php-ext-install mcrypt && sudo docker-php-ext-enable mcrypt
sudo docker-php-ext-install bcmath && sudo docker-php-ext-enable bcmath
sudo docker-php-ext-install pdo_mysql && sudo docker-php-ext-enable pdo_mysql

composer self-update -q
composer show -i
echo "{\"http-basic\":{\"repo.magento.com\":{\"username\":\"${MAGENTO_PUBLIC_KEY}\",\"password\":\"${MAGENTO_PRIVATE_KEY}\"}}}" > $HOME/.composer/auth.json
cd ..
composer create-project --repository-url=https://repo.magento.com/ magento/project-community-edition=${MAGENTO_VERSION} magento/
cd magento
composer install

echo "Installing Magento..."
mysql -uroot -h 127.0.0.1 -e 'CREATE DATABASE magento2;'
php bin/magento setup:install -q \
    --language="en_US" \
    --timezone="UTC" \
    --currency="USD" \
    --db-host=127.0.0.1 \
    --db-user=root \
    --base-url="http://magento2.test/" \
    --admin-firstname="Dev" \
    --admin-lastname="Bolt" \
    --backend-frontname="backend" \
    --admin-email="admin@example.com" \
    --admin-user="admin" \
    --use-rewrites=1 \
    --admin-use-security-key=0 \
    --admin-password="123123q"
