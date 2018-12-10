#!/usr/bin/env bash

set -e
set -u
set -x

trap '>&2 echo Error: Command \`$BASH_COMMAND\` on line $LINENO failed with exit code $?' ERR

Test/scripts/install_magento.sh

cd ..
mkdir -p magento/app/code/Bolt/Boltpay
cp -r project/. magento/app/code/Bolt/Boltpay/
cp magento/app/code/Bolt/Boltpay/Test/Unit/phpunit.xml magento/dev/tests/unit/bolt_phpunit.xml
echo "Starting Bolt Unit Tests"
php magento/vendor/phpunit/phpunit/phpunit  --testdox --verbose -c magento/dev/tests/unit/bolt_phpunit.xml
