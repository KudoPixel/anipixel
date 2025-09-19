
# Use an official PHP image with Apache web server
# This is a great lightweight choice for simple PHP apps.
FROM php:8.2-apache

# The PHP script needs the 'curl' extension to talk to the AniList API.
# This command installs and enables it.
RUN docker-php-ext-install curl

# Copy the bot script into the web server's root directory.
# We rename it to index.php so Apache serves it by default.
# This makes deployment on services like Render much easier.
COPY bot.php /var/www/html/index.php

# Apache listens on port 80 by default, so we expose it.
EXPOSE 80
