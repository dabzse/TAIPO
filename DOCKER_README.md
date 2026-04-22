# 🐳 TAIPO: Multi-Stack Docker Ecosystem

Welcome to the premium containerization setup for **TAIPO**. This infrastructure is designed for maximum flexibility, supporting multiple web servers and database engines with ease.

---

## 📦 All-in-One Image (Single Container)

If you prefer a single container with Apache and PHP bundled together:

```bash
docker build -t taipo-app .
docker run -d -p 8080:80 --env-file .env taipo-app
```

---

## 🚀 Multi-Container Stack (Recommended)

The repository now ships with:

- one universal profile-based base compose
- two ready-to-run preset overrides

```bash
# Copy the example environment file
cp .env.example .env

# Preset 1: Nginx + SQLite + PHP 8.5 (Alpine)
docker compose -f docker-compose.nginx-sqlite.prod.yml up -d --build

# Preset 2: Apache + MariaDB + PHP 8.5 (Alpine)
docker compose -f docker-compose.apache-mariadb.prod.yml up -d --build

# Preset 3: All-in-One + SQLite + PHP 8.5 (Alpine) [PROD]
docker compose -f docker-compose.all-in-one.prod.yml up -d --build

# Preset 4: All-in-One + SQLite + PHP 8.5 [DEV]
docker compose -f docker-compose.all-in-one.dev.yml up --build

# All-in-one: Apache + Nginx + MariaDB + PostgreSQL + MySQL (+ SQLite support in app)
docker compose --profile all up -d --build
```

The application will be available at:

- `http://localhost:8080/TAIPO/` for Apache
- `http://localhost:8081/TAIPO/` for Nginx
- `http://localhost:8082/TAIPO/` for All-in-One

---

## 🛠️ Customizing Your Stack

TAIPO supports **Docker Profiles**, allowing you to mix and match your preferred technology stack from source.

### 🌐 Web Servers

| Server         | Command                                 | Default Port |
| :--------------| :---------------------------------------| :------------|
| **Apache**     | `docker compose --profile apache up`    | `8080`       |
| **Nginx**      | `docker compose --profile nginx up`     | `8081`       |

### 🗄️ Database Engines

| Engine         | Command                                 | `DB_TYPE` (.env) |
| :--------------| :---------------------------------------| :----------------|
| **MariaDB**    | `docker compose --profile mariadb up`   | `mysql`          |
| **MySQL**      | `docker compose --profile mysql up`     | `mysql`          |
| **PostgreSQL** | `docker compose --profile postgres up`  | `pgsql`          |
| **SQLite**     | *(No service needed)*                   | `sqlite`         |

### 💡 Example: Nginx + PostgreSQL

```bash
DB_TYPE=pgsql DB_HOST=postgres SQLITE_FILE_NAME=None docker compose --profile nginx --profile postgres up -d --build

# Example: Apache + MySQL
DB_TYPE=mysql DB_HOST=mysql SQLITE_FILE_NAME=None docker compose --profile apache --profile mysql up -d --build

# Example: Apache + SQLite
DB_TYPE=sqlite DB_HOST=localhost SQLITE_FILE_NAME=data/kanban.sqlite docker compose --profile apache up -d --build
```

---

## 🧑‍💻 Development Environment

For real-time development with **Vite HMR** and PHP hot-reloading:

```bash
docker compose -f docker-compose.dev.yml up --build
```

- **Backend (PHP 8.5):** Hot-reloaded via bind mounts.
- **Frontend (Vue/Vite):** Accessible at `http://localhost:5173` (or proxied through Apache at `8080`).

### Dedicated Dev Presets

```bash
# Nginx + SQLite DEV
docker compose -f docker-compose.nginx-sqlite.dev.yml up --build

# Apache + MariaDB DEV
docker compose -f docker-compose.apache-mariadb.dev.yml up --build

# All-in-One DEV
docker compose -f docker-compose.all-in-one.dev.yml up --build
```

Both dedicated dev presets include:

- full `frontend/` source bind-mounted
- `pnpm install` inside the frontend dev container (`node_modules` stored in a container volume)
- `DEVPLAN.md` mounted into app container
- backend dev files available (`tests`, `tools`, `phpcs.xml`, `phpunit.xml`) via backend bind mount

---

## ⚙️ Configuration (.env)

Customize your deployment by editing the `.env` file:

```ini
# Database Selection
DB_TYPE=mysql          # options: mysql, pgsql, sqlite
DB_HOST=mariadb        # matches the selected DB service name
DB_NAME=taipo
DB_USER=taipo_user
DB_PASSWORD=taipo_password
SQLITE_FILE_NAME=None  # set to data/kanban.sqlite for SQLite mode

# Ports
PORT_APACHE=8080
PORT_NGINX=8081
```

### 🔐 Passing API Keys in Docker

For Docker deployments, the recommended approach is to pass secrets via a dedicated environment file instead of editing containers manually.

```bash
# 1) Create a dedicated docker env file in project root
cat > .env.docker <<'EOF'
GEMINI_API_KEY=your_real_gemini_api_key
GITHUB_USERNAME=your_github_username
GITHUB_REPO=your_repo_name
GITHUB_TOKEN=your_github_token
EOF

# 2) Recreate the stack with this env file
docker compose --env-file .env.docker -f docker-compose.nginx-sqlite.prod.yml up -d --force-recreate

# 2b) Or all-in-one prod
docker compose --env-file .env.docker -f docker-compose.all-in-one.prod.yml up -d --force-recreate

# 3) Verify env variable inside the app container
docker exec -it taipo-app-nginx-sqlite-prod sh -lc "printenv | grep GEMINI"
```

Notes:

- `--env-file` is preferred over editing values inside a running container.
- The same approach works for other stack files (for example `docker-compose.apache-mariadb.prod.yml`).

---

## 🏗️ Architecture

- **PHP 8.5.x-FPM:** High-performance PHP service.
- **Multi-Stage Builds:** Optimized production images.
- **Dual-Stack Support:** Switch between Apache and Nginx at runtime.
- **Persistence:** Volumes for database data and uploaded assets.
