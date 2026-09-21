<template>
    <dialog
        :class="{ 'modal-open': isOpen }"
        class="modal"
        id="dashboard_modal"
    >
        <div class="modal-box w-11/12 max-w-5xl">
            <h3 class="font-bold text-lg mb-4 flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 11-3 0m3 0a1.5 1.5 0 10-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-9.75 0h9.75" />
                </svg>
                Instructor Dashboard
            </h3>

            <!-- Loading -->
            <div
                v-if="loading"
                class="flex justify-center p-10"
            >
                <span class="loading loading-spinner loading-lg text-primary"></span>
            </div>

            <!-- Error -->
            <div
                v-else-if="error"
                class="alert alert-error mb-4"
            >
                {{ error }}
            </div>

            <!-- Content -->
            <div v-else>
                <!-- Tabs -->
                <div class="tabs tabs-boxed mb-4">
                    <a
                        :class="{ 'tab-active': activeTab === 'config' }"
                        @click="activeTab = 'config'"
                        class="tab"
                    >
                        Configuration
                    </a>
                    <a
                        :class="{ 'tab-active': activeTab === 'tawos' }"
                        @click="activeTab = 'tawos'"
                        class="tab"
                    >
                        TAWOS Dataset
                    </a>
                    <a
                        :class="{ 'tab-active': activeTab === 'projects' }"
                        @click="activeTab = 'projects'"
                        class="tab"
                    >
                        Projects
                    </a>
                    <a
                        :class="{ 'tab-active': activeTab === 'teams' }"
                        @click="activeTab = 'teams'"
                        class="tab"
                    >
                        Teams
                    </a>
                    <a
                        :class="{ 'tab-active': activeTab === 'backups' }"
                        @click="activeTab = 'backups'"
                        class="tab"
                    >
                        Backups & Restore
                    </a>
                </div>

                <!-- Tab: Configuration -->
                <div
                    v-if="activeTab === 'config'"
                    class="max-h-[60vh] overflow-y-auto space-y-2"
                >
                    <div
                        v-for="(items, group) in dashboardData.config"
                        :key="group"
                        class="collapse collapse-arrow bg-base-200 rounded-box"
                    >
                        <input
                            :id="'config-group-' + group"
                            :checked="expandedGroups[group]"
                            @change="expandedGroups[group] = !expandedGroups[group]"
                            type="checkbox"
                        >
                        <label
                            :for="'config-group-' + group"
                            class="collapse-title font-bold text-sm block cursor-pointer"
                        >
                            {{ group }}
                            <span class="badge badge-sm badge-ghost ml-2">
                                {{ Object.keys(items).length }}
                            </span>
                        </label>
                        <div class="collapse-content">
                            <table class="table table-xs w-full">
                                <thead>
                                    <tr>
                                        <th class="w-1/2">Key</th>
                                        <th class="w-1/2">Value</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="(value, key) in items"
                                        :key="key"
                                    >
                                        <td class="font-mono text-xs opacity-80">
                                            {{ key }}
                                        </td>
                                        <td>
                                            <span
                                                v-if="isMasked(value)"
                                                class="badge badge-warning badge-sm font-mono"
                                            >
                                                {{ value }}
                                            </span>
                                            <span
                                                v-else-if="isBooleanLike(value)"
                                                :class="value === 'true' ? 'badge-success' : 'badge-error'"
                                                class="badge badge-sm"
                                            >
                                                {{ value }}
                                            </span>
                                            <span
                                                v-else
                                                class="font-mono text-xs"
                                            >
                                                {{ value || '—' }}
                                            </span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Tab: TAWOS Dataset -->
                <div
                    v-if="activeTab === 'tawos'"
                    class="max-h-[60vh] overflow-y-auto"
                >
                    <div
                        v-if="dashboardData.tawos"
                        class="space-y-4"
                    >
                        <!-- Stats Overview -->
                        <div class="stats shadow w-full">
                            <div class="stat">
                                <div class="stat-title">Total Records</div>
                                <div class="stat-value text-primary">
                                    {{ dashboardData.tawos.total?.toLocaleString() }}
                                </div>
                                <div class="stat-desc">Agile issues from TAWOS</div>
                            </div>
                            <div class="stat">
                                <div class="stat-title">Issue Types</div>
                                <div class="stat-value text-secondary">
                                    {{ dashboardData.tawos.types?.length || 0 }}
                                </div>
                                <div class="stat-desc">Distinct categories</div>
                            </div>
                            <div class="stat">
                                <div class="stat-title">Source Projects</div>
                                <div class="stat-value text-accent">
                                    {{ dashboardData.tawos.projects?.length || 0 }}
                                </div>
                                <div class="stat-desc">Open-source projects</div>
                            </div>
                        </div>

                        <!-- TAWOS Dataset Status Banner -->
                        <div
                            class="alert alert-info bg-info/10 border-info/30"
                        >
                            <div class="flex-1">
                                <div class="font-bold flex items-center gap-2">
                                    <span>💾 Local Dataset Mode: Offline-First</span>
                                    <span class="badge badge-sm badge-ghost">Local DB</span>
                                </div>
                                <div class="text-xs opacity-75 mt-1">
                                    Powered by local tawos_issues database table. Sampled via ./setup.sh or php tools/tawos_sql_sampler.php.
                                </div>
                            </div>
                        </div>

                        <!-- TAWOS Search Tool -->
                        <div class="bg-base-200 p-4 rounded-box space-y-3">
                            <div class="flex justify-between items-center">
                                <h4 class="font-bold">Search TAWOS Issues</h4>
                                <span class="text-xs opacity-60">
                                    Searching local database
                                </span>
                            </div>
                            <div class="flex gap-2">
                                <input
                                    v-model="tawosSearchQuery"
                                    @keydown.enter="handleTawosSearch"
                                    id="tawos-search-input"
                                    aria-label="Search TAWOS issues"
                                    type="text"
                                    placeholder="Search by keyword, title, or description (e.g. auth, null pointer, UI)..."
                                    class="input input-sm input-bordered flex-1"
                                />
                                <select
                                    id="tawos-search-type"
                                    aria-label="Filter by issue type"
                                    v-model="tawosSearchType"
                                    class="select select-sm select-bordered"
                                >
                                    <option value="">All Types</option>
                                    <option value="Story">Story</option>
                                    <option value="Bug">Bug</option>
                                    <option value="Task">Task</option>
                                </select>
                                <button
                                    @click="handleTawosSearch"
                                    :disabled="isSearchingTawos || !tawosSearchQuery.trim()"
                                    class="btn btn-sm btn-primary"
                                >
                                    <span
                                        v-if="isSearchingTawos"
                                        class="loading loading-spinner loading-xs"
                                    >
                                    </span>
                                    <span v-else>
                                        Search
                                    </span>
                                </button>
                                <button
                                    v-if="tawosSearchResults || tawosSearchError"
                                    @click="clearTawosSearch"
                                    class="btn btn-sm btn-ghost"
                                >
                                    Clear
                                </button>
                            </div>

                            <div
                                v-if="tawosSearchError"
                                class="alert alert-warning text-xs mt-2 p-2"
                            >
                                <span>{{ tawosSearchError }}</span>
                            </div>

                            <!-- Search Results List -->
                            <div
                                v-if="tawosSearchResults"
                                class="mt-3 space-y-2"
                            >
                                <div class="flex justify-between items-center text-xs opacity-75 px-1">
                                    <span>
                                        Found {{ tawosSearchResults.total }} issues
                                    </span>
                                    <span
                                        class="badge badge-sm"
                                        :class="tawosSearchResults.source === 'external_api' ? 'badge-primary' : 'badge-neutral'"
                                    >
                                        Source: {{ tawosSearchResults.source === 'external_api' ? 'External API (500k+)' : 'Local DB' }}
                                    </span>
                                </div>
                                <div
                                    v-if="tawosSearchResults.items.length === 0"
                                    class="text-sm opacity-50 p-4 text-center"
                                >
                                    No issues matching "{{ tawosSearchQuery }}".
                                </div>
                                <div
                                    v-for="item in tawosSearchResults.items"
                                    :key="item.issue_key"
                                    class="bg-base-100 p-3 rounded-lg border border-base-300 text-sm space-y-1"
                                >
                                    <div class="flex justify-between items-start gap-2">
                                        <div class="font-semibold text-base-content flex items-center gap-2">
                                            <span class="font-mono text-xs opacity-70">{{ item.issue_key }}</span>
                                            <span>{{ item.title }}</span>
                                        </div>
                                        <div class="flex gap-1 shrink-0">
                                            <span :class="getTypeBadgeClass(item.type)" class="badge badge-sm">{{ item.type }}</span>
                                            <span
                                                v-if="item.priority"
                                                class="badge badge-outline badge-sm"
                                            >
                                                {{ item.priority }}
                                            </span>
                                        </div>
                                    </div>
                                    <div
                                        v-if="item.description_text"
                                        class="text-xs opacity-70 line-clamp-2"
                                    >
                                        {{ item.description_text }}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Type Breakdown -->
                        <div class="bg-base-200 p-4 rounded-box">
                            <h4 class="font-bold mb-3">Issue Type Distribution</h4>
                            <div class="flex flex-wrap gap-2">
                                <div
                                    v-for="typeItem in dashboardData.tawos.types"
                                    :key="typeItem.type"
                                    :class="getTypeBadgeClass(typeItem.type)"
                                    class="badge badge-lg gap-2"
                                >
                                    {{ typeItem.type }}
                                    <span class="font-bold">{{ typeItem.count }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Source Projects -->
                        <div class="bg-base-200 p-4 rounded-box">
                            <h4 class="font-bold mb-3">Source Projects</h4>
                            <div class="flex flex-wrap gap-2">
                                <span
                                    v-for="project in dashboardData.tawos.projects"
                                    :key="project"
                                    class="badge badge-outline badge-sm"
                                >
                                    {{ project }}
                                </span>
                            </div>
                        </div>
                    </div>
                    <div
                        v-else
                        class="text-center opacity-50 p-8"
                    >
                        No TAWOS data available. Dataset may not be seeded.
                    </div>
                </div>

                <!-- Tab: Teams -->
                <div
                    v-if="activeTab === 'teams'"
                    class="max-h-[60vh] overflow-y-auto"
                >
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div
                            v-for="team in dashboardData.teams"
                            :key="team.id"
                            class="card bg-base-200 shadow-xl"
                        >
                            <div class="card-body">
                                <h2 class="card-title justify-between">
                                    {{ team.name }}
                                    <div class="badge badge-primary">{{ team.members }} members</div>
                                </h2>
                                <div class="text-sm opacity-70 mb-2">
                                    Created: {{ formatDate(team.created_at) }}
                                </div>
                                <div class="flex flex-col gap-2">
                                    <div class="font-bold text-sm">Projects:</div>
                                    <div class="flex flex-wrap gap-1">
                                        <span
                                            v-for="proj in team.projects"
                                            :key="proj.id"
                                            class="badge badge-outline badge-sm"
                                        >
                                            {{ proj.name }}
                                        </span>
                                        <span v-if="team.projects.length === 0" class="opacity-50 text-xs">No projects</span>
                                    </div>
                                </div>
                                <div class="divider my-2"></div>
                                <div class="text-sm">
                                    <div class="font-bold mb-1">Simulation Config Overrides:</div>
                                    <ul class="list-disc list-inside text-xs">
                                        <li>Min Feedback: {{ team.sim_min_feedback_sec || 'Global' }}</li>
                                        <li>Max Feedback: {{ team.sim_max_feedback_sec || 'Global' }}</li>
                                        <li>Min CR: {{ team.sim_min_cr_sec || 'Global' }}</li>
                                        <li>Max CR: {{ team.sim_max_cr_sec || 'Global' }}</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div
                        v-if="!dashboardData.teams || dashboardData.teams.length === 0"
                        class="text-center opacity-50 p-8"
                    >
                        No teams found.
                    </div>
                </div>

                <!-- Tab: Backups & Restore -->
                <div
                    v-if="activeTab === 'backups'"
                    class="max-h-[60vh] overflow-y-auto space-y-6 p-2"
                >
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Export card -->
                        <div class="card bg-base-200 shadow-lg border border-base-300">
                            <div class="card-body">
                                <h3 class="card-title text-primary flex items-center gap-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
                                    </svg>
                                    Export Database Backup
                                </h3>
                                <p class="text-sm opacity-80 my-2">
                                    Download a database-independent JSON backup file of TAIPO. This contains all platform configurations, teams, users, roles, projects, tasks, requirements, history, and AI metrics.
                                </p>
                                <div class="card-actions justify-end mt-4">
                                    <a
                                        :href="api.getExportBackupUrl()"
                                        download
                                        class="btn btn-primary gap-2"
                                    >
                                        Export JSON Backup
                                    </a>
                                </div>
                            </div>
                        </div>

                        <!-- Import card -->
                        <div class="card bg-base-200 shadow-lg border border-base-300">
                            <div class="card-body">
                                <h3 class="card-title text-secondary flex items-center gap-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" />
                                    </svg>
                                    Restore Database Backup
                                </h3>
                                <p class="text-sm opacity-80 my-2">
                                    Upload a previously exported JSON backup file to restore TAIPO.
                                    <strong class="text-error">Warning: This will completely overwrite all existing database tables. This action cannot be undone.</strong>
                                </p>

                                <div class="form-control w-full mt-2">
                                    <label for="backup-file-input" class="label sr-only">
                                        <span class="label-text">Restore Database Backup File</span>
                                    </label>
                                    <input
                                        id="backup-file-input"
                                        @change="onBackupFileChange"
                                        type="file"
                                        accept=".json"
                                        class="file-input file-input-bordered file-input-sm w-full"
                                    />
                                </div>

                                <div
                                    v-if="importError"
                                    class="alert alert-error text-xs p-2 mt-2"
                                >
                                    {{ importError }}
                                </div>

                                <div
                                    v-if="importSuccess"
                                    class="alert alert-success text-xs p-2 mt-2"
                                >
                                    Database restored successfully! Reloading...
                                </div>

                                <div class="card-actions justify-end mt-4">
                                    <button
                                        @click="triggerRestore"
                                        :disabled="!selectedBackupFile || importing"
                                        class="btn btn-secondary gap-2"
                                    >
                                        <span
                                            v-if="importing"
                                            class="loading loading-spinner loading-xs">
                                        </span>
                                        Restore Backup
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tab: Projects -->
                <div
                    v-if="activeTab === 'projects'"
                    class="max-h-[60vh] overflow-y-auto relative"
                >
                    <div class="flex justify-between items-center mb-4 sticky top-0 bg-base-100 z-10 py-2 border-b border-base-200">
                        <h4 class="font-bold">Managed Projects</h4>
                        <button
                            @click="exportProjectsCsv"
                            class="btn btn-sm btn-outline btn-primary gap-2"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
                            </svg>
                            Export CSV
                        </button>
                    </div>

                    <table
                        v-if="dashboardData.projects?.length"
                        class="table table-zebra w-full"
                    >
                        <thead>
                            <tr class="text-base">
                                <th>Name</th>
                                <th>Team</th>
                                <th>Progress</th>
                                <th>Status</th>
                                <th>Alerts</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="project in dashboardData.projects"
                                :key="project.id"
                            >
                                <td class="font-bold whitespace-nowrap">
                                    {{ project.name }}
                                </td>
                                <td>
                                    <span
                                        v-if="project.team_id"
                                        class="badge badge-info badge-sm"
                                    >
                                        {{ project.team_name || ('Team #' + project.team_id) }}
                                    </span>
                                    <span
                                        v-else
                                        class="opacity-40"
                                    >—</span>
                                </td>
                                <td>
                                    <div v-if="project.metrics" class="flex flex-col gap-1 w-32">
                                        <div class="flex justify-between text-xs">
                                            <span>{{ project.metrics.completion_rate }}%</span>
                                            <span class="opacity-70">{{ project.metrics.done_tasks }}/{{ project.metrics.total_tasks }}</span>
                                        </div>
                                        <progress class="progress progress-success w-full" :value="project.metrics.completion_rate" max="100"></progress>
                                    </div>
                                    <span v-else class="opacity-40">—</span>
                                </td>
                                <td>
                                    <label
                                        :for="'project-active-' + project.id"
                                        class="sr-only"
                                    >
                                        Toggle Simulation Activity for {{ project.name }}
                                    </label>
                                    <input
                                        :id="'project-active-' + project.id"
                                        :checked="project.is_active == 1"
                                        @change="toggleProjectActivity(project, $event)"
                                        type="checkbox"
                                        class="custom-status-toggle scale-90"
                                        title="Toggle Simulation Activity"
                                    >
                                </td>
                                <td>
                                    <div
                                        v-if="project.metrics?.stalled"
                                        class="badge badge-error badge-sm gap-1 cursor-help"
                                        title="No task updates in the last 3 days"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-3 h-3">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                        </svg>
                                        Stalled
                                    </div>
                                    <span v-else class="opacity-40">—</span>
                                </td>
                                <td>
                                    <button
                                        @click="viewBoard(project.name)"
                                        class="btn btn-xs btn-outline btn-secondary"
                                    >
                                        View Board
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <div
                        v-else
                        class="text-center opacity-50 p-8"
                    >
                        No projects found.
                    </div>
                </div>
            </div>

            <div class="modal-action">
                <button
                    @click="closeModal"
                    class="btn"
                >
                    Close
                </button>
            </div>
        </div>
    </dialog>
</template>

<script setup>
import { ref, reactive, watch } from 'vue';
import { api } from '../../services/api';

const props = defineProps({
    isOpen: {
        type: Boolean,
        default: false
    }
});

const emit = defineEmits(['close', 'view-board']);

const loading = ref(false);
const error = ref(null);
const activeTab = ref('config');
const dashboardData = ref({
    config: {},
    tawos: null,
    projects: [],
    teams: []
});

const selectedBackupFile = ref(null);
const importing = ref(false);
const importError = ref(null);
const importSuccess = ref(false);

// TAWOS Search State
const tawosSearchQuery = ref('');
const tawosSearchType = ref('');
const tawosSearchResults = ref(null);
const tawosSearchError = ref(null);
const isSearchingTawos = ref(false);

const handleTawosSearch = async () => {
    const q = tawosSearchQuery.value.trim();
    if (!q) return;
    isSearchingTawos.value = true;
    tawosSearchError.value = null;
    try {
        const filters = {};
        if (tawosSearchType.value) {
            filters.type = tawosSearchType.value;
        }
        const res = await api.searchTawos(q, filters);
        if (res.success) {
            tawosSearchResults.value = res.data;
        } else {
            tawosSearchError.value = res.error || 'Failed to search TAWOS issues';
        }
    } catch (e) {
        console.error('Failed to search TAWOS issues:', e);
        tawosSearchError.value = e?.response?.data?.error || e?.message || 'Error communicating with server';
    } finally {
        isSearchingTawos.value = false;
    }
};

const clearTawosSearch = () => {
    tawosSearchQuery.value = '';
    tawosSearchType.value = '';
    tawosSearchResults.value = null;
    tawosSearchError.value = null;
};

// Track which config groups are expanded (first group open by default)
const expandedGroups = reactive({});

watch(() => props.isOpen, async (newVal) => {
    if (newVal) {
        await fetchDashboard();
    }
});

const fetchDashboard = async () => {
    loading.value = true;
    error.value = null;
    try {
        const res = await api.getDashboard();
        if (res.success) {
            dashboardData.value = res;
            // All groups closed by default
            const groups = Object.keys(res.config || {});
            groups.forEach((g) => {
                expandedGroups[g] = false;
            });
        } else {
            error.value = res.error || 'Failed to load dashboard data.';
        }
    } catch (e) {
        error.value = e.response?.data?.error || e.message;
    } finally {
        loading.value = false;
    }
};

const isMasked = (value) => {
    return typeof value === 'string' && value.includes('•');
};

const isBooleanLike = (value) => {
    return value === 'true' || value === 'false';
};

const getTypeBadgeClass = (type) => {
    const map = {
        'Story': 'badge-primary',
        'Bug': 'badge-error',
        'Task': 'badge-info',
        'Epic': 'badge-secondary',
        'Sub-task': 'badge-accent',
    };
    return map[type] || 'badge-ghost';
};

const formatDate = (dateStr) => {
    if (!dateStr) return '—';
    try {
        return new Date(dateStr).toLocaleDateString();
    } catch {
        return dateStr;
    }
};

const toggleProjectActivity = async (project, event) => {
    const isActive = event.target.checked;
    try {
        await api.toggleProjectActivity(project.id, isActive);
        project.is_active = isActive ? 1 : 0;
    } catch (e) {
        // Revert on failure
        event.target.checked = !isActive;
        console.error('Failed to toggle project activity:', e);
    }
};

const exportProjectsCsv = () => {
    if (!dashboardData.value.projects || !dashboardData.value.projects.length) return;

    const headers = ['Name', 'Team', 'Total Tasks', 'Done Tasks', 'Completion %', 'Stalled', 'Simulation Active', 'Created At'];

    const rows = dashboardData.value.projects.map(p => [
        `"${p.name.replaceAll('"', '""')}"`,
        p.team_name || p.team_id || '',
        p.metrics?.total_tasks || 0,
        p.metrics?.done_tasks || 0,
        p.metrics?.completion_rate || 0,
        p.metrics?.stalled ? 'Yes' : 'No',
        p.is_active ? 'Yes' : 'No',
        p.created_at
    ]);

    const csvContent = [headers.join(','), ...rows.map(r => r.join(','))].join('\n');

    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.setAttribute('href', url);
    link.setAttribute('download', `taipo_projects_export_${new Date().toISOString().split('T')[0]}.csv`);
    document.body.appendChild(link);
    link.click();
    link.remove();
};

const onBackupFileChange = (e) => {
    const files = e.target.files;
    if (files && files.length > 0) {
        selectedBackupFile.value = files[0];
        importError.value = null;
        importSuccess.value = false;
    }
};

const triggerRestore = async () => {
    if (!selectedBackupFile.value) return;

    const confirmed = globalThis.confirm("Are you absolutely sure you want to restore this backup? This will completely replace the current database and wipe out all current projects, tasks, and users.");
    if (!confirmed) return;

    importing.value = true;
    importError.value = null;
    importSuccess.value = false;

    try {
        const res = await api.importBackup(selectedBackupFile.value);
        if (res.success) {
            importSuccess.value = true;
            setTimeout(() => {
                globalThis.location.reload();
            }, 2000);
        } else {
            importError.value = res.error || 'Restore failed.';
        }
    } catch (e) {
        importError.value = e.response?.data?.error || e.message;
    } finally {
        importing.value = false;
    }
};

const viewBoard = (projectName) => {
    emit('view-board', projectName);
};

const closeModal = () => {
    emit('close');
};
</script>
