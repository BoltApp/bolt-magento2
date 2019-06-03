docker-compose down -v
docker-compose up -d
docker-compose run build cloud-build
docker-compose run deploy cloud-deploy