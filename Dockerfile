FROM php:8-apache

COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer
RUN apt update -y && apt upgrade -y
RUN apt install git zip -y

ENV APACHE_DOCUMENT_ROOT /var/www/html/src
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# ARG user_id=1000
# RUN usermod -u $user_id www-data

WORKDIR /var/www/html

COPY src/ src
COPY composer.json .
COPY .env.example .env
RUN chown www-data .env

RUN composer install

RUN a2enmod rewrite \
    && a2enmod headers 


# workdir verplaatsen en copy commands herwerken
# multi stage build uitwerken?
# composer install