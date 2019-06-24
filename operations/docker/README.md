# Docker Images for CI

In order to speed up our build pipeline, we pre-build the images we need with all the stuff pre-installed. The docker files for the images we use are in this directory.

## Updating an Image

If you want add a dependency  to one of these images you need update the image by following the steps below

- Update the docker file corresponding to the image you are interested in. Let us assume php70 in this case
- `cd operations/docker/php70`
- `docker build -f Dockerfile . --tag bolt boltdev/m2-plugin-ci-php70:v2` 
   Note: Make sure you update the version number to be one higher than what is currently be used in the circle yml
- `docker push boltdev/m2-plugin-ci-php70:v2`
  Note: you need to be logged into docker with a user who has push rights to the dockerhub repo.
- Update circle config yml to use the new image.
