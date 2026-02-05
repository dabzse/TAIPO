import axios from 'axios';

// Create axios instance with base URL pointing to the proxy or direct backend
const client = axios.create({
    baseURL: '/api', // Uses Vite proxy
    headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
    }
});

export const api = {
    async getKanbanTasks(project) {
        const url = project ? `/?project=${encodeURIComponent(project)}` : '/';
        const response = await client.get(url);
        // Backend returns: { tasks: {...}, existingProjects: [...], ... }
        return response.data.tasks;
    },

    async addTask(project, description, priority = 0) {
        const formData = new FormData();
        formData.append('action', 'add_task');
        formData.append('current_project', project);
        formData.append('description', description);
        formData.append('is_important', priority);

        // PHP expects POST form-data or JSON with specific structure.
        // Let's stick to JSON since we handle it in Application.php (lines 68-75)
        return client.post('/', {
            action: 'add_task',
            current_project: project,
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
        return response.data.existingProjects || [];
    },

    async generateTasks(projectName, prompt) {
        return client.post('/', {
            project_name: projectName,
            ai_prompt: prompt
        });
    },

    async editTask(taskId, description) {
        return client.post('/', {
            action: 'edit_task',
            task_id: taskId,
            description: description
        });
    },

    async generateCode(taskId, description) {
        return client.post('/', {
            action: 'generate_java_code',
            task_id: taskId,
            description: description
        });
    },

    async decomposeTask(taskId, description) {
        return client.post('/', {
            action: 'decompose_task',
            task_id: taskId,
            description: description
        });
    }
};
