set -x
source config.sh

cd ../../magento-cloud

# Creates Admin user for magento
docker-compose run cron php bin/magento admin:user:create --admin-user=bolt --admin-password=admin123 --admin-email=dev@bolt.com --admin-firstname=admin --admin-lastname=admin

# Sets up Bolt on the Magento 2 store
docker-compose run cron php bin/magento config:set payment/boltpay/active 1
docker-compose run cron php bin/magento config:set payment/boltpay/api_key $boltApiKey
docker-compose run cron php bin/magento config:set payment/boltpay/signing_secret $boltSigningSecret
docker-compose run cron php bin/magento config:set payment/boltpay/publishable_key_checkout $boltPublishableKey

# Changes store url to the ngrok address
docker-compose run cron php bin/magento config:set web/unsecure/base_url $ngrokUrlHTTP
docker-compose run cron php bin/magento config:set web/secure/base_url $ngrokUrlHTTPS
docker-compose run cron php bin/magento config:set web/unsecure/base_link_url $ngrokUrlHTTP
docker-compose run cron php bin/magento config:set web/secure/base_link_url $ngrokUrlHTTPS
docker-compose run cron php bin/magento cache:flush