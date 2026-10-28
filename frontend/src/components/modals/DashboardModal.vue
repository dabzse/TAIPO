<template>
    <dialog
        :class="{ 'modal-open': isOpen }"
        :open="isOpen"
        class="modal z-50"
        id="dashboard_modal"
    >
        <div class="modal-box w-11/12 max-w-5xl flex flex-col h-[85vh] bg-base-100 rounded-2xl border border-base-300 shadow-2xl">
            <h3 class="font-bold text-lg mb-4 flex items-center gap-2 text-primary shrink-0">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="w-6 h-6">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 11-3 0m3 0a1.5 1.5 0 10-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-9.75 0h9.75" />
                </svg>
                Instructor Dashboard & Dataset Analytics
            </h3>

            <!-- Loading -->
            <div
                v-if="loading"
                class="flex-1 flex justify-center items-center p-10 min-h-0"
            >
                <span class="loading loading-spinner loading-lg text-primary"></span>
            </div>

            <!-- Error -->
            <div
                v-else-if="error"
                class="alert alert-error mb-4 text-xs shrink-0"
            >
                {{ error }}
            </div>

            <!-- Content -->
            <div v-else class="flex-1 flex flex-col min-h-0">
                <!-- Tabs -->
                <div class="tabs tabs-boxed mb-4 bg-base-200 p-1 rounded-xl flex gap-1 shrink-0">
                    <a
                        :class="{ 'tab-active bg-primary text-primary-content font-bold shadow-sm': activeTab === 'config' }"
                        @click="activeTab = 'config'"
                        class="tab tab-sm rounded-lg transition-all"
                    >
                        Configuration
                    </a>
                    <a
                        :class="{ 'tab-active bg-primary text-primary-content font-bold shadow-sm': activeTab === 'tawos' }"
                        @click="activeTab = 'tawos'"
                        class="tab tab-sm rounded-lg transition-all"
                    >
                        TAWOS Dataset
                    </a>
                    <a
                        :class="{ 'tab-active bg-primary text-primary-content font-bold shadow-sm': activeTab === 'projects' }"
                        @click="activeTab = 'projects'"
                        class="tab tab-sm rounded-lg transition-all"
                    >
                        Projects
                    </a>
                    <a
                        :class="{ 'tab-active bg-primary text-primary-content font-bold shadow-sm': activeTab === 'teams' }"
                        @click="activeTab = 'teams'"
                        class="tab tab-sm rounded-lg transition-all"
                    >
                        Teams
                    </a>
                    <a
                        :class="{ 'tab-active bg-primary text-primary-content font-bold shadow-sm': activeTab === 'backups' }"
                        @click="activeTab = 'backups'"
                        class="tab tab-sm rounded-lg transition-all"
                    >
                        Backups & Restore
                    </a>
                </div>

                <!-- Tab: Configuration -->
                <div
                    v-if="activeTab === 'config'"
                    class="flex-1 overflow-y-auto min-h-0 space-y-2 custom-scrollbar pr-1"
                >
                    <div
                        v-for="(items, group) in dashboardData.config"
                        :key="group"
                        class="collapse collapse-arrow bg-base-200/60 border border-base-300 rounded-xl"
                    >
                        <input
                            :id="'config-group-' + group"
                            :checked="expandedGroups[group]"
                            @change="expandedGroups[group] = !expandedGroups[group]"
                            type="checkbox"
                        >
                        <label
                            :for="'config-group-' + group"
                            class="collapse-title font-bold text-xs block cursor-pointer"
                        >
                            {{ group }}
                            <span class="badge badge-xs badge-ghost ml-2">
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
                                        <td class="font-mono text-[11px] opacity-80">
                                            {{ key }}
                                        </td>
                                        <td>
                                            <span
                                                v-if="isMasked(value)"
                                                class="badge badge-warning badge-xs font-mono"
                                            >
                                                {{ value }}
                                            </span>
                                            <span
                                                v-else-if="isBooleanLike(value)"
                                                :class="value === 'true' ? 'badge-success' : 'badge-error'"
                                                class="badge badge-xs"
                                            >
                                                {{ value }}
                                            </span>
                                            <span
                                                v-else
                                                class="font-mono text-[11px]"
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
                    class="flex-1 overflow-y-auto min-h-0 space-y-4 custom-scrollbar pr-1"
                >
                    <div
                        v-if="dashboardData.tawos"
                        class="space-y-4"
                    >
                        <!-- Stats Overview -->
                        <div class="stats shadow-sm w-full bg-base-200/70 border border-base-300">
                            <div class="stat py-3">
                                <div class="stat-title text-xs">Total Records</div>
                                <div class="stat-value text-primary text-xl font-bold">
                                    {{ dashboardData.tawos.total?.toLocaleString() }}
                                </div>
                                <div class="stat-desc text-[11px]">Agile issues from TAWOS</div>
                            </div>
                            <div class="stat py-3">
                                <div class="stat-title text-xs">Issue Types</div>
                                <div class="stat-value text-secondary text-xl font-bold">
                                    {{ dashboardData.tawos.types?.length || 0 }}
                                </div>
                                <div class="stat-desc text-[11px]">Distinct categories</div>
                            </div>
                            <div class="stat py-3">
                                <div class="stat-title text-xs">Source Projects</div>
                                <div class="stat-value text-accent text-xl font-bold">
                                    {{ dashboardData.tawos.projects?.length || 0 }}
                                </div>
                                <div class="stat-desc text-[11px]">Curated open-source repos</div>
                            </div>
                        </div>

                        <!-- TAWOS Dataset Status Banner -->
                        <div class="alert alert-info bg-info/10 border-info/30 text-xs">
                            <div class="flex-1">
                                <div class="font-bold flex items-center gap-2">
                                    <span>💾 Local Dataset Mode: Offline-First</span>
                                    <span class="badge badge-xs badge-ghost">Local DB</span>
                                </div>
                                <div class="text-[11px] opacity-75 mt-0.5">
                                    Powered by local tawos_issues table. Sampled via ./setup.sh or php tools/tawos_sql_sampler.php.
                                </div>
                            </div>
                        </div>

                        <!-- TAWOS Search Tool -->
                        <div class="bg-base-200/60 p-4 rounded-xl border border-base-300 space-y-3">
                            <div class="flex justify-between items-center">
                                <h4 class="font-bold text-xs uppercase tracking-wider">Search TAWOS Issues</h4>
                                <span class="text-[11px] opacity-60">Searching local database</span>
                            </div>
                            <div class="flex gap-2">
                                <input
                                    v-model="tawosSearchQuery"
                                    @keydown.enter="handleTawosSearch"
                                    id="tawos-search-input"
                                    aria-label="Search TAWOS issues"
                                    type="text"
                                    placeholder="Search by keyword, title, or description (e.g. auth, null pointer, UI)..."
                                    class="input input-sm input-bordered flex-1 text-xs"
                                />
                                <select
                                    id="tawos-search-type"
                                    aria-label="Filter by issue type"
                                    v-model="tawosSearchType"
                                    class="select select-sm select-bordered text-xs"
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
                                    type="button"
                                >
                                    <span v-if="isSearchingTawos" class="loading loading-spinner loading-xs"></span>
                                    <span v-else>Search</span>
                                </button>
                                <button
                                    v-if="tawosSearchResults || tawosSearchError"
                                    @click="clearTawosSearch"
                                    class="btn btn-sm btn-ghost text-xs"
                                    type="button"
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
                                    <span>Found {{ tawosSearchResults.total }} issues</span>
                                    <span class="badge badge-xs badge-neutral">Local DB</span>
                                </div>
                                <div
                                    v-if="tawosSearchResults.items.length === 0"
                                    class="text-xs opacity-50 p-4 text-center italic"
                                >
                                    No issues matching "{{ tawosSearchQuery }}".
                                </div>
                                <div
                                    v-for="item in tawosSearchResults.items"
                                    :key="item.issue_key"
                                    class="bg-base-100 p-3 rounded-xl border border-base-300 text-xs space-y-1"
                                >
                                    <div class="flex justify-between items-start gap-2">
                                        <div class="font-semibold text-base-content flex items-center gap-2">
                                            <span class="font-mono opacity-70">{{ item.issue_key }}</span>
                                            <span>{{ item.title }}</span>
                                        </div>
                                        <div class="flex gap-1 shrink-0">
                                            <span :class="getTypeBadgeClass(item.type)" class="badge badge-xs">{{ item.type }}</span>
                                        </div>
                                    </div>
                                    <div
                                        v-if="item.description_text"
                                        class="opacity-70 line-clamp-2"
                                    >
                                        {{ item.description_text }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div
                        v-else
                        class="text-center opacity-50 p-8 text-xs italic"
                    >
                        No TAWOS data available. Dataset may not be seeded.
                    </div>
                </div>

                <!-- Tab: Teams -->
                <div
                    v-if="activeTab === 'teams'"
                    class="flex-1 overflow-y-auto min-h-0 custom-scrollbar pr-1"
                >
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div
                            v-for="team in dashboardData.teams"
                            :key="team.id"
                            class="card bg-base-200/60 border border-base-300 shadow-sm"
                        >
                            <div class="card-body p-4">
                                <h4 class="card-title text-sm justify-between">
                                    {{ team.name }}
                                    <div class="badge badge-primary badge-xs">{{ team.members }} members</div>
                                </h4>
                                <div class="text-[11px] opacity-70 mb-1">
                                    Created: {{ formatDate(team.created_at) }}
                                </div>
                                <div class="flex flex-col gap-1">
                                    <div class="font-bold text-xs">Projects:</div>
                                    <div class="flex flex-wrap gap-1">
                                        <span
                                            v-for="proj in team.projects"
                                            :key="proj.id"
                                            class="badge badge-outline badge-xs"
                                        >
                                            {{ proj.name }}
                                        </span>
                                        <span v-if="team.projects?.length === 0" class="opacity-50 text-xs italic">No projects</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div
                        v-if="!dashboardData.teams || dashboardData.teams.length === 0"
                        class="text-center opacity-50 p-8 text-xs italic"
                    >
                        No teams found.
                    </div>
                </div>

                <!-- Tab: Backups & Restore -->
                <div
                    v-if="activeTab === 'backups'"
                    class="flex-1 overflow-y-auto min-h-0 space-y-6 p-2 custom-scrollbar"
                >
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- Export card -->
                        <div class="card bg-base-200/60 border border-base-300 shadow-sm">
                            <div class="card-body p-5">
                                <h4 class="card-title text-primary text-sm flex items-center gap-2">
                                    Export Database Backup
                                </h4>
                                <p class="text-xs opacity-80 my-2 leading-relaxed">
                                    Download a JSON backup of TAIPO (configurations, teams, users, roles, projects, tasks, requirements, history, and AI metrics).
                                </p>
                                <div class="card-actions justify-end mt-4">
                                    <a
                                        :href="api.getExportBackupUrl()"
                                        download
                                        class="btn btn-primary btn-sm gap-2 font-bold"
                                    >
                                        Export JSON Backup
                                    </a>
                                </div>
                            </div>
                        </div>

                        <!-- Import card -->
                        <div class="card bg-base-200/60 border border-base-300 shadow-sm">
                            <div class="card-body p-5">
                                <h4 class="card-title text-secondary text-sm flex items-center gap-2">
                                    Restore Database Backup
                                </h4>
                                <p class="text-xs opacity-80 my-2 leading-relaxed">
                                    Upload an exported JSON backup file to restore TAIPO database state.
                                    <strong class="text-error block mt-1">Warning: Completely overwrites all existing database tables.</strong>
                                </p>

                                <div class="form-control w-full mt-2">
                                    <label for="backup-file-input" class="sr-only">Restore Database Backup File</label>
                                    <input
                                        id="backup-file-input"
                                        @change="onBackupFileChange"
                                        type="file"
                                        accept=".json"
                                        class="file-input file-input-bordered file-input-sm w-full text-xs"
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
                                        class="btn btn-secondary btn-sm gap-2 font-bold"
                                        type="button"
                                    >
                                        <span v-if="importing" class="loading loading-spinner loading-xs"></span>
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
                    class="flex-1 overflow-y-auto min-h-0 relative custom-scrollbar pr-1"
                >
                    <div class="flex justify-between items-center mb-4 sticky top-0 bg-base-100 z-10 py-2 border-b border-base-300">
                        <h4 class="font-bold text-xs uppercase tracking-wider">Managed Projects</h4>
                        <button
                            @click="exportProjectsCsv"
                            class="btn btn-sm btn-outline btn-primary gap-2 text-xs"
                            type="button"
                        >
                            Export CSV
                        </button>
                    </div>

                    <table
                        v-if="dashboardData.projects?.length"
                        class="table table-zebra table-xs w-full"
                    >
                        <thead>
                            <tr>
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
                                        class="badge badge-info badge-xs"
                                    >
                                        {{ project.team_name || ('Team #' + project.team_id) }}
                                    </span>
                                    <span v-else class="opacity-40">—</span>
                                </td>
                                <td>
                                    <div v-if="project.metrics" class="flex flex-col gap-1 w-28">
                                        <div class="flex justify-between text-[10px]">
                                            <span>{{ project.metrics.completion_rate }}%</span>
                                            <span class="opacity-70">{{ project.metrics.done_tasks }}/{{ project.metrics.total_tasks }}</span>
                                        </div>
                                        <progress class="progress progress-success w-full h-1" :value="project.metrics.completion_rate" max="100"></progress>
                                    </div>
                                    <span v-else class="opacity-40">—</span>
                                </td>
                                <td>
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
                                        class="badge badge-error badge-xs gap-1 cursor-help"
                                        title="No task updates in the last 3 days"
                                    >
                                        Stalled
                                    </div>
                                    <span v-else class="opacity-40">—</span>
                                </td>
                                <td>
                                    <button
                                        @click="viewBoard(project.name)"
                                        class="btn btn-xs btn-outline btn-secondary"
                                        type="button"
                                    >
                                        View Board
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <div
                        v-else
                        class="text-center opacity-50 p-8 text-xs italic"
                    >
                        No projects found.
                    </div>
                </div>
            </div>

            <div class="modal-action mt-auto pt-3 shrink-0">
                <button
                    @click="closeModal"
                    class="btn btn-sm px-6"
                    type="button"
                >
                    Close
                </button>
            </div>
        </div>
        <form method="dialog" class="modal-backdrop">
            <button @click="closeModal" type="button">close</button>
        </form>
    </dialog>
</template>

<script setup lang="ts">
import { ref, reactive, watch } from 'vue';
import { api } from '../../services/api';

const props = withDefaults(
    defineProps<{
        isOpen?: boolean;
    }>(),
    {
        isOpen: false,
    }
);

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'view-board', projectName: string): void;
}>();

const loading = ref(false);
const error = ref<string | null>(null);
const activeTab = ref<'config' | 'tawos' | 'projects' | 'teams' | 'backups'>('config');
const dashboardData = ref<any>({
    config: {},
    tawos: null,
    projects: [],
    teams: []
});

const selectedBackupFile = ref<File | null>(null);
const importing = ref(false);
const importError = ref<string | null>(null);
const importSuccess = ref(false);

// TAWOS Search State
const tawosSearchQuery = ref('');
const tawosSearchType = ref('');
const tawosSearchResults = ref<any>(null);
const tawosSearchError = ref<string | null>(null);
const isSearchingTawos = ref(false);

const handleTawosSearch = async () => {
    const q = tawosSearchQuery.value.trim();
    if (!q) return;
    isSearchingTawos.value = true;
    tawosSearchError.value = null;
    try {
        const filters: Record<string, any> = {};
        if (tawosSearchType.value) {
            filters.type = tawosSearchType.value;
        }
        const res: any = await api.searchTawos(q, filters);
        if (res.success) {
            tawosSearchResults.value = res.data;
        } else {
            tawosSearchError.value = res.error || 'Failed to search TAWOS issues';
        }
    } catch (e: any) {
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

const expandedGroups = reactive<Record<string, boolean>>({});

watch(() => props.isOpen, async (newVal) => {
    if (newVal) {
        await fetchDashboard();
    }
});

const fetchDashboard = async () => {
    loading.value = true;
    error.value = null;
    try {
        const res: any = await api.getDashboard();
        if (res.success) {
            dashboardData.value = res;
            const groups = Object.keys(res.config || {});
            groups.forEach((g) => {
                expandedGroups[g] = false;
            });
        } else {
            error.value = res.error || 'Failed to load dashboard data.';
        }
    } catch (e: any) {
        error.value = e.response?.data?.error || e.message;
    } finally {
        loading.value = false;
    }
};

const isMasked = (value: any) => {
    return typeof value === 'string' && value.includes('•');
};

const isBooleanLike = (value: any) => {
    return value === 'true' || value === 'false';
};

const getTypeBadgeClass = (type: string) => {
    const map: Record<string, string> = {
        Story: 'badge-primary',
        Bug: 'badge-error',
        Task: 'badge-info',
        Epic: 'badge-secondary',
        'Sub-task': 'badge-accent',
    };
    return map[type] || 'badge-ghost';
};

const formatDate = (dateStr?: string) => {
    if (!dateStr) return '—';
    try {
        return new Date(dateStr).toLocaleDateString();
    } catch {
        return dateStr;
    }
};

const toggleProjectActivity = async (project: any, event: any) => {
    const isActive = event.target.checked;
    try {
        await api.toggleProjectActivity(project.id, isActive);
        project.is_active = isActive ? 1 : 0;
    } catch (e) {
        event.target.checked = !isActive;
        console.error('Failed to toggle project activity:', e);
    }
};

const exportProjectsCsv = () => {
    if (!dashboardData.value.projects || !dashboardData.value.projects.length) return;

    const headers = ['Name', 'Team', 'Total Tasks', 'Done Tasks', 'Completion %', 'Stalled', 'Simulation Active', 'Created At'];

    const rows = dashboardData.value.projects.map((p: any) => [
        `"${p.name.replaceAll('"', '""')}"`,
        p.team_name || p.team_id || '',
        p.metrics?.total_tasks || 0,
        p.metrics?.done_tasks || 0,
        p.metrics?.completion_rate || 0,
        p.metrics?.stalled ? 'Yes' : 'No',
        p.is_active ? 'Yes' : 'No',
        p.created_at
    ]);

    const csvContent = [headers.join(','), ...rows.map((r: any) => r.join(','))].join('\n');

    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.setAttribute('href', url);
    link.setAttribute('download', `taipo_projects_export_${new Date().toISOString().split('T')[0]}.csv`);
    document.body.appendChild(link);
    link.click();
    link.remove();
};

const onBackupFileChange = (e: any) => {
    const files = e.target.files;
    if (files && files.length > 0) {
        selectedBackupFile.value = files[0];
        importError.value = null;
        importSuccess.value = false;
    }
};

const triggerRestore = async () => {
    if (!selectedBackupFile.value) return;

    const confirmed = globalThis.confirm('Are you absolutely sure you want to restore this backup? This will completely replace the current database and wipe out all current projects, tasks, and users.');
    if (!confirmed) return;

    importing.value = true;
    importError.value = null;
    importSuccess.value = false;

    try {
        const res: any = await api.importBackup(selectedBackupFile.value);
        if (res.success) {
            importSuccess.value = true;
            setTimeout(() => {
                globalThis.location.reload();
            }, 2000);
        } else {
            importError.value = res.error || 'Restore failed.';
        }
    } catch (e: any) {
        importError.value = e.response?.data?.error || e.message;
    } finally {
        importing.value = false;
    }
};

const viewBoard = (projectName: string) => {
    emit('view-board', projectName);
};

const closeModal = () => {
    emit('close');
};
</script>
