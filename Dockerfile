FROM php:8.2-apache

# Install required system libraries and PHP extensions
RUN apt-get update && apt-get install -y --no-install-recommends \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    zip \
    unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) mysqli pdo pdo_mysql gd zip \
    && a2enmod rewrite \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Suppress Apache FQDN warning
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf

# Enable .htaccess overrides
RUN sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY . /var/www/html/

# Create required directories and set proper permissions
RUN mkdir -p /var/www/html/uploads/materials \
             /var/www/html/uploads/videos \
             /var/www/html/uploads/documents \
             /var/www/html/receipts \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod -R 775 /var/www/html/uploads /var/www/html/receipts

# Set default Render web service port
ENV PORT=10000
EXPOSE 10000

# Dynamically configure Apache to bind to $PORT provided by Render
CMD ["/bin/bash", "-c", "sed -i \"s/Listen [0-9]*/Listen ${PORT:-10000}/g\" /etc/apache2/ports.conf && sed -i \"s/<VirtualHost \\*:[0-9]*>/<VirtualHost \\*:${PORT:-10000}>/g\" /etc/apache2/sites-available/000-default.conf && exec apache2-foreground"]
