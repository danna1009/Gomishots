# Usa una imagen base de PHP
FROM php:8.1-apache

# Instalar extensiones de PHP necesarias
RUN docker-php-ext-install pdo pdo_mysql

# Copiar archivos del proyecto a la imagen
COPY . /var/www/html/

# Expone el puerto 80 para acceder al servidor web
EXPOSE 80
