
# Use an official PHP image with Apache web server
# This is a great lightweight choice for simple PHP apps.
FROM php:8.2-apache

# FIX: First, install the system dependencies required for the PHP extensions.
# The 'curl' extension needs the 'libcurl' development library.
# We update the package list and install it.
RUN apt-get update && apt-get install -y \
    libcurl4-openssl-dev \
    --no-install-recommends \
    && rm -rf /var/lib/apt/lists/*

# Now that the system dependency is in place, we can install the PHP extension.
# This command installs and enables it.
RUN docker-php-ext-install curl

# Copy the bot script into the web server's root directory.
# We rename it to index.php so Apache serves it by default.
# This makes deployment on services like Render much easier.
COPY bot.php /var/www/html/index.php

# Apache listens on port 80 by default, so we expose it.
EXPOSE 80

