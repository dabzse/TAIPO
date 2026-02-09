<template>
    <div class="drawer z-20">
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
            <ul class="menu p-4 w-80 min-h-full bg-base-200 text-base-content gap-4">
                <!-- Generate Project Form -->
                <li class="flex flex-row justify-between items-center mb-2">
                    <span class="menu-title p-0">Generate Project</span>
                    <button
                        @click="drawerOpen = false"
                        class="btn btn-ghost btn-sm btn-circle"
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
                            <label class="label font-bold" for="projectNameInput">
                                <span class="label-text">Project Name</span>
                            </label>
                            <input
                                v-model="projectName"
                                id="projectNameInput"
                                type="text"
                                placeholder="e.g. My Awesome Project"
                                class="input input-bordered w-full"
                                required
                            >
                        </div>

                        <div class="form-control w-full">
                            <div class="flex justify-between items-center mb-2">
                                <label class="label font-bold p-0" for="promptInput">AI Prompt</label>
                                <button
                                    @click="loadDefaultPrompt"
                                    type="button"
                                    class="btn btn-xs btn-ghost text-info"
                                >
                                    Load Default
                                </button>
                            </div>
                            <textarea
                                v-model="prompt"
                                id="promptInput"
                                class="textarea textarea-bordered h-32 leading-relaxed"
                                placeholder="Describe the project you want to build..."
                            ></textarea>
                        </div>

                        <div class="grid grid-cols-2 gap-2">
                            <button
                                :disabled="loading || !prompt"
                                type="submit"
                                class="btn btn-primary bg-zinc-400"
                            >
                                {{ loading ? "Generating..." : "Generate AI" }}
                            </button>
                            <button
                                @click="handleCreateEmpty"
                                :disabled="loading || !projectName"
                                type="button"
                                class="btn btn-outline"
                            >
                                Create Empty
                            </button>
                        </div>
                    </form>
                </li>

                <div class="divider">OR</div>

                <!-- Load Existing -->
                <li class="menu-title">Load Existing</li>
                <li
                    v-if="loadingProjects"
                    class="px-4 text-sm opacity-50"
                >
                    Loading projects...
                </li>
                <li
                    v-else-if="projectLoadError"
                    class="px-4 text-sm text-error mb-2"
                >
                    {{ projectLoadError }}
                </li>
                <li>
                    <div class="join w-full">
                        <select
                            v-model="selectedProject"
                            @change="loadProject"
                            :disabled="loadingProjects || projects.length === 0"
                            class="select select-bordered join-item w-full"
                        >
                            <option disabled value="">
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
                            class="btn join-item btn-square"
                            title="Rename Project"
                        >
                            ✎
                        </button>
                    </div>
                    <button
                        v-if="projectLoadError"
                        @click="fetchProjects"
                        class="btn btn-xs btn-ghost mt-1 w-full"
                    >
                        Retry
                    </button>
                </li>

                <div class="divider"></div>

                <!-- GitHub Login -->
                <li>
                    <button
                        @click="$emit('open-github-modal')"
                        class="btn btn-outline gap-2 w-full bg-zinc-400"
                    >
                        <img
                            src="../images/github.svg"
                            alt="GitHub"
                            class="w-6 h-6"
                        >
                        GitHub Login
                    </button>
                </li>
            </ul>
        </div>

        <!-- Rename Modal (Simple impl) -->
        <dialog
            :class="{ 'modal-open': isRenameModalOpen }"
            id="rename_modal"
            class="modal"
        >
            <div class="modal-box">
                <div
                    v-if="!isDeleteConfirmOpen"
                >
                    <h3 class="font-bold text-lg">Project Settings</h3>
                    <div class="py-4">
                        <label class="label" for="rename-project-input">Rename Project</label>
                        <input
                            v-model="renameName"
                            id="rename-project-input"
                            type="text"
                            class="input input-bordered w-full mb-4"
                            placeholder="New Name"
                        >

                        <div class="divider">DANGER ZONE</div>
                        <button
                            @click="openDeleteConfirm"
                            class="btn btn-error btn-outline w-full"
                        >
                            Delete Project
                        </button>
                    </div>
                    <div class="modal-action">
                        <button
                            @click="isRenameModalOpen = false"
                            class="btn"
                        >
                            Cancel
                        </button>
                        <button
                            @click="handleRename"
                            class="btn btn-primary"
                        >
                            Save Name
                        </button>
                    </div>
                </div>

                <div v-else>
                    <h3 class="font-bold text-lg text-error">Delete Project?</h3>
                    <p class="py-4">
                        This action cannot be undone. All tasks in this project will be permanently deleted.<br>
                        Type <strong>{{ selectedProject?.name }}</strong> or <strong>delete</strong> to confirm.
                    </p>
                    <div class="py-2">
                        <input
                            v-model="deleteConfirmationText"
                            type="text"
                            class="input input-bordered input-error w-full"
                            placeholder="Type confirmation here..."
                        >
                    </div>
                    <div class="modal-action">
                        <button
                            @click="isDeleteConfirmOpen = false"
                            class="btn"
                        >
                            Back
                        </button>
                        <button
                            @click="handleDelete"
                            :disabled="deleteConfirmationText !== 'delete' && deleteConfirmationText !== selectedProject?.name"
                            class="btn btn-error"
                        >
                            Confirm Delete
                        </button>
                    </div>
                </div>
            </div>
        </dialog>
    </div>
</template>

<script setup>
import { ref, onMounted, computed } from "vue";
import { api } from "../services/api";

const props = defineProps({
    modelValue: Boolean,
});

const emit = defineEmits([
    "project-selected",
    "open-github-modal",
    "update:modelValue",
]);

const drawerOpen = computed({
    get: () => props.modelValue,
    set: (value) => emit("update:modelValue", value),
});

const projectName = ref("");
const prompt = ref("");
const selectedProject = ref(null); // stores object {id, name}
const projects = ref([]);
const loading = ref(false); // for generation
const loadingProjects = ref(false);
const projectLoadError = ref(null);

// Rename state
const isRenameModalOpen = ref(false);
const renameName = ref("");

const DEFAULT_PROMPT =
    "Plan a project named {{PROJECT_NAME}}! Generate at least 10 tasks for the Kanban board covering basic development steps. Provide each task on a new line without any prefix (e.g. [SPRINT BACKLOG]:) so they all go into the **SPRINT BACKLOG** column. Do not include introductory text.";

const loadDefaultPrompt = () => {
    // Auto-fill project name if empty
    if (!projectName.value) {
        projectName.value = "New Project";
    }
    prompt.value = DEFAULT_PROMPT.replace(
        "{{PROJECT_NAME}}",
        projectName.value,
    );
};

const handleGenerate = async () => {
    if (!projectName.value || !prompt.value) return;
    loading.value = true;
    try {
        await api.generateTasks(projectName.value, prompt.value);
        // Assuming generation automatically sets it as current or we trigger a reload
        // Refetch to get ID
        await fetchProjects();
        selectProjectByName(projectName.value);
    } catch (e) {
        alert("Error generating project: " + e.message);
    } finally {
        loading.value = false;
    }
};

const handleCreateEmpty = async () => {
    if (!projectName.value) return;
    loading.value = true;
    try {
        await api.createProject(projectName.value);
        await fetchProjects();
        selectProjectByName(projectName.value);
    } catch (e) {
        alert("Error creating project: " + (e.response?.data?.error || e.message));
    } finally {
        loading.value = false;
    }
}

const openRenameModal = () => {
    if (selectedProject.value) {
        renameName.value = selectedProject.value.name;
        isRenameModalOpen.value = true;
    }
}

const handleRename = async () => {
    if (!selectedProject.value || !renameName.value) return;
    try {
        await api.renameProject(selectedProject.value.id, renameName.value);
        // Optimization: update local state immediately
        selectedProject.value.name = renameName.value;
        const p = projects.value.find(p => p.id === selectedProject.value.id);
        if (p) p.name = renameName.value;

        isRenameModalOpen.value = false;

        // Emit change if it was selected
        emit("project-selected", renameName.value);

        await fetchProjects(); // Refresh to be sure
    } catch (e) {
        alert("Error renaming project: " + (e.response?.data?.error || e.message));
    }
}

const isDeleteConfirmOpen = ref(false);
const deleteConfirmationText = ref("");

const openDeleteConfirm = () => {
    isDeleteConfirmOpen.value = true;
    deleteConfirmationText.value = "";
}

const handleDelete = async () => {
    if (!selectedProject.value) return;

    // Strict confirmation
    if (deleteConfirmationText.value !== 'delete' && deleteConfirmationText.value !== selectedProject.value.name) {
        alert("Please type 'delete' or the project name to confirm.");
        return;
    }

    try {
        await api.deleteProject(selectedProject.value.id);
        isDeleteConfirmOpen.value = false;
        isRenameModalOpen.value = false;
        selectedProject.value = null;
        emit("project-selected", null); // Clear selection
        await fetchProjects();
    } catch (e) {
        alert("Error deleting project: " + (e.response?.data?.error || e.message));
    }
}

const loadProject = () => {
    if (selectedProject.value) {
        emit("project-selected", selectedProject.value.name);
        // Persist selection
        if (selectedProject.value.id) {
            api.saveSetting('last_active_project', selectedProject.value.id).catch(e => console.error("Failed to save setting", e));
        }
    }
};

const selectProjectByName = (name) => {
    const proj = projects.value.find(p => p.name === name);
    if (proj) {
        selectedProject.value = proj;
        loadProject();
    }
}

const parseProjectsResponse = (res) => {
    let rawList = [];
    if (Array.isArray(res)) {
        rawList = res;
    } else if (res.projects) {
        rawList = res.projects;
    } else if (res.existingProjects) {
        rawList = res.existingProjects;
    }

    return rawList.map(p => {
        if (typeof p === 'string') return { name: p, id: null };
        return p;
    });
};

const tryRestoreSavedProject = async () => {
    try {
        const settingRes = await api.getSetting('last_active_project');
        if (settingRes.success && settingRes.value) {
            const savedId = Number.parseInt(settingRes.value, 10);
            const savedProj = projects.value.find(p => p.id === savedId);
            if (savedProj) {
                selectedProject.value = savedProj;
                loadProject();
            }
        }
    } catch (e) {
        console.warn("Could not load saved project setting", e);
    }
};

const fetchProjects = async () => {
    loadingProjects.value = true;
    projectLoadError.value = null;
    try {
        const res = await api.getProjects();
        projects.value = parseProjectsResponse(res);

        // Auto-select logic
        if (projects.value.length > 0) {
            if (selectedProject.value) {
                 // Re-link selected object reference if needed
                const found = projects.value.find(p => p.name === selectedProject.value.name);
                if (found) selectedProject.value = found;
            } else {
                await tryRestoreSavedProject();
            }
        }
    } catch (e) {
        console.error("Failed to load projects", e);
        projectLoadError.value =
            "Failed to load projects. Backend may be offline.";
    } finally {
        loadingProjects.value = false;
    }
};

onMounted(() => {
    fetchProjects();
});
</script>
