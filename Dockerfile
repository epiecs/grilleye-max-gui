FROM php:8.0-apache

ENV APACHE_DOCUMENT_ROOT /srv/src

ARG user_id=1000
RUN usermod -u $user_id www-data

RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

RUN a2enmod rewrite \
    && a2enmod headers

WORKDIR /srv