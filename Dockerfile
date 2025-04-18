FROM php:8.2-apache

# Habilitar mod_rewrite para que funcione .htaccess
RUN a2enmod rewrite

# Copiar el contenido de public al document root
COPY public/ /var/www/html/

# Copiar tu archivo de lógica si lo necesitas fuera de public
COPY swit_users_app_api.php /var/www/swit_users_app_api.php

# Asegurar que se pueda acceder
RUN chown -R www-data:www-data /var/www && chmod -R 755 /var/www
