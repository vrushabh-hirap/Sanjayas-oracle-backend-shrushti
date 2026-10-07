FROM php:8.2-cli

# sqlite3 + pdo_sqlite are compiled into the official image by default.

WORKDIR /app

COPY . .

# Render sets $PORT; the PHP built-in server binds to it.
CMD ["sh", "-c", "php -S 0.0.0.0:${PORT:-10000} -t public"]
