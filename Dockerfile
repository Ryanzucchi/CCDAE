FROM php:8.3-fpm

#Prepara nodejs
RUN curl -sL https://deb.nodesource.com/setup_current.x | bash -
RUN apt-get install -y nodejs

# Instala dependências necessárias
RUN apt-get update \
    && apt-get install -y \
        libldap2-dev \
        libzip-dev \
        zip \
    && rm -rf /var/lib/apt/lists/*

# Instala a extensão zip
RUN docker-php-ext-install zip

# Instala a extensão do postgres
RUN apt-get update && apt-get install -y libpq-dev && \
    docker-php-ext-install pdo_pgsql

# Instala o Composer globalmente
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

RUN apt-get update \
    && apt-get install -y git

# Atualize a lista de pacotes e instale as dependências
RUN apt-get update && apt-get install -y libicu-dev

# Instale a extensão "intl" para o PHP
RUN docker-php-ext-configure intl
RUN docker-php-ext-install intl

# Define a variável de ambiente COMPOSER_ALLOW_SUPERUSER
ENV COMPOSER_ALLOW_SUPERUSER=1

COPY docker/php.ini /usr/local/etc/php/conf.d/docker-php.ini

