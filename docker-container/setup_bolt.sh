#!/usr/bin/env bash

set -e
set -u
set -x

trap '>&2 echo Error: Command \`$BASH_COMMAND\` on line $LINENO failed with exit code $?' ERR


# install bolt plugin
cd ..
mkdir -p magento/app/code/Bolt/Boltpay
cp -r project/. magento/app/code/Bolt/Boltpay/
cd magento
php bin/magento module:enable Bolt_Boltpay

# # set config
php bin/magento config:set payment/boltpay/active 1
php bin/magento config:set payment/boltpay/api_key "643114b6ea382088a1fe81cb7964c87b457d80bc9577b5e8fbe33004b29bdbdf"
php bin/magento config:set payment/boltpay/signing_secret "7f0d05d28446895959d9a605af1cf3de0fc59759e41365568f1e290ca1bf0d07"
php bin/magento config:set payment/boltpay/publishable_key_checkout "_XG56mgHFPE2.yrz9CGZsVPw_.981564c9a3d6d1ad0473feb801faf91b9bda87b207119012e53beb64edcd0cea"

# install and run ngrok

NGROK_URL="https://m2-test.integrations.dev.bolt.me/"

php bin/magento config:set web/unsecure/base_url "${NGROK_URL}"
php bin/magento config:set web/secure/base_url "${NGROK_URL}"
php bin/magento config:set web/unsecure/base_link_url "${NGROK_URL}"
php bin/magento config:set web/secure/base_link_url "${NGROK_URL}"

php -dmemory_limit=5G bin/magento setup:upgrade

php -dmemory_limit=5G bin/magento setup:di:compile

php -dmemory_limit=5G bin/magento indexer:reindex

php -dmemory_limit=5G bin/magento setup:static-content:deploy -f

php bin/magento cache:flush
INC_NUM=$((100*${CIRCLE_BUILD_NUM}))
mysql -uroot -h db -e "USE magento2; ALTER TABLE quote AUTO_INCREMENT=${INC_NUM};"

echo "update apache config"
sudo cp /home/circleci/launch/000-default.conf /etc/apache2/sites-enabled/000-default.conf
sudo cp /home/circleci/launch/apache2.conf /etc/apache2/sites-enabled/apache2.conf


cd ..
sudo chmod -R 777 /home/circleci/magento/
sudo a2enmod rewrite
mkdir log
sudo service apache2 restart
echo "restarted apache2"