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

sudo service mysql start -- --initialize-insecure --skip-grant-tables --skip-networking --protocol=socket
composer install
sudo mysql -u root -e "USE mysql;UPDATE user SET plugin='mysql_native_password' WHERE User='root';FLUSH PRIVILEGES;"
sudo service mysql restart
mysql -uroot -h localhost -e "GRANT ALL PRIVILEGES ON *.* TO 'root'@'localhost' WITH GRANT OPTION;"
mysql -uroot -h localhost -e "CREATE USER 'root'@'127.0.0.1';"
mysql -uroot -h localhost -e "GRANT ALL PRIVILEGES ON *.* TO 'root'@'127.0.0.1' WITH GRANT OPTION;"
mysql -uroot -h localhost -e "SELECT 1"
while ! mysql -h localhost -uroot -e "SELECT 1" >/dev/null 2>&1; do
    echo "waiting for mysql"
    sleep 1
done

echo "Installing Magento..."
mysql -uroot -h localhost -e 'CREATE DATABASE magento2;'
php bin/magento setup:install \
    --language="en_US" \
    --timezone="UTC" \
    --currency="USD" \
    --db-host=127.0.0.1 \
    --db-user=root \
    --db-name=magento2 \
    --base-url="http://magento2.test/" \
    --admin-firstname="Dev" \
    --admin-lastname="Bolt" \
    --backend-frontname="backend" \
    --admin-email="admin@example.com" \
    --admin-user="admin" \
    --use-rewrites=1 \
    --admin-use-security-key=0 \
    --admin-password="123123q"


php bin/magento module:disable Magento_Captcha --clear-static-content

php bin/magento module:disable MSP_ReCaptcha --clear-static-content

php bin/magento config:set dev/static/sign 0

echo "Create admin user"
php bin/magento admin:user:create --admin-user=bolt --admin-password=admin123 --admin-email=dev@bolt.com --admin-firstname=admin --admin-lastname=admin

cp /home/circleci/.composer/auth.json /home/circleci/magento/auth.json
echo "Installing sample data"
php -dmemory_limit=5G bin/magento sampledata:deploy

php -dmemory_limit=5G bin/magento module:enable Magento_CustomerSampleData Magento_MsrpSampleData Magento_CatalogSampleData Magento_DownloadableSampleData Magento_OfflineShippingSampleData Magento_BundleSampleData Magento_ConfigurableSampleData Magento_ThemeSampleData Magento_ProductLinksSampleData Magento_ReviewSampleData Magento_CatalogRuleSampleData Magento_SwatchesSampleData Magento_GroupedProductSampleData Magento_TaxSampleData Magento_CmsSampleData Magento_SalesRuleSampleData Magento_SalesSampleData Magento_WidgetSampleData Magento_WishlistSampleData

php -dmemory_limit=5G bin/magento setup:upgrade

php -dmemory_limit=5G bin/magento setup:di:compile

php -dmemory_limit=5G bin/magento indexer:reindex

php -dmemory_limit=5G bin/magento setup:static-content:deploy -f

php bin/magento cache:flush
