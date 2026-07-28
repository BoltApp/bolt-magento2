# Docker Images for CI

In order to speed up our build pipeline, we pre-build the images we need with all the stuff pre-installed. The docker files for the images we use are in this directory.

## ⚠️ php71 / php72 / php74 can no longer be rebuilt

Do not spend time trying. Packagist has [dropped its Composer 1 metadata
API](https://blog.packagist.com/deprecating-composer-1-support/), and Magento
2.2.8 / 2.3.0 / 2.4.2 are all Composer-1-era (2.2.8 requires
`composer/composer @alpha`). `--repository-url=repo.magento.com` only supplies
`magento/*`, so `composer create-project` can no longer resolve phpunit,
php_codesniffer or composer itself, on **any** base image. The published images
work only because they predate that change — treat them as immutable artifacts.

Two consequences:

- These images cannot run under a GitHub Actions job `container:` either: they are
  Debian Stretch (glibc 2.24) and GHA injects a Node 24 runtime to run
  `actions/checkout`, which needs GLIBC_2.28. `.github/workflows/ci.yml` therefore
  invokes them with `docker run` from a modern `ubuntu-latest` host instead.
- Adding a dependency to one of these Magento versions means patching it at test
  time in `Test/scripts/*`, not baking it into the image.

`php8.4` (Magento 2.4.8) is Composer-2-era and *can* still be rebuilt, per below.

## Updating an Image

If you want add a dependency  to one of these images you need update the image by following the steps below

- Update the docker file corresponding to the image you are interested in. Let us assume php70 in this case
- `cd operations/docker/php70`
- `docker build -f Dockerfile . --tag bolt boltdev/m2-plugin-ci-php70:v2` 
   Note: Make sure you update the version number to be one higher than what is currently be used in the circle yml
- `docker push boltdev/m2-plugin-ci-php70:v2`
  Note: you need to be logged into docker with a user who has push rights to the dockerhub repo.
- Update circle config yml to use the new image.
