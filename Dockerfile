
# Use an official PHP image with Apache web server
FROM php:8.2-apache

# Install system dependencies for PHP extensions (like curl) and Composer
RUN apt-get update && apt-get install -y \
    libcurl4-openssl-dev \
    unzip \
    --no-install-recommends \
    && rm -rf /var/lib/apt/lists/*

# Install the PHP curl extension
RUN docker-php-ext-install curl

# Install Composer (PHP's dependency manager) globally
COPY --from=composer:lts /usr/bin/composer /usr/bin/composer

# Set the working directory
WORKDIR /var/www/html

# Copy composer files first to leverage Docker's caching mechanism
COPY composer.json composer.lock* ./

# Install project dependencies using Composer.
# --no-dev: Skips development dependencies
# --optimize-autoloader: Creates a faster autoloader for production
RUN composer install --no-dev --optimize-autoloader

# Copy the rest of the application files (bot.php, anime_spa.html, etc.)
COPY . .

# Apache listens on port 80 by default
EXPOSE 80

