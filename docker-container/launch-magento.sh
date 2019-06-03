# Create Docker Envrioment
set -x
source config.sh
rm -rf magento-cloud/bolt-magento2
cp -R $boltRepo magento-cloud/
if [ "$localRepo" = true ] ; then
    python compose-format.py  "*@dev" "./bolt-magento2" "path"
else
    python compose-format.py $boltBranch "./bolt-magento2" "vcs"
fi
cd magento-cloud
composer install
if [ "$buildCompose" = true ] ; then
    ./vendor/bin/ece-tools docker:build
    python ../docker-format.py $phpVersion
fi
php -dmemory_limit=5G bin/magento sampledata:deploy
cp docker/config.php.dist docker/config.php
cp docker/global.php.dist docker/global.php
./vendor/bin/ece-tools docker:config:convert
docker-compose down -v 
docker-compose up -d
docker-compose run build cloud-build
docker-compose run deploy cloud-deploy
docker-compose run cron php bin/magento admin:user:create --admin-user=bolt --admin-password=admin123 --admin-email=dev@bolt.com --admin-firstname=admin --admin-lastname=admin
docker-compose run cron php bin/magento config:set payment/boltpay/active 1
docker-compose run cron php bin/magento config:set payment/boltpay/api_key $boltApiKey
docker-compose run cron php bin/magento config:set payment/boltpay/signing_secret $boltSigningSecret
docker-compose run cron php bin/magento config:set payment/boltpay/publishable_key_checkout $boltPublishableKey

