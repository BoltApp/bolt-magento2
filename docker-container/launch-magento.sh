# Create Docker Envrioment
set -x
source config.sh
rm -rf ../../magento-cloud/bolt-magento2
cp -R $boltRepo  ../../magento-cloud/
if [ "$localRepo" = true ] ; then
    python compose-format.py  "*@dev" "./bolt-magento2" "path"
else
    python compose-format.py $boltBranch "./bolt-magento2" "vcs"
fi
cd ../../magento-cloud
composer install
if [ "$buildCompose" = true ] ; then
    yes | ./vendor/bin/ece-tools docker:build
    python bolt-magento2/docker-container/docker-format.py $phpVersion
fi
if [ "$installSampleData" = true ] ; then
    php -dmemory_limit=5G bin/magento sampledata:deploy
fi
cp docker/config.php.dist docker/config.php
cp docker/global.php.dist docker/global.php
./vendor/bin/ece-tools docker:config:convert
docker-compose down -v 
docker-compose up -d
docker-compose run build cloud-build
docker-compose run deploy cloud-deploy