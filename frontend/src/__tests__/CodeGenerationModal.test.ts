import { describe, it, expect, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import CodeGenerationModal from '../components/modals/CodeGenerationModal.vue';

// Mock marked
vi.mock('marked', () => ({
    marked: {
        parse: (text: string) => `<div class="parsed">${text}</div>`
    }
}));

describe('CodeGenerationModal.vue', () => {
    const defaultProps = {
        isOpen: true,
        loading: false,
        code: `
## Approach 1: Functional/Concise
This is functional approach.
\`\`\`js
const x = 1;
\`\`\`

## Approach 2: Object-Oriented
This is OOP approach.
\`\`\`js
class X {}
\`\`\`
`,
        error: '',
        task: { id: 1, title: 'Test Task', description: 'Test Task', status: 'REVIEW WIP:2', is_important: 1 }
    };

    it('renders multiple approaches as tabs', () => {
        const wrapper = mount(CodeGenerationModal, {
            props: defaultProps
        });

        const tabs = wrapper.findAll('.tabs button');
        expect(tabs).toHaveLength(2);
        expect(tabs[0].text()).toContain('Functional/Concise');
        expect(tabs[1].text()).toContain('Object-Oriented');
    });

    it('allows switching between approaches', async () => {
        const wrapper = mount(CodeGenerationModal, {
            props: defaultProps
        });

        const tabs = wrapper.findAll('.tabs button');
        
        // Initially approach 1 is active
        expect(wrapper.find('.prose').text()).toContain('Approach 1: Functional/Concise');
        expect(wrapper.find('.prose').text()).not.toContain('class X');

        // Click on the second tab
        await tabs[1].trigger('click');

        expect(wrapper.find('.prose').text()).toContain('Approach 2: Object-Oriented');
        expect(wrapper.find('.prose').text()).toContain('class X');
    });

    it('displays the disclaimer', () => {
        const wrapper = mount(CodeGenerationModal, {
            props: defaultProps
        });

        expect(wrapper.text()).toContain('Disclaimer:');
        expect(wrapper.text()).toContain('educational utility');
    });

    it('emits commit event with the selected approach code when clicked', async () => {
        const wrapper = mount(CodeGenerationModal, {
            props: defaultProps
        });

        const commitBtn = wrapper.find('.btn-primary');
        await commitBtn.trigger('click');

        expect(wrapper.emitted('commit')).toBeTruthy();
        const emitted = wrapper.emitted('commit') as any[][];
        expect(emitted[0][0]).toContain('Approach 1: Functional/Concise');
    });
});
