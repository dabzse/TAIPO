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
  "data": { ... }
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

## Endpoints

### 1. Task Management

**Action Parameter:** `action` (in POST body)

| Action | Required Fields | Returns | Description |
| :--- | :--- | :--- | :--- |
| `add_task` | `title`, `description`, `current_project`, `is_important` (0/1) | `id`, `title`, `description`, `is_important` | Creates a new task in the specified project. |
| `delete_task` | `task_id` | `status` | Deletes a task by ID. |
| `toggle_importance` | `task_id`, `is_important` (0/1) | Message string | Toggles the importance flag (star) of a task. |
| `update_status` | `task_id`, `new_status`, `current_project` | Message string | Moves a task to a new Kanban column. |
| `edit_task` | `task_id`, `title`, `description` | `success: true` | Updates the title and description of a task. |
| `generate_java_code` | `description` | `code` (string) | Uses Gemini AI to generate Java code for the task. |
| `decompose_task` | `description`, `current_project` | `count` (int) | Uses Gemini AI to break down a large story into subtasks. |
| `commit_to_github` | `task_id`, `code`, `description`, `user_token` (opt), `user_username` (opt) | `filePath` (string) | Commits the generated code to the configured GitHub repository. |
| `reorder_tasks` | `project_name`, `status`, `task_ids` (array) | `success: true` | Reorders tasks within a specific column/status. |
| `query_task` | `task_id`, `query` | `answer` (string) | Uses Gemini AI to answer a question about a specific task. |

### 2. Project Management

**Action Parameter:** `action` (in POST body)

| Action | Required Fields | Description |
| :--- | :--- | :--- |
| `create_project` | `name` | Creates a new empty project. |
| `list_projects` | None | Returns a list of all available projects. |
| `update_project` | `id`, `name` | Renames an existing project. |
| `delete_project` | `id` | Deletes a project and all its tasks. |

### 3. Project Generation (Legacy/Composite)

**Endpoint:** POST `/` (No `action` parameter)

| Field | Description |
| :--- | :--- |
| `project_name` | Name of the new project to create. |
| `ai_prompt` | Prompt instructions for Gemini AI to generate initial tasks. |

This request triggers the **AI Brainstorming** flow, creating the project and populating the Sprint Backlog with AI-generated tasks.

### 4. Data Retrieval

**Endpoint:** GET `/`

Returns the dashboard data. If `Accept: application/json` is sent or `?api=1` query param is used, it returns the Kanban board state as JSON.

### 5. Settings Management

**Action Parameter:** `action` (in GET or POST)

| Action | Method | Required Fields | Description |
| :--- | :--- | :--- | :--- |
| `get_setting` | GET | `key` | Retrieves a system setting by key. |
| `save_setting` | POST | `key`, `value` | Saves or updates a system setting. |
