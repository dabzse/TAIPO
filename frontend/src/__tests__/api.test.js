import { describe, it, expect, vi, beforeEach } from 'vitest';

// We test the API service by verifying the payload shapes of each method.
// Since the module uses axios.create() at import time, we mock at a lower level.

const mockGet = vi.fn();
const mockPost = vi.fn();

vi.mock('axios', () => ({
    default: {
        create: () => ({
            get: mockGet,
            post: mockPost,
            interceptors: {
                response: { use: vi.fn() }
            }
        })
    }
}));

describe('API Service', () => {
    let api;

    beforeEach(async () => {
        vi.clearAllMocks();
        // Dynamic import to get fresh module with mocks applied
        const module = await import('../services/api.js');
        api = module.api;
    });

    it('getKanbanTasks calls GET with project param', async () => {
        mockGet.mockResolvedValue({ data: { tasks: {}, existingProjects: [] } });
        await api.getKanbanTasks('TestProject');

        expect(mockGet).toHaveBeenCalledWith(
            expect.stringContaining('TestProject')
        );
    });

    it('getKanbanTasks calls GET with root path when no project', async () => {
        mockGet.mockResolvedValue({ data: { tasks: {} } });
        await api.getKanbanTasks(null);

        expect(mockGet).toHaveBeenCalledWith('/');
    });

    it('addTask sends correct payload', async () => {
        mockPost.mockResolvedValue({ data: { success: true } });
        await api.addTask('Project1', 'Task Title', 'Task Desc', 2);

        expect(mockPost).toHaveBeenCalledWith('/', {
            action: 'add_task',
            current_project: 'Project1',
            title: 'Task Title',
            description: 'Task Desc',
            is_important: 2
        });
    });

    it('deleteTask sends correct task_id', async () => {
        mockPost.mockResolvedValue({ data: { success: true } });
        await api.deleteTask(42);

        expect(mockPost).toHaveBeenCalledWith('/', {
            action: 'delete_task',
            task_id: 42
        });
    });

    it('login sends username and password', async () => {
        mockPost.mockResolvedValue({ data: { success: true, user: { id: 1 } } });
        const result = await api.login('testuser', 'testpass');

        expect(mockPost).toHaveBeenCalledWith('/', {
            action: 'login',
            username: 'testuser',
            password: 'testpass'
        });
        expect(result.success).toBe(true);
    });

    it('toggleImportance sends correct priority', async () => {
        mockPost.mockResolvedValue({ data: { success: true } });
        await api.toggleImportance(5, 3);

        expect(mockPost).toHaveBeenCalledWith('/', {
            action: 'toggle_importance',
            task_id: 5,
            is_important: 3
        });
    });

    it('reorderTasks sends project, status, and task IDs', async () => {
        mockPost.mockResolvedValue({ data: { success: true } });
        await api.reorderTasks('Proj', 'SPRINT BACKLOG', [3, 1, 2]);

        expect(mockPost).toHaveBeenCalledWith('/', {
            action: 'reorder_tasks',
            project_name: 'Proj',
            status: 'SPRINT BACKLOG',
            task_ids: [3, 1, 2]
        });
    });

    it('createProject sends name and teamId', async () => {
        mockPost.mockResolvedValue({ data: { success: true } });
        await api.createProject('New Project', 5);

        expect(mockPost).toHaveBeenCalledWith('/', {
            action: 'create_project',
            name: 'New Project',
            team_id: 5
        });
    });

    it('editTask sends task_id, title, description, and updated_at', async () => {
        mockPost.mockResolvedValue({ data: { success: true } });
        await api.editTask(10, 'New Title', 'New Desc', '2026-01-01');

        expect(mockPost).toHaveBeenCalledWith('/', {
            action: 'edit_task',
            task_id: 10,
            title: 'New Title',
            description: 'New Desc',
            last_updated_at: '2026-01-01'
        });
    });

    it('logout sends logout action', async () => {
        mockPost.mockResolvedValue({ data: { success: true } });
        const result = await api.logout();

        expect(mockPost).toHaveBeenCalledWith('/', { action: 'logout' });
        expect(result.success).toBe(true);
    });
});
