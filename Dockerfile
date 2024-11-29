# Use an official PHP image with Apache
FROM php:8.1-apache

# Install dependencies
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    unzip \
    curl \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd \
    && docker-php-ext-install pdo pdo_mysql

# Enable Apache modules
RUN a2enmod rewrite

# Set a default ServerName to avoid warnings
RUN echo "ServerName 127.0.0.1" >> /etc/apache2/apache2.conf

# Set working directory
WORKDIR /var/www/html

# Copiar archivos al contenedor
COPY . /var/www/html/

# Instalar Composer globalmente
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Ejecutar Composer dentro del contenedor
RUN cd /var/www/html && composer install --optimize-autoloader --no-dev

# Instalar Guzzle como dependencia del proyecto
RUN cd /var/www/html && composer require guzzlehttp/guzzle

# Establecer permisos correctos para la aplicación
RUN chown -R www-data:www-data /var/www/html && chmod -R 755 /var/www/html

# Expose port 80
EXPOSE 80
