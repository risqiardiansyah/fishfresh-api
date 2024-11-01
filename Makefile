DOCKER_USERNAME ?= erwinra
APPLICATION_NAME ?= vogaon-be
SERVER_NAME ?= vogaon-webserver

build:
	docker build --tag ${DOCKER_USERNAME}/${APPLICATION_NAME}:1.0.8 -f Docker/dockerfile/app.Dockerfile .
	docker build --tag ${DOCKER_USERNAME}/${SERVER_NAME}:1.0.3 -f Docker/dockerfile/nginx.Dockerfile .

push:
	docker push ${DOCKER_USERNAME}/${APPLICATION_NAME}:1.0.8
	docker push ${DOCKER_USERNAME}/${SERVER_NAME}:1.0.3

nginx:
	docker build --tag ${DOCKER_USERNAME}/${SERVER_NAME} -f Docker/dockerfile/nginx.Dockerfile .

push-nginx:
	docker push ${DOCKER_USERNAME}/${SERVER_NAME}

done-git:
	git add .
	git commit -m "$(msg)"
	git push origin develop