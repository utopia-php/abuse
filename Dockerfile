FROM composer:2.0 as step0

WORKDIR /src/

COPY composer.lock /src/
COPY composer.json /src/

RUN composer update --ignore-platform-reqs --optimize-autoloader \
    --no-plugins --no-scripts --prefer-dist

FROM php:8.0-cli-alpine as final

LABEL maintainer="team@appwrite.io"

RUN docker-php-ext-install pdo_mysql

WORKDIR /code

COPY --from=step0 /src/vendor /code/vendor

# Add Source Code
COPY ./tests /code/tests
COPY ./src /code/src
COPY ./phpunit.xml /code/phpunit.xml
COPY ./psalm.xml /code/psalm.xml

CMD [ "tail", "-f", "/dev/null" ]