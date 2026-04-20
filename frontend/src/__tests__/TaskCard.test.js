import { describe, it, expect, vi } from 'vitest';
import { mount } from '@vue/test-utils';

// Mock API
vi.mock('../services/api.js', () => ({
    api: {
        toggleImportance: vi.fn().mockResolvedValue({ data: { success: true } }),
    }
}));

describe('TaskCard.vue', () => {
    const defaultTask = {
        id: 1,
        title: 'Test Task Title',
        description: 'This is a test description for the task card.',
        is_important: 0,
        status: 'SPRINT BACKLOG',
        is_subtask: 0,
        parent_id: null,
        po_comments: null,
        generated_code: null,
        subtaskCount: 0,
    };

    const mountCard = async (taskOverrides = {}) => {
        const TaskCard = (await import('../components/TaskCard.vue')).default;
        return mount(TaskCard, {
            props: {
                task: { ...defaultTask, ...taskOverrides },
            },
        });
    };

    it('renders task title and description', async () => {
        const wrapper = await mountCard();

        expect(wrapper.text()).toContain('Test Task Title');
        expect(wrapper.text()).toContain('This is a test description');
    });

    it('renders "Untitled" when title is empty', async () => {
        const wrapper = await mountCard({ title: '' });
        expect(wrapper.text()).toContain('Untitled');
    });

    it('shows 3 priority stars', async () => {
        const wrapper = await mountCard();
        const stars = wrapper.findAll('svg');
        // Should have at least 3 star SVGs (+ dropdown icon)
        expect(stars.length).toBeGreaterThanOrEqual(3);
    });

    it('shows Technical Task badge for subtasks', async () => {
        const wrapper = await mountCard({ is_subtask: 1 });
        expect(wrapper.text()).toContain('Technical Task');
    });

    it('shows subtask count badge for parent tasks', async () => {
        const wrapper = await mountCard({ subtaskCount: 3 });
        expect(wrapper.text()).toContain('3 subtasks');
    });

    it('does not show subtask count when zero', async () => {
        const wrapper = await mountCard({ subtaskCount: 0 });
        expect(wrapper.text()).not.toContain('subtask');
    });

    it('shows PO feedback badge when po_comments exist', async () => {
        const wrapper = await mountCard({ po_comments: 'Some feedback from TAIPO' });
        expect(wrapper.text()).toContain('Feedback');
    });

    it('shows Code Generated badge when generated_code exists', async () => {
        const wrapper = await mountCard({ generated_code: 'public class Foo {}' });
        expect(wrapper.text()).toContain('Code Generated');
    });

    it('does not show Code Generated badge when no code', async () => {
        const wrapper = await mountCard({ generated_code: null });
        expect(wrapper.text()).not.toContain('Code Generated');
    });

    it('applies correct border color for priority 1', async () => {
        const wrapper = await mountCard({ is_important: 1 });
        expect(wrapper.find('.border-yellow-500').exists()).toBe(true);
    });

    it('applies correct border color for priority 2', async () => {
        const wrapper = await mountCard({ is_important: 2 });
        expect(wrapper.find('.border-orange-500').exists()).toBe(true);
    });

    it('applies correct border color for priority 3', async () => {
        const wrapper = await mountCard({ is_important: 3 });
        expect(wrapper.find('.border-red-500').exists()).toBe(true);
    });

    it('has dropdown menu with expected actions', async () => {
        const wrapper = await mountCard();
        const menuItems = wrapper.findAll('.dropdown-content li button');

        const texts = menuItems.map(b => b.text());
        expect(texts).toContain('✏️ Edit');
        expect(texts).toContain('🔨 Decompose Story');
        expect(texts).toContain('💻 Generate Code');
        expect(texts).toContain('❓ Ask AI');
        expect(texts).toContain('🗑️ Delete');
    });

    it('emits request-edit when Edit is clicked', async () => {
        const wrapper = await mountCard();
        const editBtn = wrapper.findAll('.dropdown-content li button').find(b => b.text().includes('Edit'));

        await editBtn.trigger('click');
        expect(wrapper.emitted('request-edit')).toBeTruthy();
    });

    it('emits decompose when Decompose is clicked', async () => {
        const wrapper = await mountCard();
        const btn = wrapper.findAll('.dropdown-content li button').find(b => b.text().includes('Decompose'));

        await btn.trigger('click');
        expect(wrapper.emitted('decompose')).toBeTruthy();
    });

    it('reduces opacity for subtask cards', async () => {
        const wrapper = await mountCard({ is_subtask: 1 });
        expect(wrapper.find('.opacity-50').exists()).toBe(true);
    });

    it('shows AI disclaimer in dropdown', async () => {
        const wrapper = await mountCard();
        expect(wrapper.text()).toContain('AI features send data to Gemini API');
    });
});
