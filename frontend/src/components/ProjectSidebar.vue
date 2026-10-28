<template>
    <div class="drawer z-50">
        <input
            v-model="drawerOpen"
            type="checkbox"
            id="my-drawer"
            class="drawer-toggle"
        >
        <div class="drawer-content">
            <!-- Page content here, toggle button is external or in navbar -->
        </div>

        <div class="drawer-side">
            <label for="my-drawer" class="drawer-overlay" aria-label="close sidebar"></label>
            <ul class="menu p-5 w-84 min-h-full bg-base-200/95 backdrop-blur-xl text-base-content gap-4 border-r border-base-300">
                <!-- Generate Project Form -->
                <li class="flex flex-row justify-between items-center mb-1">
                    <span class="menu-title p-0 text-xs font-bold uppercase tracking-wider text-primary">Generate Project</span>
                    <button
                        @click="drawerOpen = false"
                        class="btn btn-ghost btn-sm btn-circle"
                        type="button"
                    >
                        ✕
                    </button>
                </li>
                <li>
                    <form
                        @submit.prevent="handleGenerate"
                        class="flex flex-col gap-4"
                    >
                        <div class="form-control w-full">
                            <label class="label font-bold text-xs" for="projectNameInput">
                                <span class="label-text">Project Name</span>
                            </label>
                            <input
                                v-model="projectName"
                                id="projectNameInput"
                                type="text"
                                placeholder="e.g. Project Titan"
                                class="input input-sm input-bordered w-full"
                                required
                            >
                        </div>

                        <div class="form-control w-full">
                            <div class="flex justify-between items-center mb-1">
                                <label class="label font-bold p-0 text-xs" for="promptInput">AI Prompt</label>
                                <div class="dropdown dropdown-end">
                                    <button type="button" tabindex="0" class="btn btn-xs btn-ghost text-info m-1">Load Default ▾</button>
                                    <ul class="dropdown-content z-30 menu p-2 shadow-xl bg-base-100 rounded-xl w-52 text-xs border border-base-300">
                                        <li
                                            v-for="lang in supportedLanguages"
                                            :key="lang"
                                        >
                                            <a @mousedown.prevent="loadDefaultPrompt(lang)" @click.prevent="loadDefaultPrompt(lang)" class="cursor-pointer">{{ lang }}</a>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                            <textarea
                                v-model="prompt"
                                id="promptInput"
                                class="textarea textarea-bordered h-32 leading-relaxed text-xs"
                                placeholder="Describe the agile project you want Gemini to create stories for..."
                            >
                            </textarea>
                            <div class="pt-1 pb-0">
                                <span class="label-text-alt text-[10px] text-warning leading-tight block">
                                    *Prompts are sent to Google Gemini API. Avoid Personally Identifiable Information (PII).
                                </span>
                            </div>
                        </div>

                        <div class="divider text-[11px] my-1">OR UPLOAD SPEC</div>

                        <div class="form-control w-full">
                            <label class="sr-only" for="spec-file-upload">Upload Spec File</label>
                            <input
                                id="spec-file-upload"
                                @change="handleFileUpload"
                                :disabled="loading"
                                type="file"
                                accept=".txt,.md"
                                class="file-input file-input-bordered file-input-sm w-full text-xs"
                            >
                        </div>

                        <div class="grid grid-cols-2 gap-2 pt-1">
                            <button
                                :disabled="loading || !prompt"
                                type="submit"
                                class="btn btn-primary btn-sm font-bold shadow-md shadow-primary/20"
                            >
                                <span v-if="loading" class="loading loading-spinner loading-xs"></span>
                                {{ loading ? "Generating..." : "Generate AI" }}
                            </button>
                            <button
                                @click="handleCreateEmpty"
                                :disabled="loading || !projectName"
                                type="button"
                                class="btn btn-outline btn-sm"
                            >
                                Create Empty
                            </button>
                        </div>
                    </form>
                </li>

                <div class="divider my-1">OR</div>

                <!-- Load Existing -->
                <li class="menu-title text-xs font-bold uppercase tracking-wider text-base-content/60">Load Existing Project</li>
                <li
                    v-if="loadingProjects"
                    class="px-4 text-xs opacity-50"
                >
                    Loading projects...
                </li>
                <li
                    v-else-if="projectLoadError"
                    class="px-4 text-xs text-error mb-1"
                >
                    {{ projectLoadError }}
                </li>
                <li>
                    <div class="join w-full">
                        <label class="sr-only" for="select-existing-project">Select Existing Project</label>
                        <select
                            id="select-existing-project"
                            v-model="selectedProject"
                            @change="loadProject"
                            :disabled="loadingProjects || projects.length === 0"
                            class="select select-sm select-bordered join-item w-full text-xs"
                        >
                            <option disabled :value="null">
                                {{
                                    projects.length === 0
                                        ? "No projects found"
                                        : "Select a project"
                                }}
                            </option>
                            <option
                                v-for="proj in projects"
                                :key="proj.name"
                                :value="proj"
                            >
                                {{ proj.name }}
                            </option>
                        </select>
                        <button
                            @click="openRenameModal"
                            :disabled="!selectedProject || !selectedProject.id"
                            class="btn btn-sm join-item btn-square"
                            title="Project Settings"
                            type="button"
                        >
                            ✎
                        </button>
                    </div>

                    <!-- Simulation Toggle -->
                    <div
                        v-if="selectedProject && selectedProject.id"
                        class="form-control mt-2 px-1"
                    >
                        <label class="label cursor-pointer justify-between p-0">
                            <span class="label-text text-xs font-bold opacity-80">Simulation Active</span>
                            <input
                                :checked="selectedProject?.is_active == 1"
                                @click="toggleActivity"
                                type="checkbox"
                                class="custom-status-toggle"
                            >
                        </label>
                    </div>

                    <button
                        v-if="projectLoadError"
                        @click="fetchProjects"
                        class="btn btn-xs btn-ghost mt-1 w-full text-xs"
                        type="button"
                    >
                        Retry
                    </button>
                </li>

                <div class="divider my-1"></div>

                <li>
                    <a
                        href="http://localhost:8000/?action=github_login"
                        class="btn btn-outline btn-sm gap-2 w-full flex items-center justify-center font-bold"
                    >
                        <img
                            src="../images/github.svg"
                            alt="GitHub"
                            class="w-4 h-4"
                        >
                        GitHub Connect
                    </a>
                </li>
            </ul>
        </div>

        <!-- Rename Modal -->
        <dialog
            :class="{ 'modal-open': isRenameModalOpen }"
            :open="isRenameModalOpen"
            id="rename_modal"
            class="modal"
        >
            <div class="modal-box rounded-2xl border border-base-300">
                <div v-if="!isDeleteConfirmOpen">
                    <h3 class="font-bold text-lg">Project Settings</h3>
                    <div class="py-4">
                        <label class="label text-xs font-semibold" for="rename-project-input">Rename Project</label>
                        <input
                            v-model="renameName"
                            id="rename-project-input"
                            type="text"
                            class="input input-sm input-bordered w-full mb-4 text-xs"
                            placeholder="New Name"
                        >

                        <div class="form-control mb-4">
                            <label class="label cursor-pointer justify-start gap-4">
                                <input
                                    :checked="selectedProject?.is_active == 1"
                                    @change="toggleActivity"
                                    type="checkbox"
                                    class="custom-status-toggle"
                                >
                                <span class="label-text font-bold text-xs">Product Owner Simulation (AI Comments/CRs)</span>
                            </label>
                            <span class="text-xs opacity-60 ml-14">When enabled, the AI Product Owner will periodically comment on tasks and generate Change Requests.</span>
                        </div>

                        <div class="divider text-xs">DANGER ZONE</div>
                        <button
                            @click="openDeleteConfirm"
                            type="button"
                            class="btn btn-error btn-outline btn-sm w-full"
                        >
                            Delete Project
                        </button>
                    </div>
                    <div class="modal-action">
                        <button
                            @click="isRenameModalOpen = false"
                            class="btn btn-sm"
                            type="button"
                        >
                            Cancel
                        </button>
                        <button
                            @click="handleRename"
                            class="btn btn-primary btn-sm"
                            type="button"
                        >
                            Save Name
                        </button>
                    </div>
                </div>

                <div v-else>
                    <h3 class="font-bold text-lg text-error">Delete Project?</h3>
                    <p class="py-4 text-xs">
                        This action cannot be undone. All tasks in this project will be permanently deleted.<br>
                        Type <strong>{{ selectedProject?.name }}</strong> or <strong>delete</strong> to confirm.
                    </p>
                    <div class="py-2">
                        <label class="sr-only" for="delete-confirmation-text">Delete confirmation</label>
                        <input
                            id="delete-confirmation-text"
                            v-model="deleteConfirmationText"
                            type="text"
                            class="input input-sm input-bordered input-error w-full text-xs"
                            placeholder="Type confirmation here..."
                        >
                    </div>
                    <div class="modal-action">
                        <button
                            @click="isDeleteConfirmOpen = false"
                            class="btn btn-sm"
                            type="button"
                        >
                            Back
                        </button>
                        <button
                            @click="handleDelete"
                            :disabled="deleteConfirmationText !== 'delete' && deleteConfirmationText !== selectedProject?.name"
                            class="btn btn-error btn-sm"
                            type="button"
                        >
                            Confirm Delete
                        </button>
                    </div>
                </div>
            </div>
            <form method="dialog" class="modal-backdrop">
                <button @click="isRenameModalOpen = false" type="button">close</button>
            </form>
        </dialog>

        <ConfirmationModal
            :is-open="isAlertOpen"
            :title="alertTitle"
            :message="alertMessage"
            :is-danger="isAlertDanger"
            is-alert
            @close="isAlertOpen = false"
        />
    </div>
</template>

<script setup lang="ts">
import { ref, onMounted, computed, watch } from 'vue';
import ConfirmationModal from './modals/ConfirmationModal.vue';
import { api } from '../services/api';

interface ProjectItem {
    id: number | null;
    name: string;
    is_active?: number | boolean;
}

const props = withDefaults(
    defineProps<{
        modelValue: boolean;
        activeProjectName?: string;
    }>(),
    {
        activeProjectName: '',
    }
);

const emit = defineEmits<{
    (e: 'project-selected', name: string | null): void;
    (e: 'open-github-modal'): void;
    (e: 'update:modelValue', value: boolean): void;
}>();

const drawerOpen = computed({
    get: () => props.modelValue,
    set: (value: boolean) => emit('update:modelValue', value),
});

const projectName = ref('');
const prompt = ref('');
const selectedProject = ref<ProjectItem | null>(null);
const projects = ref<ProjectItem[]>([]);
const loading = ref(false);
const loadingProjects = ref(false);
const projectLoadError = ref<string | null>(null);

// Rename state
const isRenameModalOpen = ref(false);
const renameName = ref('');

// Alert State
const isAlertOpen = ref(false);
const alertTitle = ref('Notification');
const alertMessage = ref('');
const alertDanger = ref(false);
const isAlertDanger = computed(() => alertDanger.value);

const showAlert = (title: string, message: string, isDanger: boolean = false) => {
    alertTitle.value = title;
    alertMessage.value = message;
    alertDanger.value = isDanger;
    isAlertOpen.value = true;
};

const supportedLanguages = ref<string[]>([]);
const languagePrompts = ref<Record<string, string>>({});

watch(() => props.activeProjectName, (newVal) => {
    if (newVal && (!selectedProject.value || selectedProject.value.name !== newVal)) {
        const found = projects.value.find((p) => p.name === newVal);
        if (found) {
            selectedProject.value = found;
        }
    }
});

const loadDefaultPrompt = (language: string) => {
    if (!projectName.value || /^New .* Project \d{4}-\d{2}-\d{2}/.test(projectName.value) || projectName.value.startsWith('New Project')) {
        const now = new Date();
        const yyyy = now.getFullYear();
        const mm = String(now.getMonth() + 1).padStart(2, '0');
        const dd = String(now.getDate()).padStart(2, '0');
        const hh = String(now.getHours()).padStart(2, '0');
        const min = String(now.getMinutes()).padStart(2, '0');
        const sec = String(now.getSeconds()).padStart(2, '0');
        projectName.value = `New ${language} Project ${yyyy}-${mm}-${dd} ${hh}:${min}:${sec}`;
    }

    const promptText = languagePrompts.value[language] || '';
    if (promptText) {
        prompt.value = promptText.replace('{{PROJECT_NAME}}', projectName.value);
    }
};

const handleFileUpload = async (event: any) => {
    const file = event.target.files[0];
    if (!file) return;

    try {
        const text = await file.text();
        if (!text.trim()) return;

        loading.value = true;
        const res = await api.createProjectFromSpec(text, null);
        if (res.success) {
            await fetchProjects();
            if (res.projectName) {
                selectProjectByName(res.projectName);
            }
            drawerOpen.value = false;
        }
    } catch (err: any) {
        console.error(err);
        showAlert('Upload Error', err.response?.data?.error || err.message, true);
    } finally {
        loading.value = false;
        event.target.value = '';
    }
};

const handleGenerate = async () => {
    if (!projectName.value || !prompt.value) return;
    loading.value = true;
    try {
        await api.generateTasks(projectName.value, prompt.value, null);
        await fetchProjects();
        selectProjectByName(projectName.value);
        drawerOpen.value = false;

        prompt.value = '';
        projectName.value = '';
    } catch (e: any) {
        showAlert('Generation Failed', e.response?.data?.error || e.message, true);
    } finally {
        loading.value = false;
    }
};

const handleCreateEmpty = async () => {
    if (!projectName.value) return;
    loading.value = true;
    try {
        await api.createProject(projectName.value, null);
        await fetchProjects();
        selectProjectByName(projectName.value);
        drawerOpen.value = false;
    } catch (e: any) {
        showAlert('Creation Failed', e.response?.data?.error || e.message, true);
    } finally {
        loading.value = false;
    }
};

const openRenameModal = async () => {
    if (selectedProject.value) {
        renameName.value = selectedProject.value.name;
        isRenameModalOpen.value = true;
    }
};

const handleRename = async () => {
    if (!selectedProject.value || !renameName.value || !selectedProject.value.id) return;
    try {
        await api.renameProject(selectedProject.value.id, renameName.value);
        selectedProject.value.name = renameName.value;
        const p = projects.value.find((item) => item.id === selectedProject.value?.id);
        if (p) p.name = renameName.value;

        isRenameModalOpen.value = false;
        emit('project-selected', renameName.value);
        await fetchProjects();
    } catch (e: any) {
        showAlert('Rename Failed', e.response?.data?.error || e.message, true);
    }
};

const isDeleteConfirmOpen = ref(false);
const deleteConfirmationText = ref('');

const openDeleteConfirm = () => {
    isDeleteConfirmOpen.value = true;
    deleteConfirmationText.value = '';
};

const handleDelete = async () => {
    if (!selectedProject.value || !selectedProject.value.id) return;

    if (deleteConfirmationText.value !== 'delete' && deleteConfirmationText.value !== selectedProject.value.name) {
        showAlert('Validation Required', "Please type 'delete' or the exact project name to confirm.", true);
        return;
    }

    try {
        await api.deleteProject(selectedProject.value.id);
        isDeleteConfirmOpen.value = false;
        isRenameModalOpen.value = false;
        selectedProject.value = null;
        emit('project-selected', null);
        await fetchProjects();
    } catch (e: any) {
        showAlert('Deletion Failed', e.response?.data?.error || e.message, true);
    }
};

const toggleActivity = async (event: any) => {
    if (!selectedProject.value || !selectedProject.value.id) return;
    const isActive = event.target.checked;
    try {
        await api.toggleProjectActivity(selectedProject.value.id, isActive);
        selectedProject.value.is_active = isActive ? 1 : 0;
        const p = projects.value.find(item => item.id === selectedProject.value?.id);
        if (p) p.is_active = isActive ? 1 : 0;
    } catch (e: any) {
        showAlert('Update Failed', e.response?.data?.error || e.message, true);
        event.target.checked = !isActive;
    }
};

const loadProject = () => {
    if (selectedProject.value) {
        emit('project-selected', selectedProject.value.name);
        if (selectedProject.value.id) {
            api.saveSetting('last_active_project', selectedProject.value.id).catch(e => console.error('Failed to save setting', e));
        }
    }
};

const selectProjectByName = (name: string) => {
    const proj = projects.value.find((p) => p.name === name);
    if (proj) {
        selectedProject.value = proj;
        loadProject();
    }
};

const parseProjectsResponse = (res: any): ProjectItem[] => {
    let rawList: any[] = [];
    if (Array.isArray(res)) {
        rawList = res;
    } else if (res.projects) {
        rawList = res.projects;
    } else if (res.data) {
        rawList = res.data;
    } else if (res.existingProjects) {
        rawList = res.existingProjects;
    }

    return rawList.map((p) => {
        if (typeof p === 'string') return { name: p, id: null };
        return p;
    });
};

const tryRestoreSavedProject = async () => {
    try {
        const settingRes = await api.getSetting('last_active_project');
        if (settingRes.success && settingRes.value) {
            const savedId = Number.parseInt(settingRes.value, 10);
            const savedProj = projects.value.find((p) => p.id === savedId);
            if (savedProj) {
                selectedProject.value = savedProj;
                if (!props.activeProjectName || props.activeProjectName !== savedProj.name) {
                    loadProject();
                }
            }
        }
    } catch (e) {
        console.warn('Could not load saved project setting', e);
    }
};

const fetchProjects = async () => {
    loadingProjects.value = true;
    projectLoadError.value = null;
    try {
        const res = await api.getProjects();
        projects.value = parseProjectsResponse(res);

        if (projects.value.length > 0) {
            if (props.activeProjectName) {
                const found = projects.value.find((p) => p.name === props.activeProjectName);
                if (found) selectedProject.value = found;
            } else if (selectedProject.value) {
                const found = projects.value.find((p) => p.name === selectedProject.value?.name);
                if (found) selectedProject.value = found;
            } else {
                await tryRestoreSavedProject();
            }
        }
    } catch (e) {
        console.error('Failed to load projects', e);
        projectLoadError.value = 'Failed to load projects. Backend may be offline.';
    } finally {
        loadingProjects.value = false;
    }
};

onMounted(async () => {
    const [defaultsResult] = await Promise.allSettled([
        api.getProjectDefaults(),
        fetchProjects()
    ]);

    if (defaultsResult.status === 'fulfilled' && defaultsResult.value.success) {
        supportedLanguages.value = defaultsResult.value.languages || [];
        languagePrompts.value = defaultsResult.value.prompts || {};
    }
});
</script>
