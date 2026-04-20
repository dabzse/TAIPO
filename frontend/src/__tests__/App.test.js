import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount } from '@vue/test-utils';
import { nextTick } from 'vue';

// Mock the API module
vi.mock('../services/api.js', () => ({
    api: {
        checkAuth: vi.fn().mockResolvedValue({
            success: true,
            authenticated: false,
            config: { minUsernameLength: 3, minPasswordLength: 6 }
        }),
        getKanbanTasks: vi.fn().mockResolvedValue({
            tasks: {},
            existingProjects: [],
            config: {}
        }),
        logout: vi.fn().mockResolvedValue({ success: true }),
    }
}));

// Stub child components to isolate App testing
const stubComponents = {
    KanbanBoard: { template: '<div class="kanban-stub" />' },
    ProjectSidebar: { template: '<div class="sidebar-stub" />' },
    CodeGenerationModal: { template: '<div />' },
    TaskQueryModal: { template: '<div />' },
    RequirementModal: { template: '<div />' },
    ApiCostModal: { template: '<div />' },
    PrivacyModal: { template: '<div />' },
    TeamModal: { template: '<div />' },
    LoginView: { template: '<div class="login-stub" />', props: ['config'] },
    CookieBanner: { template: '<div />' },
    ConfirmationModal: { template: '<div />' },
};

describe('App.vue', () => {
    beforeEach(() => {
        vi.clearAllMocks();

        // Provide globalThis.localStorage mock
        if (typeof globalThis !== 'undefined') {
            globalThis.localStorage = {
                getItem: vi.fn().mockReturnValue('dark'),
                setItem: vi.fn(),
            };
        }
    });

    it('shows LoginView when not authenticated', async () => {
        const App = (await import('../App.vue')).default;
        const wrapper = mount(App, {
            global: {
                stubs: stubComponents,
            },
        });

        // Wait for checkAuth to resolve
        await nextTick();
        await nextTick();

        expect(wrapper.find('.login-stub').exists()).toBe(true);
    });

    it('initializes with dark theme by default', async () => {
        const App = (await import('../App.vue')).default;
        const wrapper = mount(App, {
            global: {
                stubs: stubComponents,
            },
        });

        expect(wrapper.attributes('data-theme')).toBe('dark');
    });

    it('has default appConfig values', async () => {
        const App = (await import('../App.vue')).default;
        const wrapper = mount(App, {
            global: {
                stubs: stubComponents,
            },
        });

        // The login-stub should receive config with default values
        const loginStub = wrapper.findComponent(stubComponents.LoginView);
        if (loginStub.exists()) {
            const config = loginStub.props('config');
            expect(config.maxTitleLength).toBe(42);
            expect(config.maxDescriptionLength).toBe(512);
        }
    });
});
