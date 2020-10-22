#!/bin/sh

if [ $# -le 0 ]; then
    echo "You must specify version number. \nExample: ./create_release.sh 2.14.0"
    exit -1
fi

rm -fr /tmp/bolt_magento_module

mkdir /tmp/bolt_magento_module
cp -r * /tmp/bolt_magento_module/.
find /tmp/bolt_magento_module -name ".DS_Store" -type f -delete
rm /tmp/bolt_magento_module/create_release.sh
rm /tmp/bolt_magento_module/.gitignore
rm /tmp/bolt_magento_module/.eslintignore
rm /tmp/bolt_magento_module/.eslintrc.json
rm /tmp/bolt_magento_module/*.zip
rm -r /tmp/bolt_magento_module/Test
rm -r /tmp/bolt_magento_module/docker-container

current_dir=$(pwd)
cd /tmp/bolt_magento_module
zip -r boltpay_bolt-magento2-$1.zip *
cp boltpay_bolt-magento2-$1.zip $current_dir/.

echo "\n\nZip file boltpay_bolt-magento2-$1.zip created."