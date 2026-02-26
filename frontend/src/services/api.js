import axios from 'axios';

// Create axios instance with base URL pointing to the proxy or direct backend
const client = axios.create({
    baseURL: '/api', // Uses Vite proxy
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

    async generateTasks(projectName, prompt) {
        const response = await client.post('/', {
            action: 'generate_project_tasks',
            project_name: projectName,
            ai_prompt: prompt
        });
        return response.data;
    },

    async editTask(taskId, title, description) {
        return client.post('/', {
            action: 'edit_task',
            task_id: taskId,
            title: title,
            description: description
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

    async decomposeTask(taskId, description) {
        const response = await client.post('/', {
            action: 'decompose_task',
            task_id: taskId,
            description: description
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

    async createProject(name) {
        return client.post('/', {
            action: 'create_project',
            name: name
        });
    },

    async createProjectFromSpec(specContent) {
        const response = await client.post('/', {
            action: 'create_project_from_spec',
            spec: specContent
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
    }
};
