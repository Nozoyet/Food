# Dockerfile vacío para forzar modo Docker
FROM php:8.3-cli
COPY . /app
WORKDIR /app
CMD ["php", "-S", "0.0.0.0:$PORT", "-t", "public"]