#!/bin/bash
set -euo pipefail

# Update package lists
apt-get update

# Install PHP and common extensions
apt-get install -y php php-cli php-fpm php-common php-mbstring php-xml php-gd php-curl php-mysql php-zip php-bcmath

# Install MySQL server
apt-get install -y mysql-server

# Install Composer (dependency manager for PHP)
EXPECTED_CHECKSUM="$(wget -q -O - https://composer.github.io/installer.sig)"
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
ACTUAL_CHECKSUM="$(php -r "echo hash_file('sha384', 'composer-setup.php');")"
if [ "$EXPECTED_CHECKSUM" != "$ACTUAL_CHECKSUM" ]; then
    >&2 echo 'ERROR: Invalid Composer installer checksum'
    rm composer-setup.php
    exit 1
fi
php composer-setup.php --install-dir=/usr/local/bin --filename=composer
rm composer-setup.php

# Clean up apt caches
apt-get clean
rm -rf /var/lib/apt/lists/*

