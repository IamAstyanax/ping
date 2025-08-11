FROM php:8.2-cli

RUN apt-get update && apt-get install -y iputils-ping unzip git \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY /src/monitor.php /app/monitor.php

RUN mkdir -p /app/public

COPY /src/index.php /app/public/index.php

RUN composer require phpmailer/phpmailer

COPY entrypoint.sh /entrypoint.sh

RUN chmod +x /entrypoint.sh

EXPOSE 8000

CMD ["/entrypoint.sh"]