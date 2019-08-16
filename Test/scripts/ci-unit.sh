#!/usr/bin/env bash

set -e
set -u
set -x

trap '>&2 echo Error: Command \`$BASH_COMMAND\` on line $LINENO failed with exit code $?' ERR

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
cp magento/app/code/Bolt/Boltpay/Test/Unit/phpunit.xml magento/dev/tests/unit/bolt_phpunit.xml
echo "Starting Bolt Unit Tests"
php magento/vendor/phpunit/phpunit/phpunit --verbose -c magento/dev/tests/unit/bolt_phpunit.xml --coverage-clover=./artifacts/coverage.xml
bash <(curl -s https://bolt-devops.s3-us-west-2.amazonaws.com/testing/codecov_uploader) -f ./artifacts/coverage.xml -F $TEST_ENV
