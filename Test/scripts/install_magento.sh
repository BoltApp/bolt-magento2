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

composer show -i
echo "{\"http-basic\":{\"repo.magento.com\":{\"username\":\"${MAGENTO_PUBLIC_KEY}\",\"password\":\"${MAGENTO_PRIVATE_KEY}\"}}}" > $HOME/.composer/auth.json
cd ..

# install and run ngrok
wget -O ngrok.zip https://bin.equinox.io/c/4VmDzA7iaHb/ngrok-stable-linux-amd64.zip
unzip ngrok.zip
./ngrok http 80 &

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

echo "{\"http-basic\":{\"repo.magento.com\":{\"username\":\"${MAGENTO_PUBLIC_KEY}\",\"password\":\"${MAGENTO_PRIVATE_KEY}\"}}}" > auth.json

echo "Installing sample data"
php -dmemory_limit=5G bin/magento sampledata:deploy

echo "Install bugsnag"
composer require "bugsnag/bugsnag:^3.0"

echo "Create admin user"
php bin/magento admin:user:create --admin-user=bolt --admin-password=admin123 --admin-email=dev@bolt.com --admin-firstname=admin --admin-lastname=admin

php bin/magento module:enable Bolt_Boltpay
php bin/magento config:set payment/boltpay/active 1
php bin/magento config:set payment/boltpay/api_key $boltApiKeyTODO
php bin/magento config:set payment/boltpay/signing_secret $boltSigningSecretTODO
php bin/magento config:set payment/boltpay/publishable_key_checkout $boltPublishableKeyTODO

# TODO
$storeURL=http://localhost/
php bin/magento config:set web/unsecure/base_url $storeURL
php bin/magento config:set web/secure/base_url $storeURL
php bin/magento config:set web/unsecure/base_link_url $storeURL
php bin/magento config:set web/secure/base_link_url $storeURL

php bin/magento setup:upgrade

php bin/magento cache:flush

echo "update apache config"
sudo sh -c 'echo "<VirtualHost *:80>
    DocumentRoot /home/circleci/magento
    <Directory /home/circleci/magento>
        order allow,deny
        allow from all
    </Directory>
</VirtualHost>" > /etc/apache2/sites-enabled/000-default.conf'
sudo sh -c 'echo "<Directory /home/circleci/magento/>
        Options Indexes FollowSymLinks
        AllowOverride None
        Require all granted
</Directory>" >> /etc/apache2/apache2.conf'

cd ..
mkdir log
sudo APACHE_PID_FILE=apache.pid APACHE_RUN_USER=circleci APACHE_RUN_GROUP=circleci APACHE_LOG_DIR=~/log APACHE_RUN_DIR=~/magento apache2 -k start

curl localhost
curl localhost -o ~/artifacts/magento-index.html