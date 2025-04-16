FROM php:7.4-fpm

RUN apt-get update && \
    apt-get install -y sqlite3 libsqlite3-dev && \
    docker-php-ext-install pdo_sqlite

# Указываем volume, куда попадёт база данных
VOLUME ["/var/www/db"]

# Копируем SQL-скрипт
COPY sql/schema.sql /var/www/db/schema.sql

# Инициализация базы данных
RUN echo "Preparing SQLite DB..." && \
    cat /var/www/db/schema.sql | sqlite3 /var/www/db/db.sqlite && \
    chmod 777 /var/www/db/db.sqlite && \
    rm /var/www/db/schema.sql && \
    echo "Database created!"

# Копируем сайт
COPY site /var/www/html
