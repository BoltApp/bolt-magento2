cp LoadTestCartDataInterface.php /var/www/html/vendor/boltpay/bolt-magento2/Api/
cp LoadTestCartData.php /var/www/html/vendor/boltpay/bolt-magento2/Model/Api/
php /var/www/html/bin/magento setup:di:compile
php /var/www/html/bin/magento cache:clean
php /var/www/html/bin/magento cache:flush