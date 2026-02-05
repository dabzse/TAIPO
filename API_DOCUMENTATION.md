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
  "error": "Error description"
}
```

## Endpoints

### 1. Task Management

**Action Parameter:** `action` (in POST body)

| Action | Required Fields | Description |
| :--- | :--- | :--- |
| `add_task` | `description`, `current_project`, `is_important` (0/1) | Creates a new task in the specified project. |
| `delete_task` | `task_id` | Deletes a task by ID. |
| `toggle_importance` | `task_id`, `is_important` (0/1) | Toggles the importance flag (star) of a task. |
| `update_status` | `task_id`, `new_status`, `current_project` | Moves a task to a new Kanban column. |
| `edit_task` | `task_id`, `description` | Updates the text description of a task. |
| `generate_java_code` | `description` | Uses Gemini AI to generate Java code for the task. |
| `decompose_task` | `description`, `current_project` | Uses Gemini AI to break down a large story into subtasks. |
| `commit_to_github` | `task_id`, `code`, `description`, `user_token` (opt), `user_username` (opt) | Commits the generated code to the configured GitHub repository. |

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
