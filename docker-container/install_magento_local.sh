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
cd launch
trap '>&2 echo Error: Command \`$BASH_COMMAND\` on line $LINENO failed with exit code $?' ERR
source config.sh

cd ../magento
composer install
MAGENTO_VERSION=2.3.0
while ! mysql -uroot -h db -e "SELECT 1" >/dev/null 2>&1; do
    sleep 1
done
echo "Installing Magento..."
mysql -uroot -h db -e 'CREATE DATABASE magento2;'
php bin/magento setup:install -q \
    --language="en_US" \
    --timezone="UTC" \
    --currency="USD" \
    --db-host=db \
    --db-user=root \
    --base-url=$ngrokUrlHTTP \
    --admin-firstname="Dev" \
    --admin-lastname="Bolt" \
    --backend-frontname="backend" \
    --admin-email="admin@example.com" \
    --admin-user="admin" \
    --use-rewrites=1 \
    --admin-use-security-key=0 \
    --admin-password="123123q"


php bin/magento module:disable Magento_Captcha --clear-static-content

if [ "${magentoVersion}" == "2.3.0" ]; then 
    php bin/magento module:disable MSP_ReCaptcha --clear-static-content
fi


php bin/magento config:set dev/static/sign 0

echo "Create admin user"
php bin/magento admin:user:create --admin-user=bolt --admin-password=admin123 --admin-email=dev@bolt.com --admin-firstname=admin --admin-lastname=admin

cp /home/circleci/.composer/auth.json /home/circleci/magento/auth.json

if [ "$installSampleData" = true ] ; then
    echo "Installing sample data"
    php -dmemory_limit=5G bin/magento sampledata:deploy
fi

php -dmemory_limit=5G bin/magento module:enable Magento_CustomerSampleData Magento_MsrpSampleData Magento_CatalogSampleData Magento_DownloadableSampleData Magento_OfflineShippingSampleData Magento_BundleSampleData Magento_ConfigurableSampleData Magento_ThemeSampleData Magento_ProductLinksSampleData Magento_ReviewSampleData Magento_CatalogRuleSampleData Magento_SwatchesSampleData Magento_GroupedProductSampleData Magento_TaxSampleData Magento_CmsSampleData Magento_SalesRuleSampleData Magento_SalesSampleData Magento_WidgetSampleData Magento_WishlistSampleData

