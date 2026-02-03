<template>
    <div class="drawer z-20">
        <input id="my-drawer" type="checkbox" class="drawer-toggle" v-model="drawerOpen">
        <div class="drawer-content">
            <!-- Page content here, toggle button is external or in navbar -->
        </div>

        <div class="drawer-side">
            <label for="my-drawer" aria-label="close sidebar" class="drawer-overlay"></label>
            <ul class="menu p-4 w-80 min-h-full bg-base-200 text-base-content gap-4">

                <!-- Generate Project Form -->
                <li class="flex flex-row justify-between items-center mb-2">
                    <span class="menu-title p-0">Generate Project</span>
                    <button class="btn btn-ghost btn-sm btn-circle" @click="drawerOpen = false">✕</button>
                </li>
                <li>
                    <form @submit.prevent="handleGenerate" class="flex flex-col gap-4">
                        <div class="form-control w-full">
                            <label class="label font-bold" for="projectNameInput">
                                <span class="label-text">Project Name</span>
                            </label>
                            <input
                                id="projectNameInput"
                                v-model="projectName"
                                type="text"
                                placeholder="e.g. My Awesome Project"
                                class="input input-bordered w-full"
                                required
                            />
                        </div>

                        <div class="form-control w-full">
                            <div class="flex justify-between items-center mb-2">
                                <label class="label font-bold p-0" for="promptInput">AI Prompt</label>
                                <button
                                    type="button"
                                    class="btn btn-xs btn-ghost text-info"
                                    @click="loadDefaultPrompt"
                                >
                                    Load Default
                                </button>
                            </div>
                            <textarea
                                id="promptInput"
                                v-model="prompt"
                                class="textarea textarea-bordered h-32 leading-relaxed"
                                placeholder="Describe the project you want to build..."
                                required
                            ></textarea>
                        </div>

                        <button type="submit" class="btn btn-primary w-full bg-zinc-400" :disabled="loading">
                            {{ loading ? 'Generating...' : 'Generate Project' }}
                        </button>
                    </form>
                </li>

                <div class="divider">OR</div>

                <!-- Load Existing -->
                <li class="menu-title">Load Existing</li>
                <li v-if="loadingProjects" class="px-4 text-sm opacity-50">Loading projects...</li>
                <li v-else-if="projectLoadError" class="px-4 text-sm text-error mb-2">{{ projectLoadError }}</li>
                <li>
                    <select
                        class="select select-bordered w-full"
                        v-model="selectedProject"
                        @change="loadProject"
                        :disabled="loadingProjects || projects.length === 0"
                    >
                        <option disabled value="">
                            {{ projects.length === 0 ? 'No projects found' : 'Select a project' }}
                        </option>
                        <option v-for="proj in projects" :key="proj" :value="proj">{{ proj }}</option>
                    </select>
                    <button v-if="projectLoadError" @click="fetchProjects" class="btn btn-xs btn-ghost mt-1 w-full">Retry</button>
                </li>

                <div class="divider"></div>

                <!-- GitHub Login -->
                <li>
                    <button class="btn btn-outline gap-2 w-full bg-zinc-400" @click="$emit('open-github-modal')">
                        <img src="../images/github.svg" class="w-6 h-6" alt="GitHub">
                        GitHub Login
                    </button>
                </li>
            </ul>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, defineEmits, defineProps, computed } from 'vue';
import { api } from '../services/api';

const props = defineProps({
    modelValue: Boolean
});

const emit = defineEmits(['project-selected', 'open-github-modal', 'update:modelValue']);

const drawerOpen = computed({
    get: () => props.modelValue,
    set: (value) => emit('update:modelValue', value)
});

const projectName = ref('');
const prompt = ref('');
const selectedProject = ref('');
const projects = ref([]);
const loading = ref(false); // For generation
const loadingProjects = ref(false);
const projectLoadError = ref(null);

const DEFAULT_PROMPT = "Plan a project named {{PROJECT_NAME}}! Generate at least 10 tasks for the Kanban board covering basic development steps. Provide each task on a new line without any prefix (e.g. [SPRINT BACKLOG]:) so they all go into the **SPRINT BACKLOG** column. Do not include introductory text.";

const loadDefaultPrompt = () => {
    // Auto-fill project name if empty
    if (!projectName.value) {
        projectName.value = "New Project";
    }
    prompt.value = DEFAULT_PROMPT.replace('{{PROJECT_NAME}}', projectName.value);
};

const handleGenerate = async () => {
    if (!projectName.value || !prompt.value) return;
    loading.value = true;
    try {
        await api.generateTasks(projectName.value, prompt.value);
        // Assuming generation automatically sets it as current or we trigger a reload
        emit('project-selected', projectName.value);
        // Refresh project list?
        await fetchProjects();
    } catch (e) {
        alert("Error generating project: " + e.message);
    } finally {
        loading.value = false;
    }
};

const loadProject = () => {
    if (selectedProject.value) {
        emit('project-selected', selectedProject.value);
    }
};

const fetchProjects = async () => {
    loadingProjects.value = true;
    projectLoadError.value = null;
    try {
        const res = await api.getProjects();
        // Adjust based on actual API response structure
        // Assuming res is array of strings
        if (Array.isArray(res)) {
            projects.value = res;
        } else if (res.projects) {
            projects.value = res.projects;
        } else if (res.existingProjects) { // Handle explicit key return
            projects.value = res.existingProjects;
        }

        // Auto-select first project or default
        if (projects.value.length > 0) {
            if (!selectedProject.value) {
                selectedProject.value = projects.value[0];
                loadProject();
            }
        } else if (!selectedProject.value) {
            // No projects exist, default to "Project Name Goes Here" for prototype experience
            selectedProject.value = "Project Name Goes Here";
            loadProject();
        }
    } catch (e) {
        console.error("Failed to load projects", e);
        projectLoadError.value = "Failed to load projects. Backend may be offline.";
        // On error, we might still want to allow creating a new one, so don't lock everything.
    } finally {
        loadingProjects.value = false;
    }
};

onMounted(() => {
    fetchProjects();
});
</script>
