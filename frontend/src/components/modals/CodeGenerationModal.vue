<template>
    <div
        :class="{ 'modal-open': isOpen }"
        class="modal modal-bottom sm:modal-middle z-50"
    >
        <div class="modal-box relative max-w-3xl bg-base-100 p-0 overflow-hidden flex flex-col max-h-[90vh] rounded-2xl border border-base-300 shadow-2xl">
            <!-- Sticky Header -->
            <div class="flex items-center justify-between px-6 py-4 border-b border-base-200 bg-base-100/90 backdrop-blur-md shrink-0">
                <h3 class="font-bold text-xl flex items-center gap-2 text-base-content">
                    <span class="w-2.5 h-2.5 bg-emerald-500 rounded-full animate-pulse"></span>
                    Implementation Preview
                </h3>
                <div class="flex items-center gap-2">
                    <button
                        v-if="code && !loading"
                        @click="copyToClipboard"
                        type="button"
                        class="btn btn-sm btn-ghost gap-2 text-primary hover:bg-primary/10"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                        </svg>
                        {{ copyTooltip }}
                    </button>
                    <button
                        @click="$emit('close')"
                        type="button"
                        class="btn btn-sm btn-circle btn-ghost"
                        aria-label="Close"
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
                    <p class="mt-4 text-sm opacity-70 font-medium">TAIPO & Gemini are architecting your code...</p>
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

                <div v-else class="h-full flex flex-col gap-4">
                    <!-- Tab switcher for multiple approaches -->
                    <div
                        v-if="parsedApproaches.length > 1"
                        class="tabs tabs-boxed bg-base-300/50 p-1 rounded-xl flex gap-1 self-start"
                        role="tablist"
                    >
                        <button
                            v-for="approach in parsedApproaches"
                            :key="approach.id"
                            @click="activeApproachId = approach.id"
                            :class="['tab tab-sm md:tab-md font-semibold transition-all duration-200 rounded-lg', activeApproachId === approach.id ? 'tab-active bg-primary text-primary-content shadow-sm' : 'hover:bg-base-200/50']"
                            role="tab"
                            :aria-selected="activeApproachId === approach.id"
                            type="button"
                        >
                            {{ approach.name }}
                        </button>
                    </div>

                    <!-- Selected approach content -->
                    <div
                        v-if="currentApproach"
                        class="prose prose-sm prose-slate max-w-none"
                    >
                        <div
                            v-html="formattedCode"
                            class="bg-base-100 border border-base-300 rounded-2xl p-6 shadow-inner text-base-content selection:bg-primary/20 overflow-x-auto custom-scrollbar"
                        >
                        </div>
                    </div>

                    <!-- AI Secondary Use Case Disclaimer -->
                    <div class="alert alert-info/10 border border-info/20 bg-info/5 rounded-xl p-3 flex gap-3 items-start text-xs text-info shrink-0 mt-2">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="stroke-info shrink-0 w-4 h-4 mt-0.5">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <div>
                            <span class="font-bold">Disclaimer:</span> Code generation is an educational utility provided to accelerate prototyping. It is not auto-committed to your repository unless manually triggered. Always review and test generated code before production use.
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer Action Bar -->
            <div class="px-6 py-4 bg-base-100 border-t border-base-200 flex justify-between items-center shrink-0">
                <button
                    @click="$emit('regenerate')"
                    :disabled="loading"
                    type="button"
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
                        @click="emitCommit"
                        type="button"
                        class="btn btn-primary btn-sm gap-2 shadow-md hover:scale-105 transition-transform"
                        title="Commit to GitHub (Moves task to DONE)"
                    >
                        <img src="../../images/github.svg" class="w-4 h-4 invert" alt="GitHub">
                        Commit
                    </button>
                    <button
                        @click="$emit('close')"
                        type="button"
                        class="btn btn-ghost btn-sm px-6 shadow-sm"
                    >
                        Dismiss
                    </button>
                </div>
            </div>
        </div>
        <form method="dialog" class="modal-backdrop">
            <button
                @click="$emit('close')"
                type="button"
            >close</button>
        </form>
    </div>
</template>

<script setup lang="ts">
import { ref, onMounted, onUnmounted, computed } from 'vue';
import { marked } from 'marked';
import type { Task } from '../../types';

interface Approach {
    id: number;
    name: string;
    content: string;
}

const props = defineProps<{
    isOpen: boolean;
    loading: boolean;
    code?: string | null;
    error?: string | null;
    task?: Task | null;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'regenerate'): void;
    (e: 'commit', code: string): void;
}>();

const activeApproachId = ref(1);
const copyTooltip = ref('Copy Code');

const parsedApproaches = computed<Approach[]>(() => {
    if (!props.code) return [];

    const parts = props.code.split(/## Approach (\d+):/i);
    if (parts.length < 3) {
        return [{
            id: 1,
            name: 'Default Approach',
            content: props.code
        }];
    }

    const approaches: Approach[] = [];
    for (let i = 1; i < parts.length; i += 2) {
        const id = Number.parseInt(parts[i], 10);
        const rawContent = parts[i + 1] || '';

        const lines = rawContent.split('\n');
        let name = `Approach ${id}`;
        let contentIndex = 0;

        for (let j = 0; j < lines.length; j++) {
            if (lines[j].trim()) {
                name = lines[j].trim();
                contentIndex = j + 1;
                break;
            }
        }

        const content = lines.slice(contentIndex).join('\n').trim();
        approaches.push({
            id: id,
            name: name,
            content: `## Approach ${id}: ${name}\n\n${content}`
        });
    }
    return approaches;
});

const currentApproach = computed(() => {
    const approaches = parsedApproaches.value;
    if (!approaches.length) return null;
    const match = approaches.find(a => a.id === activeApproachId.value);
    return match || approaches[0];
});

const formattedCode = computed(() => {
    const approach = currentApproach.value;
    if (!approach) return '';
    return marked.parse(approach.content);
});

const canCommit = computed(() => {
    if (!props.task) return false;
    return (props.task.status || '').toUpperCase().includes('REVIEW');
});

const emitCommit = () => {
    const approach = currentApproach.value;
    const codeToCommit = approach ? approach.content : (props.code || '');
    emit('commit', codeToCommit);
};

const copyToClipboard = async () => {
    try {
        const approach = currentApproach.value;
        const codeToCopy = approach ? approach.content : (props.code || '');
        if (typeof navigator !== 'undefined' && navigator.clipboard) {
            await navigator.clipboard.writeText(codeToCopy);
            copyTooltip.value = 'Copied!';
            setTimeout(() => {
                copyTooltip.value = 'Copy Code';
            }, 2000);
        }
    } catch (err) {
        console.error('Failed to copy: ', err);
    }
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
