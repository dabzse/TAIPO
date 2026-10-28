import axios, { type AxiosInstance, type InternalAxiosRequestConfig } from 'axios';
import type {
    KanbanTasksResponse,
    AuthResponse,
    ApiUsageResponse,
    GenericApiResponse,
    ApiKeyStatusResponse
} from '../types';

const API_BASE = '/TAIPO/api';

// Create axios instance with base URL pointing to the proxy or direct backend
const client: AxiosInstance = axios.create({
    baseURL: API_BASE,
    headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
    },
    withCredentials: true // Important for sending/receiving session cookies
});

// Add a request interceptor to send active session Gemini API key if present
if (client?.interceptors?.request?.use) {
    client.interceptors.request.use((config: InternalAxiosRequestConfig) => {
        try {
            if (typeof sessionStorage !== 'undefined') {
                const sessionKey = sessionStorage.getItem('geminiApiKey');
                if (sessionKey) {
                    config.headers = config.headers || {};
                    config.headers['X-User-Gemini-Api-Key'] = sessionKey;
                }
            }
        } catch (e) {
            // sessionStorage might be restricted in some environments
        }
        return config;
    });
}

// Add a response interceptor to handle global 401s
if (client?.interceptors?.response?.use) {
    client.interceptors.response.use(
        (response) => response,
        (error) => {
            if (error?.response?.status === 401) {
                let action = '';
                try {
                    if (error.config?.data) {
                        const parsed = typeof error.config.data === 'string'
                            ? JSON.parse(error.config.data)
                            : error.config.data;
                        action = parsed.action || '';
                    }
                } catch (e) {
                    // ignore JSON parse error
                }
                if (!['login', 'register', 'check_auth'].includes(action)) {
                    if (typeof globalThis !== 'undefined' && (globalThis as any).window) {
                        (globalThis as any).window.dispatchEvent(new CustomEvent('taipo:unauthorized'));
                    }
                }
            }
            return Promise.reject(error);
        }
    );
}

export const api = {
    async getKanbanTasks(project?: string | null): Promise<KanbanTasksResponse> {
        const url = project ? `/?project=${encodeURIComponent(project)}` : '/';
        const response = await client.get<KanbanTasksResponse>(url);
        return response.data;
    },

    async addTask(project: string, title: string, description: string, priority: number = 0): Promise<GenericApiResponse> {
        return client.post('/', {
            action: 'add_task',
            current_project: project,
            title: title,
            description: description,
            is_important: priority
        });
    },

    async updateStatus(taskId: number, newStatus: string, currentProject: string): Promise<GenericApiResponse> {
        return client.post('/', {
            action: 'update_status',
            task_id: taskId,
            new_status: newStatus,
            current_project: currentProject
        });
    },

    async deleteTask(taskId: number): Promise<GenericApiResponse> {
        return client.post('/', {
            action: 'delete_task',
            task_id: taskId
        });
    },

    async toggleImportance(taskId: number, isImportant: number): Promise<GenericApiResponse> {
        return client.post('/', {
            action: 'toggle_importance',
            task_id: taskId,
            is_important: isImportant
        });
    },

    async getProjects(): Promise<{ success: boolean; projects: string[] }> {
        const response = await client.post('/', {
            action: 'list_projects'
        });
        return response.data;
    },

    async generateTasks(projectName: string, prompt: string, teamId: number | null = null): Promise<GenericApiResponse> {
        const response = await client.post('/', {
            action: 'generate_project_tasks',
            project_name: projectName,
            ai_prompt: prompt,
            team_id: teamId
        });
        return response.data;
    },

    async editTask(taskId: number, title: string, description: string, lastUpdatedAt: string | null = null): Promise<GenericApiResponse> {
        return client.post('/', {
            action: 'edit_task',
            task_id: taskId,
            title: title,
            description: description,
            last_updated_at: lastUpdatedAt
        });
    },

    async getProjectDefaults(): Promise<any> {
        const response = await client.post('/', {
            action: 'get_project_defaults'
        });
        return response.data;
    },

    async generateCode(taskId: number, description: string): Promise<{ success: boolean; code?: string; error?: string }> {
        const response = await client.post('/', {
            action: 'generate_code',
            task_id: taskId,
            description: description
        });
        return response.data;
    },

    async commitToGithub(taskId: number, code: string, description: string): Promise<{ success: boolean; filePath?: string; error?: string }> {
        const token = typeof sessionStorage !== 'undefined' ? sessionStorage.getItem('githubToken') : null;
        const username = typeof sessionStorage !== 'undefined' ? sessionStorage.getItem('githubUsername') : null;
        const repo = typeof sessionStorage !== 'undefined' ? sessionStorage.getItem('githubRepo') : null;

        const response = await client.post('/', {
            action: 'commit_to_github',
            task_id: taskId,
            code: code,
            description: description,
            user_token: token,
            user_username: username,
            user_repo: repo
        });
        return response.data;
    },

    async decomposeTask(taskId: number, description: string, currentProject: string): Promise<GenericApiResponse> {
        const response = await client.post('/', {
            action: 'decompose_task',
            task_id: taskId,
            description: description,
            current_project: currentProject
        });
        return response.data;
    },

    async reorderTasks(projectName: string, status: string, taskIds: number[]): Promise<GenericApiResponse> {
        return client.post('/', {
            action: 'reorder_tasks',
            project_name: projectName,
            status: status,
            task_ids: taskIds
        });
    },

    async createProject(name: string, teamId: number | null = null): Promise<GenericApiResponse> {
        return client.post('/', {
            action: 'create_project',
            name: name,
            team_id: teamId
        });
    },

    async createProjectFromSpec(specContent: string, teamId: number | null = null): Promise<GenericApiResponse> {
        const response = await client.post('/', {
            action: 'create_project_from_spec',
            spec: specContent,
            team_id: teamId
        });
        return response.data;
    },

    async renameProject(id: number | string, name: string): Promise<GenericApiResponse> {
        return client.post('/', {
            action: 'update_project',
            id: id,
            name: name
        });
    },

    async deleteProject(id: number | string): Promise<GenericApiResponse> {
        return client.post('/', {
            action: 'delete_project',
            id: id
        });
    },

    async toggleProjectActivity(id: number | string, isActive: boolean | number): Promise<GenericApiResponse> {
        return client.post('/', {
            action: 'toggle_project_activity',
            id: id,
            is_active: isActive
        });
    },

    async getSetting(key: string): Promise<any> {
        const response = await client.get(`/?action=get_setting&key=${encodeURIComponent(key)}`);
        return response.data;
    },

    async saveSetting(key: string, value: any): Promise<GenericApiResponse> {
        return client.post('/', {
            action: 'save_setting',
            key: key,
            value: value
        });
    },

    async queryTask(taskId: number, query: string): Promise<{ success: boolean; answer?: string; error?: string }> {
        const response = await client.post('/', {
            action: 'query_task',
            task_id: taskId,
            query: query
        });
        return response.data;
    },

    async getTaskHistory(taskId: number): Promise<any> {
        const response = await client.get(`/?action=get_task_history&task_id=${taskId}`);
        return response.data;
    },

    async getProjectHistory(projectName: string): Promise<any> {
        const response = await client.get(`/?action=get_project_history&project_name=${encodeURIComponent(projectName)}`);
        return response.data;
    },

    async saveRequirement(projectName: string, content: string): Promise<GenericApiResponse> {
        return client.post('/', {
            action: 'save_requirement',
            project_name: projectName,
            content: content
        });
    },

    async getRequirements(projectName: string): Promise<any> {
        const response = await client.get(`/?action=get_requirements&project_name=${encodeURIComponent(projectName)}`);
        return response.data;
    },

    async getApiUsage(): Promise<ApiUsageResponse> {
        const response = await client.get<ApiUsageResponse>(`/?action=get_api_usage`);
        return response.data;
    },

    // Authentication
    async login(username: string, password: string): Promise<AuthResponse> {
        const response = await client.post<AuthResponse>('/', {
            action: 'login',
            username: username,
            password: password
        });
        return response.data;
    },

    async register(username: string, password: string): Promise<AuthResponse> {
        const response = await client.post<AuthResponse>('/', {
            action: 'register',
            username: username,
            password: password
        });
        return response.data;
    },

    async logout(): Promise<GenericApiResponse> {
        const response = await client.post('/', {
            action: 'logout'
        });
        return response.data;
    },

    async checkAuth(): Promise<AuthResponse> {
        const response = await client.post<AuthResponse>('/', {
            action: 'check_auth'
        });
        return response.data;
    },

    async changePassword(currentPassword: string, newPassword: string): Promise<GenericApiResponse> {
        const response = await client.post('/', {
            action: 'change_password',
            current_password: currentPassword,
            new_password: newPassword
        });
        return response.data;
    },

    async saveActiveProject(projectName: string): Promise<GenericApiResponse> {
        return client.post('/', {
            action: 'save_active_project',
            project_name: projectName
        });
    },

    async reviewTask(taskId: number): Promise<GenericApiResponse> {
        return client.post('/', {
            action: 'review_task',
            task_id: taskId
        });
    },

    async refineTask(taskId: number): Promise<GenericApiResponse> {
        const response = await client.post('/', {
            action: 'refine_task',
            task_id: taskId
        });
        return response.data;
    },

    async suggestPriority(taskId: number): Promise<GenericApiResponse> {
        const response = await client.post('/', {
            action: 'suggest_priority',
            task_id: taskId
        });
        return response.data;
    },

    // Team Management
    async listTeams(): Promise<any> {
        const response = await client.get('/?action=list_teams');
        return response.data;
    },

    async createTeam(name: string): Promise<GenericApiResponse> {
        const response = await client.post('/', {
            action: 'create_team',
            name: name
        });
        return response.data;
    },

    async updateTeam(teamId: number, name: string, settings: any = null): Promise<GenericApiResponse> {
        const payload: Record<string, any> = {
            action: 'update_team',
            team_id: teamId,
            name: name
        };
        if (settings) {
            payload.settings = typeof settings === 'string' ? settings : JSON.stringify(settings);
        }
        const response = await client.post('/', payload);
        return response.data;
    },

    async listRoles(): Promise<any> {
        const response = await client.get('/?action=list_roles');
        return response.data;
    },

    async assignTeamUser(teamId: number, userIdentifier: string, roleId: number): Promise<GenericApiResponse> {
        const isNumericString = /^\d+$/.test(userIdentifier);
        const response = await client.post('/', {
            action: 'assign_team_user',
            team_id: teamId,
            user_id: isNumericString ? Number.parseInt(userIdentifier, 10) : undefined,
            username: isNumericString ? undefined : userIdentifier,
            role_id: roleId
        });
        return response.data;
    },

    async listTeamUsers(teamId: number): Promise<any> {
        const response = await client.get(`/?action=list_team_users&team_id=${teamId}`);
        return response.data;
    },

    async removeTeamUser(teamId: number, userId: number): Promise<GenericApiResponse> {
        const response = await client.post('/', {
            action: 'remove_team_user',
            team_id: teamId,
            user_id: userId
        });
        return response.data;
    },

    async updateTeamUserRole(teamId: number, userId: number, roleId: number): Promise<GenericApiResponse> {
        const response = await client.post('/', {
            action: 'update_team_user_role',
            team_id: teamId,
            user_id: userId,
            role_id: roleId
        });
        return response.data;
    },

    async listUserTeams(): Promise<any> {
        const response = await client.get('/?action=list_user_teams');
        return response.data;
    },

    async setProjectTeam(projectId: number | string, teamId: number | null): Promise<GenericApiResponse> {
        const response = await client.post('/', {
            action: 'set_project_team',
            id: projectId,
            team_id: teamId
        });
        return response.data;
    },

    // Instructor Dashboard
    async getDashboard(): Promise<any> {
        const response = await client.get('/?action=get_dashboard');
        return response.data;
    },

    async searchTawos(query: string, filters: Record<string, any> = {}, limit: number = 20): Promise<any> {
        const params = new URLSearchParams({
            action: 'search_tawos',
            q: query,
            limit: String(limit),
            ...filters
        });
        const response = await client.get(`/?${params.toString()}`);
        return response.data;
    },

    getExportBackupUrl(): string {
        return `${API_BASE}/?action=export_backup`;
    },

    async importBackup(file: File): Promise<GenericApiResponse> {
        const formData = new FormData();
        formData.append('action', 'import_backup');
        formData.append('backup_file', file);
        const response = await client.post('/', formData, {
            headers: {
                'Content-Type': 'multipart/form-data'
            }
        });
        return response.data;
    },

    // API Key Management (?student -> ?team -> :university)
    async getApiKeyStatus(teamId: number | string | null = null): Promise<ApiKeyStatusResponse> {
        const url = teamId
            ? `/?action=get_api_key_status&team_id=${encodeURIComponent(String(teamId))}`
            : '/?action=get_api_key_status';
        const response = await client.get<ApiKeyStatusResponse>(url);
        return response.data;
    },

    async setSessionApiKey(apiKey: string, source: string = 'manual', target: string = 'student'): Promise<GenericApiResponse> {
        const response = await client.post('/', {
            action: 'set_session_api_key',
            api_key: apiKey,
            source: source,
            target: target
        });
        return response.data;
    },

    async clearSessionApiKey(target: string = 'all'): Promise<GenericApiResponse> {
        const response = await client.post('/', {
            action: 'clear_session_api_key',
            target: target
        });
        return response.data;
    },

    // GitHub Session Credentials
    async setSessionGitHubKeys(token: string, username: string, repo: string = ''): Promise<GenericApiResponse & { github_username?: string; github_repo?: string | null; masked_github_token?: string }> {
        const response = await client.post('/', {
            action: 'set_session_github_keys',
            github_token: token,
            github_username: username,
            github_repo: repo
        });
        return response.data;
    },

    async clearSessionGitHubKeys(): Promise<GenericApiResponse> {
        const response = await client.post('/', {
            action: 'clear_session_github_keys'
        });
        return response.data;
    },

    async saveUserApiKey(apiKey: string, target: string = 'student'): Promise<GenericApiResponse> {
        const response = await client.post('/', {
            action: 'save_user_api_key',
            api_key: apiKey,
            target: target
        });
        return response.data;
    },

    async deleteUserApiKey(target: string = 'student'): Promise<GenericApiResponse> {
        const response = await client.post('/', {
            action: 'delete_user_api_key',
            target: target
        });
        return response.data;
    }
};
