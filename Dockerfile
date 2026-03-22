FROM php:8.2-apache

# Install dependencies and PDO MySQL
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libpq-dev \
    zip \
    unzip \
    && rm -rf /var/lib/apt/lists/* \
    && docker-php-ext-configure gd --with-jpeg \
    && docker-php-ext-install gd pdo pdo_mysql opcache

# Enable Apache mod_rewrite and compression
RUN a2enmod rewrite deflate headers expires

# Configure PHP for performance and uploads
RUN echo "upload_max_filesize = 50M" > /usr/local/etc/php/conf.d/uploads.ini && \
    echo "post_max_size = 50M" >> /usr/local/etc/php/conf.d/uploads.ini && \
    echo "memory_limit = 256M" >> /usr/local/etc/php/conf.d/uploads.ini

# Configure opcache for better performance
RUN echo "opcache.enable = 1" > /usr/local/etc/php/conf.d/opcache.ini && \
    echo "opcache.memory_consumption = 128" >> /usr/local/etc/php/conf.d/opcache.ini && \
    echo "opcache.max_accelerated_files = 10000" >> /usr/local/etc/php/conf.d/opcache.ini && \
    echo "opcache.revalidate_freq = 60" >> /usr/local/etc/php/conf.d/opcache.ini && \
    echo "opcache.fast_shutdown = 1" >> /usr/local/etc/php/conf.d/opcache.ini && \
    echo "opcache.interned_strings_buffer = 16" >> /usr/local/etc/php/conf.d/opcache.ini

# Enable PHP output compression
RUN echo "zlib.output_compression = On" > /usr/local/etc/php/conf.d/compression.ini && \
    echo "zlib.output_compression_level = 6" >> /usr/local/etc/php/conf.d/compression.ini

# Update Apache DocumentRoot if necessary (default is /var/www/html)
# ENV APACHE_DOCUMENT_ROOT /var/www/html
# RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
# RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Copy application files
COPY . /var/www/html/

# Ensure correct permissions
RUN chown -R www-data:www-data /var/www/html/ \
    && chmod -R 755 /var/www/html/

# Expose port (Render sets PORT env)
EXPOSE 80
