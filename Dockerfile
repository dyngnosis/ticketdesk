FROM php:8.2-cli
RUN apt-get update && apt-get install -y zip sqlite3 libsqlite3-dev \
    && docker-php-ext-install pdo pdo_sqlite \
    && rm -rf /var/lib/apt/lists/*
RUN mkdir -p /var/uploads /var/data
WORKDIR /app
COPY . .
EXPOSE 3002
CMD ["php", "-S", "0.0.0.0:3002", "-t", "/app", "index.php"]
