<template>
    <div
        :data-theme="theme"
        class="min-h-screen bg-base-200"
    >

        <ProjectSidebar
            v-model="drawerOpen"
            @project-selected="handleProjectSelected"
            @open-github-modal="showGithubModal = true"
        />

        <div class="drawer-content flex flex-col">
            <!-- Navbar -->
            <div class="navbar bg-base-100 shadow-md mb-8">
                <div class="flex-none">
                    <button
                        @click="drawerOpen = !drawerOpen"
                        aria-label="Toggle Menu"
                        class="btn btn-square btn-ghost drawer-button"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="inline-block w-5 h-5 stroke-current"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
                    </button>
                </div>
                <!-- Brand -->
                <div class="flex-none">
                    <a class="btn btn-ghost text-xl">
                        <img src="./images/robot_head.svg" alt="App Logo" class="w-8 h-8 mr-2" />
                        AI-Driven Kanban
                    </a>
                </div>

                <!-- Spacer & Centered Project Name -->
                <div class="flex-1 flex justify-center">
                    <span
                        v-if="currentProject"
                        class="badge badge-lg badge-primary font-bold"
                    >
                        {{ currentProject }}
                    </span>
                    <span
                        v-else
                        class="text-sm opacity-50"
                    >
                        Select a project
                    </span>
                </div>

                <!-- Theme Toggle -->
                <div class="flex-none">
                    <label class="swap swap-rotate btn btn-ghost btn-circle">
                        <input
                            @change="toggleTheme"
                            :checked="theme === 'dark'"
                            type="checkbox"
                        >

                        <!-- sun icon (show in dark mode) -->
                        <svg class="swap-on fill-current w-6 h-6" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M5.64,17l-.71.71a1,1,0,0,0,0,1.41,1,1,0,0,0,1.41,0l.71-.71A1,1,0,0,0,5.64,17ZM5,12a1,1,0,0,0-1-1H3a1,1,0,0,0,0,2H4A1,1,0,0,0,5,12Zm7-7a1,1,0,0,0,1-1V3a1,1,0,0,0-2,0V4A1,1,0,0,0,12,5ZM5.64,7.05a1,1,0,0,0,.7.29,1,1,0,0,0,.71-.29,1,1,0,0,0,0-1.41l-.71-.71A1,1,0,0,0,4.93,4.93,1,1,0,0,0,4.93,6.34Zm12,.29a1,1,0,0,0,.7-.29l.71-.71a1,1,0,1,0-1.41-1.41L17,5.64a1,1,0,0,0,0,1.41A1,1,0,0,0,17.66,7.34ZM21,11H20a1,1,0,0,0,0,2h1a1,1,0,0,0,0-2Zm-9,8a1,1,0,0,0-1,1v1a1,1,0,0,0,2,0V20A1,1,0,0,0,12,19ZM18.36,17A1,1,0,0,0,17,18.36l.71.71a1,1,0,0,0,1.41,0,1,1,0,0,0,0-1.41ZM12,6.5A5.5,5.5,0,1,0,17.5,12,5.51,5.51,0,0,0,12,6.5Zm0,9A3.5,3.5,0,1,1,15.5,12,3.5,3.5,0,0,1,12,15.5Z"/></svg>

                        <!-- moon icon (show in light mode) -->
                        <svg class="swap-off fill-current w-6 h-6" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M21.64,13a1,1,0,0,0-1.05-.14,8.05,8.05,0,0,1-3.37.73A8.15,8.15,0,0,1,9.08,5.49a8.59,8.59,0,0,1,.25-2A1,1,0,0,0,8,2.36,10.14,10.14,0,1,0,22,14.05,1,1,0,0,0,21.64,13Zm-9.5,6.69A8.14,8.14,0,0,1,7.08,5.22v.27A10.15,10.15,0,0,0,17.22,15.63a9.79,9.79,0,0,0,2.1-.22A8.11,8.11,0,0,1,12.14,19.73Z"/></svg>
                    </label>
                </div>
            </div>

            <!-- Main Content -->
            <main class="container mx-auto px-4">
                <div
                    v-if="loading"
                    class="flex justify-center p-10"
                >
                    <span class="loading loading-spinner loading-lg text-primary"></span>
                </div>

                <div
                    v-else-if="error"
                    role="alert"
                    class="alert alert-error"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    <span>Error: {{ error }}</span>
                </div>

                <KanbanBoard v-else-if="currentProject"
                    :columns="columns"
                    :tasks="tasks"
                    :current-project="currentProject"
                    @task-updated="refreshTasks"
                    @task-deleted="refreshTasks"
                    @task-added="refreshTasks"
                    @decompose="handleDecompose"
                    @generate-code="handleGenerateCode"
                    @query-task="handleQueryTask"
                    @show-notification="showNotification"
                />

            </main>
        </div>

        <CodeGenerationModal
            :is-open="isCodeModalOpen"
            :loading="codeLoading"
            :code="generatedCode"
            :error="codeError"
            @close="isCodeModalOpen = false"
        />

        <TaskQueryModal
            :is-open="isQueryModalOpen"
            :loading="queryLoading"
            :answer="queryAnswer"
            :error="queryError"
            @close="isQueryModalOpen = false"
            @submit="handleQueryTaskSubmit"
        />

        <!-- Global Toast Notification -->
        <div
            v-if="notification"
            class="toast toast-top toast-end z-50 font-bold"
        >
            <div
                :class="`alert alert-${notification.type}`"
            >
                <span>{{ notification.message }}</span>
                <div
                    v-if="notification.details"
                    class="text-xs opacity-80 mt-1 whitespace-pre-wrap">
                    {{ notification.details }}
                </div>
            </div>
        </div>

    </div>
</template>

<script setup>
import { ref } from 'vue';
import KanbanBoard from './components/KanbanBoard.vue';
import ProjectSidebar from './components/ProjectSidebar.vue';
import CodeGenerationModal from './components/modals/CodeGenerationModal.vue';
import TaskQueryModal from './components/modals/TaskQueryModal.vue';
import { api } from './services/api';

const loading = ref(false);
const error = ref(null);
const tasks = ref({});
const currentProject = ref(null);
const showGithubModal = ref(false);
const drawerOpen = ref(false);
const theme = ref('cupcake');

const toggleTheme = () => {
    theme.value = theme.value === 'cupcake' ? 'light' : 'cupcake';
};

// Code Modal State
const isCodeModalOpen = ref(false);
const codeLoading = ref(false);
const generatedCode = ref('');
const codeError = ref('');

// Query Modal State
const isQueryModalOpen = ref(false);
const queryLoading = ref(false);
const queryAnswer = ref('');
const queryError = ref('');
const queryTaskTarget = ref(null);

// Global Notification State
const notification = ref(null);

const showNotification = (message, type = 'info', details = null) => {
    notification.value = { message, type, details };
    setTimeout(() => {
        notification.value = null;
    }, 3000);
};

const columns = ref({
    'SPRINT BACKLOG': 'info',
    'IMPLEMENTATION WIP:3': 'error',
    'TESTING WIP:2': 'warning',
    'REVIEW WIP:2': 'primary',
    'DONE': 'success',
});

const handleProjectSelected = async (projectName) => {
    currentProject.value = projectName;
    await refreshTasks();
};

const refreshTasks = async () => {
    if (!currentProject.value) return;

    try {
        loading.value = true;
        const allTasks = await api.getKanbanTasks(currentProject.value);
        tasks.value = allTasks;
    } catch (e) {
        error.value = e.response?.data?.error || e.message;
    } finally {
        loading.value = false;
    }
};

const handleDecompose = async (task) => {
    if (!confirm(`Are you sure you want to decompose "${task.description}"?`)) return;

    loading.value = true;
    try {
        await api.decomposeTask(task.id, task.description);
        await refreshTasks();
        showNotification("Task decomposed successfully!", "success");
    } catch (e) {
        const errorMsg = e.response?.data?.error || e.message;
        const mainMsg = errorMsg.split(' - Response:')[0];
        const details = errorMsg.includes(' - Response:') ? errorMsg.split(' - Response:')[1] : null;
        showNotification("Failed to decompose task: " + mainMsg, "error", details);
    } finally {
        loading.value = false;
    }
};

const handleGenerateCode = async (task) => {
    isCodeModalOpen.value = true;
    codeLoading.value = true;
    generatedCode.value = '';
    codeError.value = '';

    try {
        const res = await api.generateCode(task.id, task.description);
        if (res.success && res.code) {
            generatedCode.value = res.code;
        } else {
            codeError.value = res.error || "Failed to generate code.";
        }
    } catch (e) {
        codeError.value = e.response?.data?.error || e.message;
    } finally {
        codeLoading.value = false;
        // Refresh to show robot icon
        await refreshTasks();
    }
};

const handleQueryTask = (task) => {
    queryTaskTarget.value = task;
    isQueryModalOpen.value = true;
    queryAnswer.value = '';
    queryError.value = '';
};

const handleQueryTaskSubmit = async (query) => {
    if (!queryTaskTarget.value) return;

    queryLoading.value = true;
    queryAnswer.value = '';
    queryError.value = '';

    try {
        const res = await api.queryTask(queryTaskTarget.value.id, query);
        if (res.success && res.answer) {
            queryAnswer.value = res.answer;
            await refreshTasks();
        } else {
            queryError.value = res.error || "Failed to get an answer.";
        }
    } catch (e) {
        queryError.value = e.response?.data?.error || e.message;
    } finally {
        queryLoading.value = false;
    }
};
</script>
