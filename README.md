# 🤖 TAIPO: AI-Driven Kanban Board

An intelligent, web-based **Agile Project Management Tool** that integrates **Generative AI** with modern DevOps workflows. TAIPO simulates a **Product Owner** by automatically generating user stories, providing sprint feedback, and introducing requirement changes — all powered by the Google Gemini API.

---

## 🌟 Overview

**TAIPO** (Teaching AI Product Owner) is designed to enhance software development education and efficiency. By leveraging the **Google Gemini API**, it can brainstorm entire project backlogs, decompose user stories into tasks, generate source code, and simulate realistic PO behavior through autonomous comments and change requests. It enforces Agile discipline through **Work In Progress (WIP) limits** and streamlines the version control process via **GitHub API** integration.

For empirical evaluation, see the [Use Case Study](USE_CASE_STUDY.md).

---

## 🚀 How to Set Up (Step-by-Step)

For detailed API usage, please refer to the [Developer API Documentation](API_DOCUMENTATION.md).

### 1. Prepare Environment

* **Web Server:** For Windows environments, we recommend utilizing WAMP Server [3].
* **Project Files:** Place the project folder into your server's public directory (e.g., `C:\wamp64\www\[project_name]`).
  * WAMP is recommended for its ease of use and compatibility with PHP 8.5 in Windows environments.
  * For the latest updates, consult the WAMP aviatechno resource [4].

### 2. Obtain API Keys

* **Google Gemini API:** Access the Google AI Studio [5] and retrieve your API key.
  * A full list of supported models is available in the Gemini API documentation [6].
* **GitHub Personal Access Token (PAT):** Navigate to your GitHub Settings > Developer Settings > Personal Access Tokens > Tokens (classic).
  * Generate a token with the `repo` scope enabled [3].

### 3. Configuration

Copy the `.env.example` file to `.env`. This file is located in the `backend` directory. Fill in the following:

```env
GEMINI_API_KEY="your_google_gemini_api_key_here"
GITHUB_USERNAME="your_github_username"
GITHUB_REPO="your_github_repository_name"
GITHUB_TOKEN="your_github_personal_access_token"
```

You can change your `GEMINI_FALLBACK_MODEL` to another model if you want to use a different model. I gave you two models, one is commented out.  
At the moment `GEMINI_BASE_URL` and `GEMINI_FALLBACK_URL` are the same, but they can be different. I just prepared it for future use in case of changes in the API.  
**TAIPO** settings are optional, but recommended for better performance. In case of missing or incorrect settings, the app will use default values which are built-in (see [Config.php](backend/src/Config.php)).

### 3.1 About the API costs

It is set to the current costs of the models (2026-02-25). I gave you the link also to check the costs of the models (in case of changes in the pricing, or you need another model). Links are in the `.env.example` file (on the top).

`*._MODEL_PROMPT_COST_PER_MILLION` is the cost of the prompt (input) per million tokens.  
`*._MODEL_CANDIDATE_COST_PER_MILLION` is the cost of the response (output) per million tokens.

### 3.2 MIN_USERNAME_LENGTH and MIN_PASSWORD_LENGTH

These values are set to the current minimum lengths of the username and password.  
Default values are 6 and 8, but built-in values are 3 and 6.  
`MIN_USERNAME_LENGTH` is the minimum length of the username.  
`MIN_PASSWORD_LENGTH` is the minimum length of the password.  
Maximum lengths of the username and password are built-in, and those are: 16 and 31.

---

### 4. 🎮 How to Use the App

The application follows a streamlined workflow to take you from a project idea to committed source code.

---

### 5. 🚀 Project Generation (AI Brainstorming)

1. Click the **Menu (☰)** icon in the top-left corner.  
2. **Input**: Enter a project name (e.g., *"E-commerce Mobile App"*).  
3. **AI Instruction**: You can customize the prompt, or use the default one to generate development tasks.  
4. Click **"Generate with AI"**.  

**Result:**  
The Gemini AI processes your request and populates the **Sprint Backlog** with approximately 10 technical tasks tailored to your project.

---

### 6. 🗂 Managing the Workflow (Kanban & WIP)

The board is divided into **5 stages**. You can move tasks by dragging them.

### 7. WIP Limits

To prevent multitasking and bottlenecks, columns like **Implementation** and **Testing** have **Work In Progress (WIP)** limits.

### 8. Enforcement

If you try to move a task into a full column, the system blocks the move, encouraging you to finish pending tasks first.

---

### 9. 🤖 Solving Tasks with AI

When you are ready to implement a feature:

1. Click the **three dots (...)** on the task card.  
2. Select **"Generate Code"**.  

**AI Logic:**  
The system sends the task description to **Gemini**, which returns a functional **source code snippet** (supporting Python, PHP, Rust, Java, TypeScript, and more).

---

### 10. 🔗 Committing to GitHub (The "Done" State)

The final step is integrating your code into your repository:

1. In the code preview window, click the **GitHub icon**.  
2. The app sends a **POST request** to the backend, which then pushes the code to GitHub via a **PUT request**.  

**Success:**  
The code is saved as a new source file (e.g., `.py`, `.php`, `.rs`, `.java`, `.ts`) in your repository.

**Automation:**  
Once the commit is successful, the app automatically moves the task to the **DONE** column.

---

### 11. 🤖 Autonomous PO Simulator (Simulation Engine)

TAIPO now acts as a proactive Product Owner by simulating background activity.

* **Check-in Comments**: Every **2 hours** (during working hours), TAIPO will pick a task and add a professional, Jira-style comment asking for progress or offering guidance.
* **Change Requests**: Approximately every **3 days**, TAIPO will generate a realistic, unforeseen **Change Request** and add it to the *Sprint Backlog* to simulate project dynamics.
* **Working Hours**: The simulation is active between **8:00 AM and 4:00 PM** (Mon-Fri) to reflect a standard industrial environment.
* **TAWOS Data Grounding**: The simulation is enriched with real-world agile patterns from the [TAWOS dataset](https://github.com/SOLAR-group/TAWOS) (Tawosi et al., MSR 2022). A curated subset of 458K+ Jira issues is used to calibrate comment tone and change request realism. See [LICENCE.md](LICENCE.md) for full attribution.

---

### 12. 🌙 UI / UX Features

* **Dark Mode** – Toggle between themes using the **Moon/Sun (🌙 / ☀️)** icon  
* **Importance Tagging** – Click the proper **Star (![Empty](assets/star-empty.svg))** to mark the priority of a task
  * **(![Empty](assets/star-empty.svg)![Empty](assets/star-empty.svg)![Empty](assets/star-empty.svg))** NO priority
  * **(![Low](assets/star-yellow.svg)![Empty](assets/star-empty.svg)![Empty](assets/star-empty.svg))** LOW priority
  * **(![Low](assets/star-yellow.svg)![Medium](assets/star-orange.svg)![Empty](assets/star-empty.svg))** MEDIUM priority
  * **(![Low](assets/star-yellow.svg)![Medium](assets/star-orange.svg)![High](assets/star-red.svg))** HIGH priority
* **Inline Editing** – Modify task descriptions directly on the board by selecting **"Edit"** from the task menu

---

## 🛠 Troubleshooting

### Database Errors

The app creates a SQLite database automatically if no other database is configured.

* **Non-Docker:** `kanban.sqlite`  
* **Docker Prod:** `data/kanban.sqlite`  
* **Docker Dev:** `data/kanban.sqlite`  

Make sure the data folder has **write permissions**.

### GitHub 403 Forbidden

Ensure your **PAT** has `repo` scope and your `.env` configuration is correct.

### AI Format Errors

If the AI does not return tasks in the correct format, try refreshing the prompt in the side menu.

---

## 🐳 Docker Deployment

TAIPO features a **modern, dual-stack Docker infrastructure** that supports multiple web servers and database engines.

### Quick Start

1. **Nginx + SQLite + PHP 8.5 (Alpine):**

    ```bash
    docker compose -f docker-compose.nginx-sqlite.prod.yml up -d --build
    ```

2. **Apache + MariaDB + PHP 8.5 (Alpine):**

    ```bash
    docker compose -f docker-compose.apache-mariadb.prod.yml up -d --build
    ```

3. **All-in-one stack (Apache + Nginx + MariaDB + PostgreSQL + MySQL + SQLite support):**

    ```bash
    docker compose --profile all up -d --build
    ```

4. **All-in-One + PHP 8.5 (Alpine) [PROD]:**

    ```bash
    docker compose -f docker-compose.all-in-one.prod.yml up -d --build
    ```

5. **All-in-One + PHP 8.5 [DEV]:**

    ```bash
    docker compose -f docker-compose.all-in-one.dev.yml up --build
    ```

6. **Access:** `http://localhost:8080/TAIPO/` (Apache), `http://localhost:8081/TAIPO/` (Nginx), `http://localhost:8082/TAIPO/` (All-in-One)

### 🛠️ Advanced Multi-Stack

You can switch between **Nginx/Apache** and **MariaDB/Postgres/MySQL** using Docker Profiles:

Note: the MariaDB service uses the stable `mariadb:11.4` image tag.

```bash
# Example: Nginx + PostgreSQL
DB_TYPE=pgsql DB_HOST=postgres SQLITE_FILE_NAME=None docker compose --profile nginx --profile postgres up -d --build
```

For detailed instructions on multi-stack configuration and persistence, see the **[Docker Documentation](DOCKER_README.md)**.

### 🔐 Docker API Keys / Tokens

When running in Docker, set API keys through an env file and pass it with `--env-file`:

```bash
# Create a dedicated env file in project root
cat > .env.docker <<'EOF'
GEMINI_API_KEY=your_real_gemini_api_key
GITHUB_USERNAME=your_github_username
GITHUB_REPO=your_repo_name
GITHUB_TOKEN=your_github_token
EOF

# Recreate stack with env values
docker compose --env-file .env.docker -f docker-compose.nginx-sqlite.prod.yml up -d --force-recreate

# Or all-in-one prod
docker compose --env-file .env.docker -f docker-compose.all-in-one.prod.yml up -d --force-recreate
```

### 🧑‍💻 Dev Presets

**Nginx + SQLite Dev (includes frontend source + pnpm install + DEVPLAN + backend tests/tools/xml  configs):**

```bash
docker compose -f docker-compose.nginx-sqlite.dev.yml up --build
```

**Apache + MariaDB Dev (includes frontend source + pnpm install + DEVPLAN + backend tests/tools/xml configs):**

```bash
docker compose -f docker-compose.apache-mariadb.dev.yml up --build
```

### Stopping

```bash
docker compose down
```

Data is persisted in a Docker volume (`taipo-data`).

---

## 🧪 Testing

TAIPO includes both backend and frontend test suites.

### Backend (PHPUnit)

```bash
cd backend
composer install
vendor/bin/phpunit
```

Tests cover: Config, Database schema, TaskService, ProjectService, GeminiService, and PoActivityService.

### Frontend (Vitest)

```bash
cd frontend
pnpm install
pnpm test
```

Tests cover: API service payloads, App.vue authentication flow, KanbanBoard rendering, and TaskCard interactions.

---

## 📝 Academic Background

* **Author:** Judit Szabó *(Software Engineering Student)*  
* **Contributor:** Mihaly Nyilas *(Software Engineering Student)*  
* **Supervisor:** Dr. Gábor Kusper  
* **Institution:** Eszterházy Károly Catholic University  

## 📚 Additional Documentation

* [API Documentation](API_DOCUMENTATION.md)
* [Database Schema](DATABASE_README.md)
* [Docker Environment](DOCKER_README.md)
* [Project Structure](PROJECT.md)
* [Development Roadmap](DEVPLAN.md)
* [Use Case Study](USE_CASE_STUDY.md)
* [Licences & Attributions](LICENCE.md)

## 7. References

[1] TAIPO Source Code (Modernized). GitHub: `https://github.com/dabzse/TAIPO`.  
[2] AI-Kanban Original Repository. GitHub: `https://github.com/szabojuci/AIKanban`.  
[3] GitHub Developer Documentation - Personal Access Tokens. `https://docs.github.com/en/authentication`.  
[4] WAMP Server Official Site. `https://www.wampserver.com/`.  
[5] WAMP aviatechno (Update Resource). `https://wampserver.aviatechno.net/`.  
[6] Google AI Studio. `https://aistudio.google.com/`.  
[7] Google Gemini API Models. `https://ai.google.dev/gemini-api/docs/models`.  
[8] TAIPO Official Docker Image. `docker.io/dabzse/taipo:latest`.  
[9] TAWOS Dataset (Tawosi et al., MSR 2022). GitHub: `https://github.com/SOLAR-group/TAWOS`. DOI: `10.5522/04/21308124`.  
