#!/usr/bin/env bash

set -e
set -u
set -x
cd launch
trap '>&2 echo Error: Command \`$BASH_COMMAND\` on line $LINENO failed with exit code $?' ERR

source config.sh

# Initialize MySQL server
sudo chown -R mysql:mysql /var/lib/mysql /var/run/mysqld
sudo service mysql start -- --initialize-insecure --skip-grant-tables --skip-networking --protocol=socket

# Install Bolt plugin
cd ..
mkdir -p magento/app/code/Bolt/Boltpay
cp -r project/. magento/app/code/Bolt/Boltpay/
cd magento
php bin/magento module:enable Bolt_Boltpay

# Set Bolt config
php bin/magento config:set payment/boltpay/active 1
php bin/magento config:set payment/boltpay/api_key $boltApiKey
php bin/magento config:set payment/boltpay/signing_secret $boltSigningSecret
php bin/magento config:set payment/boltpay/publishable_key_checkout $boltPublishableKey

# Sets ngrok url to be the stores url
php bin/magento config:set web/unsecure/base_url $ngrokUrlHTTP
php bin/magento config:set web/secure/base_url $ngrokUrlHTTPS
php bin/magento config:set web/unsecure/base_link_url $ngrokUrlHTTP
php bin/magento config:set web/secure/base_link_url $ngrokUrlHTTPS

# Initializes the Magento 2 store to accept all changes
php -dmemory_limit=5G bin/magento setup:upgrade
php -dmemory_limit=5G bin/magento setup:di:compile
php -dmemory_limit=5G bin/magento indexer:reindex
php -dmemory_limit=5G bin/magento setup:static-content:deploy -f
php bin/magento cache:flush

# Logic to avoid repeat quote ID's for test stores
incNum=$(date +'%s')
mysql -uroot -e "USE magento2; ALTER TABLE quote AUTO_INCREMENT=${incNum};"

# Set up apache server on the docker container
echo "update apache config"
sudo cp /home/circleci/launch/000-default.conf /etc/apache2/sites-enabled/000-default.conf
sudo cp /home/circleci/launch/apache2.conf /etc/apache2/sites-enabled/apache2.conf
cd ..
sudo chmod -R 777 /home/circleci/magento/
sudo a2enmod rewrite
mkdir log
sudo service apache2 restart