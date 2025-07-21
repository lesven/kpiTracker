FROM php:8.2-fpm

# Notwendige Pakete und Extensions installieren
RUN apt-get update && apt-get install -y \
    unzip \
    git \
    curl \
    && docker-php-ext-install pdo_mysql \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Node.js installieren
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs

# Composer installieren
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Arbeitsverzeichnis setzen
WORKDIR /var/www/html

# Verzeichnisse erstellen
RUN mkdir -p /var/www/html/var/cache /var/www/html/var/log /var/www/html/var/sessions \
    && chmod -R 777 /var/www/html

# Port freigeben
EXPOSE 9000

# PHP-FPM starten
CMD ["php-fpm"]
CMD ["php-fpm"]
