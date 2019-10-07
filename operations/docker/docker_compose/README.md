# Dependencies

## ngrok

[https://ngrok.com/](https://ngrok.com/)
[https://ngrok.com/pricing](https://ngrok.com/pricing)

In order for your docker container to communicate with the webhook and shipping & tax url set on [merchant.bolt.com](https://merchant.bolt.com/), one must set up an ngrok account to expose your store's docker ports to a public IP address. In order to do this one must have at least the Basic plan with ngrok to reserve a domain for themselves.

Once the initial configuration scripts are ran in your local environment and a public domain is reserved, run the following command

```shell
./ngrok http <STORE_PORT> -subdomain=<NGROK_DOMAIN>
# or
./ngrok http <STORE_PORT> -hostname=<DEV.BOLT.ME_DOMAIN>
```

### **Account Creation**

Refer to the [notion page](https://www.notion.so/boltteam/Docker-Playbook-318c8fb023e0407cbd8c84afab3686c8)

# How to run

- Set the initial variables of config.sh to match your environment and versioning

```shell
#VARIABLES
boltApiKey=""
boltSigningSecret=""
boltPublishableKey=""
ngrokUrlHTTP="https://m2.<NAME>.dev.bolt.me/"
ngrokUrlHTTPS="https://m2.<NAME.dev.bolt.me/"
installSampleData=true
```

- Run `docker-compose up -d` to build your docker environment
- Run **run_docker_scripts.sh** to fully deploy a docker container containing Magento2 with Bolt
- Visit store at your set ngrokUrlHTTP and admin ngrokUrlHTTP/backend
  - Admin credentials are username: **bolt** password: **admin123**

# Trouble Shooting

## Docker File

[Documentation](https://docs.docker.com/engine/reference/builder/)

### Install MySQL

Based off of [MySql Docker Build](https://github.com/docker-library/mysql/blob/master/5.7/Dockerfile)

### Fetch Magento 2

- **Installs dependencies needed for docker image**

  Uses Docker to install all the dependencies needed to host Magento 2's complex php environment. If a library needs to be removed or added look here

- Runs commands to update composer and fetch the Magento 2

  Uses composer to grab the **MAGENTO_VERSION** from Magento's public repo

### Install Magento 2

- **Initializes MySQL environment**

 Starts the MySql server and creates the proper permissions so the database is easily accessible

- Install Magneto 2 with given user and parameters
  
  Given the base files the compose initialized, this script creates a store configured to a default user and your local database

- **Disable modules that are flakey**

  Currently this script disables all Captcha modules that cause the docker container to have flakey behavior

- Create admin user

 Creates a bolt admin user with the credentials specified earlier in the documentation. It is important to have two admin users in order one of the accounts is locked out

- Install Sample Data
- Resets the Magento 2 environment so that all changes occur
  
## Docker Compose

[Documentation](https://docs.docker.com/compose/)

- Image

  Name of the image to be used for the local setup. The current format for the image name is:

 ``` shell
    boltdev/m2-installed-plugin-ci-<PHP_VERSION>:<MAGENTO2_VERSION>-<CONTAINER_VERSION>
    ex: boltdev/m2-installed-plugin-ci-php72:2.3.0-v2
```

- Container Name
  
  Name used to reference the container with `docker` commands. Feel free to change, but make sure to update `run_docker_scripts.sh` with the correct container_name

- Restart

  Set to always so that if any off errors happen the container will restart

- TTY

  Set to `true` so the container remains active after once booted up

- Volumes

  Used to mount files directly from your computer's filesystem to your docker container's file system. Avoid using these files as active directories. The changing of OS's causes a significant increase in FileIO latency.

  `/home/circleci/launch` contains all the scripts needed to start and launc the docker container

  `/home/circleci/project` contains the entire bolt-m2-plugin directory. **To update the plugin used by your store run the following command:**

  ``` shell
  rsync -avh ~/Documents/GitHub/bolt-magento2/ vendor/boltpay/bolt-magento2
  ```

  This helps avoid the increased FileIO mentioned earlier

- Ports

  Links your docker containers port to your localhost port in the given format

  ```text
  <HOST_PORT>:<CONTAINER_PORT>
  ```

## Setup Script

- **Initialize MySQL server**
- Install Bolt plugin
- Set Bolt config (uses config.sh)
- Sets ngrok url to be the stores url (uses config.sh)
- Initializes the Magento 2 store to accept all changes
- Logic to avoid repeat quote ID's for test stores
- **Set up apache server on the docker container**

## Debugging Tips

- Bolded bullet points above are good points to begin debugging
- If container is acting very slow go to Docker>Preferences>Advanced to allocate more resources to the container
- Run `docker-compose down` to clear your docker environment for this project
