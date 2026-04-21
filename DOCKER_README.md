# TAIPO: Docker Environment Documentation

This document explains the multi-container architecture of TAIPO and provides instructions for both production and development environments.

## 1. Architecture Overview

TAIPO uses a **dual-stack** architecture based on industry best practices for PHP applications. The setup is divided into three main services:

- **Web (Apache)**: An Alpine-based Apache server (`httpd:2.4-alpine`) that serves static frontend assets and proxies PHP requests to the application server using `mod_proxy_fcgi`.
- **App (PHP-FPM)**: A PHP 8.5.5 Application server (`php:8.5.5-fpm-alpine3.22`) that handles backend logic and database interactions.
- **DB (MariaDB)**: A dedicated database service. The system also supports **SQLite** as a portable fallback.

## 2. Environment Options

### 2.1 Production Mode

Designed for stability and minimal footprint. The frontend is built into static assets, and the PHP code is optimized with an opcache.

**To start production:**

```bash
docker compose up --build
```

Access the application at: `http://localhost:8080/TAIPO/`

### 2.2 Development Mode

Designed for real-time coding. It includes a **Vite Dev Server** for the frontend with **Hot Module Replacement (HMR)** and uses bind mounts for the backend.

**To start development:**

```bash
docker compose -f docker-compose.dev.yml up --build
```

Access the application at: `http://localhost:8080/TAIPO/`

## 3. Customization & Extension

### 3.1 PHP Extensions and Database Drivers

The `Dockerfile.php` uses a multi-stage build. You can customize the `base` stage to add your own drivers (e.g., Oracle, SQL Server, MongoDB).

To add a new extension:

1. Search for the package on [Docker Hub](https://hub.docker.com/) or the [Alpine Packages Repository](https://pkgs.alpinelinux.org/packages).
2. Add the required libraries via `apk add` in the `base` stage.
3. Install the PHP extension using `docker-php-ext-install`.

### 3.2 WSL2 and Git Support

If you are developing on Windows, it is highly recommended to use **WSL2** (Windows Subsystem for Linux).

- WSL2 provides a native Linux environment, ensuring maximum compatibility with Docker.
- Git works natively within the WSL2 terminal. You can commit and push directly from your Linux environment.

## 4. References

[1] TAIPO Source Code (Modernized Version). GitHub: `https://github.com/dabzse/TAIPO`  
[2] AI-Kanban (Original Repository). GitHub: `https://github.com/szabojuci/AIKanban`  
[3] Official PHP Docker Images. `https://hub.docker.com/_/php`  
[4] Official Apache Docker Images. `https://hub.docker.com/_/httpd`  
[5] Official Node.js Docker Images. `https://hub.docker.com/_/node`  
[6] TAIPO Official Docker Image. `docker.io/dabzse/taipo:latest`  

---
*Created as part of the TAIPO modernization project.*
