# TAIPO Project Structure

This project is separated into two main components: a **PHP** backend (**API**) and a **Vue 3** frontend.

## Directory Structure

- `backend/`: Contains the PHP application, database, and Composer dependencies.
  - `backend/data/`: Contains seed data files (e.g., `tawos_seed.csv` for TAWOS dataset integration).
- `frontend/`: Contains the Vue 3 application, Vite config, and Node dependencies.

## Prerequisites

- **PHP 8.5+**
- **Composer** (PHP dependency manager)
- **Node.js 18+** & **pnpm** (Frontend package manager)

### TO USE IT OFFLINE / SELF-HOSTED, YOU NEED

**`.offline`** named file  
OR  
**`__OFFLINE`** named directory/folder

(see: Config.php: `public static function isOffline(): bool`)  
Why we need one of these?  
HTTPS is required, if you don't have any of these bypass "solutions", you can face `Unsupported SSL request` errors.

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

2. Install dependencies \* (see note below):

    ```bash
    npx pnpm install
    ```

3. Start the development server \* (see note below):

    ```bash
    npx pnpm dev
    ```

    The application will be available at `http://localhost:5173` (or similar).

**NOTE**: `npx` temporarily downloads the `pnpm package` from the `npm registry` each time it is run, and then executes it. This is why it only works when connected to the internet. To avoid this, you can run `npm run dev` to start the server. But if you need `pnpm`, you have few options:

- `corepack enable`
- `corepack prepare pnpm@latest --activate`
- `pnpm dev`

OR

- `npm install -g pnpm`
  - this installs `pnpm` on user-level

OR in Linux without root:

- `curl -fsSL https://get.pnpm.io/install.sh | sh -`
- then restart your bash/fish/zsh with `source`
- you are ready to use `pnpm dev`

## Main Commands

| Component     | Action       | Command                                                            | Description                                                                    |
| ------------- | ------------ | ------------------------------------------------------------------ | ------------------------------------------------------------------------------ |
| **Backend**   | Start Server | `php -S localhost:8000` (inside `backend/`)                        | Start PHP server                                                               |
| **Backend**   | Update Deps  | `composer update`                                                  | Update dependencies                                                            |
| **Frontend**  | Start Dev    | `pnpm dev` OR `npm run dev` OR `npx pnpm dev` (inside `frontend/`) | Start Vue dev server (if you use `npx pnpm dev`, you need internet connection) |
| **Frontend**  | Build Prod   | `pnpm build`                                                       | Build for production                                                           |
| **Frontend**  | Install Deps | `pnpm install`                                                     | Install dependencies                                                           |

$ ~ $

## Why pnpm?

We use **pnpm** instead of npm or yarn for the frontend because:

1. **Speed**: It is significantly faster at installing dependencies.

2. **Disk Efficiency**: It uses a content-addressable store, meaning dependencies are saved once on disk and linked, saving massive amounts of space.

3. **Strictness**: It prevents "ghost dependencies" (accessing packages not listed in `package.json`), ensuring a more stable build environment.
