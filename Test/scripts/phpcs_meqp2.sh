#!/usr/bin/env bash
# Exit immediately on error, treat unset variables as errors, and print commands
set -euo pipefail
set -x

# ================================
# Composer 2 installation & setup
# ================================
# Use /tmp for Composer cache and home (writable in CircleCI containers)
export COMPOSER_CACHE_DIR=/tmp/composer-cache
export COMPOSER_HOME=/tmp/composer-home
export XDG_CONFIG_HOME=/tmp/composer-home
mkdir -p "$COMPOSER_CACHE_DIR" "$COMPOSER_HOME"

# Choose installation directory for Composer binary
if [ -w /usr/local/bin ]; then
  INSTALL_DIR="/usr/local/bin"
else
  INSTALL_DIR="$HOME/bin"
  mkdir -p "$INSTALL_DIR"
  export PATH="$INSTALL_DIR:$PATH"
fi

# Download and install Composer 2
php -r "copy('https://getcomposer.org/installer','composer-setup.php');"
php composer-setup.php --2 --install-dir="$INSTALL_DIR" --filename=composer
rm -f composer-setup.php

# Verify Composer installation
composer --version

cd ..

# ================================
# Magento coding standard checks
# ================================

composer create-project magento/magento-coding-standard magento-coding-standard

cd magento-coding-standard

vendor/bin/phpcs ../project --standard=Magento2 --colors --severity=10 -p

cd ../project
export MAGENTO_VERSION="2.3.0"

Test/scripts/install_magento.sh
cd ..


mkdir -p magento/app/code/Bolt/Boltpay
mv project/.circleci/phpstan/* magento/app/code/Bolt/Boltpay/
mv project/* magento/app/code/Bolt/Boltpay/
cd magento

# generate classes for phpstan
php -dmemory_limit=5G bin/magento module:enable Bolt_Boltpay
php -dmemory_limit=5G bin/magento setup:di:compile
cd ..
cd magento/app/code/Bolt/Boltpay
wget https://github.com/phpstan/phpstan/releases/download/1.10.3/phpstan.phar
chmod +x phpstan.phar

# rename .mock files extension to .php
MOCK_FILES=`find . -type f \( -name '*.mock' \)`
for file in $MOCK_FILES
do
  mv -- "$file" "${file%.mock}.php"
done
php -dmemory_limit=5G ./phpstan.phar analyse --level=0 --xdebug
