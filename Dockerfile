# Gunakan image PHP dengan server bawaan
FROM php:8.1-cli

# Set working directory ke dalam folder cmnotes
WORKDIR /var/www/html/cmnotes

# Copy semua file proyek ke dalam container
COPY . /var/www/html

# Jalankan PHP server
CMD php -S 0.0.0.0:10000 -t /var/www/html/cmnotes
