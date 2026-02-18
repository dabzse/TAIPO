<template>
    <div
        :class="{ 'modal-open': isOpen }"
        class="modal modal-bottom sm:modal-middle"
    >
        <div class="modal-box relative">
            <button
                @click="$emit('close')"
                class="btn btn-sm btn-circle absolute right-2 top-2"
            >
                ✕
            </button>
            <h3 class="font-bold text-lg mb-4">Ask TAIPO about this task</h3>

            <div class="form-control w-full">
                <div class="flex justify-between items-center mb-2">
                    <label class="label" for="task-query-input">
                        <span class="label-text">Your Question</span>
                    </label>
                    <div class="dropdown dropdown-end">
                        <button type="button" class="btn btn-xs btn-ghost text-info">
                            ✨ Quick Prompts
                        </button>
                        <div tabindex="0" role="menu" class="dropdown-content z-[2] menu p-2 shadow bg-base-100 rounded-box w-52">
                            <div
                                v-for="t in templates" :key="t.label"
                                role="menuitem"
                            >
                                <button
                                    @click="applyTemplate(t.text)"
                                    type="button"
                                >
                                    {{ t.label }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <textarea
                    v-model="query"
                    @keydown.enter.ctrl="submitQuery"
                    :maxlength="maxQueryLength"
                    ref="queryInput"
                    id="task-query-input"
                    class="textarea h-28 w-full border-outline-cyan-400"
                    placeholder="e.g., How do I implement the login logic?"
                >
                </textarea>
                <div class="label mt-2">
                    <span class="label-text-alt">Ctrl+Enter to submit</span>
                </div>
            </div>

            <div class="modal-action justify-between items-center">
                <span 
                    :class="query.length >= maxQueryLength ? 'text-error font-bold' : 'opacity-60'"
                    class="text-green-400"
                >
                    {{ maxQueryLength - query.length }} chars remaining
                </span>
                <button
                    @click="submitQuery"
                    :disabled="loading || !query.trim()"
                    class="btn btn-primary"
                >
                    <span
                        v-if="loading"
                        class="loading loading-spinner"
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
                v-if="answer || error"
                class="mt-6 border-t pt-4"
            >
                <div
                    v-if="error"
                    class="alert alert-error"
                >
                    <p class="font-bold">{{ error.split(' - Response:')[0] }}</p>
                    <div v-if="error.includes(' - Response:')" class="mt-2 text-xs opacity-75 font-mono break-all bg-black/10 p-2 rounded">
                        {{ error.split(' - Response:')[1] }}
                    </div>
                </div>
                <div
                    v-else
                    class="prose"
                >
                    <h4 class="text-sm font-bold uppercase text-base-content/50">TAIPO Answer:</h4>
                    <div class="bg-base-200 p-4 rounded-lg whitespace-pre-wrap text-sm">{{ answer }}</div>
                </div>
            </div>
        </div>
        <form method="dialog" class="modal-backdrop">
            <button @click="$emit('close')">close</button>
        </form>
    </div>
</template>

<script setup>
import { ref, watch, nextTick } from 'vue';

const props = defineProps({
    isOpen: Boolean,
    loading: Boolean,
    answer: String,
    error: String,
    maxQueryLength: {
        type: Number,
        default: 1320
    }
});

const emit = defineEmits(['close', 'submit']);

const query = ref('');
const queryInput = ref(null);

watch(() => props.isOpen, async (newVal) => {
    if (newVal) {
        query.value = ''; // Reset query on open
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
    { label: "Explain this task", text: "Explain what this task is about and what needs to be done." },
    { label: "Suggest implementation", text: "Suggest a technical implementation plan for this task." },
    { label: "Generate test cases", text: "Generate a list of test cases for this task." },
    { label: "Security check", text: "Identify potential security risks associated with this task." },
    { label: "Code snippets", text: "Provide code snippets to help get started with this task." },
];

const applyTemplate = (text) => {
    query.value = text;
    // unfocus dropdown to close it
    if (document.activeElement instanceof HTMLElement) {
        document.activeElement.blur();
    }
    // Focus textarea
    nextTick(() => {
        queryInput.value?.focus();
    });
};
</script>
