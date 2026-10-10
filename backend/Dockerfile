FROM php:8.3-cli

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git curl libpng-dev libonig-dev libxml2-dev \
    zip unzip libzip-dev default-mysql-client \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copy application
COPY . .

# 🔑 Delete ALL bootstrap cache BEFORE composer
RUN rm -rf bootstrap/cache/* \
    && mkdir -p bootstrap/cache \
    && echo '*' > bootstrap/cache/.gitignore \
    && echo '!.gitignore' >> bootstrap/cache/.gitignore

# 🔑 Delete existing .env if copied from local
RUN rm -f .env

# Install dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

# 🔑 Delete ALL cache AGAIN after composer
RUN rm -rf bootstrap/cache/* \
    && mkdir -p bootstrap/cache \
    && echo '*' > bootstrap/cache/.gitignore \
    && echo '!.gitignore' >> bootstrap/cache/.gitignore

# 🔑 Delete .env AGAIN if created by any process
RUN rm -f .env

# 🔑 FULL WRITE PERMISSIONS
RUN chmod -R 777 bootstrap/cache storage

EXPOSE 8000

# 🔑 Startup: NO config:cache, NO .env file — env vars read fresh
CMD php artisan config:clear \
    && php artisan route:clear \
    && php artisan view:clear \
    && php artisan cache:clear \
    && rm -f bootstrap/cache/*.php \
    && rm -f .env \
    && php artisan serve --host=0.0.0.0 --port=${PORT:-8000}