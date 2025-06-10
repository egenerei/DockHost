#!/bin/sh
mkdir /clients /db
touch /db/clients.sqlite
chown -R www-data:www-data /usr/share/nginx/html /clients /db /db/clients.sqlite
chmod -R 755 /usr/share/nginx/html /clients /db
php-fpm