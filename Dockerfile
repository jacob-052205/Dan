FROM php:8.2-apache

# Install MySQL extensions
RUN docker-php-ext-install pdo pdo_mysql mysqli

# Copy ALL PHP and HTML files to the web server
COPY *.php /var/www/html/
COPY *.html /var/www/html/

# Enable Apache mod_rewrite
RUN a2enmod rewrite

EXPOSE 80