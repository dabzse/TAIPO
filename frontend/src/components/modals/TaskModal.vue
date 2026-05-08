<template>
    <!-- Overlay -->
    <div
        v-if="isOpen"
        @click.self="$emit('close')"
        class="fixed inset-0 bg-neutral/60 backdrop-blur-sm overflow-y-auto h-full w-full z-50 flex items-center justify-center p-4 transition-all duration-300"
    >
        <!-- Modal Content Container -->
        <div class="relative w-full max-w-2xl flex flex-col shadow-2xl rounded-2xl bg-base-100 border border-base-300 overflow-hidden max-h-[min(90vh,800px)] animate-in fade-in zoom-in duration-200">

            <!-- Sticky Header -->
            <div class="px-6 py-4 border-b border-base-300 flex flex-col gap-4 bg-base-100/80 backdrop-blur-md z-10 shrink-0">
                <div class="flex items-center">
                    <h3 class="text-xl font-bold text-base-content mr-auto flex items-center gap-3">
                        <span class="w-1.5 h-6 bg-primary rounded-full"></span>
                        {{ isReadOnly ? 'View Task' : (isEditMode ? 'Edit Task' : 'Add New Task') }}
                    </h3>
                    <div
                        v-if="!activeTab || activeTab === 'details'"
                        @mouseleave="hoverPriority = 0"
                        class="flex items-center gap-1.5 bg-base-200 p-1.5 rounded-xl border border-base-300 shadow-inner"
                    >
                        <button
                            v-for="i in 3"
                            :key="i"
                            :disabled="isReadOnly"
                            :title="`Priority ${i}`"
                            @click="setPriority(i)"
                            @mouseover="hoverPriority = i"
                            class="focus:outline-none transition-all duration-200 text-base-content/40 hover:scale-110 disabled:hover:scale-100 disabled:cursor-default disabled:opacity-80"
                        >
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                class="h-7 w-7 filter drop-shadow-sm"
                                viewBox="0 0 24 24"
                                :fill="
                                    hoverPriority >= i ||
                                    (!hoverPriority && priority >= i)
                                        ? getStarColor(i)
                                        : 'none'
                                "
                                :stroke="
                                    hoverPriority >= i ||
                                    (!hoverPriority && priority >= i)
                                        ? getStarColor(i)
                                        : 'currentColor'
                                "
                                stroke-width="1.5"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.519 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.519-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"
                                />
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Tabs -->
                <div
                    v-if="isEditMode || isReadOnly"
                    class="tabs tabs-boxed bg-base-200/50 p-1 rounded-xl w-fit"
                >
                    <button
                        @click="activeTab = 'details'"
                        :class="{'tab-active bg-base-100 shadow-sm text-primary': activeTab === 'details'}"
                        class="tab tab-sm font-bold transition-all duration-200"
                    >
                        Details
                    </button>
                    <div class="w-px h-4 bg-base-content/20 mx-1 self-center"></div>
                    <button
                        @click="activeTab = 'history'"
                        :class="{'tab-active bg-base-100 shadow-sm text-primary': activeTab === 'history'}"
                        class="tab tab-sm font-bold transition-all duration-200"
                    >
                        History
                    </button>
                </div>
            </div>

            <!-- Scrollable Body Content -->
            <div class="px-7 py-6 overflow-y-auto custom-scrollbar flex-grow bg-base-200/50 min-h-[300px]">

                <!-- Tab: Details -->
                <div
                    v-if="activeTab === 'details'"
                    class="animate-in fade-in slide-in-from-bottom-2 duration-300"
                >
                    <!-- Task Title Section -->
                    <div class="mb-6 group">
                        <label class="block text-[11px] font-bold text-base-content/60 uppercase tracking-widest mb-2.5 ml-1 transition-colors group-focus-within:text-primary" for="task-title">
                            Task Heading
                        </label>
                        <div
                            v-if="!isReadOnly"
                            class="relative"
                        >
                            <input
                                v-model="title"
                                @keyup.enter="save"
                                :maxlength="maxTitleLength"
                                ref="titleInput"
                                type="text"
                                id="task-title"
                                class="w-full bg-base-100 border border-base-300 text-base-content text-lg rounded-xl p-3.5 pr-14 focus:ring-2 focus:ring-primary/50 focus:border-primary outline-none transition-all duration-300 shadow-sm placeholder:text-base-content/40"
                                placeholder="Brief title for your task..."
                            >
                            <div
                                class="absolute right-3.5 top-1/2 -translate-y-1/2 bg-base-200 text-base-content/60 text-[10px] px-2 py-0.5 rounded-md border border-base-300 font-mono"
                            >
                                {{ maxTitleLength - title.length }}
                            </div>
                        </div>
                        <div
                            v-else
                            class="w-full p-4 bg-base-100 text-base-content rounded-xl border border-base-300 font-bold text-xl leading-tight shadow-sm"
                        >
                            {{ title || 'Untitled Task' }}
                        </div>
                    </div>

                    <!-- Task Description Section -->
                    <div class="mb-6 group">
                        <label class="block text-[11px] font-bold text-base-content/60 uppercase tracking-widest mb-2.5 ml-1 transition-colors group-focus-within:text-primary" for="task-desc">
                            Context & Details
                        </label>
                        <div
                            v-if="!isReadOnly"
                            class="relative"
                        >
                            <textarea
                                v-model="description"
                                :maxlength="maxDescriptionLength"
                                id="task-desc"
                                class="w-full bg-base-100 border border-base-300 text-base-content rounded-xl p-4 pb-10 h-44 focus:ring-2 focus:ring-primary/50 focus:border-primary outline-none transition-all duration-300 shadow-sm placeholder:text-base-content/40 resize-none leading-relaxed"
                                placeholder="What exactly needs to be done? Add relevant context..."
                            >
                            </textarea>
                            <div
                                class="absolute right-4 bottom-4 bg-base-200 text-base-content/60 text-[10px] px-2 py-0.5 rounded-md border border-base-300 font-mono shadow-sm"
                            >
                                {{ maxDescriptionLength - description.length }}
                            </div>
                        </div>

                        <!-- Refinement Suggestion Button -->
                        <div
                            v-if="!isReadOnly && isEditMode"
                            class="mt-2 flex justify-end"
                        >
                            <button
                                @click="refineWithAi"
                                :disabled="isRefining"
                                class="btn btn-xs btn-ghost text-primary gap-1.5 hover:bg-primary/10 transition-all duration-200"
                            >
                                <span
                                    v-if="isRefining"
                                    class="loading loading-spinner loading-xs"
                                >
                                </span>
                                <span v-else class="text-sm">✨</span>
                                <span class="font-bold tracking-tight">Refine with AI</span>
                            </button>
                        </div>

                        <!-- Suggestion Preview Box -->
                        <div
                            v-if="aiSuggestion"
                            class="mt-4 bg-primary/5 border border-primary/20 rounded-2xl p-5 animate-in fade-in slide-in-from-top-2 duration-300 shadow-sm relative overflow-hidden"
                        >
                            <div class="absolute top-0 left-0 w-1 h-full bg-primary"></div>
                            <div class="flex items-center justify-between mb-3">
                                <span class="text-[10px] font-black uppercase tracking-widest text-primary flex items-center gap-2">
                                    <span class="bg-primary text-primary-content w-4 h-4 rounded-full flex items-center justify-center text-[8px]">✨</span>
                                    TAIPO's Enhancement
                                </span>
                                <div class="flex gap-2">
                                    <button
                                        @click="discardSuggestion"
                                        class="btn btn-xs btn-ghost text-base-content/40 hover:text-error hover:bg-error/10 transition-colors"
                                    >
                                        Discard
                                    </button>
                                    <button
                                        @click="acceptSuggestion"
                                        class="btn btn-xs btn-primary shadow-lg shadow-primary/20"
                                    >
                                        Accept & Apply
                                    </button>
                                </div>
                            </div>
                            <div class="text-[13px] text-base-content/80 whitespace-pre-wrap leading-relaxed italic border-l-2 border-primary/20 pl-4 py-1">
                                {{ aiSuggestion }}
                            </div>
                        </div>

                        <div
                            v-else-if="isReadOnly"
                            class="w-full p-5 bg-base-100 text-base-content/80 rounded-xl border border-base-300 whitespace-pre-wrap min-h-[8rem] text-[15px] leading-relaxed shadow-sm"
                        >
                            {{ description || 'No detailed description provided.' }}
                        </div>
                    </div>

                    <!-- AI / TAIPO Feedback Section (Read-Only) -->
                    <div
                        v-if="isReadOnly && task?.po_comments"
                        class="mt-8 relative"
                    >
                        <div class="absolute -top-3 left-4 bg-primary text-primary-content text-[10px] font-black px-2.5 py-1 rounded-full uppercase tracking-tighter shadow-lg z-20 flex items-center gap-1.5">
                            <span>🤖</span> AI COUNSEL
                        </div>
                        <div class="bg-primary/5 rounded-2xl border border-primary/20 overflow-hidden shadow-sm">
                            <div class="p-6">
                                <div
                                    v-html="formattedPoComments"
                                    class="prose prose-sm max-w-none text-base-content/90 leading-relaxed font-normal"
                                >
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tab: History -->
                <div
                    v-if="activeTab === 'history'"
                    class="animate-in fade-in slide-in-from-bottom-2 duration-300"
                >
                    <div
                        v-if="loadingHistory"
                        class="flex flex-col items-center justify-center py-12 opacity-40"
                    >
                        <span class="loading loading-spinner loading-md text-primary mb-2"></span>
                        <span class="text-xs font-bold tracking-widest uppercase">Loading Audit Trail...</span>
                    </div>
                    <div
                        v-else-if="history.length === 0"
                        class="flex flex-col items-center justify-center py-12 opacity-30"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-12 h-12 mb-2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span class="text-sm font-medium">No history recorded yet.</span>
                    </div>
                    <div
                        v-else
                        class="space-y-4"
                    >
                        <div
                            v-for="item in history"
                            :key="item.id"
                            class="relative pl-8 before:absolute before:left-3 before:top-2 before:bottom-0 before:w-0.5 before:bg-base-300 last:before:hidden"
                        >
                            <div
                                :class="getHistoryIconClass(item.action)"
                                class="absolute left-0 top-1.5 w-6.5 h-6.5 rounded-full border-4 border-base-100 flex items-center justify-center z-10 shadow-sm"
                            >
                                <span class="text-[10px]">{{ getHistoryIcon(item.action) }}</span>
                            </div>
                            <div class="bg-base-100 rounded-xl border border-base-300 p-3 shadow-sm">
                                <div class="flex justify-between items-start mb-1">
                                    <span class="text-xs font-bold text-base-content uppercase tracking-tight">
                                        {{ formatActionLabel(item.action) }}
                                    </span>
                                    <span class="text-[10px] font-mono opacity-40">
                                        {{ formatDate(item.created_at) }}
                                    </span>
                                </div>
                                <div class="text-[13px] text-base-content/80 leading-snug mb-2">
                                    <span v-if="item.action === 'status_change'">
                                        Moved to <span class="badge badge-outline badge-xs font-bold">{{ item.new_value }}</span>
                                    </span>
                                    <span v-else-if="item.action === 'ai_query'">
                                        Queried TAIPO: <span class="italic">"{{ item.old_value }}"</span>
                                    </span>
                                    <span v-else-if="item.action === 'ai_comment'">
                                        TAIPO commented on the task.
                                    </span>
                                    <span v-else>
                                        {{ item.details || 'Task updated.' }}
                                    </span>
                                </div>
                                <div class="flex items-center gap-1.5">
                                    <div class="w-4 h-4 rounded-full bg-base-300 flex items-center justify-center text-[8px] font-bold">
                                        {{ (item.username || 'AI')[0].toUpperCase() }}
                                    </div>
                                    <span class="text-[10px] font-bold opacity-60 uppercase tracking-tighter">
                                        {{ item.username || 'TAIPO Assistant (AI)' }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sticky Footer Action Bar -->
            <div class="px-6 py-4 bg-base-100 border-t border-base-300 flex justify-end items-center gap-3 shrink-0 backdrop-blur-md">
                <!-- Request Review Button -->
                <button
                    v-if="isReadOnly && (task?.status === 'REVIEW WIP:2' || task?.status === 'TESTING WIP:2')"
                    @click="requestReview"
                    :disabled="isReviewing"
                    class="mr-auto px-5 py-2.5 text-sm font-bold bg-secondary hover:bg-secondary/90 text-secondary-content rounded-xl shadow-lg shadow-secondary/20 disabled:opacity-50 transition-all duration-300 flex items-center gap-2"
                >
                    <span
                        v-if="isReviewing"
                        class="loading loading-spinner loading-xs"
                    >
                    </span>
                    <span v-else>🔍</span>
                    <span>
                        {{ isReviewing ? 'Reviewing...' : 'Request PO Review' }}
                    </span>
                </button>

                <button
                    @click="$emit('close')"
                    class="px-5 py-2.5 text-sm font-semibold text-base-content/60 hover:text-base-content hover:bg-base-200 rounded-xl transition-all duration-200"
                >
                    {{ isReadOnly ? 'Close' : 'Dismiss' }}
                </button>
                <button
                    v-if="!isReadOnly && activeTab === 'details'"
                    @click="save"
                    :disabled="!title"
                    class="px-6 py-2.5 text-sm font-bold bg-primary hover:bg-primary/90 text-primary-content rounded-xl shadow-xl shadow-primary/20 disabled:opacity-30 disabled:cursor-not-allowed transition-all duration-300 flex items-center gap-2"
                >
                    <svg
                        v-if="isEditMode"
                        xmlns="http://www.w3.org/2000/svg"
                        class="h-4 w-4"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    <svg
                        v-else
                        xmlns="http://www.w3.org/2000/svg"
                        class="h-4 w-4"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    <span>{{ isEditMode ? 'Update Task' : 'Add Task' }}</span>
                </button>
            </div>
        </div>
    </div>
</template>


<script setup>
import { ref, watch, nextTick, computed } from "vue";
import { marked } from "marked";
import { api } from "../../services/api";

const props = defineProps({
    isOpen: Boolean,
    task: Object,
    isReadOnly: Boolean,
    maxTitleLength: {
        type: Number,
        default: 42
    },
    maxDescriptionLength: {
        type: Number,
        default: 512
    }
});

const isEditMode = computed(() => !!props.task);

const emit = defineEmits(["close", "save"]);

const priority = ref(0);
const hoverPriority = ref(0);
const title = ref("");
const description = ref("");
const titleInput = ref(null);

// Tab management
const activeTab = ref("details");
const history = ref([]);
const loadingHistory = ref(false);
const isReviewing = ref(false);
const isRefining = ref(false);
const aiSuggestion = ref("");

const formattedPoComments = computed(() => {
    if (!props.task?.po_comments) return "";
    return marked.parse(props.task.po_comments);
});


watch(
    () => props.isOpen,
    (newVal) => {
        if (newVal) {
            activeTab.value = "details";
            if (props.task) {
                // Edit mode
                title.value = props.task.title || '';
                // If title was auto-generated from description before migration, it might be messy, but assuming clean state.
                description.value = props.task.description || '';
                priority.value = Number(props.task.is_important) || 0;
            } else {
                // Add mode
                title.value = "";
                description.value = "";
                priority.value = 0;
            }
            hoverPriority.value = 0;
            nextTick(() => {
                titleInput.value?.focus();
            });
        }
    },
);

watch(activeTab, (newTab) => {
    if (newTab === 'history' && props.task?.id) {
        fetchHistory();
    }
});

const fetchHistory = async () => {
    if (!props.task?.id) return;
    loadingHistory.value = true;
    try {
        const response = await api.getTaskHistory(props.task.id);
        if (response.success) {
            history.value = response.data;
        }
    } catch (e) {
        console.error("Failed to fetch history:", e);
    } finally {
        loadingHistory.value = false;
    }
};

const getStarColor = (index) => {
    if (index === 1) return "#EAB308"; // yellow-500
    if (index === 2) return "#F97316"; // orange-500
    if (index === 3) return "#EF4444"; // red-500
    return "currentColor";
};

const setPriority = (p) => {
    if (priority.value === p) {
        priority.value = 0;
    } else {
        priority.value = p;
    }
};

const save = () => {
    if (!title.value) return;

    emit("save", { title: title.value, description: description.value, priority: priority.value });
};

const requestReview = async () => {
    if (!props.task?.id || isReviewing.value) return;

    isReviewing.value = true;
    try {
        const response = await api.reviewTask(props.task.id);
        if (response.success) {
            // We need to refresh the task data in the parent or emit an event
            // For now, let's just close and let the parent refresh the board
            emit('close');
            // We could also emit a 'refresh' event
        }
    } catch (e) {
        console.error("Review failed:", e);
    } finally {
        isReviewing.value = false;
    }
};

const refineWithAi = async () => {
    if (!props.task?.id || isRefining.value) return;

    isRefining.value = true;
    aiSuggestion.value = "";
    try {
        const response = await api.refineTask(props.task.id);
        if (response.success) {
            aiSuggestion.value = response.refined_description;
        } else {
            console.error("Refinement failed:", response.error);
        }
    } catch (e) {
        console.error("Refinement failed:", e);
    } finally {
        isRefining.value = false;
    }
};

const acceptSuggestion = () => {
    description.value = aiSuggestion.value;
    aiSuggestion.value = "";
};

const discardSuggestion = () => {
    aiSuggestion.value = "";
};

// History Formatters
const formatDate = (dateStr) => {
    if (!dateStr) return "";
    const date = new Date(dateStr);
    return date.toLocaleString([], {
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
};

const formatActionLabel = (action) => {
    const labels = {
        'create_task': 'Task Created',
        'status_change': 'Status Updated',
        'edit_content': 'Content Edited',
        'ai_comment': 'AI Feedback',
        'ai_query': 'AI Query',
        'ai_review': 'PO Review Decision',
        'ai_decompose': 'Task Decomposed',
        'ai_code_gen': 'Code Generated',
        'ai_change_request': 'Change Request'
    };
    return labels[action] || action.replace('_', ' ');
};

const getHistoryIcon = (action) => {
    const icons = {
        'create_task': '📝',
        'status_change': '🚀',
        'edit_content': '✏️',
        'ai_comment': '🤖',
        'ai_query': '❓',
        'ai_review': '🔍',
        'ai_decompose': '🧩',
        'ai_code_gen': '💻',
        'ai_change_request': '⚠️'
    };
    return icons[action] || '🔹';
};

const getHistoryIconClass = (action) => {
    if (action.startsWith('ai_')) return 'bg-primary/20 text-primary border-primary/30';
    if (action === 'status_change') return 'bg-success/20 text-success border-success/30';
    return 'bg-base-300 text-base-content border-base-400';
};
</script>
