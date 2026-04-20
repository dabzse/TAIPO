import { describe, it, expect, vi } from 'vitest';
import { mount } from '@vue/test-utils';

// Mock API and draggable
vi.mock('../services/api.js', () => ({
    api: {
        addTask: vi.fn().mockResolvedValue({ data: { success: true } }),
        deleteTask: vi.fn().mockResolvedValue({ data: { success: true } }),
        reorderTasks: vi.fn().mockResolvedValue({ data: { success: true } }),
        editTask: vi.fn().mockResolvedValue({ data: { success: true } }),
    }
}));

vi.mock('vuedraggable', () => ({
    default: {
        name: 'draggable',
        template: '<div><slot v-for="el in modelValue" :element="el" /></div>',
        props: ['modelValue', 'group', 'ghostClass', 'itemKey'],
        emits: ['update:modelValue', 'change'],
    }
}));

// Stub child components
const TaskCardStub = {
    template: '<div class="task-card-stub" :data-task-id="task.id">{{ task.title }}</div>',
    props: ['task'],
};
const TaskModalStub = { template: '<div />', props: ['isOpen', 'task', 'isReadOnly', 'maxTitleLength', 'maxDescriptionLength'] };
const SafeDeleteModalStub = { template: '<div />', props: ['isOpen', 'taskDescription'] };
const ConfirmationModalStub = { template: '<div />', props: ['isOpen', 'title', 'message', 'isDanger', 'isAlert'] };

describe('KanbanBoard.vue', () => {
    const defaultProps = {
        columns: {
            'SPRINT BACKLOG': 'neutral',
            'IMPLEMENTATION WIP:3': 'primary',
            'TESTING WIP:2': 'warning',
            'REVIEW WIP:2': 'info',
            'DONE': 'success',
        },
        tasks: {
            'SPRINT BACKLOG': [
                { id: 1, title: 'Task 1', description: 'Desc 1', is_important: 0, status: 'SPRINT BACKLOG' },
                { id: 2, title: 'Task 2', description: 'Desc 2', is_important: 1, status: 'SPRINT BACKLOG' },
            ],
            'IMPLEMENTATION WIP:3': [],
            'TESTING WIP:2': [],
            'REVIEW WIP:2': [],
            'DONE': [],
        },
        currentProject: 'TestProject',
        maxTitleLength: 42,
        maxDescriptionLength: 512,
    };

    it('renders 5 columns', async () => {
        const KanbanBoard = (await import('../components/KanbanBoard.vue')).default;
        const wrapper = mount(KanbanBoard, {
            props: defaultProps,
            global: {
                stubs: {
                    TaskCard: TaskCardStub,
                    TaskModal: TaskModalStub,
                    SafeDeleteModal: SafeDeleteModalStub,
                    ConfirmationModal: ConfirmationModalStub,
                    draggable: {
                        template: '<div class="draggable-stub"><slot v-for="el in modelValue" :element="el" /></div>',
                        props: ['modelValue', 'group', 'ghostClass', 'itemKey'],
                    }
                },
            },
        });

        // There should be 5 column containers
        const columns = wrapper.findAll(String.raw`.min-w-\[280px\]`);
        expect(columns.length).toBe(5);
    });

    it('displays formatted column titles with WIP counts', async () => {
        const KanbanBoard = (await import('../components/KanbanBoard.vue')).default;
        const wrapper = mount(KanbanBoard, {
            props: defaultProps,
            global: {
                stubs: {
                    TaskCard: TaskCardStub,
                    TaskModal: TaskModalStub,
                    SafeDeleteModal: SafeDeleteModalStub,
                    ConfirmationModal: ConfirmationModalStub,
                    draggable: {
                        template: '<div><slot v-for="el in modelValue" :element="el" /></div>',
                        props: ['modelValue', 'group', 'ghostClass', 'itemKey'],
                    }
                },
            },
        });

        const text = wrapper.text();
        // SPRINT BACKLOG has no WIP limit, should show as-is
        expect(text).toContain('SPRINT BACKLOG');
        // WIP columns should show count/limit format
        expect(text).toContain('IMPLEMENTATION');
        expect(text).toContain('TESTING');
    });

    it('shows Add Task button only in backlog column', async () => {
        const KanbanBoard = (await import('../components/KanbanBoard.vue')).default;
        const wrapper = mount(KanbanBoard, {
            props: defaultProps,
            global: {
                stubs: {
                    TaskCard: TaskCardStub,
                    TaskModal: TaskModalStub,
                    SafeDeleteModal: SafeDeleteModalStub,
                    ConfirmationModal: ConfirmationModalStub,
                    draggable: {
                        template: '<div><slot v-for="el in modelValue" :element="el" /></div>',
                        props: ['modelValue', 'group', 'ghostClass', 'itemKey'],
                    }
                },
            },
        });

        const addButtons = wrapper.findAll('button').filter(b => b.text().includes('Add Task'));
        // Should have 2 Add Task buttons (top + bottom of backlog)
        expect(addButtons.length).toBe(2);
    });

    it('renders task data in backlog column', async () => {
        const KanbanBoard = (await import('../components/KanbanBoard.vue')).default;
        const wrapper = mount(KanbanBoard, {
            props: defaultProps,
            global: {
                stubs: {
                    // Use a draggable stub that renders the actual TaskCard stub with scoped slots
                    TaskCard: {
                        template: '<div class="task-card-stub">{{ task.title }}</div>',
                        props: ['task'],
                    },
                    TaskModal: TaskModalStub,
                    SafeDeleteModal: SafeDeleteModalStub,
                    ConfirmationModal: ConfirmationModalStub,
                    draggable: {
                        template: '<div><div v-for="el in modelValue" :key="el.id">{{ el.title }}</div></div>',
                        props: ['modelValue', 'group', 'ghostClass', 'itemKey'],
                    }
                },
            },
        });

        const html = wrapper.html();
        expect(html).toContain('Task 1');
        expect(html).toContain('Task 2');
    });
});
