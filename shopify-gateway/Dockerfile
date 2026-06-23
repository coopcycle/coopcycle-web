FROM php:8.2-cli

WORKDIR /app

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

COPY composer.json ./
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts

COPY . .

EXPOSE 8080

HEALTHCHECK --interval=30s --timeout=5s --start-period=5s \
    CMD php -r "exit((int)(false === file_get_contents('http://localhost:8080/health')));"

CMD ["php", "-S", "0.0.0.0:8080", "-t", "public"]
