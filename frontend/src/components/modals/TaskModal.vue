<template>
    <!-- Overlay -->
    <div
        v-if="isOpen"
        @click.self="$emit('close')"
        class="fixed inset-0 bg-neutral/70 backdrop-blur-md overflow-y-auto h-full w-full z-50 flex items-center justify-center p-4 transition-all duration-300"
    >
        <!-- Modal Content Container -->
        <div class="relative w-full max-w-2xl flex flex-col shadow-2xl rounded-2xl bg-base-100 border border-base-300/80 overflow-hidden h-[min(90vh,680px)] animate-in fade-in zoom-in duration-200">

            <!-- Sticky Header -->
            <div class="px-6 py-4 border-b border-base-300/60 flex flex-col gap-3 bg-base-100/90 backdrop-blur-md z-10 shrink-0">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-primary to-purple-600 flex items-center justify-center text-primary-content font-bold shadow-md shadow-primary/20">
                            {{ isReadOnly ? '👁️' : (isEditMode ? '✏️' : '➕') }}
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-base-content flex items-center gap-2">
                                {{ isReadOnly ? 'View Task' : (isEditMode ? 'Edit Task' : 'Add New Task') }}
                                <span v-if="task?.id" class="text-xs font-mono px-2 py-0.5 rounded-md bg-base-200 text-base-content/60 border border-base-300">
                                    #{{ task.id }}
                                </span>
                            </h3>
                            <p class="text-xs text-base-content/50">
                                {{ isReadOnly ? 'Task details and AI audit history' : 'Configure task title, context, and priority level' }}
                            </p>
                        </div>
                    </div>

                    <!-- Priority Stars -->
                    <div class="flex items-center gap-2">
                        <div
                            v-if="!activeTab || activeTab === 'details'"
                            @mouseleave="hoverPriority = 0"
                            class="flex items-center gap-1 bg-base-200/80 p-1 rounded-xl border border-base-300 shadow-inner"
                        >
                            <button
                                v-for="i in 3"
                                :key="i"
                                :disabled="isReadOnly"
                                :title="`Priority ${i} (${['Low', 'Medium', 'High'][i - 1]})`"
                                @click="setPriority(i)"
                                @mouseover="hoverPriority = i"
                                class="p-1 focus:outline-none transition-all duration-200 text-base-content/40 hover:scale-110 disabled:hover:scale-100 disabled:cursor-default"
                            >
                                <svg
                                    xmlns="http://www.w3.org/2000/svg"
                                    class="h-5 w-5 filter drop-shadow-sm"
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
                                    stroke-width="1.8"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.519 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.519-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"
                                    />
                                </svg>
                            </button>
                        </div>

                        <!-- Priority Suggestion Button -->
                        <button
                            v-if="(!activeTab || activeTab === 'details') && !isReadOnly && isEditMode"
                            @click="suggestPriority"
                            :disabled="isSuggestingPriority"
                            class="btn btn-xs btn-circle btn-ghost text-primary hover:bg-primary/10 transition-all duration-300"
                            title="Suggest priority with AI"
                        >
                            <span
                                v-if="isSuggestingPriority"
                                class="loading loading-spinner loading-[14px]">
                            </span>
                            <span v-else class="text-sm">✨</span>
                        </button>

                        <button
                            @click="$emit('close')"
                            class="btn btn-sm btn-circle btn-ghost text-base-content/60 hover:text-base-content ml-2"
                            title="Close"
                        >
                            ✕
                        </button>
                    </div>
                </div>

                <!-- Tabs -->
                <div
                    v-if="isEditMode || isReadOnly"
                    class="tabs tabs-boxed bg-base-200/60 p-1 rounded-xl w-fit border border-base-300/40 flex gap-2"
                >
                    <button
                        @click="activeTab = 'details'"
                        :class="{'tab-active bg-base-100 shadow-sm text-primary': activeTab === 'details'}"
                        class="tab tab-sm font-semibold rounded-lg transition-all duration-200"
                    >
                        Details
                    </button>
                    <button
                        @click="activeTab = 'history'"
                        :class="{'tab-active bg-base-100 shadow-sm text-primary': activeTab === 'history'}"
                        class="tab tab-sm font-semibold rounded-lg transition-all duration-200 flex items-center gap-1.5"
                    >
                        <span>History & Audit</span>
                        <span v-if="history.length" class="badge badge-xs badge-neutral">{{ history.length }}</span>
                    </button>
                </div>
            </div>

            <!-- Scrollable Body Content -->
            <div class="px-7 py-5 overflow-y-auto custom-scrollbar flex-1 min-h-0 bg-base-200/40">

                <!-- Tab: Details -->
                <div
                    v-if="activeTab === 'details'"
                    class="animate-in fade-in slide-in-from-bottom-2 duration-300 space-y-5"
                >
                    <!-- AI Priority Suggestion Banner -->
                    <div
                        v-if="prioritySuggestion"
                        class="p-3.5 rounded-xl bg-primary/10 border border-primary/20 flex items-center justify-between gap-3 text-xs"
                    >
                        <div class="flex items-center gap-2">
                            <span class="text-base">✨</span>
                            <div>
                                <span class="font-bold text-primary">AI Recommends: </span>
                                <span>Priority {{ prioritySuggestion.priority }} ({{ getPriorityLabel(prioritySuggestion.priority) }})</span>
                                <p class="text-base-content/70 text-[11px] mt-0.5">{{ prioritySuggestion.reason }}</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-1.5 shrink-0">
                            <button @click="applyPrioritySuggestion" class="btn btn-xs btn-primary font-bold">Apply</button>
                            <button @click="prioritySuggestion = null" class="btn btn-xs btn-ghost">Dismiss</button>
                        </div>
                    </div>

                    <!-- Task Title Section -->
                    <div class="group">
                        <label class="block text-[11px] font-bold text-base-content/60 uppercase tracking-widest mb-2 ml-1" for="task-title">
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
                                class="w-full bg-base-100 border border-base-300 text-base-content text-base rounded-xl p-3 pr-14 focus:ring-2 focus:ring-primary/40 focus:border-primary outline-none transition-all shadow-sm placeholder:text-base-content/40"
                                placeholder="Brief title for your task..."
                            >
                            <div
                                class="absolute right-3 top-1/2 -translate-y-1/2 bg-base-200 text-base-content/60 text-[10px] px-2 py-0.5 rounded-md border border-base-300 font-mono"
                            >
                                {{ maxTitleLength - title.length }}
                            </div>
                        </div>
                        <div
                            v-else
                            class="w-full p-3.5 bg-base-100 text-base-content rounded-xl border border-base-300 font-bold text-lg shadow-sm"
                        >
                            {{ title || 'Untitled Task' }}
                        </div>
                    </div>

                    <!-- Task Description Section -->
                    <div class="group">
                        <div class="flex items-center justify-between mb-2 ml-1">
                            <label class="block text-[11px] font-bold text-base-content/60 uppercase tracking-widest" for="task-desc">
                                Context & Details
                            </label>
                            <button
                                v-if="!isReadOnly && isEditMode"
                                @click="refineWithAi"
                                :disabled="isRefining"
                                type="button"
                                class="btn btn-ghost btn-xs text-primary gap-1 hover:bg-primary/10"
                                title="Refine description using AI"
                            >
                                <span v-if="isRefining" class="loading loading-spinner loading-xs"></span>
                                <span v-else>✨ Refine with AI</span>
                            </button>
                        </div>

                        <!-- AI Suggestion Box -->
                        <div
                            v-if="aiSuggestion"
                            class="mb-3 p-3 bg-purple-500/10 border border-purple-500/30 rounded-xl space-y-2 text-xs"
                        >
                            <div class="flex items-center justify-between font-bold text-purple-400">
                                <span>✨ AI Refined Description:</span>
                                <div class="flex gap-1.5">
                                    <button @click="acceptSuggestion" class="btn btn-xs btn-primary">Accept</button>
                                    <button @click="discardSuggestion" class="btn btn-xs btn-ghost">Discard</button>
                                </div>
                            </div>
                            <p class="text-base-content/80 whitespace-pre-wrap font-sans">{{ aiSuggestion }}</p>
                        </div>

                        <div
                            v-if="!isReadOnly"
                            class="relative"
                        >
                            <textarea
                                v-model="description"
                                :maxlength="maxDescriptionLength"
                                id="task-desc"
                                rows="5"
                                class="w-full bg-base-100 border border-base-300 text-base-content text-sm rounded-xl p-3 pr-14 focus:ring-2 focus:ring-primary/40 focus:border-primary outline-none transition-all shadow-sm placeholder:text-base-content/40 custom-scrollbar resize-none"
                                placeholder="Add context, acceptance criteria, or technical details..."
                            ></textarea>
                            <div
                                class="absolute right-3 bottom-3 bg-base-200 text-base-content/60 text-[10px] px-2 py-0.5 rounded-md border border-base-300 font-mono"
                            >
                                {{ maxDescriptionLength - description.length }}
                            </div>
                        </div>
                        <div
                            v-else
                            class="w-full p-3.5 bg-base-100 text-base-content rounded-xl border border-base-300 text-sm whitespace-pre-wrap leading-relaxed shadow-sm min-h-[90px]"
                        >
                            {{ description || 'No details provided.' }}
                        </div>
                    </div>

                    <!-- PO Comments Section if present -->
                    <div
                        v-if="task?.po_comments"
                        class="p-4 rounded-xl bg-amber-500/10 border border-amber-500/30 space-y-2"
                    >
                        <div class="flex items-center gap-2 text-amber-500 font-bold text-xs uppercase tracking-wide">
                            <span>🤖</span>
                            <span>Product Owner Simulator Review Feedback</span>
                        </div>
                        <div
                            class="prose prose-sm max-w-none text-base-content/90 text-xs"
                            v-html="formattedPoComments"
                        ></div>
                    </div>
                </div>

                <!-- Tab: History -->
                <div
                    v-else-if="activeTab === 'history'"
                    class="animate-in fade-in slide-in-from-bottom-2 duration-300 space-y-4"
                >
                    <div
                        v-if="loadingHistory"
                        class="flex flex-col items-center justify-center p-8 gap-3"
                    >
                        <span class="loading loading-spinner loading-md text-primary"></span>
                        <span class="text-xs text-base-content/60">Loading audit history...</span>
                    </div>

                    <div
                        v-else-if="history.length === 0"
                        class="flex flex-col items-center justify-center p-8 text-base-content/40 text-center"
                    >
                        <span class="text-3xl mb-2">📜</span>
                        <span class="text-sm font-medium">No history recorded yet.</span>
                    </div>

                    <div
                        v-else
                        class="space-y-3"
                    >
                        <div
                            v-for="item in history"
                            :key="item.id"
                            class="relative pl-7 before:absolute before:left-2.5 before:top-2 before:bottom-0 before:w-0.5 before:bg-base-300 last:before:hidden"
                        >
                            <div
                                :class="getHistoryIconClass(item.action)"
                                class="absolute left-0 top-1 w-5 h-5 rounded-full border-2 border-base-100 flex items-center justify-center z-10 shadow-sm"
                            >
                                <span class="text-[9px]">{{ getHistoryIcon(item.action) }}</span>
                            </div>
                            <div class="bg-base-100 rounded-xl border border-base-300/80 p-3 shadow-sm">
                                <div class="flex justify-between items-start mb-1">
                                    <span class="text-xs font-bold text-base-content tracking-tight">
                                        {{ formatActionLabel(item.action) }}
                                    </span>
                                    <span class="text-[10px] font-mono text-base-content/40">
                                        {{ formatDate(item.created_at) }}
                                    </span>
                                </div>
                                <div class="text-xs text-base-content/80 leading-snug mb-1.5">
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
                                    <div class="w-3.5 h-3.5 rounded-full bg-base-300 flex items-center justify-center text-[7px] font-bold">
                                        {{ (item.username || 'AI')[0].toUpperCase() }}
                                    </div>
                                    <span class="text-[9px] font-medium text-base-content/60">
                                        {{ item.username || 'TAIPO Assistant (AI)' }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sticky Footer Action Bar -->
            <div class="px-6 py-4 bg-base-100 border-t border-base-300/60 flex justify-end items-center gap-3 shrink-0 backdrop-blur-md">
                <!-- Request Review Button -->
                <button
                    v-if="isReadOnly && (task?.status === 'REVIEW WIP:2' || task?.status === 'TESTING WIP:2')"
                    @click="requestReview"
                    :disabled="isReviewing"
                    class="mr-auto px-4 py-2 text-xs font-bold bg-secondary hover:bg-secondary/90 text-secondary-content rounded-xl shadow-md shadow-secondary/20 disabled:opacity-50 transition-all flex items-center gap-2"
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
                    class="px-4 py-2 text-xs font-semibold text-base-content/60 hover:text-base-content hover:bg-base-200 rounded-xl transition-all"
                >
                    {{ isReadOnly ? 'Close' : 'Dismiss' }}
                </button>

                <button
                    v-if="!isReadOnly && activeTab === 'details'"
                    @click="save"
                    :disabled="!title"
                    class="px-5 py-2 text-xs font-bold bg-primary hover:bg-primary/90 text-primary-content rounded-xl shadow-lg shadow-primary/20 disabled:opacity-30 disabled:cursor-not-allowed transition-all flex items-center gap-2"
                >
                    <span>{{ isEditMode ? 'Update Task' : 'Add Task' }}</span>
                </button>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref, watch, nextTick, computed } from "vue";
import { marked } from "marked";
import { api } from "../../services/api";
import type { Task, TaskHistoryItem } from "../../types";

const props = withDefaults(
    defineProps<{
        isOpen?: boolean;
        task?: Task | null;
        isReadOnly?: boolean;
        maxTitleLength?: number;
        maxDescriptionLength?: number;
    }>(),
    {
        isOpen: false,
        task: null,
        isReadOnly: false,
        maxTitleLength: 42,
        maxDescriptionLength: 512
    }
);

const emit = defineEmits<{
    (e: "close"): void;
    (e: "save", payload: { id?: number; title: string; description: string; priority: number }): void;
}>();

const isEditMode = computed(() => !!props.task);

const priority = ref<number>(0);
const hoverPriority = ref<number>(0);
const title = ref<string>("");
const description = ref<string>("");
const titleInput = ref<HTMLInputElement | null>(null);

// Tab management
const activeTab = ref<"details" | "history">("details");
const history = ref<TaskHistoryItem[]>([]);
const loadingHistory = ref<boolean>(false);
const isReviewing = ref<boolean>(false);
const isRefining = ref<boolean>(false);
const aiSuggestion = ref<string>("");
const isSuggestingPriority = ref<boolean>(false);
const prioritySuggestion = ref<{ priority: number; reason: string } | null>(null);

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
                title.value = props.task.title || props.task.description || "";
                description.value = props.task.description || "";
                priority.value = Number(props.task.is_important) || 0;
            } else {
                title.value = "";
                description.value = "";
                priority.value = 0;
            }
            hoverPriority.value = 0;
            prioritySuggestion.value = null;
            aiSuggestion.value = "";
            nextTick(() => {
                titleInput.value?.focus();
            });
        }
    }
);

watch(activeTab, (newTab) => {
    if (newTab === "history" && props.task?.id) {
        fetchHistory();
    }
});

const fetchHistory = async () => {
    if (!props.task?.id) return;
    loadingHistory.value = true;
    try {
        const response = await api.getTaskHistory(props.task.id);
        if (response.success && Array.isArray(response.data)) {
            history.value = response.data;
        }
    } catch (e) {
        console.error("Failed to fetch history:", e);
    } finally {
        loadingHistory.value = false;
    }
};

const getStarColor = (index: number): string => {
    if (index === 1) return "#EAB308"; // yellow-500
    if (index === 2) return "#F97316"; // orange-500
    if (index === 3) return "#EF4444"; // red-500
    return "currentColor";
};

const setPriority = (p: number) => {
    priority.value = priority.value === p ? 0 : p;
};

const save = () => {
    if (!title.value.trim()) return;
    emit("save", {
        id: props.task?.id,
        title: title.value.trim(),
        description: description.value.trim(),
        priority: priority.value
    });
};

const requestReview = async () => {
    if (!props.task?.id || isReviewing.value) return;

    isReviewing.value = true;
    try {
        const response = await api.reviewTask(props.task.id);
        if (response.success) {
            emit("close");
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
        if (response.success && response.refined_description) {
            aiSuggestion.value = response.refined_description;
        }
    } catch (e) {
        console.error("Refinement failed:", e);
    } finally {
        isRefining.value = false;
    }
};

const suggestPriority = async () => {
    if (!props.task?.id) return;
    isSuggestingPriority.value = true;
    try {
        const response = await api.suggestPriority(props.task.id);
        if (response.success && response.suggestion) {
            prioritySuggestion.value = response.suggestion;
        }
    } catch (error) {
        console.error("Priority suggestion error:", error);
    } finally {
        isSuggestingPriority.value = false;
    }
};

const applyPrioritySuggestion = () => {
    if (!prioritySuggestion.value) return;
    setPriority(prioritySuggestion.value.priority);
    prioritySuggestion.value = null;
};

const getPriorityLabel = (p: number): string => {
    const labels = ["None", "Low", "Medium", "High"];
    return labels[p] || "Unknown";
};

const acceptSuggestion = () => {
    description.value = aiSuggestion.value;
    aiSuggestion.value = "";
};

const discardSuggestion = () => {
    aiSuggestion.value = "";
};

// History Formatters
const formatDate = (dateStr?: string): string => {
    if (!dateStr) return "";
    const date = new Date(dateStr);
    return date.toLocaleString([], {
        month: "short",
        day: "numeric",
        hour: "2-digit",
        minute: "2-digit"
    });
};

const formatActionLabel = (action: string): string => {
    const labels: Record<string, string> = {
        create_task: "Task Created",
        status_change: "Status Updated",
        edit_content: "Content Edited",
        ai_comment: "AI Feedback",
        ai_query: "AI Query",
        ai_review: "PO Review Decision",
        ai_decompose: "Task Decomposed",
        ai_code_gen: "Code Generated",
        ai_change_request: "Change Request"
    };
    return labels[action] || action.replace("_", " ");
};

const getHistoryIcon = (action: string): string => {
    const icons: Record<string, string> = {
        create_task: "📝",
        status_change: "🚀",
        edit_content: "✏️",
        ai_comment: "🤖",
        ai_query: "❓",
        ai_review: "🔍",
        ai_decompose: "🧩",
        ai_code_gen: "💻",
        ai_change_request: "⚠️"
    };
    return icons[action] || "🔹";
};

const getHistoryIconClass = (action: string): string => {
    if (action.startsWith("ai_")) return "bg-primary/20 text-primary border-primary/30";
    if (action === "status_change") return "bg-success/20 text-success border-success/30";
    return "bg-base-300 text-base-content border-base-400";
};
</script>
