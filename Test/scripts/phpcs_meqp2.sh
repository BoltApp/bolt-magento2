#!/usr/bin/env bash

set -e
set -u
set -x

#sudo composer self-update -q
composer show -i
pwd

#echo "{\"http-basic\":{\"repo.magento.com\":{\"username\":\"${MAGENTO_PUBLIC_KEY}\",\"password\":\"${MAGENTO_PRIVATE_KEY}\"}}}" > $HOME/.composer/auth.json

cd ..

composer create-project magento/magento-coding-standard --stability=dev magento-coding-standard

cd magento-coding-standard

vendor/bin/phpcs ../project --standard=Magento2 --colors --severity=10 -p

pwd

cd ../project
export MAGENTO_VERSION="2.3.0"

Test/scripts/install_magento.sh
pwd
cd ../magento
pwd
# generate classes for phpstan
php -dmemory_limit=5G bin/magento module:enable Bolt_Boltpay
php -dmemory_limit=5G bin/magento setup:di:compile
cd ..
pwd
mkdir -p magento/app/code/Bolt/Boltpay
mv project/* magento/app/code/Bolt/Boltpay/
cd magento/app/code/Bolt/Boltpay
pwd
wget https://github.com/phpstan/phpstan/releases/download/1.10.3/phpstan.phar
chmod +x phpstan.phar
cp /home/circleci/project/.circleci/phpstan.neon phpstan.neon
ls
php -dmemory_limit=5G ./phpstan.phar analyse --level=0 --debug