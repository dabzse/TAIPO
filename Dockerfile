# ======================================================================
# TAIPO: AI-Driven Kanban Board — All-in-One Docker Image (Alpine)
# ======================================================================
FROM node:22.22.2-alpine3.22 AS frontend-build
WORKDIR /app/frontend
RUN corepack enable && corepack prepare pnpm@latest --activate
COPY frontend/package.json frontend/pnpm-lock.yaml ./
RUN pnpm install --frozen-lockfile
COPY frontend/ ./
RUN pnpm build

# Stage 2: Alpine-based PHP 8.5 + Apache
FROM php:8.5.5-fpm-alpine3.22 AS production

# Install Apache2 and PHP extensions via installer
ADD https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/

RUN apk add --no-cache apache2 apache2-utils apache2-proxy bash curl && \
    chmod +x /usr/local/bin/install-php-extensions && \
    install-php-extensions gd pdo_mysql pdo_pgsql zip intl opcache mbstring bcmath

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Apache & App setup
RUN mkdir -p /run/apache2 && \
    sed -i 's/#LoadModule\ proxy_module/LoadModule\ proxy_module/' /etc/apache2/httpd.conf && \
    sed -i 's/#LoadModule\ proxy_fcgi_module/LoadModule\ proxy_fcgi_module/' /etc/apache2/httpd.conf && \
    sed -i 's/#LoadModule\ rewrite_module/LoadModule\ rewrite_module/' /etc/apache2/httpd.conf

WORKDIR /var/www/html
COPY backend/ ./backend/
COPY README.md API_DOCUMENTATION.md DATABASE_README.md PROJECT.md ./
COPY --from=frontend-build /app/frontend/dist ./backend/public/dist/

# VirtualHost and permissions
RUN echo 'ServerName localhost \n\
DocumentRoot "/var/www/html/backend/public" \n\
<Directory "/var/www/html/backend/public"> \n\
    AllowOverride All \n\
    Require all granted \n\
</Directory> \n\
ProxyPassMatch ^/(.*\.php(/.*)?)$ \
    fcgi://127.0.0.1:9000/var/www/html/backend/public/$1' > /etc/apache2/conf.d/taipo.conf && \
    cd backend && composer install --no-dev --optimize-autoloader && \
    mkdir -p /var/www/html/backend/data && \
    chown -R www-data:www-data /var/www/html/backend

EXPOSE 80
CMD ["sh", "-c", "php-fpm -D && httpd -D FOREGROUND"]
