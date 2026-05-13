<template>
    <dialog
        :class="{ 'modal-open': isOpen }"
        class="modal"
        id="audit_log_modal"
    >
        <div class="modal-box w-11/12 max-w-5xl flex flex-col h-[80vh]">
            <!-- Header -->
            <div class="flex justify-between items-center mb-4">
                <h3 class="font-bold text-xl flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m3 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                    </svg>
                    Audit Log: {{ projectName }}
                </h3>
                <div class="flex gap-2">
                    <button
                        @click="exportCsv"
                        class="btn btn-sm btn-outline btn-accent gap-2"
                        :disabled="!filteredLogs.length || loading"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
                        </svg>
                        Export CSV
                    </button>
                    <button
                        @click="closeModal"
                        class="btn btn-sm btn-circle btn-ghost"
                    >
                        ✕
                    </button>
                </div>
            </div>

            <!-- Controls -->
            <div class="flex flex-col sm:flex-row gap-4 mb-4">
                <input
                    v-model="searchQuery"
                    type="text"
                    class="input input-bordered input-sm w-full sm:max-w-xs"
                    placeholder="Search logs..."
                >
                <select
                    v-model="actionFilter"
                    class="select select-bordered select-sm w-full sm:flex-1"
                >
                    <option value="">All Actions</option>
                    <option
                        v-for="action in uniqueActions"
                        :key="action"
                        :value="action"
                    >
                        {{ action }}
                    </option>
                </select>
                <select
                    v-model="userFilter"
                    class="select select-bordered select-sm w-full sm:flex-1"
                >
                    <option value="">All Users</option>
                    <option
                        v-for="user in uniqueUsers"
                        :key="user"
                        :value="user"
                    >
                        {{ user }}
                    </option>
                </select>
            </div>

            <!-- Error -->
            <div
                v-if="error"
                class="alert alert-error mb-4"
            >
                <span>{{ error }}</span>
            </div>

            <!-- Table content -->
            <div class="flex-1 overflow-x-auto border border-base-300 rounded-box relative">
                <div
                    v-if="loading"
                    class="absolute inset-0 flex items-center justify-center bg-base-100 bg-opacity-50 z-10"
                >
                    <span class="loading loading-spinner loading-lg text-primary"></span>
                </div>

                <table class="table table-sm table-pin-rows">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>User/System</th>
                            <th>Action</th>
                            <th>Task</th>
                            <th>Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="log in filteredLogs"
                            :key="log.id"
                        >
                            <td class="whitespace-nowrap">{{ formatDate(log.created_at) }}</td>
                            <td>{{ log.username || 'System (AI)' }}</td>
                            <td>
                                <div
                                    :class="getActionBadgeClass(log.action)"
                                    class="badge"
                                >
                                    {{ log.action }}
                                </div>
                            </td>
                            <td
                                :title="log.task_title"
                                class="max-w-xs truncate"
                            >
                                {{ log.task_title || `Task #${log.task_id}` }}
                            </td>
                            <td class="max-w-md">
                                <div
                                    v-if="log.old_value || log.new_value"
                                    class="text-xs"
                                >
                                    <span
                                        v-if="log.old_value"
                                        class="line-through opacity-70"
                                    >
                                        {{ log.old_value }}
                                    </span>
                                    <span
                                        v-if="log.old_value && log.new_value"
                                    >
                                        &rarr;
                                    </span>
                                    <span
                                        v-if="log.new_value"
                                        class="font-bold text-success"
                                    >
                                        {{ log.new_value }}
                                    </span>
                                </div>
                                <div
                                    v-if="log.details"
                                    :title="log.details"
                                    class="text-xs mt-1 truncate"
                                >
                                    {{ log.details }}
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!loading && filteredLogs.length === 0">
                            <td colspan="5" class="text-center py-4 text-base-content/50 italic">
                                No audit logs found.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <form method="dialog" class="modal-backdrop">
            <button @click="closeModal">close</button>
        </form>
    </dialog>
</template>

<script setup>
import { ref, watch, computed } from 'vue';
import { api } from '../../services/api';

const props = defineProps({
    isOpen: Boolean,
    projectName: String,
});

const emit = defineEmits(['close']);

const logs = ref([]);
const loading = ref(false);
const error = ref('');

// Filters
const searchQuery = ref('');
const actionFilter = ref('');
const userFilter = ref('');

const fetchLogs = async () => {
    if (!props.projectName) return;

    loading.value = true;
    error.value = '';

    try {
        const res = await api.getProjectHistory(props.projectName);
        if (res.success) {
            logs.value = res.data || [];
        } else {
            error.value = res.error || "Failed to fetch audit log.";
        }
    } catch (err) {
        error.value = err.response?.data?.error || err.message;
    } finally {
        loading.value = false;
    }
};

watch(() => props.isOpen, (newVal) => {
    if (newVal) {
        searchQuery.value = '';
        actionFilter.value = '';
        userFilter.value = '';
        fetchLogs();
    }
});

const closeModal = () => {
    emit('close');
};

const formatDate = (dateString) => {
    if (!dateString) return '';
    const d = new Date(dateString);
    return d.toLocaleString();
};

const getActionBadgeClass = (action) => {
    if (!action) return 'badge-neutral';
    const a = action.toLowerCase();
    if (a.includes('create') || a.includes('add')) return 'badge-success';
    if (a.includes('delete') || a.includes('remove')) return 'badge-error';
    if (a.includes('status')) return 'badge-info';
    if (a.includes('edit') || a.includes('update')) return 'badge-warning';
    if (a.includes('ai') || a.includes('generate')) return 'badge-primary';
    return 'badge-neutral';
};

const uniqueActions = computed(() => {
    const actions = new Set(logs.value.map(l => l.action).filter(Boolean));
    return Array.from(actions).sort();
});

const uniqueUsers = computed(() => {
    const users = new Set(logs.value.map(l => l.username || 'System (AI)'));
    return Array.from(users).sort();
});

const filteredLogs = computed(() => {
    return logs.value.filter(log => {
        // Action filter
        if (actionFilter.value && log.action !== actionFilter.value) {
            return false;
        }

        // User filter
        const user = log.username || 'System (AI)';
        if (userFilter.value && user !== userFilter.value) {
            return false;
        }

        // Search query
        if (searchQuery.value) {
            const query = searchQuery.value.toLowerCase();
            const searchableText = `
                ${log.action || ''} 
                ${user} 
                ${log.task_title || ''} 
                ${log.old_value || ''} 
                ${log.new_value || ''} 
                ${log.details || ''}
            `.toLowerCase();

            if (!searchableText.includes(query)) {
                return false;
            }
        }

        return true;
    });
});

const exportCsv = () => {
    if (!filteredLogs.value.length) return;

    // Headers
    const headers = ['Date', 'User/System', 'Action', 'Task ID', 'Task Title', 'Old Value', 'New Value', 'Details'];

    // Rows
    const rows = filteredLogs.value.map(log => [
        log.created_at || '',
        log.username || 'System (AI)',
        log.action || '',
        log.task_id || '',
        log.task_title || '',
        log.old_value || '',
        log.new_value || '',
        log.details || ''
    ]);

    // Combine into CSV string
    const csvContent = [
        headers.join(','),
        ...rows.map(row => row.map(val => `"${String(val).replaceAll('"', '""')}"`).join(','))
    ].join('\n');

    // Create blob and download link
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.setAttribute('href', url);
    link.setAttribute('download', `audit_log_${props.projectName}_${new Date().toISOString().split('T')[0]}.csv`);
    document.body.appendChild(link);
    link.click();
    link.remove();
};
</script>
