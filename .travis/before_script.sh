#!/usr/bin/env bash

# Copyright © Magento, Inc. All rights reserved.
# See COPYING.txt for license details.

set -e
trap '>&2 echo Error: Command \`$BASH_COMMAND\` on line $LINENO failed with exit code $?' ERR

# prepare for test suite

echo "Installing Magento"
mysql -uroot -e 'CREATE DATABASE magento2_travis;'
php bin/magento setup:install -q \
    --language="en_US" \
    --timezone="UTC" \
    --currency="USD" \
    --base-url="http://travis.magento.local/" \
    --admin-firstname="John" \
    --admin-lastname="Doe" \
    --backend-frontname="backend" \
    --admin-email="admin@example.com" \
    --admin-user="admin" \
    --use-rewrites=1 \
    --admin-use-security-key=0 \
    --admin-password="123123q"

echo "Enabling production mode"
php bin/magento deploy:mode:set production

cp app/code/Bolt/Boltpay/Test/Unit/phpunit.xml dev/tests/unit/bolt_phpunit.xml
