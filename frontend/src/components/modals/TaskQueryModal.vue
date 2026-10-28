<template>
    <div
        :class="{ 'modal-open': isOpen }"
        class="modal modal-bottom sm:modal-middle z-50"
    >
        <div class="modal-box relative max-w-2xl bg-base-100 p-6 rounded-2xl border border-base-300 shadow-2xl">
            <button
                @click="$emit('close')"
                class="btn btn-sm btn-circle btn-ghost absolute right-4 top-4"
                type="button"
                aria-label="Close"
            >
                ✕
            </button>
            <h3 class="font-bold text-xl mb-6 flex items-center gap-2">
                <span class="w-8 h-8 rounded-xl ai-gradient flex items-center justify-center text-white text-xs font-black shadow-md">
                    AI
                </span>
                <span>Ask TAIPO</span>
            </h3>

            <div class="form-control w-full">
                <div class="flex justify-between items-center mb-2 px-1">
                    <label class="label p-0" for="task-query-input">
                        <span class="label-text font-semibold text-xs">Your Question for Gemini PO</span>
                    </label>
                    <details class="dropdown dropdown-end">
                        <summary class="btn btn-xs btn-ghost text-info list-none font-semibold">
                            ✨ Quick Prompts
                        </summary>
                        <div class="dropdown-content z-30 menu p-2 shadow-xl bg-base-100 border border-base-300 rounded-xl w-64 mt-2 text-xs">
                            <div
                                v-for="t in templates" :key="t.label"
                                class="w-full"
                            >
                                <button
                                    @click="applyTemplate(t.text)"
                                    type="button"
                                    class="btn btn-ghost btn-sm justify-start w-full font-normal text-xs"
                                >
                                    {{ t.label }}
                                </button>
                            </div>
                        </div>
                    </details>
                </div>
                <textarea
                    v-model="query"
                    @keydown.enter.ctrl="submitQuery"
                    :maxlength="maxQueryLength"
                    ref="queryInput"
                    id="task-query-input"
                    class="textarea h-32 w-full border-base-300 focus:border-info outline-none leading-relaxed text-xs"
                    placeholder="e.g., How should I implement authentication for this task? What edge cases to handle?"
                >
                </textarea>
                <div class="flex justify-between mt-2 px-1">
                    <span class="label-text-alt opacity-50 italic text-[11px]">Ctrl+Enter to submit</span>
                    <span
                        :class="query.length >= maxQueryLength ? 'text-error font-bold' : 'opacity-40'"
                        class="text-[10px] font-mono"
                    >
                        {{ maxQueryLength - query.length }} chars
                    </span>
                </div>
            </div>

            <div class="modal-action justify-between items-center mt-6">
                <span class="text-[10px] opacity-40 italic max-w-[200px]">
                    *Prompts are sent to Google Gemini API. Avoid PII.
                </span>
                <button
                    @click="submitQuery"
                    :disabled="loading || !query.trim()"
                    type="button"
                    class="btn btn-primary btn-sm px-8 font-bold shadow-md shadow-primary/20"
                >
                    <span
                        v-if="loading"
                        class="loading loading-spinner loading-xs"
                    >
                    </span>
                    <span
                        v-else
                    >
                        Ask TAIPO
                    </span>
                </button>
            </div>

            <!-- Response Area -->
            <div
                v-if="formattedAnswer || error || loading"
                class="mt-8 border-t border-base-300 pt-6"
            >
                <div
                    v-if="loading"
                    class="flex flex-col items-center justify-center py-6"
                >
                    <span class="loading loading-spinner loading-md text-info"></span>
                    <p class="mt-2 text-xs opacity-50">TAIPO is reasoning...</p>
                </div>

                <div
                    v-else-if="error"
                    class="alert alert-error text-sm py-4 shadow-md"
                >
                    <div class="flex flex-col gap-1">
                        <p class="font-bold">Error from AI</p>
                        <p class="opacity-90 leading-tight text-xs">{{ error.split(' - Response:')[0] }}</p>
                    </div>
                </div>

                <div
                    v-else-if="formattedAnswer"
                    class="prose prose-sm max-w-none bg-base-200/50 p-6 rounded-2xl border border-base-300"
                >
                    <h4 class="text-xs font-bold uppercase opacity-40 tracking-widest mb-4">TAIPO Answer:</h4>
                    <div
                        v-html="formattedAnswer"
                        class="text-base-content leading-relaxed text-xs"
                    >
                    </div>
                </div>
            </div>
        </div>
        <form method="dialog" class="modal-backdrop">
            <button @click="$emit('close')" type="button">close</button>
        </form>
    </div>
</template>

<script setup lang="ts">
import { ref, watch, nextTick, computed, onMounted, onUnmounted } from 'vue';
import { marked } from 'marked';

const props = withDefaults(
    defineProps<{
        isOpen: boolean;
        loading: boolean;
        answer?: string | null;
        error?: string | null;
        maxQueryLength?: number;
    }>(),
    {
        maxQueryLength: 1320,
    }
);

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'submit', query: string): void;
}>();

const query = ref('');
const queryInput = ref<HTMLTextAreaElement | null>(null);

const formattedAnswer = computed(() => {
    if (!props.answer) return '';
    return marked.parse(props.answer);
});

watch(() => props.isOpen, async (newVal) => {
    if (newVal) {
        query.value = '';
        await nextTick();
        queryInput.value?.focus();
    }
});

const submitQuery = () => {
    if (!props.loading && query.value.trim()) {
        emit('submit', query.value);
    }
};

const templates = [
    {
        label: 'Explain this task',
        text: 'Explain what this task is about and what needs to be done.',
    },
    {
        label: 'Suggest implementation',
        text: 'Suggest a technical implementation plan for this task.',
    },
    {
        label: 'Generate test cases',
        text: 'Generate a list of test cases for this task.',
    },
    {
        label: 'Security check',
        text: 'Identify potential security risks associated with this task.',
    },
    {
        label: 'Code snippets',
        text: 'Provide code snippets to help get started with this task.',
    },
];

const applyTemplate = (text: string) => {
    query.value = text;
    if (typeof document !== 'undefined') {
        const detailsEl = document.querySelector('details.dropdown');
        if (detailsEl) {
            detailsEl.removeAttribute('open');
        }
        if (document.activeElement instanceof HTMLElement) {
            document.activeElement.blur();
        }
    }
    nextTick(() => {
        queryInput.value?.focus();
    });
};

const handleEsc = (e: KeyboardEvent) => {
    if (e.key === 'Escape' && props.isOpen) {
        emit('close');
    }
};

onMounted(() => {
    if (typeof globalThis !== 'undefined' && globalThis.addEventListener) {
        globalThis.addEventListener('keydown', handleEsc);
    }
});

onUnmounted(() => {
    if (typeof globalThis !== 'undefined' && globalThis.removeEventListener) {
        globalThis.removeEventListener('keydown', handleEsc);
    }
});
</script>
