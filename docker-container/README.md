# Official Magento Documentation

[https://devdocs.magento.com/guides/v2.2/cloud/docker/docker-config.html](https://devdocs.magento.com/guides/v2.2/cloud/docker/docker-config.html)

# Dependencies

## ngrok

[https://ngrok.com/](https://ngrok.com/pricing)
[https://ngrok.com/pricing](https://ngrok.com/pricing)

In order for your docker container to communicate with the webhook and shipping & tax url set on [merchant.bolt.com](http://merchant.bolt.com/), one must set up an ngrok account to expose your store's docker ports to a public IP address. In order to do this one must have at least the Basic plan with ngrok to reserve a domain for themselves. 

Once the initial configuration scripts are ran in your local environment and a public domain is reserved, run the following command 

    ./ngrok http <STORE_PORT> -subdomain=<NGROK_DOMAIN>

***Make sure not to include [ngrok.io](http://ngrok.io) in your domain***. For example if your store is [test-store.ngrok.io](http://test-store.ngrok.io), **just enter test-store** for the domain. 

## Launch Script Dependencies

### Python:

Make sure you have a stable version of python in your environment. These scripts were ran with 2.7.15.

Ensure you have the yaml library is installed for your python version. 

    pip install pyyaml

# How to run

- Set the initial variables of config.sh to match your environment and versioning
```
#VARIABLES
m2Version="master"
phpVersion="7.1"
boltBranch="develop"
boltRepo="/Users/ewayda/Documents/GitHub/bolt-magento2"
publicKey= <PUBLIC_KEY>
privateKey= <PRIVATE_KEY>
buildCompose=false
localRepo=true
boltApiKey=#<BOLT_API_KEY>
boltSigningSecret=#<BOLT_SIGNING_SECRET>
boltPublishableKey=#<BOLT_PUBLISHABLE_KEY>
```    

- Run **install.sh** to install the Magento Cloud configuration and repository locally
- Otherwise you can copy the pre-existing **docker-compose.yml** into the magento cloud directory
- For the initial run set **buildCompose** to true
- One can find the public and private key for an M2 store when creating a magento cloud account
- Run **launch-magento.sh** to fully deploy a docker container containing Magento2 with Bolt
- Visit store ([https://magento2.docker/](https://magento2.docker/admin)) and admin ([https://magento2.docker/admin](https://magento2.docker/admin))
    - Admin credentials are username: **bolt** password: **admin123**

# Trouble Shooting

Make sure Homebrew is installed on your Mac [https://brew.sh/](https://brew.sh/)

I have noticed that the deploy script can take a while depending on the time of day. Be patient while running, any feedback to improve the speed would be great. 

# Updating

If you are changing the composer.json one will need to take down and re-deploy the docker containers:

    docker-compose down -v
    docker-compose up -d
    docker-compose run build cloud-build
    docker-compose run deploy cloud-deploy
    docker-compose run cron php bin/magento admin:user:create --admin-user=bolt --admin-password=admin123 --admin-email=dev@bolt.com --admin-firstname=admin --admin-lastname=admin
    docker-compose run cron php bin/magento config:set payment/boltpay/active 1
    docker-compose run cron php bin/magento config:set payment/boltpay/api_key $boltApiKey
    docker-compose run cron php bin/magento config:set payment/boltpay/signing_secret $boltSigningSecret
    docker-compose run cron php bin/magento config:set payment/boltpay/publishable_key_checkout $boltPublishableKey



## M2 - Build

During the [build phase](https://devdocs.magento.com/guides/v2.3/cloud/reference/discover-deploy.html#cloud-deploy-over-phases-build), we perform the following tasks:

- Apply patches distributed to all Magento Commerce Cloud accounts
- Apply patches we provided specifically to you
- Enable modules to build
- Compile code and the dependency injection configuration

## M2 - Deploy

We highly recommend having Magento already installed prior to deployment. During the [deployment phase](https://devdocs.magento.com/guides/v2.3/cloud/reference/discover-deploy.html#cloud-deploy-over-phases-hook), we perform the following tasks:

- Install the Magento application if needed
- If the Magento application is installed, upgrade components
- Clear the cache
- Set the Magento application for production mode

## Quick Magento Commands

### Connecting to CLI container
    docker-compose run cron bash
### Running Magento command
    docker-compose run cron magento-command
    
# Functionality

## Current

- Parameterize versioning for Magento 2, PHP and Bolt
- Single script Deploy of M2 docker container
- Masked complexity of underlying tasks
- Auto populate Bolt Keys

## Next Steps

- Integrate into testing pipline
- Increase speed of deploy
