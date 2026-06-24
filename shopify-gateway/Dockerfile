FROM php:8.3-cli

WORKDIR /app

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

COPY composer.json ./
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts

COPY . .

EXPOSE 8081

HEALTHCHECK --interval=30s --timeout=5s --start-period=5s \
    CMD php -r "exit((int)(false === file_get_contents('http://localhost:8081/health')));"

CMD ["php", "-S", "0.0.0.0:8081", "-t", "public"]
