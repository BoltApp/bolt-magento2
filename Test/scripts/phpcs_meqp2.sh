#!/usr/bin/env bash

set -e
set -u
set -x

sudo composer self-update -q
composer show -i

echo "{\"http-basic\":{\"repo.magento.com\":{\"username\":\"${MAGENTO_PUBLIC_KEY}\",\"password\":\"${MAGENTO_PRIVATE_KEY}\"}}}" > $HOME/.composer/auth.json

cd ..
composer create-project --repository=https://repo.magento.com magento/marketplace-eqp
composer create-project magento/magento-coding-standard --stability=dev magento-coding-standard -n

cd magento-coding-standard

vendor/bin/phpcs ../project --standard=../project/phpcs.xml --colors --severity=10 --extensions=php,phtml
