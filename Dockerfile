# Gunakan image PHP dengan Apache
FROM php:8.1-apache

# Install ekstensi MySQL untuk koneksi ke database
RUN docker-php-ext-install pdo pdo_mysql

# Set working directory ke dalam container
WORKDIR /var/www/html

# Copy semua file proyek ke dalam folder kerja
COPY . /var/www/html

# Beri izin ke folder yang diperlukan
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Expose port 80 untuk akses ke server
EXPOSE 80

# Jalankan Apache
CMD ["apache2-foreground"]
