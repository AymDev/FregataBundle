FROM php:7.4.19-alpine

WORKDIR /var/www/html

COPY --from=composer:2.0.12 /usr/bin/composer /usr/bin/composer
COPY --from=mlocati/php-extension-installer:1.2.24 /usr/bin/install-php-extensions /usr/local/bin/

RUN apk add --no-cache bash && \
    install-php-extensions pdo_pgsql && \
    wget https://get.symfony.com/cli/installer -O - | bash && \
    mv /root/.symfony/bin/symfony /usr/local/bin/symfony

COPY . .

RUN composer install

EXPOSE 8000

ENTRYPOINT ["symfony", "server:start", "--dir=./_implementation", "--no-tls"]
