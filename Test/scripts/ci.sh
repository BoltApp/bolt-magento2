#!/usr/bin/env bash

set -e
set -u
set -x

trap '>&2 echo Error: Command \`$BASH_COMMAND\` on line $LINENO failed with exit code $?' ERR

Test/scripts/install_magento.sh

# install bolt plugin
# cd ..
# mkdir -p magento/app/code/Bolt/Boltpay
# cp -r project/. magento/app/code/Bolt/Boltpay/
cd ../magento
# php bin/magento module:enable Bolt_Boltpay

# # set config
# php bin/magento config:set payment/boltpay/active 1
# php bin/magento config:set payment/boltpay/api_key $BOLT_STAGING_MERCHANT_API_KEY
# php bin/magento config:set payment/boltpay/signing_secret $BOLT_STAGING_MERCHANT_SIGNING_SECRET
# php bin/magento config:set payment/boltpay/publishable_key_checkout $BOLT_STAGING_MERCHANT_PUBLISHABLE_KEY

# install and run ngrok
wget -O ngrok.zip https://bin.equinox.io/c/4VmDzA7iaHb/ngrok-stable-linux-amd64.zip
unzip ngrok.zip
./ngrok authtoken $NGROK_TOKEN
./ngrok http 80 -hostname=${CIRCLE_BUILD_NUM}.integrations.dev.bolt.me &
sleep 10
curl http://127.0.0.1:4040/api/tunnels
NGROK_URL=$(curl http://127.0.0.1:4040/api/tunnels | grep  -oE '"public_url":"http://([^"]+)' | cut -c15-)/

# TODO use proper store URL
# php bin/magento config:set web/unsecure/base_url "${NGROK_URL}"
# php bin/magento config:set web/secure/base_url "https://ethan-m2.dev.bolt.me/"
# php bin/magento config:set web/unsecure/base_link_url "${NGROK_URL}"
# php bin/magento config:set web/secure/base_link_url "https://ethan-m2.dev.bolt.me/"


php -dmemory_limit=5G bin/magento setup:upgrade

php -dmemory_limit=5G bin/magento setup:di:compile

php -dmemory_limit=5G bin/magento indexer:reindex

php -dmemory_limit=5G bin/magento setup:static-content:deploy -f

php bin/magento cache:flush
# tweak apache config
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
        DirectoryIndex index.php
        AllowOverride None
        Require all granted
</Directory>" >> /etc/apache2/apache2.conf'

cd ..
mkdir log
sudo service apache2 restart
echo "restarted apache2"
wget ${CIRCLE_BUILD_NUM}.integrations.dev.bolt.me
#sudo APACHE_PID_FILE=apache.pid APACHE_RUN_USER=circleci APACHE_RUN_GROUP=circleci APACHE_LOG_DIR=~/log APACHE_RUN_DIR=~/magento apache2 -k start

# curl $NGROK_URL
# curl $NGROK_URL -o ~/project/artifacts/magento-index.html
# git clone -b add_ci_config git@github.com:BoltApp/integration-tests.git
# cd integration-tests
# npm install
# TEST_ENV=ci WDIO_CONFIG=localChrome npm run test-spec bolt/integration-tests/checkout/specs/magento2/magento2QuickCheckout.spec.ts

