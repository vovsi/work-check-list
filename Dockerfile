FROM php:8.3-cli-alpine

# pdo_sqlite нужен для работы с БД (storage/app.sqlite)
RUN apk add --no-cache sqlite-dev \
    && docker-php-ext-install pdo_sqlite

WORKDIR /app
COPY . /app

# Каталог для файла БД должен быть доступен для записи веб-процессу
RUN mkdir -p storage && chmod -R 775 storage

EXPOSE 8000

# Встроенный сервер PHP, корень — весь проект (нужно для относительных путей public/ -> ../api/)
CMD ["php", "-S", "0.0.0.0:8000", "-t", "/app"]
