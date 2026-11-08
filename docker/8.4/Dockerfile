FROM ubuntu:24.04

ARG WWWGROUP=1000
ARG NODE_VERSION=24
ARG MYSQL_CLIENT="mysql-client"
ARG POSTGRES_VERSION=17
ARG PHP_EXTENSIONS=""

SHELL ["/bin/bash", "-o", "pipefail", "-c"]

WORKDIR /var/www/html

ENV DEBIAN_FRONTEND=noninteractive \
    TZ=UTC \
    LANG=C.UTF-8 \
    SUPERVISOR_PHP_COMMAND="/usr/sbin/php-fpm8.4 --nodaemonize" \
    SUPERVISOR_PHP_USER="root" \
    PLAYWRIGHT_BROWSERS_PATH=0

RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && printf '%s\n' "$TZ" > /etc/timezone

RUN printf '%s\n' \
    'Acquire::http::Pipeline-Depth 0;' \
    'Acquire::http::No-Cache true;' \
    'Acquire::BrokenProxy true;' \
    'Acquire::Retries "3";' > /etc/apt/apt.conf.d/99custom

RUN apt-get update && apt-get upgrade -y \
    && apt-get install -y gnupg gosu curl ca-certificates zip unzip git supervisor sqlite3 libcap2-bin libpng-dev python3 dnsutils librsvg2-bin fswatch ffmpeg nano \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

RUN mkdir -p /etc/apt/keyrings \
    && curl -fsSLo /tmp/debsuryorg-archive-keyring.deb https://packages.sury.org/debsuryorg-archive-keyring.deb \
    && dpkg -i /tmp/debsuryorg-archive-keyring.deb \
    && printf '%s\n' 'deb [signed-by=/usr/share/keyrings/debsuryorg-archive-keyring.gpg] https://packages.sury.org/php/ noble main' > /etc/apt/sources.list.d/php.list \
    && apt-get update \
    && apt-get install -y \
        libgd3 \
        php8.4-cli php8.4-fpm php8.4-dev \
        php8.4-pgsql php8.4-sqlite3 php8.4-gd php8.4-curl \
        php8.4-mongodb php8.4-imap php8.4-mysql php8.4-mbstring \
        php8.4-xml php8.4-zip php8.4-bcmath php8.4-soap php8.4-intl \
        php8.4-readline php8.4-ldap php8.4-msgpack php8.4-igbinary \
        php8.4-redis php8.4-swoole php8.4-memcached php8.4-pcov \
        php8.4-imagick php8.4-xdebug \
    && curl -fsSL https://getcomposer.org/installer | php -- --install-dir=/usr/bin/ --filename=composer \
    && COMPOSER_HOME=/usr/local/share/composer composer global require cpx/cpx:^2.0 \
    && ln -s /usr/local/share/composer/vendor/bin/cpx /usr/local/bin/cpx \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

RUN curl -fsSL https://deb.nodesource.com/setup_$NODE_VERSION.x | bash - \
    && apt-get install -y nodejs \
    && npm install -g npm pnpm bun corepack \
    && corepack enable \
    && corepack prepare yarn@stable --activate \
    && npx -y playwright install-deps \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

RUN curl -fsSL https://www.postgresql.org/media/keys/ACCC4CF8.asc | gpg --dearmor -o /etc/apt/keyrings/pgdg.gpg \
    && printf '%s\n' 'deb [signed-by=/etc/apt/keyrings/pgdg.gpg] http://apt.postgresql.org/pub/repos/apt noble-pgdg main' > /etc/apt/sources.list.d/pgdg.list \
    && apt-get update \
    && apt-get install -y $MYSQL_CLIENT postgresql-client-$POSTGRES_VERSION \
    && apt-get -y autoremove \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

RUN if [ -n "$PHP_EXTENSIONS" ]; then \
    apt-get update \
    && apt-get install -y $(for ext in $PHP_EXTENSIONS; do printf 'php8.4-%s ' "$ext"; done) \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*; \
    fi

RUN userdel -r ubuntu \
    && groupadd --force -g $WWWGROUP sail \
    && useradd -ms /bin/bash --no-user-group -g $WWWGROUP -u 1337 sail \
    && git config --global --add safe.directory /var/www/html

COPY start-container /usr/local/bin/start-container
COPY supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY php.ini /etc/php/8.4/cli/conf.d/99-sail.ini
COPY php.ini /etc/php/8.4/fpm/conf.d/99-sail.ini
COPY www.conf /etc/php/8.4/fpm/pool.d/www.conf
RUN chmod +x /usr/local/bin/start-container \
    && mkdir -p /run/php \
    && printf '\n[global]\nerror_log = /proc/self/fd/2\n' >> /etc/php/8.4/fpm/php-fpm.conf

EXPOSE 9000/tcp

ENTRYPOINT ["start-container"]
