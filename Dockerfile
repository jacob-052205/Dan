FROM php:8.2-apache

# Install MySQL extensions
RUN docker-php-ext-install pdo pdo_mysql mysqli

# Copy application files
COPY index.php /var/www/html/
COPY db-connect.php /var/www/html/

# Enable Apache mod_rewrite
RUN a2enmod rewrite

EXPOSE 80