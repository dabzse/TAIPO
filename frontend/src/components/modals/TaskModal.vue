<template>
    <!-- Overlay -->
    <div
        v-if="isOpen"
        @click.self="$emit('close')"
        class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm overflow-y-auto h-full w-full z-50 flex items-center justify-center p-4 transition-all duration-300"
    >
        <!-- Modal Content Container -->
        <div class="relative w-full max-w-2xl flex flex-col shadow-2xl rounded-2xl bg-slate-800 border border-slate-700/50 overflow-hidden max-h-[min(90vh,800px)] animate-in fade-in zoom-in duration-200">

            <!-- Sticky Header -->
            <div class="px-6 py-4 border-b border-slate-700/50 flex items-center shrink-0 bg-slate-800/80 backdrop-blur-md z-10">
                <h3 class="text-xl font-bold text-white mr-auto flex items-center gap-3">
                    <span class="w-1.5 h-6 bg-indigo-500 rounded-full"></span>
                    {{ isReadOnly ? 'View Task' : (isEditMode ? 'Edit Task' : 'Add New Task') }}
                </h3>
                <div
                    @mouseleave="hoverPriority = 0"
                    class="flex items-center gap-1.5 bg-slate-900/50 p-1.5 rounded-xl border border-slate-700/30 shadow-inner"
                >
                    <button
                        v-for="i in 3"
                        :key="i"
                        :disabled="isReadOnly"
                        :title="`Priority ${i}`"
                        @click="setPriority(i)"
                        @mouseover="hoverPriority = i"
                        class="focus:outline-none transition-all duration-200 hover:scale-110 disabled:hover:scale-100 disabled:cursor-default disabled:opacity-80"
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
                                    : '#94a3b8'
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

            <!-- Scrollable Body Content -->
            <div class="px-7 py-6 overflow-y-auto custom-scrollbar flex-grow bg-slate-800/20">
                <!-- Task Title Section -->
                <div class="mb-6 group">
                    <label class="block text-[11px] font-bold text-slate-400 uppercase tracking-widest mb-2.5 ml-1 transition-colors group-focus-within:text-indigo-400" for="task-title">
                        Task Heading
                    </label>
                    <div v-if="!isReadOnly" class="relative">
                        <input
                            v-model="title"
                            @keyup.enter="save"
                            :maxlength="maxTitleLength"
                            ref="titleInput"
                            type="text"
                            id="task-title"
                            class="w-full bg-slate-900 border border-slate-700/50 text-white text-lg rounded-xl p-3.5 pr-14 focus:ring-2 focus:ring-indigo-500/50 focus:border-indigo-500 outline-none transition-all duration-300 shadow-sm placeholder:text-slate-600"
                            placeholder="Brief title for your task..."
                        >
                        <div
                            class="absolute right-3.5 top-1/2 -translate-y-1/2 bg-slate-800 text-slate-400 text-[10px] px-2 py-0.5 rounded-md border border-slate-700 font-mono"
                        >
                            {{ maxTitleLength - title.length }}
                        </div>
                    </div>
                    <div
                        v-else
                        class="w-full p-4 bg-slate-900/40 text-white rounded-xl border border-slate-700/40 font-bold text-xl leading-tight"
                    >
                        {{ title || 'Untitled Task' }}
                    </div>
                </div>

                <!-- Task Description Section -->
                <div class="mb-6 group">
                    <label class="block text-[11px] font-bold text-slate-400 uppercase tracking-widest mb-2.5 ml-1 transition-colors group-focus-within:text-indigo-400" for="task-desc">
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
                            class="w-full bg-slate-900 border border-slate-700/50 text-white rounded-xl p-4 pb-10 h-44 focus:ring-2 focus:ring-indigo-500/50 focus:border-indigo-500 outline-none transition-all duration-300 shadow-sm placeholder:text-slate-600 resize-none leading-relaxed"
                            placeholder="What exactly needs to be done? Add relevant context..."
                        >
                        </textarea>
                        <div
                            class="absolute right-4 bottom-4 bg-slate-800 text-slate-400 text-[10px] px-2 py-0.5 rounded-md border border-slate-700 font-mono shadow-sm"
                        >
                            {{ maxDescriptionLength - description.length }}
                        </div>
                    </div>
                    <div
                        v-else
                        class="w-full p-5 bg-slate-900/40 text-slate-300 rounded-xl border border-slate-700/40 whitespace-pre-wrap min-h-[8rem] text-[15px] leading-relaxed"
                    >
                        {{ description || 'No detailed description provided.' }}
                    </div>
                </div>

                <!-- AI / TAIPO Feedback Section (Read-Only) -->
                <div
                    v-if="isReadOnly && task?.po_comments"
                    class="mt-8 relative"
                >
                    <div class="absolute -top-3 left-4 bg-indigo-600 text-white text-[10px] font-black px-2.5 py-1 rounded-full uppercase tracking-tighter shadow-lg z-20 flex items-center gap-1.5">
                        <span>🤖</span> AI COUNSEL
                    </div>
                    <div class="bg-indigo-900/10 rounded-2xl border border-indigo-500/20 overflow-hidden shadow-sm">
                        <div class="p-6 bg-gradient-to-br from-indigo-500/5 to-transparent">
                            <div
                                v-html="formattedPoComments"
                                class="prose prose-sm prose-invert max-w-none text-indigo-100/80 leading-relaxed font-normal"
                            ></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sticky Footer Action Bar -->
            <div class="px-6 py-4 bg-slate-900/40 border-t border-slate-700/50 flex justify-end items-center gap-3 shrink-0 backdrop-blur-md">
                <button
                    @click="$emit('close')"
                    class="px-5 py-2.5 text-sm font-semibold text-slate-400 hover:text-white hover:bg-slate-700/50 rounded-xl transition-all duration-200"
                >
                    {{ isReadOnly ? 'Close' : 'Dismiss' }}
                </button>
                <button
                    v-if="!isReadOnly"
                    @click="save"
                    :disabled="!title"
                    class="px-6 py-2.5 text-sm font-bold bg-indigo-600 hover:bg-indigo-500 active:bg-indigo-700 text-white rounded-xl shadow-xl shadow-indigo-900/20 disabled:opacity-30 disabled:cursor-not-allowed transition-all duration-300 flex items-center gap-2"
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

const formattedPoComments = computed(() => {
    if (!props.task?.po_comments) return "";
    return marked.parse(props.task.po_comments);
});


watch(
    () => props.isOpen,
    (newVal) => {
        if (newVal) {
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
</script>
