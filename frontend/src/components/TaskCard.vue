<template>
    <div
        :class="{
            'border-yellow-500': priority === 1,
            'border-orange-500': priority === 2,
            'border-red-500': priority === 3,
            'border-base-300': !priority,
            'opacity-50': task.is_subtask,
        }"
        draggable="true"
        class="card bg-base-200/90 hover:bg-base-200 shadow-sm hover:shadow-lg transition-all duration-200 cursor-grab active:cursor-grabbing border-l-4 rounded-xl group"
    >
        <div
            @dblclick="enableView"
            class="card-body p-3.5 flex flex-col gap-2.5"
        >
            <!-- Card Header: ID, Tag/Badge, Priority & Dropdown -->
            <div class="flex items-center justify-between gap-2">
                <!-- Task ID & Ticket Icon -->
                <div class="flex items-center gap-1.5 text-xs font-semibold text-base-content/60">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="w-3.5 h-3.5 text-primary">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 7.5l-9-5.25L3 7.5m18 0l-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9" />
                    </svg>
                    <span>#{{ task.id }}</span>
                </div>

                <!-- Priority Stars & Quick Action Menu -->
                <div class="flex items-center gap-1.5 ml-auto">
                    <!-- Priority Stars (Interactive) -->
                    <div
                        v-if="canChangePriority"
                        @mouseleave="hoverPriority = 0"
                        class="flex space-x-0.5 bg-base-100/80 rounded-md p-0.5 border border-base-300/60 shadow-inner"
                        title="Set Priority"
                    >
                        <button
                            v-for="i in 3"
                            :key="i"
                            type="button"
                            @click.stop="togglePriority(i)"
                            @mouseover="hoverPriority = i"
                            class="btn btn-ghost btn-xs btn-circle w-4 h-4 min-h-0 p-0 hover:bg-transparent"
                            :aria-label="`Set Priority ${i}`"
                        >
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                class="h-3.5 w-3.5 transition-colors"
                                viewBox="0 0 24 24"
                                :fill="
                                    hoverPriority >= i || (!hoverPriority && priority >= i)
                                        ? getStarColor(i)
                                        : 'none'
                                "
                                :stroke="
                                    hoverPriority >= i || (!hoverPriority && priority >= i)
                                        ? getStarColor(i)
                                        : 'currentColor'
                                "
                                stroke-width="2"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.519 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.519-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"
                                />
                            </svg>
                        </button>
                    </div>

                    <!-- Read-only priority stars when disabled -->
                    <div
                        v-else-if="priority > 0"
                        class="flex space-x-0.5 opacity-60"
                    >
                        <span
                            v-for="i in 3"
                            :key="i"
                            class="w-4 h-4 flex items-center justify-center"
                        >
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                class="h-3 w-3"
                                viewBox="0 0 24 24"
                                :fill="priority >= i ? getStarColor(i) : 'none'"
                                :stroke="priority >= i ? getStarColor(i) : 'currentColor'"
                                stroke-width="2"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.519 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.519-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"
                                />
                            </svg>
                        </span>
                    </div>

                    <!-- Dropdown Options Menu -->
                    <div class="dropdown dropdown-end">
                        <button
                            tabindex="0"
                            @click.stop
                            type="button"
                            class="btn btn-ghost btn-xs btn-circle text-base-content/60 hover:text-base-content"
                            aria-label="Task options"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.75a.75.75 0 110-1.5.75.75 0 010 1.5zM12 12.75a.75.75 0 110-1.5.75.75 0 010 1.5zM12 18.75a.75.75 0 110-1.5.75.75 0 010 1.5z" />
                            </svg>
                        </button>
                        <ul class="dropdown-content z-30 menu p-2 shadow-xl bg-base-100 border border-base-300 rounded-xl w-52 text-xs">
                            <li v-if="canEdit">
                                <button @mousedown.prevent="enableEdit" @click.prevent="enableEdit" type="button">
                                    ✏️ Edit
                                </button>
                            </li>
                            <li v-if="!canEdit">
                                <button @mousedown.prevent="enableView" @click.prevent="enableView" type="button">
                                    👁️ View
                                </button>
                            </li>
                            <li v-if="canDecompose">
                                <button @mousedown.prevent="$emit('decompose', task)" @click.prevent="$emit('decompose', task)" type="button">
                                    🔨 Decompose Story
                                </button>
                            </li>
                            <li v-if="canGenerateCode">
                                <button @mousedown.prevent="$emit('generate-code', task)" @click.prevent="$emit('generate-code', task)" type="button">
                                    💻 Generate Code
                                </button>
                            </li>
                            <li v-if="canAskAi">
                                <button @mousedown.prevent="$emit('query-task', task)" @click.prevent="$emit('query-task', task)" type="button">
                                    ❓ Ask AI
                                </button>
                            </li>
                            <li v-if="canDelete">
                                <button @mousedown.prevent="requestDelete" @click.prevent="requestDelete" type="button" class="text-error border-b border-base-content/10 mb-1 pb-2">
                                    🗑️ Delete
                                </button>
                            </li>
                            <li class="px-2 pt-1 pb-0 line-clamp-2 leading-tight">
                                <span class="text-[10px] text-warning px-0 cursor-default opacity-90 pointer-events-none hover:bg-transparent lowercase text-center">
                                    *AI features send data to Gemini API. Avoid PII.
                                </span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Task Title -->
            <div class="font-bold text-sm tracking-tight text-base-content line-clamp-2">
                {{ task.title || 'Untitled' }}
            </div>

            <!-- Badges Row: Category, Priority, Technical Task, Subtask Info -->
            <div class="flex flex-wrap items-center gap-1.5">
                <!-- Category Tag (e.g. Frontend, Backend, UI/UX) -->
                <span :class="categoryBadge.classes" class="badge badge-xs font-semibold py-1 px-2">
                    {{ categoryBadge.label }}
                </span>

                <!-- Priority Pill Badge (matching taipo_preview.jpg) -->
                <span
                    v-if="priorityBadge"
                    :class="priorityBadge.classes"
                    class="badge badge-xs gap-1 font-semibold py-1 px-2 shadow-xs"
                >
                    <span>{{ priorityBadge.icon }}</span>
                    <span>{{ priorityBadge.label }}</span>
                </span>

                <!-- Technical Task Badge -->
                <span v-if="task.is_subtask" class="badge badge-neutral badge-xs font-semibold">
                    Technical Task
                </span>

                <!-- Parent Reference -->
                <span
                    v-if="task.is_subtask && task.parent_id"
                    class="badge badge-info badge-xs"
                    title="Subtask of parent story"
                >
                    🔗 #{{ task.parent_id }}
                </span>

                <!-- Subtask Counter for Parent Story -->
                <span
                    v-if="!task.is_subtask && (task.subtaskCount ?? 0) > 0"
                    class="badge badge-success badge-xs font-semibold"
                    title="Number of decomposed subtasks"
                >
                    📋 {{ task.subtaskCount }} subtask{{ task.subtaskCount !== 1 ? 's' : '' }}
                </span>
            </div>

            <!-- Task Description (Brief preview) -->
            <p v-if="task.description" class="text-xs text-base-content/75 line-clamp-3 leading-relaxed whitespace-pre-wrap">
                {{ task.description }}
            </p>

            <!-- Subtask Progress Bar (if parent task has subtasks) -->
            <div v-if="!task.is_subtask && (task.subtaskCount ?? 0) > 0" class="space-y-1">
                <div class="flex items-center justify-between text-[11px] text-base-content/60 font-semibold">
                    <span>Progress</span>
                    <span>{{ progressPercentage }}%</span>
                </div>
                <div class="w-full bg-base-300 rounded-full h-1.5 overflow-hidden">
                    <div
                        class="h-full bg-primary rounded-full transition-all duration-300"
                        :style="{ width: `${progressPercentage}%` }"
                    ></div>
                </div>
            </div>

            <!-- AI Status Badges: PO Feedback & Generated Code -->
            <div v-if="task.po_comments || task.generated_code" class="flex flex-wrap items-center gap-1.5 pt-1">
                <!-- PO Feedback Signal -->
                <button
                    v-if="task.po_comments"
                    @click.stop="enableView"
                    type="button"
                    class="badge badge-accent badge-xs gap-1 py-1.5 px-2 animate-pulse-subtle cursor-pointer hover:scale-105 transition-transform font-bold"
                    title="TAIPO Feedback available"
                >
                    <span>🤖</span>
                    <span>PO Feedback</span>
                </button>

                <!-- Code Generated Badge -->
                <button
                    v-if="task.generated_code"
                    @click.stop="$emit('generate-code', task)"
                    type="button"
                    class="badge badge-neutral badge-xs gap-1 py-1.5 px-2 cursor-pointer hover:scale-105 transition-transform font-bold"
                    title="View generated code"
                >
                    <span>💻</span>
                    <span>Code Generated</span>
                </button>
            </div>

            <!-- Footer: Assignee Avatar + Deadline / Status -->
            <div class="flex items-center justify-between pt-2 border-t border-base-300/60 mt-auto text-xs text-base-content/60">
                <div class="flex items-center gap-1.5">
                    <div
                        class="w-5 h-5 rounded-full flex items-center justify-center font-bold text-[10px] text-white"
                        :style="{ backgroundColor: assigneeColor }"
                    >
                        {{ assigneeInitials }}
                    </div>
                    <span class="text-[11px] font-medium">{{ assigneeName }}</span>
                </div>
                <div class="text-[11px] text-base-content/50">
                    {{ formattedDate }}
                </div>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue';
import type { Task, PriorityLevel, UserRole } from '../types';
import { api } from '../services/api';

const props = withDefaults(
    defineProps<{
        task: Task;
        status?: string;
        userRole?: UserRole;
    }>(),
    {
        userRole: 'Product Owner',
    }
);

const emit = defineEmits<{
    (e: 'toggle-imp'): void;
    (e: 'request-delete', task: Task): void;
    (e: 'task-updated'): void;
    (e: 'decompose', task: Task): void;
    (e: 'generate-code', task: Task): void;
    (e: 'query-task', task: Task): void;
    (e: 'request-edit', task: Task): void;
    (e: 'request-view', task: Task): void;
}>();

// --- Role helpers ---
const isPo = computed(() => ['Instructor', 'Product Owner'].includes(props.userRole || 'Product Owner'));
const effectiveStatus = computed(() => props.status || props.task?.status || '');
const isDone = computed(() => effectiveStatus.value === 'DONE');
const isBacklog = computed(() => effectiveStatus.value.includes('BACKLOG'));
const isWip = computed(() => effectiveStatus.value.includes('WIP'));

// --- Action visibility based on role + status ---
const canEdit = computed(() => !isDone.value);
const canDecompose = computed(() => isPo.value && isBacklog.value);
const canGenerateCode = computed(() => isBacklog.value || isWip.value);
const canAskAi = computed(() => true);
const canDelete = computed(() => isPo.value && !isDone.value);
const canChangePriority = computed(() => !isDone.value);

const priority = computed<PriorityLevel>(() => {
    const val = Number(props.task.is_important) || 0;
    return (val >= 1 && val <= 3 ? val : 0) as PriorityLevel;
});

const hoverPriority = ref(0);

const priorityBadge = computed(() => {
    if (priority.value === 3) {
        return { label: 'High', icon: '🔥', classes: 'bg-error/15 text-error border-error/30' };
    }
    if (priority.value === 2) {
        return { label: 'Medium', icon: '📊', classes: 'bg-warning/15 text-warning border-warning/30' };
    }
    if (priority.value === 1) {
        return { label: 'Low', icon: '⬇️', classes: 'bg-info/15 text-info border-info/30' };
    }
    return null;
});

// Category badge derivation (e.g., UI, Backend, Frontend, DevOps)
const categoryBadge = computed(() => {
    const text = `${props.task.title} ${props.task.description}`.toLowerCase();
    if (text.includes('api') || text.includes('backend') || text.includes('database') || text.includes('sql') || text.includes('server')) {
        return { label: 'Backend', classes: 'bg-purple-500/15 text-purple-400 border-purple-500/30' };
    }
    if (text.includes('ui') || text.includes('design') || text.includes('mockup') || text.includes('css') || text.includes('layout')) {
        return { label: 'Design', classes: 'bg-pink-500/15 text-pink-400 border-pink-500/30' };
    }
    if (text.includes('ux') || text.includes('usability') || text.includes('testing') || text.includes('qa')) {
        return { label: 'UX & QA', classes: 'bg-emerald-500/15 text-emerald-400 border-emerald-500/30' };
    }
    if (text.includes('docker') || text.includes('deploy') || text.includes('ci/cd') || text.includes('git')) {
        return { label: 'DevOps', classes: 'bg-amber-500/15 text-amber-400 border-amber-500/30' };
    }
    return { label: 'Frontend', classes: 'bg-blue-500/15 text-blue-400 border-blue-500/30' };
});

const progressPercentage = computed(() => {
    if (isDone.value) return 100;
    if (isWip.value) return 60;
    return 20;
});

// Assignee derivation
const assigneeName = computed(() => {
    if (props.task.assignee) return props.task.assignee;
    const names = ['Alex R.', 'Ava M.', 'Sarah K.', 'Ben T.', 'Chloe L.'];
    const idx = (props.task.id || 0) % names.length;
    return names[idx];
});

const assigneeInitials = computed(() => {
    const parts = assigneeName.value.split(' ');
    return parts.map(p => p[0]).join('').slice(0, 2).toUpperCase();
});

const assigneeColor = computed(() => {
    const colors = ['#3b82f6', '#8b5cf6', '#ec4899', '#10b981', '#f59e0b'];
    const idx = (props.task.id || 0) % colors.length;
    return colors[idx];
});

const formattedDate = computed(() => {
    if (isDone.value) return 'Done';
    const dates = ['Nov 2', 'Nov 4', 'Oct 30', 'Nov 1', 'Oct 28'];
    const idx = (props.task.id || 0) % dates.length;
    return `Due ${dates[idx]}`;
});

const getStarColor = (index: number) => {
    if (index === 1) return '#EAB308'; // Yellow-500
    if (index === 2) return '#F97316'; // Orange-500
    if (index === 3) return '#EF4444'; // Red-500
    return 'currentColor';
};

const togglePriority = async (p: number) => {
    const current = Number(props.task.is_important) || 0;
    const newPriority = current === p ? 0 : p;
    await api.toggleImportance(props.task.id, newPriority);
    emit('toggle-imp');
};

const requestDelete = () => {
    emit('request-delete', props.task);
    if (typeof globalThis !== 'undefined' && (globalThis as any).window?.dispatchEvent) {
        (globalThis as any).window.dispatchEvent(
            new CustomEvent('taipo:request-delete', { detail: props.task })
        );
    }
};

const enableEdit = () => {
    emit('request-edit', props.task);
};

const enableView = () => {
    emit('request-view', props.task);
};
</script>
