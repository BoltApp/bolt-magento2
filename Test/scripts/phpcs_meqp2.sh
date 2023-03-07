#!/usr/bin/env bash

set -e
set -u
set -x

composer show -i

cd ..

composer create-project magento/magento-coding-standard --stability=dev magento-coding-standard

cd magento-coding-standard

vendor/bin/phpcs ../project --standard=Magento2 --colors --severity=10 -p

cd ../project
export MAGENTO_VERSION="2.3.0"

Test/scripts/install_magento.sh
cd ..


mkdir -p magento/app/code/Bolt/Boltpay
mv project/.circleci/phpstan/* magento/app/code/Bolt/Boltpay/
mv project/* magento/app/code/Bolt/Boltpay/
cd magento/app/code/Bolt/Boltpay/
# rename .mock files extension to .php
for i in *.mock; do mv -- "$i" "${i%.mock}.php"; done
cd magento

# generate classes for phpstan
php -dmemory_limit=5G bin/magento module:enable Bolt_Boltpay
php -dmemory_limit=5G bin/magento setup:di:compile
cd ..
cd magento/app/code/Bolt/Boltpay
wget https://github.com/phpstan/phpstan/releases/download/1.10.3/phpstan.phar
chmod +x phpstan.phar

php -dmemory_limit=5G ./phpstan.phar analyse --level=0 --xdebug
