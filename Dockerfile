FROM composer:2.0 as step0

WORKDIR /src/

COPY composer.lock /src/
COPY composer.json /src/

RUN composer install --ignore-platform-reqs --optimize-autoloader \
    --no-plugins --no-scripts --prefer-dist

FROM php:8.0-cli-alpine as final

LABEL maintainer="team@appwrite.io"

RUN docker-php-ext-install pdo_mysql

RUN \
  apk update \
  && apk add --no-cache make automake autoconf gcc g++ git zlib-dev libmemcached-dev \
  && rm -rf /var/cache/apk/*

RUN \
  # Redis Extension
  git clone https://github.com/phpredis/phpredis.git && \
  cd phpredis && \
  git checkout $PHP_REDIS_VERSION && \
  phpize && \
  ./configure && \
  make && make install && \
  cd ..

RUN echo extension=redis.so >> /usr/local/etc/php/conf.d/redis.ini

WORKDIR /code

COPY --from=step0 /src/vendor /code/vendor

# Add Source Code
COPY ./tests /code/tests
COPY ./src /code/src
COPY ./phpunit.xml /code/phpunit.xml

CMD [ "tail", "-f", "/dev/null" ]