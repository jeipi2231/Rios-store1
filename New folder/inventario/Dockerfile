FROM php:8.2-apache

# Habilitar módulos de Apache
RUN a2enmod rewrite

# Instalar extensiones necesarias
RUN docker-php-ext-install mysqli pdo pdo_mysql && \
    docker-php-ext-enable pdo_mysql

# Configurar DocumentRoot
ENV APACHE_DOCUMENT_ROOT=/var/www/html
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf && \
    sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Configurar límites de PHP para uploads
RUN echo "upload_max_filesize = 50M" >> /usr/local/etc/php/conf.d/uploads.ini && \
    echo "post_max_size = 50M" >> /usr/local/etc/php/conf.d/uploads.ini && \
    echo "max_input_time = 300" >> /usr/local/etc/php/conf.d/uploads.ini && \
    echo "max_execution_time = 300" >> /usr/local/etc/php/conf.d/uploads.ini

# Crear estructura de carpetas necesarias
RUN mkdir -p /var/www/html/assets/uploads/productos && \
    mkdir -p /var/www/html/logs

# Asignar permisos correctos
RUN chown -R www-data:www-data /var/www/html && \
    chmod -R 775 /var/www/html && \
    chmod -R 777 /var/www/html/assets/uploads && \
    chmod -R 777 /var/www/html/logs

WORKDIR /var/www/html

EXPOSE 80
