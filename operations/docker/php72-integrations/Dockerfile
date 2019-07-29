# Dockerfile
FROM circleci/php:7.2-apache-node-browsers-legacy

USER root
ENV LANG=C.UTF-8

RUN MAGENTO_VERSION=2.3.0

RUN apt-get update && apt-get -y install curl mysql-client libmcrypt-dev mcrypt libpng-dev libjpeg-dev libxml2-dev libxslt-dev
RUN pecl channel-update pecl.php.net
RUN pecl install zip &&  docker-php-ext-enable zip
RUN docker-php-ext-enable xdebug
RUN docker-php-ext-configure gd --with-jpeg-dir=/usr/include/
RUN docker-php-ext-install gd
RUN docker-php-ext-install soap
RUN docker-php-ext-install xsl
RUN apt-get install -y libmcrypt-dev
RUN pecl install mcrypt-1.0.2
RUN docker-php-ext-enable mcrypt
RUN docker-php-ext-install bcmath && docker-php-ext-enable bcmath
RUN docker-php-ext-install pdo_mysql && docker-php-ext-enable pdo_mysql
RUN composer self-update -q

COPY auth.json /home/circleci/.composer/auth.json
USER circleci
WORKDIR /home/circleci
RUN composer create-project --repository-url=https://repo.magento.com/ magento/project-community-edition=2.3.0 magento/
WORKDIR /home/circleci/magento
RUN composer install

# RUN php bin/magento setup:install -q \
#     --language="en_US" \
#     --timezone="UTC" \
#     --currency="USD" \
#     --db-host=127.0.0.1 \
#     --db-user=root \
#     --base-url="http://magento2.test/" \
#     --admin-firstname="Dev" \
#     --admin-lastname="Bolt" \
#     --backend-frontname="backend" \
#     --admin-email="admin@example.com" \
#     --admin-user="admin" \
#     --use-rewrites=1 \
#     --admin-use-security-key=0 \
#     --admin-password="123123q"

COPY auth.json /home/circleci/magento/auth.json
RUN composer require "bugsnag/bugsnag:^3.0"
# RUN php bin/magento module:disable Magento_Captcha --clear-static-content
# RUN php bin/magento module:disable MSP_ReCaptcha --clear-static-content
# RUN php bin/magento config:set dev/static/sign 0
# RUN php bin/magento admin:user:create --admin-user=bolt --admin-password=admin123 --admin-email=dev@bolt.com --admin-firstname=admin --admin-lastname=admin
# RUN php -dmemory_limit=5G bin/magento sampledata:deploy
# RUN php -dmemory_limit=5G bin/magento module:enable Magento_CustomerSampleData Magento_MsrpSampleData Magento_CatalogSampleData Magento_DownloadableSampleData Magento_OfflineShippingSampleData Magento_BundleSampleData Magento_ConfigurableSampleData Magento_ThemeSampleData Magento_ProductLinksSampleData Magento_ReviewSampleData Magento_CatalogRuleSampleData Magento_SwatchesSampleData Magento_GroupedProductSampleData Magento_TaxSampleData Magento_CmsSampleData Magento_SalesRuleSampleData Magento_SalesSampleData Magento_WidgetSampleData Magento_WishlistSampleData
# RUN cd ..
# RUN mkdir -p magento/app/code/Bolt/Boltpay
# RUN cp -r project/. magento/app/code/Bolt/Boltpay/
# RUN cd magento
# RUN php bin/magento module:enable Bolt_Boltpay
# COPY 000-default.conf /etc/apache2/sites-enabled/000-default.conf
# COPY apache2.conf /etc/apache2/apache2.conf
# RUN sudo a2enmod rewrite
WORKDIR /home/circleci

