#!/usr/bin/env bash

set -e
set -u
set -x

trap '>&2 echo Error: Command \`$BASH_COMMAND\` on line $LINENO failed with exit code $?' ERR

PROJECT_DIR="$(pwd)"

Test/scripts/install_magento.sh

cd ..
mkdir -p magento/app/code/Bolt/Boltpay
# magento requires the code to be in the magento installation dir
# However if we copy codecov gets confused because of multiple sources.
# So a quick fix is to keep a copy of the composer.json
# TODO(roopakv): Initialize circle with the repo in the right place
mv project/* magento/app/code/Bolt/Boltpay/
mkdir -p project
cp magento/app/code/Bolt/Boltpay/composer.json project/composer.json
# PHPUnit 10 (Magento 2.4.8+) dropped the config elements integration_phpunit.xml relies on
if magento/vendor/bin/phpunit --version | grep -qE 'PHPUnit ([1-9][0-9])\.'; then
    cp magento/app/code/Bolt/Boltpay/Test/Unit/integration_phpunit10.xml magento/dev/tests/integration/bolt_phpunit.xml
else
    cp magento/app/code/Bolt/Boltpay/Test/Unit/integration_phpunit.xml magento/dev/tests/integration/bolt_phpunit.xml
fi


echo "Creating DB for integration tests"
mysql -uroot -h 127.0.0.1 -e 'CREATE DATABASE magento_integration_tests;'

cd magento/dev/tests/integration/
cp etc/install-config-mysql.php.dist etc/install-config-mysql.php
if [ -f etc/post-install-setup-command-config.php.dist ]; then
cp etc/post-install-setup-command-config.php.dist etc/post-install-setup-command-config.php
fi
sed -i 's/localhost/127.0.0.1/g' etc/install-config-mysql.php
sed -i 's/123123q//g' etc/install-config-mysql.php
sed -i '/amqp/d' etc/install-config-mysql.php

sudo chmod -R 777 ../../..

echo "Starting Bolt Integration Tests"
if [ "${MAGENTO_VERSION}" == "2.4.2" ]; then
    echo "Restarting Elastic Search..."
    sudo service elasticsearch restart
fi
../../../vendor/bin/phpunit -d memory_limit=5G -c bolt_phpunit.xml --coverage-clover="${PROJECT_DIR}/artifacts/coverage.xml"
cd ../../../..
cp -r magento/app/code/Bolt/Boltpay .
rm -rf magento
mkdir -p magento/app/code/Bolt
mv Boltpay magento/app/code/Bolt
