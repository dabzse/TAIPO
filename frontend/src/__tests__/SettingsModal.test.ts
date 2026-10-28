import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount } from '@vue/test-utils';
import SettingsModal from '../components/modals/SettingsModal.vue';

vi.mock('../../services/api', () => ({
    api: {
        getApiKeyStatus: vi.fn().mockResolvedValue({
            success: true,
            active_source: 'student_session',
            masked_key: 'AIzaSy...1234',
            has_session_key: true,
            has_saved_user_key: false,
            github_status: {
                has_session_github: true,
                github_username: 'dabzse',
                github_repo: 'TAIPO',
                masked_github_token: 'ghp_...5678'
            }
        }),
        setSessionApiKey: vi.fn().mockResolvedValue({ success: true }),
        clearSessionApiKey: vi.fn().mockResolvedValue({ success: true }),
        saveUserApiKey: vi.fn().mockResolvedValue({ success: true }),
        deleteUserApiKey: vi.fn().mockResolvedValue({ success: true }),
        setSessionGitHubKeys: vi.fn().mockResolvedValue({ success: true }),
        clearSessionGitHubKeys: vi.fn().mockResolvedValue({ success: true }),
        changePassword: vi.fn().mockResolvedValue({ success: true })
    }
}));

describe('SettingsModal.vue', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        sessionStorage.clear();
    });

    it('renders all 3 tabs: Gemini AI, GitHub, and Password', () => {
        const wrapper = mount(SettingsModal, {
            props: {
                isOpen: true,
                authUser: { id: 1, username: 'testuser', is_instructor: false, must_change_password: false }
            }
        });

        const tabs = wrapper.findAll('button[role="tab"]');
        expect(tabs).toHaveLength(3);
        expect(tabs[0].text()).toContain('Gemini AI');
        expect(tabs[1].text()).toContain('GitHub');
        expect(tabs[2].text()).toContain('Password');
    });

    it('allows switching to GitHub tab and displays direct PAT helper link', async () => {
        const wrapper = mount(SettingsModal, {
            props: {
                isOpen: true,
                authUser: { id: 1, username: 'testuser', is_instructor: false, must_change_password: false }
            }
        });

        const tabs = wrapper.findAll('button[role="tab"]');
        await tabs[1].trigger('click'); // Click GitHub tab

        const patLink = wrapper.find('a[href*="github.com/settings/tokens/new"]');
        expect(patLink.exists()).toBe(true);
        expect(patLink.attributes('href')).toBe('https://github.com/settings/tokens/new?scopes=repo&description=TAIPO%20Lab%20Token');
    });

    it('switches to password tab initially if user must change password', () => {
        const wrapper = mount(SettingsModal, {
            props: {
                isOpen: true,
                authUser: { id: 1, username: 'testuser', is_instructor: false, must_change_password: true }
            }
        });

        const activeTabs = wrapper.findAll('button[role="tab"].btn-primary');
        expect(activeTabs[0].text()).toContain('Password');
    });

    it('closes modal and emits close event when close button is clicked', async () => {
        const wrapper = mount(SettingsModal, {
            props: {
                isOpen: true,
                authUser: { id: 1, username: 'testuser', is_instructor: false, must_change_password: false }
            }
        });

        const closeBtn = wrapper.find('button[aria-label="Close modal"]');
        await closeBtn.trigger('click');

        expect(wrapper.emitted('close')).toBeTruthy();
    });
});
