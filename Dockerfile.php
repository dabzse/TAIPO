# ======================================================================
# TAIPO: AI-Driven Kanban Board — PHP-FPM Service
# ======================================================================

# Base Stage (Common for Dev & Prod)
FROM php:8.5.5-fpm-alpine3.22 AS base

# Install system dependencies and PHP extensions
ADD https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/

RUN apk add --no-cache bash git && \
    chmod +x /usr/local/bin/install-php-extensions && \
    install-php-extensions gd pdo_mysql pdo_pgsql zip intl opcache mbstring bcmath

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Development Stage
FROM base AS development
# In dev, we don't copy files; we use bind mounts in docker-compose
EXPOSE 9000
CMD ["php-fpm"]

# Frontend Build Stage (for Prod)
FROM node:22.22.2-alpine3.22 AS frontend-build
WORKDIR /app/frontend
RUN corepack enable && corepack prepare pnpm@latest --activate
COPY frontend/package.json frontend/pnpm-lock.yaml ./
RUN pnpm install --frozen-lockfile
COPY frontend/ ./
RUN pnpm build

# Production Stage
FROM base AS production
# Copy backend source
COPY backend/ ./backend/
# Copy project-level documentation
COPY README.md API_DOCUMENTATION.md DATABASE_README.md PROJECT.md ./
# Copy built frontend assets
COPY --from=frontend-build /app/frontend/dist ./backend/public/dist/

# Install PHP dependencies and set permissions
RUN cd backend && composer install --no-dev --optimize-autoloader \
    && mkdir -p /var/www/html/backend/data \
    && chown -R www-data:www-data /var/www/html/backend

USER www-data
EXPOSE 9000
CMD ["php-fpm"]
