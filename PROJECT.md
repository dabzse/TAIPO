# TAIPO Project Structure

This project is separated into two main components: a **PHP** backend (**API**) and a **Vue 3** frontend.

## Directory Structure

- `backend/`: Contains the PHP application, database, and Composer dependencies.
- `frontend/`: Contains the Vue 3 application, Vite config, and Node dependencies.

## Prerequisites

- **PHP 8.5+**
- **Composer** (PHP dependency manager)
- **Node.js 18+** & **pnpm** (Frontend package manager)

## Setup & Running

### Backend (PHP)

The backend serves the API and connects to the SQLite database.

1. Navigate to the directory:

    ```bash
    cd backend
    ```

2. Install dependencies (if needed):

    ```bash
    composer install
    ```

    *(If you encounter autoload issues after moving files, run `composer dump-autoload`)*

3. Start the server:

    ```bash
    php -S localhost:8000
    ```

    The API will be available at `http://localhost:8000`.

### Frontend (Vue + Vite)

The frontend is the user interface.

1. Navigate to the directory:

    ```bash
    cd frontend
    ```

2. Install dependencies:

    ```bash
    npx pnpm install
    ```

3. Start the development server:

    ```bash
    npx pnpm dev
    ```

    The application will be available at `http://localhost:5173` (or similar).

## Main Commands

| Component | Action | Command |
| --------- | ------ | ------- |
| **Backend** | Start Server | `php -S localhost:8000` (inside `backend/`) |
| **Backend** | Update Deps | `composer update` |
| **Frontend** | Start Dev | `npx pnpm dev` (inside `frontend/`) |
| **Frontend** | Build Prod | `npx pnpm build` |
| **Frontend** | Install Deps | `npx pnpm install` |

$ ~ $

## Why pnpm?

We use **pnpm** instead of npm or yarn for the frontend because:

1. **Speed**: It is significantly faster at installing dependencies.

2. **Disk Efficiency**: It uses a content-addressable store, meaning dependencies are saved once on disk and linked, saving massive amounts of space.

3. **Strictness**: It prevents "ghost dependencies" (accessing packages not listed in `package.json`), ensuring a more stable build environment.
