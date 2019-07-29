# Initialize Magento2 Enviroment
set -x
source config.sh
currentPhpVersion=$(php -v | head -n 1 | cut -d " " -f 2)
if [[ $currentPhpVersion != *"7.0"* &&  $currentPhpVersion != *"7.1"*  &&  $currentPhpVersion != *"7.2"* ]]; then
    brew update
    brew install php@7.2
    echo 'export PATH="/usr/local/opt/php@7.2/bin:$PATH"' >> ~/.bash_profile
    source ~/.bash_profile
fi
rm -rf ../../magento-cloud/
git clone -b $m2Version https://github.com/magento/magento-cloud.git ../../magento-cloud
python auth-format.py $magentoCloudPublicKey $magentoCloudPrivateKey
cp auth.json ../../magento-cloud/
