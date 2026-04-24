import axios from 'axios';

// const API_BASE = '/TAIPO/api';
const API_BASE = 'http://localhost:8000';

// Create axios instance with base URL pointing to the proxy or direct backend
const client = axios.create({
    baseURL: API_BASE, // Uses Vite proxy or direct backend in production
    headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
    },
    withCredentials: true // Important for sending/receiving session cookies
});

// Add a response interceptor to handle global 401s
client.interceptors.response.use(
    (response) => response,
    (error) => {
        if (error?.response?.status === 401) {
            // If we get an unauthorized error and we're not already trying to login/check auth
            const action = error.config?.data ? JSON.parse(error.config.data).action : '';
            if (!['login', 'register', 'check_auth'].includes(action)) {
                // Dispatch event so App.vue can log the user out visually
                if (typeof globalThis !== 'undefined' && globalThis.window) {
                    globalThis.window.dispatchEvent(new CustomEvent('taipo:unauthorized'));
                }
            }
        }
        return Promise.reject(error);
    }
);

export const api = {
    async getKanbanTasks(project) {
        const url = project ? `/?project=${encodeURIComponent(project)}` : '/';
        const response = await client.get(url);
        // Backend returns: { tasks: {...}, existingProjects: [...], config: {...}, ... }
        return response.data;
    },

    async addTask(project, title, description, priority = 0) {
        // PHP expects POST form-data or JSON with specific structure.
        return client.post('/', {
            action: 'add_task',
            current_project: project,
            title: title,
            description: description,
            is_important: priority
        });
    },

    async updateStatus(taskId, newStatus, currentProject) {
        return client.post('/', {
            action: 'update_status',
            task_id: taskId,
            new_status: newStatus,
            current_project: currentProject
        });
    },

    async deleteTask(taskId) {
        return client.post('/', {
            action: 'delete_task',
            task_id: taskId
        });
    },

    async toggleImportance(taskId, isImportant) {
        return client.post('/', {
            action: 'toggle_importance',
            task_id: taskId,
            is_important: isImportant
        });
    },

    async getProjects() {
        // Backend returns existingProjects in the main view data
        const response = await client.get('/');
        return response.data.projects || response.data.existingProjects || [];
    },

    async generateTasks(projectName, prompt, teamId = null) {
        const response = await client.post('/', {
            action: 'generate_project_tasks',
            project_name: projectName,
            ai_prompt: prompt,
            team_id: teamId
        });
        return response.data;
    },

    async editTask(taskId, title, description, lastUpdatedAt = null) {
        return client.post('/', {
            action: 'edit_task',
            task_id: taskId,
            title: title,
            description: description,
            last_updated_at: lastUpdatedAt
        });
    },

    async getProjectDefaults() {
        const response = await client.post('/', {
            action: 'get_project_defaults'
        });
        return response.data;
    },

    async generateCode(taskId, description) {
        const response = await client.post('/', {
            action: 'generate_code',
            task_id: taskId,
            description: description
        });
        return response.data;
    },

    async commitToGithub(taskId, code, description) {
        // Retrieves GitHub credentials from sessionStorage (set by GitHub login)
        const token = sessionStorage.getItem('githubToken');
        const username = sessionStorage.getItem('githubUsername');
        const repo = sessionStorage.getItem('githubRepo');

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

    async decomposeTask(taskId, description, currentProject) {
        const response = await client.post('/', {
            action: 'decompose_task',
            task_id: taskId,
            description: description,
            current_project: currentProject
        });
        return response.data;
    },

    async reorderTasks(projectName, status, taskIds) {
        return client.post('/', {
            action: 'reorder_tasks',
            project_name: projectName,
            status: status,
            task_ids: taskIds
        });
    },

    async createProject(name, teamId = null) {
        return client.post('/', {
            action: 'create_project',
            name: name,
            team_id: teamId
        });
    },

    async createProjectFromSpec(specContent, teamId = null) {
        const response = await client.post('/', {
            action: 'create_project_from_spec',
            spec: specContent,
            team_id: teamId
        });
        return response.data;
    },

    async renameProject(id, name) {
        return client.post('/', {
            action: 'update_project',
            id: id,
            name: name
        });
    },

    async deleteProject(id) {
        return client.post('/', {
            action: 'delete_project',
            id: id
        });
    },

    async toggleProjectActivity(id, isActive) {
        return client.post('/', {
            action: 'toggle_project_activity',
            id: id,
            is_active: isActive
        });
    },

    async getSetting(key) {
        const response = await client.get(`/?action=get_setting&key=${key}`);
        return response.data;
    },

    async saveSetting(key, value) {
        return client.post('/', {
            action: 'save_setting',
            key: key,
            value: value
        });
    },

    async queryTask(taskId, query) {
        const response = await client.post('/', {
            action: 'query_task',
            task_id: taskId,
            query: query
        });
        return response.data;
    },

    async saveRequirement(projectName, content) {
        return client.post('/', {
            action: 'save_requirement',
            project_name: projectName,
            content: content
        });
    },

    async getRequirements(projectName) {
        const response = await client.get(`/?action=get_requirements&project_name=${encodeURIComponent(projectName)}`);
        return response.data;
    },

    async getApiUsage() {
        const response = await client.get(`/?action=get_api_usage`);
        return response.data;
    },

    // Authentication
    async login(username, password) {
        const response = await client.post('/', {
            action: 'login',
            username: username,
            password: password
        });
        return response.data;
    },

    async register(username, password) {
        const response = await client.post('/', {
            action: 'register',
            username: username,
            password: password
        });
        return response.data;
    },

    async logout() {
        const response = await client.post('/', {
            action: 'logout'
        });
        return response.data;
    },

    async checkAuth() {
        const response = await client.post('/', {
            action: 'check_auth'
        });
        return response.data;
    },

    // Team Management
    async listTeams() {
        const response = await client.get('/?action=list_teams');
        return response.data;
    },

    async createTeam(name) {
        const response = await client.post('/', {
            action: 'create_team',
            name: name
        });
        return response.data;
    },
    async updateTeam(teamId, name) {
        const response = await client.post('/', {
            action: 'update_team',
            team_id: teamId,
            name: name
        });
        return response.data;
    },

    async listRoles() {
        const response = await client.get('/?action=list_roles');
        return response.data;
    },

    async assignTeamUser(teamId, userIdentifier, roleId) {
        // If it's pure digits, send as user_id. If a string, send as username.
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

    async listTeamUsers(teamId) {
        const response = await client.get(`/?action=list_team_users&team_id=${teamId}`);
        return response.data;
    },

    async removeTeamUser(teamId, userId) {
        const response = await client.post('/', {
            action: 'remove_team_user',
            team_id: teamId,
            user_id: userId
        });
        return response.data;
    },

    async updateTeamUserRole(teamId, userId, roleId) {
        const response = await client.post('/', {
            action: 'update_team_user_role',
            team_id: teamId,
            user_id: userId,
            role_id: roleId
        });
        return response.data;
    },

    async listUserTeams() {
        const response = await client.get('/?action=list_user_teams');
        return response.data;
    },

    async setProjectTeam(projectId, teamId) {
        const response = await client.post('/', {
            action: 'set_project_team',
            id: projectId,
            team_id: teamId
        });
        return response.data;
    }
};
