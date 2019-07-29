#!/usr/bin/env bash

#/**
#* Bolt magento2 plugin
#*
#* NOTICE OF LICENSE
#*
#* This source file is subject to the Open Software License (OSL 3.0)
#* that is bundled with this package in the file LICENSE.txt.
#* It is also available through the world-wide-web at this URL:
#* http://opensource.org/licenses/osl-3.0.php
#*
#* @category   Bolt
#* @package    Bolt_Boltpay
#* @copyright  Copyright (c) 2018 Bolt Financial, Inc (https://www.bolt.com)
#* @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
#*/

set -e
set -u
set -x

trap '>&2 echo Error: Command \`$BASH_COMMAND\` on line $LINENO failed with exit code $?' ERR

composer show -i
echo "{\"http-basic\":{\"repo.magento.com\":{\"username\":\"${MAGENTO_PUBLIC_KEY}\",\"password\":\"${MAGENTO_PRIVATE_KEY}\"}}}" > $HOME/.composer/auth.json
cd ..

composer create-project --repository-url=https://repo.magento.com/ magento/project-community-edition=${MAGENTO_VERSION} magento/
cd ../magento
composer install
while ! mysql -uroot -h 127.0.0.1 -e "SELECT 1" >/dev/null 2>&1; do
    sleep 1
done
echo "Installing Magento..."
mysql -uroot -h 127.0.0.1 -e 'CREATE DATABASE magento2;'
php bin/magento setup:install -q \
    --language="en_US" \
    --timezone="UTC" \
    --currency="USD" \
    --db-host=127.0.0.1 \
    --db-user=root \
    --base-url="http://magento2.test/" \
    --admin-firstname="Dev" \
    --admin-lastname="Bolt" \
    --backend-frontname="backend" \
    --admin-email="admin@example.com" \
    --admin-user="admin" \
    --use-rewrites=1 \
    --admin-use-security-key=0 \
    --admin-password="123123q"

echo "{\"http-basic\":{\"repo.magento.com\":{\"username\":\"${MAGENTO_PUBLIC_KEY}\",\"password\":\"${MAGENTO_PRIVATE_KEY}\"}}}" > auth.json



echo "Install bugsnag"
composer require "bugsnag/bugsnag:^3.0"

php bin/magento module:disable Magento_Captcha --clear-static-content

if [ "${MAGENTO_VERSION}" -eq 2.3.0 ]; then 
    php bin/magento module:disable MSP_ReCaptcha --clear-static-content
fi


php bin/magento config:set dev/static/sign 0

echo "Create admin user"
php bin/magento admin:user:create --admin-user=bolt --admin-password=admin123 --admin-email=dev@bolt.com --admin-firstname=admin --admin-lastname=admin

echo "Installing sample data"
php -dmemory_limit=5G bin/magento sampledata:deploy

php -dmemory_limit=5G bin/magento module:enable Magento_CustomerSampleData Magento_MsrpSampleData Magento_CatalogSampleData Magento_DownloadableSampleData Magento_OfflineShippingSampleData Magento_BundleSampleData Magento_ConfigurableSampleData Magento_ThemeSampleData Magento_ProductLinksSampleData Magento_ReviewSampleData Magento_CatalogRuleSampleData Magento_SwatchesSampleData Magento_GroupedProductSampleData Magento_TaxSampleData Magento_CmsSampleData Magento_SalesRuleSampleData Magento_SalesSampleData Magento_WidgetSampleData Magento_WishlistSampleData

