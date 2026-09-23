# API Documentation

The TAIPO backend provides a simple HTTP API. While it supports standard REST methodology for some resource fetching, the primary modification interactions follow a **Post-RPC (Action-based)** pattern.

All API requests should be directed to the root URL (e.g., `/` or `/index.php`).

## Authentication

Currently, the API is open but designed to be used by the local frontend.
Configuration via `.env` is required for external services (Google Gemini, GitHub).

## Response Format

Responses are generally in JSON format when the `Accept` header is set to `application/json` or when using specific AJAX-driven actions.

Success Mockup:

```json
{
  "success": true,
  "data": { ... },
  "config": {
    "projectName": "TAIPO: AI-Kanban",
    "maxTitleLength": 42,
    "maxDescriptionLength": 512,
    "maxQueryLength": 1320
  }
}
```

Error Mockup:

```json
{
  "success": false,
  "error": "Error description (e.g. API Error: INVALID_API_KEY)"
}
```

> [!NOTE]
> The backend now returns appropriate HTTP status codes (e.g., 400 for bad requests, 403 for WIP limits, 502 for AI failures) instead of a generic 500.
> Optimistic Concurrency Control (OCC) is implemented for `edit_task`: if the `last_updated_at` parameter does not match the current database value, the API returns a **409 Conflict** status.

## Endpoints

### 1. Task Management

**Action Parameter:** `action` (in POST body)

| Action                | Required Fields                                                              | Returns                                      | Description                                                                                                                                                                                                                     |
| :-------------------- | :--------------------------------------------------------------------------- | :------------------------------------------- | :------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| `add_task`            | `title`, `description`, `current_project`, `is_important` (0/1)              | `id`, `title`, `description`, `is_important` | Creates a new task in the specified projec  t.                                                                                                                                                                                  |
| `delete_task`         | `task_id`                                                                    | `status`                                     | Deletes a    task by ID.                                                                                                                                                                                                        |
| `toggle_importance`   | `task_id`, `is_important` (0/1)                                              | Message string                               | Toggles the importance flag (star) of a task.                                                                                                                                                                                   |
| `update_status`       | `task_id`, `new_status`, `current_project`                                   | Message string                               | Moves a task to a new Kanban column.                                                                                                                                                                                            |
| `edit_task`           | `task_id`, `title`, `description`, `last_updated_at` (opt)                   | `success: true`                              | Updates the title and description of a task. Uses `last_updated_at` for optimistic locking to prevent overwriting concurrent edits.                                                                                             |
| `generate_code`       | `description`, `task_id` (opt)                                               | `code` (string)                              | Uses Gemini AI to generate **2 distinct implementation approaches** (e.g. Functional/Concise vs. Object-Oriented/Structured) using markdown headings. The frontend automatically parses these headings into interactive tabs.   |
| `decompose_task`      | `task_id`, `description`, `current_project`                                  | `count` (int)                                | Uses Gemini AI to break down a parent story. Prioritizes the database description for the parent `task_id`.                                                                                                                     |
| `commit_to_github`    | `task_id`, `code`, `description`, `user_token` (opt), `user_username` (opt)  | `filePath` (string)                          | Commits the generated code to the configured GitHub repository.                                                                                                                                                                 |
| `reorder_tasks`       | `project_name`, `status`, `task_ids` (array)                                 | `success: true`                              | Reorders tasks within a specific column/status.                                                                                                                                                                                 |
| `query_task`          | `task_id`, `query`                                                           | `answer` (string)                            | Uses Gemini AI to answer a question about a specific task.                                                                                                                                                                      |
| `get_task_history`    | `task_id`                                                                    | `data` (array)                               | Returns the full audit trail for a task, including status changes, edits, and AI interactions.                                                                                                                                  |
| `get_project_history` | `project_name`                                                               | `data` (array)                               | Returns the full audit log/history for an entire project.                                                                                                                                                                       |
| `refine_task`         | `task_id`                                                                    | `refined_description`                        | Uses Gemini AI to enhance a task description (clarity, acceptance criteria).                                                                                                                                                    |
| `suggest_priority`    | `task_id`                                                                    | `suggestion` (object)                        | Uses Gemini AI to analyze context and suggest a priority level (0-3) with rationale.                                                                                                                                            |

`decompose_task` behavior notes:

- Generated subtasks are created in `SPRINT BACKLOG`.
- Generated subtasks are marked with `is_subtask = 1`.
- Generated subtasks are linked to the source story via `parent_id = task_id`.
- Generated subtasks include `po_comments` traceability text referencing the original story.

### 2. Project Management

**Action Parameter:** `action` (in POST body)

| Action                     | Required Fields                              | Description                                                                                                        |
| :------------------------- | :------------------------------------------- | :----------------------------------------------------------------------------------------------------------------- |
| `create_project`           | `name`, `team_id` (opt)                      | Creates a new empty project.                                                                                       |
| `list_projects`            | None                                         | Returns a list of all available projects.                                                                          |
| `update_project`           | `id`, `name`                                 | Renames an existing project.                                                                                       |
| `delete_project`           | `id`                                         | Deletes a project and all its tasks.                                                                               |
| `toggle_project_activity`  | `id`, `is_active` (0/1)                      | Enables or disables the Autonomous PO Simulation for a specific project.                                           |
| `create_project_from_spec` | `spec` (string), `team_id` (opt)             | Uses Gemini AI to automatically create a project and tasks from a text specification.                              |
| `get_project_defaults`     | None                                         | Returns supported programming `languages` and their default `prompts`.                                             |
| `set_project_team`         | `id` (project), `team_id` (null to unassign) | Assigns/Unassigns a project to a specific team.                                                                    |
| `list_user_teams`          | None                                         | Returns a list of teams associated with the current user. If the user is an **Instructor**, returns **all** teams. |

### 3. Project Generation

**Action Parameter:** `action` = `generate_project_tasks`

| Field          | Description                                                  |
| :------------- | :----------------------------------------------------------- |
| `project_name` | Name of the new project to create.                           |
| `ai_prompt`    | Prompt instructions for Gemini AI to generate initial tasks. |
| `team_id`      | (Optional) Team to assign the project to upon creation.      |

This request triggers the **AI Brainstorming** flow, creating the project and populating the Sprint Backlog with AI-generated tasks.

### 4. Data Retrieval

**Endpoint:** GET `/`

Returns the dashboard data. If `Accept: application/json` is sent or `?api=1` query param is used, it returns the Kanban board state as JSON.

> [!IMPORTANT]
> **Autonomous Simulation Heartbeat:** Every time this endpoint is called, the backend triggers the **Autonomous PO Simulation** engine. If the interval has passed (2h for comments, 3d for CRs) and it is currently working hours (8AM-4PM), TAIPO will automatically update the project state before returning the response.

**Response Fields:**

- `currentProjectName`: String - Name of the active project.
- `existingProjects`: Array - List of all project names.
- `projects`: Array - Detailed project objects.
- `tasks`: Object - Tasks grouped by status.
- Task object fields include:
  - `id`: Integer
  - `title`: String
  - `description`: String
  - `status`: String
  - `is_important`: Integer (`0-3`)
  - `is_subtask`: Integer (`0` or `1`)
  - `parent_id`: Integer or `null` (set for decomposed subtasks)
  - `po_comments`: String or `null` (traceability and TAIPO notes)
  - `generated_code`: String or `null`
  - `position`: Integer
  - `updated_at`: String (DATETIME format) - Used for concurrency control.
- `config`: Object - System configuration:
  - `projectName`: String - The globally configured name of the application.
  - `maxTitleLength`: Integer - Maximum characters allowed for task titles.
  - `maxDescriptionLength`: Integer - Maximum characters allowed for task descriptions.
  - `maxQueryLength`: Integer - Maximum characters allowed for TAIPO queries.

### 5. Settings Management

**Action Parameter:** `action` (in GET or POST)

| Action         | Method | Required Fields | Description                        |
| :------------- | :----- | :-------------- | :--------------------------------- |
| `get_setting`  | GET    | `key`           | Retrieves a system setting by key. |
| `save_setting` | POST   | `key`, `value`  | Saves or updates a system setting. |

### 6. Requirement Management

**Action Parameter:** `action` (in GET or POST)

| Action             | Method | Required Fields           | Description                                                            |
| :----------------- | :----- | :------------------------ | :--------------------------------------------------------------------- |
| `save_requirement` | POST   | `project_name`, `content` | Saves a new requirement for a project.                                 |
| `get_requirements` | GET    | `project_name`            | Retrieves all requirements for a specific project (ordered by newest). |

### 7. API Usage Management

**Action Parameter:** `action` (in GET)

| Action             | Method | Required Fields | Description                                                                                            |
| :----------------- | :----- | :-------------- | :----------------------------------------------------------------------------------------------------- |
| `get_api_usage`    | GET    | None            | Retrieves token usage statistics (prompt, candidate, total) and cost configuration for the Gemini API. |

### 8. Team Management

**Action Parameter:** `action` (in POST or GET)

| Action                  | Method   | Required Fields                       | Description                                                                   |
| :---------------------- | :------- | :------------------------------------ | :---------------------------------------------------------------------------- |
| `list_teams`            | GET/POST | None                                  | Retrieve a list of all teams.                                                 |
| `create_team`           | POST     | `name`                                | Creates a new group/team of students.                                         |
| `list_roles`            | GET/POST | None                                  | Retrieve available system roles (e.g., Instructor, Student, PO).              |
| `assign_team_user`      | POST     | `team_id`, `user_id`, `role_id`       | Assigns a user (by ID or exact username) to a team mapped to a specific role. |
| `list_team_users`       | GET      | `team_id`                             | Lists all users and their respective roles for a given team.                  |
| `remove_team_user`      | POST     | `team_id`, `user_id`                  | Removes a user from a specific team.                                          |
| `update_team_user_role` | POST     | `team_id`, `user_id`, `role_id`       | Changes the role of a user within a team.                                     |
| `update_team`           | POST     | `team_id`, `name`, `settings` (opt)   | Renames an existing team and/or updates its simulation config overrides.      |

### 9. TAWOS Dataset

**Action Parameter:** `action` (in GET)

TAIPO ships a curated subset of the [TAWOS dataset](https://github.com/SOLAR-group/TAWOS) (Tawosi et al., MSR 2022) containing real-world agile issue patterns from Jira repositories. The data auto-seeds on first boot from `backend/data/tawos_seed.csv`.

| Action             | Method | Required Fields                    | Description                                                                        |
| :----------------- | :----- | :----------------------------------| :--------------------------------------------------------------------------------- |
| `get_tawos_stats`  | GET    | None                               | Returns dataset statistics: total record count, type breakdown, and project names. |
| `get_tawos_sample` | GET    | `limit` (opt, default: 5, max: 20) | Returns a random sample of TAWOS records for debug/demo purposes.                  |

> [!NOTE]
> TAWOS data is primarily used internally to enrich the Autonomous PO Simulation prompts with real-world agile feedback tone and change request patterns. The API endpoints above are provided for transparency and debugging.

### 10. Instructor Dashboard

**Action Parameter:** `action` (in GET)

| Action          | Method | Required Fields                                             | Auth Level | Description                                                                                           |
| :-------------- | :----- | :---------------------------------------------------------- | :--------- | :---------------------------------------------------------------------------------------------------- |
| `get_dashboard` | GET    | None                                                        | Instructor | Returns a system overview: grouped `.env` config (masked secrets), TAWOS stats, and project activity. |
| `export_backup` | GET    | None                                                        | Instructor | Generates and downloads a database-independent JSON backup containing all tables in the system.       |
| `import_backup` | POST   | `backup_file` (uploaded file) or `backup_data` (raw string) | Instructor | Restores the database from a structured JSON backup within a safe database transaction.               |

> [!IMPORTANT]
> These endpoints require the **Instructor** role. Non-instructor users will receive a **403 Forbidden** response.

**Response Fields:**

- `config`: Object — Environment variables grouped by category (Project, Gemini API, Gemini Costs, PO Simulation, Users, GitHub, Database, Network). API keys and tokens are masked with bullet characters showing only the last 4 characters. Database credentials (`DB_NAME`, `DB_USER`, `DB_PASS`) are fully masked.
- `tawos`: Object — Dataset statistics including `total` record count, `types` breakdown array, and `projects` list.
- `projects`: Array — All projects with `id`, `name`, `team_id`, `is_active`, `created_at`, and a `metrics` object (`total_tasks`, `done_tasks`, `completion_rate`, `stalled`).
- `teams`: Array — All registered teams, their members count, associated projects, and specific simulation parameter overrides.

### 11. API Key & Hierarchy Management (`?student -> ?team -> :university`)

TAIPO implements a dynamic three-tier API key resolution hierarchy to support individual students, shared teams, and university lab environments without requiring hardcoded credentials or compromising privacy:

1. **`?student` (Highest Priority):**
   - Evaluates active session memory (`$_SESSION['user_gemini_api_key']` or `X-User-Gemini-Api-Key` HTTP header).
   - If not in session, checks for a persistently saved key in `users.api_key_encrypted` (decrypted using OpenSSL AES-256-CBC).
2. **`?team` (Secondary Priority):**
   - If no student key exists, checks the team session key (`$_SESSION['team_gemini_api_key']`) or team database key in `teams.api_key_encrypted`.
3. **`:university` (Central Fallback):**
   - If neither student nor team keys are configured, falls back to the server `.env` key (`GEMINI_API_KEY`).

**Endpoints:**

| Action                  | Method | Required Fields                                  | Description                                                                                                                                                   |
| :---------------------- | :----- | :----------------------------------------------- | :------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| `get_api_key_status`    | GET    | `team_id` (opt)                                  | Returns active key tier (`student_usb`, `student_session`, `student_database`, `team_session`, `team_database`, `university`, `none`), masked key, and flags. |
| `set_session_api_key`   | POST   | `api_key`, `source` (opt), `target`              | Stores key in volatile session memory. Target can be `'student'` or `'team'`. Source can be `'manual'`, `'file'`, or `'usb'`.                                 |
| `clear_session_api_key` | POST   | `target` (opt: `'student'`, `'team'`, `'all'`)   | Clears the active session key from server session memory.                                                                                                     |
| `save_user_api_key`     | POST   | `api_key`, `target` (opt: `'student'`, `'team'`) | Encrypts key with **OpenSSL AES-256-CBC** and stores it in the database (`users` or `teams` table).                                                           |
| `delete_user_api_key`   | POST   | `target` (opt: `'student'`, `'team'`)            | Removes the encrypted key from the database and clears the corresponding session key.                                                                         |

> [!NOTE]
> **Key Protection & Ephemeral Lab Mode:**
>
> - When a file named `.apikeyusb` is uploaded or dropped in the Settings modal, the frontend automatically enforces ephemeral session-only storage (`source: usb`). Database storage is disabled to prevent accidental credential retention on public lab computers.
> - Plaintext keys are never stored in the database. Saved keys are encrypted using AES-256-CBC with an initialization vector (IV) and the server's encryption key.
> - On logout or session expiration, temporary session keys are wiped from both browser `sessionStorage` and backend PHP sessions.
