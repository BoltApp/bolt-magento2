#!/usr/bin/env bash

set -e
set -u
set -x

trap '>&2 echo Error: Command \`$BASH_COMMAND\` on line $LINENO failed with exit code $?' ERR

sudo service mysql start -- --initialize-insecure --skip-grant-tables --skip-networking --protocol=socket

cd Test/scripts/
gzip -d create_test_data.sql.gz
ls 
sudo mysql magento2 < create_test_data.sql
cd /home/circleci/project
cp Test/scripts/CouponCode.php ../$MAGENTO_DIR
cp Test/scripts/FreeShipping.php ../$MAGENTO_DIR

# install bolt plugin
cd ..
mkdir -p magento/app/code/Bolt/Boltpay
cp -r project/. magento/app/code/Bolt/Boltpay/
cd magento
php bin/magento module:enable Bolt_Boltpay

# # set config
php bin/magento config:set payment/boltpay/active 1
php bin/magento config:set payment/boltpay/api_key $BOLT_STAGING_MERCHANT_API_KEY
php bin/magento config:set payment/boltpay/signing_secret $BOLT_STAGING_MERCHANT_SIGNING_SECRET
php bin/magento config:set payment/boltpay/publishable_key_checkout $BOLT_STAGING_MERCHANT_PUBLISHABLE_KEY
php bin/magento config:set payment/boltpay/product_page_checkout 1

# install and run ngrok
wget -O ngrok.zip https://bolt-devops.s3-us-west-2.amazonaws.com/testing/ngrok.zip
unzip ngrok.zip
./ngrok authtoken $NGROK_TOKEN


php bin/magento config:set web/unsecure/base_url "${NGROK_URL}"
php bin/magento config:set web/secure/base_url "${NGROK_URL}"
php bin/magento config:set web/unsecure/base_link_url "${NGROK_URL}"
php bin/magento config:set web/secure/base_link_url "${NGROK_URL}"
php CouponCode.php
php FreeShipping.php

INC_NUM=$((100*${CIRCLE_BUILD_NUM}))
mysql -uroot -h 127.0.0.1 -e "USE magento2; ALTER TABLE quote AUTO_INCREMENT=${INC_NUM};"
mysql -uroot -h 127.0.0.1 -e "USE magento2; UPDATE flag SET flag_data = NULL WHERE flag_code = 'system_config_snapshot';"
mysql -uroot -h 127.0.0.1 -e "USE magento2; UPDATE flag SET flag_data = NULL WHERE flag_code = 'config_hash';"


cd ..
sudo chmod -R 777 magento/
cd magento

php -dmemory_limit=5G bin/magento setup:upgrade
php -dmemory_limit=5G bin/magento setup:di:compile
php bin/magento cache:flush


echo "update apache config"
sudo cp /home/circleci/project/Test/scripts/000-default.conf /etc/apache2/sites-enabled/000-default.conf
sudo cp /home/circleci/project/Test/scripts/apache2.conf /etc/apache2/sites-enabled/apache2.conf


./ngrok http 80 -hostname=$NGROK_HOSTNAME &
cd ..
sudo chmod -R 777 /home/circleci/magento/
sudo a2enmod rewrite
mkdir log
sudo service apache2 restart
echo "restarted apache2"

cd project
git clone --depth 1 --branch m2-rc-tests git@github.com:BoltApp/integration-tests.git
cd integration-tests
mkdir test-results
npm install
npm run build
export JUNIT_REPORT_DIR=./test-results
export SCREENSHOT_DIR=./screenshots
export TEST_SUITE=checkout_magento2_front
export WDIO_CONFIG=localChrome
export TEST_ENV=plugin_ci
export THREAD_COUNT=1 
npm run test-retry-runner