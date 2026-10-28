<template>
    <div class="flex flex-col gap-4 w-full">
        <!-- In-Board Filter & Search Toolbar -->
        <div class="flex flex-wrap items-center justify-between gap-3 px-2 py-1 bg-base-100/60 backdrop-blur-md rounded-2xl border border-base-300">
            <div class="flex items-center gap-2 flex-1 min-w-[240px] max-w-md">
                <div class="relative w-full">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-base-content/40">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="w-4 h-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                        </svg>
                    </span>
                    <label for="kanban-search-input" class="sr-only">Search tasks</label>
                    <input
                        id="kanban-search-input"
                        v-model="searchQuery"
                        type="text"
                        placeholder="Search tasks by title or description..."
                        class="input input-sm input-bordered w-full pl-9 bg-base-200/60 focus:bg-base-100 transition-all text-xs"
                    />
                </div>
            </div>

            <div class="flex items-center gap-2 flex-wrap">
                <!-- Priority Filter -->
                <div class="flex items-center gap-1 bg-base-200/80 p-1 rounded-xl border border-base-300/60 text-xs">
                    <button
                        v-for="p in priorityFilterOptions"
                        :key="p.value"
                        type="button"
                        @click="selectedPriority = p.value"
                        :class="[
                            'btn btn-xs rounded-lg transition-all',
                            selectedPriority === p.value ? 'btn-primary font-bold shadow-sm' : 'btn-ghost text-base-content/70'
                        ]"
                    >
                        {{ p.label }}
                    </button>
                </div>

                <!-- Only PO Feedback filter -->
                <button
                    type="button"
                    @click="filterPoOnly = !filterPoOnly"
                    :class="[
                        'btn btn-xs rounded-xl gap-1.5 transition-all',
                        filterPoOnly ? 'btn-accent font-bold shadow-sm' : 'btn-ghost border border-base-300/80 text-base-content/70'
                    ]"
                    title="Filter tasks with PO feedback"
                >
                    <span>🤖</span>
                    <span>Feedback Only</span>
                </button>

                <!-- Board View Options (matching taipo_preview.jpg) -->
                <div class="hidden md:flex items-center gap-1.5 pl-2 border-l border-base-300">
                    <span class="btn btn-xs btn-ghost gap-1.5 font-semibold text-base-content/70 pointer-events-none border border-base-300/60 bg-base-200/50 rounded-lg">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
                        </svg>
                        Modern
                    </span>
                    <button
                        @click="searchQuery = ''; selectedPriority = 'all'; filterPoOnly = false"
                        type="button"
                        class="btn btn-xs btn-ghost btn-circle text-base-content/60 hover:text-base-content border border-base-300/60"
                        title="Reset Filters"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                        </svg>
                    </button>
                    <button
                        @click="$emit('task-updated')"
                        type="button"
                        class="btn btn-xs btn-ghost btn-circle text-base-content/60 hover:text-base-content border border-base-300/60"
                        title="Refresh Board"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        <!-- Kanban Columns Container -->
        <div class="flex flex-nowrap overflow-x-auto gap-4.5 pb-4 min-h-[calc(100vh-180px)] custom-scrollbar">
            <div
                v-for="(style, title) in columns"
                :key="title"
                class="min-w-[280px] w-80 shrink-0 flex flex-col bg-base-100/90 backdrop-blur-md rounded-2xl border border-base-300 shadow-xl overflow-hidden"
            >
                <!-- Column Header -->
                <div
                    :class="['p-4 border-b border-base-300 flex items-center justify-between', getColumnHeaderClasses(style)]"
                >
                    <div class="flex flex-col">
                        <div class="flex items-center gap-2">
                            <span class="font-extrabold text-sm tracking-tight">{{ formatColumnTitle(title) }}</span>
                            <span
                                :class="[
                                    'badge badge-sm font-bold shadow-sm',
                                    isColumnFull(title) ? 'badge-error animate-pulse' : 'badge-ghost bg-base-100/50'
                                ]"
                            >
                                {{ getColumnWipText(title) }}
                            </span>
                        </div>
                        <span class="text-[11px] opacity-75 font-medium mt-0.5">{{ getColumnSubtitle(title) }}</span>
                    </div>

                    <!-- Column Options Menu -->
                    <div class="dropdown dropdown-end">
                        <button
                            tabindex="0"
                            type="button"
                            class="btn btn-ghost btn-xs btn-circle opacity-70 hover:opacity-100"
                            aria-label="Column options"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.75a.75.75 0 110-1.5.75.75 0 010 1.5zM12 12.75a.75.75 0 110-1.5.75.75 0 010 1.5zM12 18.75a.75.75 0 110-1.5.75.75 0 010 1.5z" />
                            </svg>
                        </button>
                        <ul class="dropdown-content z-20 menu p-2 shadow-xl bg-base-100 border border-base-300 rounded-xl w-44 text-xs">
                            <li v-if="title.includes('BACKLOG') && canAddTask">
                                <button @mousedown.prevent="openAddTaskModal" @click.prevent="openAddTaskModal" type="button">
                                    ➕ Add Story
                                </button>
                            </li>
                            <li>
                                <button @mousedown.prevent="$emit('task-updated')" @click.prevent="$emit('task-updated')" type="button">
                                    🔄 Refresh Column
                                </button>
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- Add Task Button (Top - Only for Backlog) -->
                <div
                    v-if="title.includes('BACKLOG') && canAddTask"
                    class="p-3 pb-1"
                >
                    <button
                        @click="openAddTaskModal"
                        type="button"
                        class="w-full bg-primary hover:bg-primary-focus text-primary-content font-bold py-2 px-4 rounded-xl shadow-md transition-all duration-200 flex items-center justify-center gap-1.5 text-xs hover:scale-[1.01]"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor" class="w-4 h-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                        Add Task
                    </button>
                </div>

                <!-- Task List (Draggable) -->
                <Draggable
                    v-if="localTasks[title]"
                    v-model="localTasks[title]"
                    @change="onDraggableChange($event, title)"
                    group="tasks"
                    ghost-class="opacity-40"
                    item-key="id"
                    class="flex-1 p-3 flex flex-col gap-3 min-h-[140px] overflow-y-auto custom-scrollbar"
                >
                    <template #item="{ element }">
                        <TaskCard
                            v-if="matchesFilter(element)"
                            :task="element"
                            :status="title"
                            :user-role="userRole"
                            @request-delete="confirmDeleteTask(element)"
                            @toggle-imp="$emit('task-updated')"
                            @task-updated="$emit('task-updated')"
                            @decompose="$emit('decompose', element)"
                            @generate-code="$emit('generate-code', element)"
                            @query-task="$emit('query-task', element)"
                            @request-edit="openEditTaskModal(element)"
                            @request-view="openViewTaskModal(element)"
                        />
                    </template>
                </Draggable>

                <!-- Fallback if tasks[title] is undefined/null -->
                <div
                    v-else
                    class="flex-1 overflow-y-auto p-4 flex items-center justify-center text-base-content/40 italic text-xs"
                >
                    No tasks data
                </div>

                <!-- Add Task Button (Bottom - Only for Backlog, matching taipo_preview.jpg) -->
                <div
                    v-if="title.includes('BACKLOG') && canAddTask"
                    class="p-3 pt-1 mt-auto"
                >
                    <button
                        @click="openAddTaskModal"
                        type="button"
                        class="w-full bg-base-200 hover:bg-base-300 text-base-content font-bold py-2.5 px-4 rounded-xl border border-dashed border-base-content/20 transition-all duration-200 flex items-center justify-center gap-1.5 text-xs hover:border-primary hover:text-primary"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor" class="w-4 h-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                        Add Task
                    </button>
                </div>
            </div>
        </div>

        <TaskModal
            :is-open="isTaskModalOpen"
            :task="taskToEdit"
            :is-read-only="isTaskModalReadOnly"
            :max-title-length="maxTitleLength"
            :max-description-length="maxDescriptionLength"
            @close="closeTaskModal"
            @save="handleSaveTask"
        />

        <Teleport to="body">
            <SafeDeleteModal
                :is-open="isDeleteModalOpen"
                :task-description="taskToDelete?.description || ''"
                @close="isDeleteModalOpen = false"
                @confirm="handleTaskDeleted"
            />
            <ConfirmationModal
                :is-open="isAlertOpen"
                :title="alertTitle"
                :message="alertMessage"
                :is-danger="isAlertDanger"
                is-alert
                @close="isAlertOpen = false"
            />
        </Teleport>
    </div>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import draggable from 'vuedraggable';
const Draggable = draggable as any;
import TaskCard from './TaskCard.vue';
import TaskModal from './modals/TaskModal.vue';
import SafeDeleteModal from './modals/SafeDeleteModal.vue';
import ConfirmationModal from './modals/ConfirmationModal.vue';
import { api } from '../services/api';
import type { Task, TasksByColumn, ColumnsMap, UserRole } from '../types';

const props = withDefaults(
    defineProps<{
        columns: ColumnsMap;
        tasks: TasksByColumn;
        currentProject: string | null;
        maxTitleLength?: number;
        maxDescriptionLength?: number;
        userRole?: UserRole;
    }>(),
    {
        maxTitleLength: 42,
        maxDescriptionLength: 512,
        userRole: 'Product Owner',
    }
);

// Local tasks mirror to avoid direct mutation of props.tasks
const localTasks = ref<TasksByColumn>({});
watch(
    () => props.tasks,
    (val) => {
        localTasks.value = val ? { ...val } : {};
    },
    { immediate: true, deep: true }
);

const emit = defineEmits<{
    (e: 'task-updated'): void;
    (e: 'task-deleted'): void;
    (e: 'task-added'): void;
    (e: 'decompose', task: Task): void;
    (e: 'generate-code', task: Task): void;
    (e: 'show-notification', message: string, type?: 'info' | 'success' | 'warning' | 'error'): void;
    (e: 'query-task', task: Task): void;
    (e: 'request-view', task: Task): void;
}>();

// Modals state
const isTaskModalOpen = ref(false);
const taskToEdit = ref<Task | null>(null);
const isTaskModalReadOnly = ref(false);

const isDeleteModalOpen = ref(false);
const taskToDelete = ref<Task | null>(null);

const isAlertOpen = ref(false);
const alertTitle = ref('');
const alertMessage = ref('');
const alertDanger = ref(false);
const isAlertDanger = computed(() => alertDanger.value);

// Search & Filter state
const searchQuery = ref('');
const selectedPriority = ref<'all' | 'high' | 'medium' | 'low'>('all');
const filterPoOnly = ref(false);

const priorityFilterOptions = [
    { label: 'All', value: 'all' as const },
    { label: '🔥 High', value: 'high' as const },
    { label: '📊 Med', value: 'medium' as const },
    { label: '⬇️ Low', value: 'low' as const },
];

const matchesFilter = (task: Task) => {
    // Search query match
    if (searchQuery.value.trim().length > 0) {
        const query = searchQuery.value.toLowerCase();
        const matchesTitle = (task.title || '').toLowerCase().includes(query);
        const matchesDesc = (task.description || '').toLowerCase().includes(query);
        if (!matchesTitle && !matchesDesc) return false;
    }

    // Priority filter match
    if (selectedPriority.value === 'high' && Number(task.is_important) !== 3) return false;
    if (selectedPriority.value === 'medium' && Number(task.is_important) !== 2) return false;
    if (selectedPriority.value === 'low' && Number(task.is_important) !== 1) return false;

    // PO feedback filter match
    if (filterPoOnly.value && (!task.po_comments || task.po_comments.trim().length === 0)) {
        return false;
    }

    return true;
};

// Only PO and Instructor can add tasks (defaulting to true if role is PO or instructor)
const canAddTask = computed(() => {
    return ['Instructor', 'Product Owner'].includes(props.userRole || 'Product Owner');
});

const parseWipLimit = (columnTitle: string): number => {
    const match = columnTitle.match(/WIP:(\d+)/i);
    return match ? Number.parseInt(match[1], 10) : Number.POSITIVE_INFINITY;
};

const formatColumnTitle = (title: string): string => {
    return title.replace(/WIP:\d+/i, '').trim();
};

const getColumnSubtitle = (title: string): string => {
    const upper = title.toUpperCase();
    if (upper.includes('BACKLOG')) return 'Tasks pending';
    if (upper.includes('IMPLEMENTATION')) return 'Tasks active';
    if (upper.includes('TESTING')) return 'Verification & QA';
    if (upper.includes('REVIEW')) return 'Tasks awaiting feedback';
    if (upper.includes('DONE')) return 'Completed tasks';
    return 'Active stage';
};

const getColumnWipText = (title: string): string => {
    const count = localTasks.value[title]?.length || 0;
    const limit = parseWipLimit(title);
    if (limit === Number.POSITIVE_INFINITY) {
        return `${count}`;
    }
    return `${count} / ${limit}`;
};

const isColumnFull = (title: string): boolean => {
    const limit = parseWipLimit(title);
    if (limit === Number.POSITIVE_INFINITY) return false;
    return (localTasks.value[title]?.length || 0) >= limit;
};

const getColumnHeaderClasses = (style: string): string => {
    const classMap: Record<string, string> = {
        info: 'bg-info/10 text-info border-b-info/20',
        primary: 'bg-primary/10 text-primary border-b-primary/20',
        warning: 'bg-warning/10 text-warning border-b-warning/20',
        error: 'bg-error/10 text-error border-b-error/20',
        success: 'bg-success/10 text-success border-b-success/20',
        neutral: 'bg-neutral/30 text-base-content border-b-base-content/10'
    };
    const normalizedStyle = style === 'danger' ? 'error' : style;
    return classMap[normalizedStyle] || 'bg-base-200 text-base-content';
};

const onDraggableChange = async (event: any, newStatus: string) => {
    let task: Task | null = null;

    if (event.added) {
        task = event.added.element;
    } else if (event.moved) {
        task = event.moved.element;
    }

    const limit = parseWipLimit(newStatus);
    if (limit !== Number.POSITIVE_INFINITY && (localTasks.value[newStatus]?.length || 0) > limit) {
        alertTitle.value = 'WIP Limit Exceeded';
        alertMessage.value = `Cannot move task. The "${formatColumnTitle(newStatus)}" column has reached its Work-In-Progress limit of ${limit}.`;
        alertDanger.value = true;
        isAlertOpen.value = true;
        emit('task-updated');
        return;
    }

    if (task && event.added) {
        try {
            await api.updateStatus(task.id, newStatus, props.currentProject || '');
            emit('show-notification', `Moved #${task.id} to ${formatColumnTitle(newStatus)}`, 'success');
        } catch (e: any) {
            alertTitle.value = 'Error';
            alertMessage.value = e.response?.data?.error || 'Failed to update task status.';
            alertDanger.value = true;
            isAlertOpen.value = true;
            emit('task-updated');
            return;
        }
    }

    // Persist reorder if tasks exist in this column
    const columnTasks = localTasks.value[newStatus] || [];
    const taskIds = columnTasks.map(t => t.id);
    if (taskIds.length > 0 && props.currentProject) {
        try {
            await api.reorderTasks(props.currentProject, newStatus, taskIds);
        } catch (e) {
            console.error('Failed to persist task reorder:', e);
        }
    }
};

const openAddTaskModal = () => {
    taskToEdit.value = null;
    isTaskModalReadOnly.value = false;
    isTaskModalOpen.value = true;
};

const openEditTaskModal = (task: Task) => {
    taskToEdit.value = task;
    isTaskModalReadOnly.value = false;
    isTaskModalOpen.value = true;
};

const openViewTaskModal = (task: Task) => {
    taskToEdit.value = task;
    isTaskModalReadOnly.value = true;
    isTaskModalOpen.value = true;
};

const closeTaskModal = () => {
    isTaskModalOpen.value = false;
    taskToEdit.value = null;
};

const handleSaveTask = async (data: { title: string; description: string; priority: number; id?: number }) => {
    try {
        const taskId = data.id || taskToEdit.value?.id;
        if (taskId) {
            await api.editTask(taskId, data.title, data.description);
            if (data.priority !== undefined) {
                await api.toggleImportance(taskId, data.priority);
            }
            emit('show-notification', 'Task updated successfully', 'success');
        } else {
            if (!props.currentProject) return;
            await api.addTask(props.currentProject, data.title, data.description, data.priority);
            emit('show-notification', 'Task created successfully', 'success');
        }
        closeTaskModal();
        emit('task-updated');
    } catch (e: any) {
        emit('show-notification', e.response?.data?.error || 'Failed to save task', 'error');
    }
};

const confirmDeleteTask = (task: Task) => {
    taskToDelete.value = task;
    isDeleteModalOpen.value = true;
};

const handleTaskDeleted = async () => {
    if (!taskToDelete.value) return;
    try {
        await api.deleteTask(taskToDelete.value.id);
        isDeleteModalOpen.value = false;
        taskToDelete.value = null;
        emit('show-notification', 'Task deleted', 'info');
        emit('task-deleted');
    } catch (e: any) {
        emit('show-notification', e.response?.data?.error || 'Failed to delete task', 'error');
    }
};
</script>
