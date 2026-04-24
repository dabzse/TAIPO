<template>
    <div
        :class="{ 'modal-open': isOpen }"
        class="modal modal-bottom sm:modal-middle"
    >
        <div class="modal-box relative max-w-3xl bg-base-100 p-0 overflow-hidden flex flex-col max-h-[90vh]">
            <!-- Sticky Header -->
            <div class="flex items-center justify-between px-6 py-4 border-b border-base-200 bg-base-100 shrink-0">
                <h3 class="font-bold text-xl flex items-center gap-2">
                    <span class="w-2 h-2 bg-emerald-500 rounded-full animate-pulse"></span>
                    Implementation
                </h3>
                <div class="flex items-center gap-2">
                    <button
                        v-if="code && !loading"
                        @click="copyToClipboard"
                        class="btn btn-sm btn-ghost gap-2 text-primary hover:bg-primary/10"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                        </svg>
                        {{ copyTooltip }}
                    </button>
                    <button
                        @click="$emit('close')"
                        class="btn btn-sm btn-circle btn-ghost"
                    >
                        ✕
                    </button>
                </div>
            </div>

            <!-- Scrollable Content Area -->
            <div class="flex-grow overflow-y-auto custom-scrollbar p-6 bg-base-200/30">
                <div
                    v-if="loading"
                    class="flex flex-col items-center justify-center py-20"
                >
                    <span class="loading loading-spinner loading-lg text-primary"></span>
                    <p class="mt-4 text-sm opacity-60 font-medium">TAIPO is architecting your code...</p>
                </div>

                <div
                    v-else-if="error"
                    class="alert alert-error shadow-lg"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    <div class="flex flex-col gap-0.5">
                        <span class="font-bold">Generation Failed</span>
                        <span class="text-xs opacity-90">{{ error.split(' - Response:')[0] }}</span>
                    </div>
                </div>

                <div v-else class="h-full">
                    <div
                        v-if="code"
                        class="prose prose-sm prose-slate max-w-none"
                    >
                        <div
                            v-html="formattedCode"
                            class="bg-base-100 border border-base-300 rounded-2xl p-6 shadow-inner text-base-content selection:bg-primary/20"
                        >
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer Action Bar -->
            <div class="px-6 py-4 bg-base-100 border-t border-base-200 flex justify-between items-center shrink-0">
                <button
                    @click="$emit('regenerate')"
                    :disabled="loading"
                    class="btn btn-outline btn-sm gap-2 hover:bg-emerald-500 hover:border-emerald-500 hover:text-white transition-all duration-300"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                    Regenerate
                </button>
                <div class="flex gap-2">
                    <button
                        v-if="code && !loading && canCommit"
                        @click="$emit('commit')"
                        class="btn btn-primary btn-sm gap-2 shadow-md hover:scale-105 transition-transform"
                        title="Commit to GitHub (Moves task to DONE)"
                    >
                        <img src="../../images/github.svg" class="w-4 h-4 invert" alt="GitHub">
                        Commit
                    </button>
                    <button
                        @click="$emit('close')"
                        class="btn btn-ghost px-6 shadow-sm"
                    >
                        Dismiss
                    </button>
                </div>
            </div>
        </div>
        <form method="dialog" class="modal-backdrop">
            <button
                @click="$emit('close')"
            >close</button>
        </form>
    </div>
</template>


<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue';
import { marked } from 'marked';
import axios from 'axios';

const props = defineProps({
    isOpen: Boolean,
    loading: Boolean,
    code: String,
    error: String,
    task: Object,
});

const emit = defineEmits(['close', 'regenerate', 'commit']);

const isCommitting = ref(false);

const commitToGithub = async () => {
    if (!props.code) return;
    isCommitting.value = true;
    try {
        const formData = new FormData();
        formData.append('action', 'commit_to_github');
        formData.append('task_id', props.task?.id || '');
        formData.append('code', props.code);

        const response = await axios.post('http://localhost:8000/', formData, {
            withCredentials: true
        });

        if (response.data.success) {
            alert('🚀 Siker! A kód feltöltve és a feladat DONE állapotba került.');
            emit('close');
        } else {
            alert('❌ Hiba: ' + response.data.error);
        }
    } catch (err) {
        // ... hibakezelés marad ...
    } finally {
        isCommitting.value = false;
    }
};

const formattedCode = computed(() => {
    if (!props.code) return '';
    return marked.parse(props.code);
});

const canCommit = computed(() => {
    if (!props.task) return false;
    // Commits are only allowed from REVIEW column per JIRA-style process
    return props.task.status?.toUpperCase().includes('REVIEW');
});

const copyTooltip = ref('Copy Code');

const copyToClipboard = async () => {
    try {
        await navigator.clipboard.writeText(props.code);
        copyTooltip.value = 'Copied!';
        setTimeout(() => {
            copyTooltip.value = 'Copy Code';
        }, 2000);
    } catch (err) {
        console.error('Failed to copy: ', err);
    }
};

const handleEsc = (e) => {
    if (e.key === 'Escape' && props.isOpen) {
        emit('close');
    }
};

onMounted(() => {
    globalThis.addEventListener('keydown', handleEsc);
});

onUnmounted(() => {
    globalThis.removeEventListener('keydown', handleEsc);
});
</script>
