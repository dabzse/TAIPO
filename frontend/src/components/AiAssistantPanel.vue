<template>
    <aside
        :class="[
            'fixed right-0 top-16 bottom-0 z-30 w-80 bg-base-200/95 backdrop-blur-xl border-l border-base-300 shadow-2xl transition-all duration-300 flex flex-col',
            isOpen ? 'translate-x-0' : 'translate-x-full'
        ]"
        aria-label="TAIPO AI Assistant Panel"
    >
        <!-- Header -->
        <div class="p-5 border-b border-base-300 flex items-center justify-between bg-base-100/50">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl ai-gradient flex items-center justify-center shadow-lg shadow-purple-500/20 font-extrabold text-white text-base tracking-wider">
                    AI
                </div>
                <div>
                    <h2 class="font-bold text-base tracking-tight text-base-content">
                        TAIPO AI Assistant
                    </h2>
                    <div class="flex items-center gap-1.5 text-xs text-success">
                        <span class="w-2 h-2 rounded-full bg-success animate-pulse"></span>
                        <span>Gemini 3 Flash • Active</span>
                    </div>
                </div>
            </div>
            <button
                @click="$emit('close')"
                class="btn btn-ghost btn-sm btn-circle"
                title="Close AI Panel"
                aria-label="Close AI Panel"
            >
                ✕
            </button>
        </div>

        <!-- Panel Content -->
        <div class="flex-1 overflow-y-auto p-5 space-y-6 custom-scrollbar text-sm">
            <!-- Project Health Card -->
            <div class="p-4 rounded-2xl bg-base-100 border border-base-300 shadow-sm space-y-2">
                <div class="flex items-center justify-between gap-2 text-xs font-semibold text-base-content/60 uppercase tracking-wider">
                    <span class="shrink-0">Project Health</span>
                    <span class="badge badge-success font-bold text-xs h-auto min-h-[1.75rem] py-1 px-2.5 leading-tight text-center">
                        {{ healthRating }}
                    </span>
                </div>
                <div class="flex items-baseline justify-between">
                    <span class="text-2xl font-black text-base-content">{{ healthPercentage }}%</span>
                    <span class="text-xs text-base-content/60">{{ completedCount }} / {{ totalCount }} tasks done</span>
                </div>
                <div class="w-full bg-base-300 rounded-full h-2 overflow-hidden">
                    <div
                        class="h-full bg-gradient-to-r from-primary to-accent transition-all duration-500 rounded-full"
                        :style="{ width: `${healthPercentage}%` }"
                    ></div>
                </div>
            </div>

            <!-- Workflow Analytics -->
            <div class="p-4 rounded-2xl bg-base-100 border border-base-300 shadow-sm space-y-3">
                <div class="text-xs font-semibold text-base-content/60 uppercase tracking-wider">
                    Workflow Analytics
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div class="p-2.5 rounded-xl bg-base-200/70 border border-base-300/50">
                        <div class="text-[11px] text-base-content/60">Active Tasks</div>
                        <div class="text-lg font-bold text-primary">{{ activeCount }}</div>
                    </div>
                    <div class="p-2.5 rounded-xl bg-base-200/70 border border-base-300/50">
                        <div class="text-[11px] text-base-content/60">WIP Load</div>
                        <div class="text-lg font-bold" :class="wipLoadColor">{{ wipLoadPercent }}%</div>
                    </div>
                </div>
                <div class="text-xs text-base-content/70 flex items-center justify-between pt-1">
                    <span>Backlog queue:</span>
                    <span class="font-semibold text-base-content">{{ backlogCount }} stories</span>
                </div>
            </div>

            <!-- Recommendations / PO Insights -->
            <div class="p-4 rounded-2xl bg-base-100 border border-base-300 shadow-sm space-y-2">
                <div class="flex items-center gap-1.5 text-xs font-semibold text-base-content/60 uppercase tracking-wider">
                    <span>💡 Recommendations</span>
                </div>
                <ul class="space-y-2">
                    <li
                        v-for="(rec, index) in recommendations"
                        :key="index"
                        class="p-2.5 rounded-xl bg-base-200/50 border border-base-300/60 text-xs leading-relaxed text-base-content/85 flex items-start gap-2"
                    >
                        <span class="text-primary mt-0.5">•</span>
                        <span>{{ rec }}</span>
                    </li>
                </ul>
            </div>

            <!-- Team & Simulation Update -->
            <div class="p-4 rounded-2xl bg-base-100 border border-base-300 shadow-sm space-y-2">
                <div class="text-xs font-semibold text-base-content/60 uppercase tracking-wider">
                    Autonomous PO Simulator
                </div>
                <div v-if="latestPoComment" class="p-2.5 rounded-xl bg-accent/10 border border-accent/20 text-xs text-base-content/90">
                    <div class="font-bold text-accent mb-1 flex items-center gap-1">
                        <span>🤖 Latest PO Feedback:</span>
                    </div>
                    <p class="line-clamp-3 italic">"{{ latestPoComment.text }}"</p>
                    <div class="text-[10px] text-base-content/50 mt-1">on task: {{ latestPoComment.taskTitle }}</div>
                </div>
                <div v-else class="text-xs text-base-content/50 italic py-1">
                    PO Simulator is monitoring the sprint. Check-ins occur during working hours.
                </div>
            </div>

            <!-- Quick AI Actions -->
            <div class="space-y-2 pt-2">
                <button
                    @click="$emit('open-query')"
                    type="button"
                    class="btn btn-primary btn-sm w-full gap-2 font-bold shadow-md shadow-primary/20 cursor-pointer"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="w-4 h-4">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 00-2.456 2.456zM16.894 20.567L16.5 21.75l-.394-1.183a2.25 2.25 0 00-1.423-1.423L13.5 18.75l1.183-.394a2.25 2.25 0 001.423-1.423l.394-1.183.394 1.183a2.25 2.25 0 001.423 1.423l1.183.394-1.183.394a2.25 2.25 0 00-1.423 1.423z" />
                    </svg>
                    Ask TAIPO Question
                </button>
                <button
                    @click="$emit('open-generate')"
                    type="button"
                    class="btn btn-outline btn-secondary btn-sm w-full gap-2 font-bold cursor-pointer"
                >
                    ✨ Brainstorm Backlog
                </button>
                <div class="grid grid-cols-2 gap-2 pt-1">
                    <button
                        @click="$emit('decompose-requested')"
                        type="button"
                        class="btn btn-outline btn-ghost btn-xs gap-1 border-base-300 hover:border-primary cursor-pointer text-base-content/80"
                        title="Decompose first available backlog user story"
                    >
                        <span>🔨 Decompose</span>
                    </button>
                    <button
                        @click="$emit('review-requested')"
                        type="button"
                        class="btn btn-outline btn-ghost btn-xs gap-1 border-base-300 hover:border-info cursor-pointer text-base-content/80"
                        title="Request PO review for in-review task"
                    >
                        <span>🔍 PO Review</span>
                    </button>
                </div>
            </div>
        </div>
    </aside>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import type { TasksByColumn, Task } from '../types';

const props = defineProps<{
    isOpen: boolean;
    tasks: TasksByColumn;
    currentProject?: string | null;
    userRole?: string | null;
}>();

defineEmits<{
    (e: 'close'): void;
    (e: 'open-generate'): void;
    (e: 'open-query'): void;
    (e: 'open-requirements'): void;
    (e: 'open-costs'): void;
    (e: 'decompose-requested'): void;
    (e: 'review-requested'): void;
    (e: 'refresh'): void;
}>();

const allTasks = computed<Task[]>(() => {
    if (!props.tasks) return [];
    return Object.values(props.tasks).flat();
});

const totalCount = computed(() => allTasks.value.length);

const completedCount = computed(() => {
    const doneTasks = props.tasks['DONE'] || [];
    return doneTasks.length;
});

const backlogCount = computed(() => {
    const backlog = props.tasks['SPRINT BACKLOG'] || [];
    return backlog.length;
});

const activeCount = computed(() => {
    let count = 0;
    for (const [col, list] of Object.entries(props.tasks || {})) {
        if (col !== 'DONE' && !col.includes('BACKLOG')) {
            count += (list || []).length;
        }
    }
    return count;
});

const healthPercentage = computed(() => {
    if (totalCount.value === 0) return 100;
    // Calculate health based on completion and backlog progress
    const ratio = completedCount.value / totalCount.value;
    const base = Math.round(ratio * 70) + 30; // 30% base + up to 70%
    return Math.min(100, Math.max(0, base));
});

const healthRating = computed(() => {
    if (healthPercentage.value >= 80) return 'On Track';
    if (healthPercentage.value >= 50) return 'In Progress';
    return 'Attention Needed';
});

const wipLoadPercent = computed(() => {
    let currentWip = 0;
    let maxWip = 0;
    for (const [col, list] of Object.entries(props.tasks || {})) {
        const match = col.match(/WIP:(\d+)/);
        if (match) {
            const limit = Number.parseInt(match[1], 10);
            maxWip += limit;
            currentWip += (list || []).length;
        }
    }
    if (maxWip === 0) return 0;
    return Math.min(100, Math.round((currentWip / maxWip) * 100));
});

const wipLoadColor = computed(() => {
    if (wipLoadPercent.value >= 90) return 'text-error';
    if (wipLoadPercent.value >= 70) return 'text-warning';
    return 'text-success';
});

const recommendations = computed(() => {
    const list: string[] = [];
    if (!props.currentProject) {
        return ['Select or create an Agile project to start the simulation.'];
    }

    // Check WIP bottlenecks
    for (const [col, tasksList] of Object.entries(props.tasks || {})) {
        const match = col.match(/WIP:(\d+)/);
        if (match) {
            const limit = Number.parseInt(match[1], 10);
            if ((tasksList || []).length >= limit) {
                const colName = col.split(' ')[0];
                list.push(`Bottleneck in '${colName}' column (${tasksList.length}/${limit}). Complete tasks before taking on new ones.`);
            }
        }
    }

    // Check review column
    const reviewTasks = props.tasks['REVIEW WIP:2'] || [];
    if (reviewTasks.length > 0) {
        list.push(`${reviewTasks.length} task(s) in Review. Reviewers & PO feedback needed.`);
    }

    // Check stories needing decomposition
    const backlogTasks = props.tasks['SPRINT BACKLOG'] || [];
    const undecomposed = backlogTasks.filter(t => !t.is_subtask && (!t.subtaskCount || t.subtaskCount === 0));
    if (undecomposed.length > 0) {
        list.push(`${undecomposed.length} backlog stories ready to decompose into technical tasks.`);
    }

    if (list.length === 0) {
        list.push('Sprint workflow is balanced and on track. Continue development!');
    }

    return list.slice(0, 3);
});

const latestPoComment = computed(() => {
    for (const task of allTasks.value) {
        if (task.po_comments && task.po_comments.trim().length > 0) {
            return {
                text: task.po_comments.replace(/<[^>]*>?/gm, '').slice(0, 140),
                taskTitle: task.title
            };
        }
    }
    return null;
});
</script>
