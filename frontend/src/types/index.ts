export type PriorityLevel = 0 | 1 | 2 | 3;

export type UserRole = 'Student' | 'Instructor' | 'Product Owner';

export interface Task {
    id: number;
    title: string;
    description: string;
    status: string;
    is_important: number;
    generated_code?: string | null;
    is_subtask?: number | boolean;
    parent_id?: number | null;
    po_comments?: string | null;
    position?: number;
    project_name?: string;
    updated_at?: string | null;
    subtaskCount?: number;
    // UI enhancements
    category?: string;
    assignee?: string;
    deadline?: string;
}

export type TasksByColumn = Record<string, Task[]>;

export type ColumnsMap = Record<string, string>;

export interface AuthUser {
    id?: number;
    username: string;
    is_instructor: boolean | number;
    must_change_password?: boolean | number;
    last_active_project?: string | null;
    team_id?: number | null;
    team_name?: string | null;
    role?: string;
}

export interface AppConfig {
    projectName: string;
    maxTitleLength: number;
    maxDescriptionLength: number;
    maxQueryLength: number;
    minUsernameLength?: number;
    minPasswordLength?: number;
    tablePrefix?: string;
    isOffline?: boolean;
}

export interface NotificationState {
    message: string;
    type: 'info' | 'success' | 'warning' | 'error';
    details?: string | null;
}

export interface ApiCostRecord {
    id: number;
    created_at: string;
    model: string;
    prompt_tokens: number;
    candidate_tokens: number;
    prompt_cost: number;
    candidate_cost: number;
    total_cost: number;
    user_id?: number;
    project_name?: string;
}

export interface ApiUsageResponse {
    success: boolean;
    total_cost: number;
    total_tokens: number;
    records?: ApiCostRecord[];
    summary_by_model?: Record<string, { prompt_tokens: number; candidate_tokens: number; total_cost: number }>;
}

export interface Team {
    id: number;
    name: string;
    created_at?: string;
    user_count?: number;
    settings?: Record<string, any>;
}

export interface TeamUser {
    id: number;
    user_id: number;
    username: string;
    team_id: number;
    role_id: number;
    role_name?: string;
}

export interface Role {
    id: number;
    name: string;
    description?: string;
}

export interface ProjectRequirement {
    id?: number;
    project_name: string;
    content: string;
    updated_at?: string;
}

export interface AuditLogEntry {
    id: number;
    timestamp: string;
    user_id?: number;
    username?: string;
    action: string;
    details?: string;
    project_name?: string;
}

export interface TaskHistoryItem {
    id: number;
    task_id: number;
    action: string;
    old_value?: string | null;
    new_value?: string | null;
    details?: string | null;
    username?: string | null;
    created_at?: string;
}

export interface GitHubSessionStatus {
    has_session_github: boolean;
    github_username?: string | null;
    github_repo?: string | null;
    masked_github_token?: string | null;
}

export interface ApiKeyStatusResponse {
    success: boolean;
    active_source?: string;
    masked_key?: string | null;
    has_session_key?: boolean;
    session_key_source?: string | null;
    has_saved_user_key?: boolean;
    saved_user_key_hash?: string | null;
    has_team_session_key?: boolean;
    has_team_saved_key?: boolean;
    saved_team_key_hash?: string | null;
    has_university_key?: boolean;
    team_id?: number | null;
    team_name?: string | null;
    github_status?: GitHubSessionStatus;
    error?: string;
}

export interface KanbanTasksResponse {
    success?: boolean;
    authenticated?: boolean;
    tasks: TasksByColumn;
    existingProjects?: string[];
    userRole?: string;
    config?: Partial<AppConfig>;
    error?: string;
}

export interface AuthResponse {
    success: boolean;
    authenticated: boolean;
    user?: AuthUser;
    config?: Partial<AppConfig>;
    error?: string;
}

export interface GenericApiResponse {
    success: boolean;
    error?: string;
    message?: string;
    [key: string]: any;
}

