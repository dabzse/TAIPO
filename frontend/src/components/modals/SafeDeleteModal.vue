<template>
    <div
        v-if="isOpen"
        class="fixed inset-0 bg-neutral/60 backdrop-blur-sm z-[100] flex items-center justify-center p-4 animate-in fade-in duration-200"
        @click.self="$emit('close')"
    >
        <div class="relative w-full max-w-md bg-base-100 border border-base-300 shadow-2xl rounded-2xl overflow-hidden animate-in zoom-in duration-200">
            <div class="p-6">
                <!-- Icon and Title -->
                <div class="flex items-center gap-4 mb-6">
                    <div class="w-12 h-12 rounded-full bg-error/10 text-error flex items-center justify-center shrink-0">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-xl font-bold text-base-content leading-tight">Permanent Deletion</h3>
                        <p class="mt-1 text-base-content/70 text-sm">This action cannot be undone.</p>
                    </div>
                </div>

                <div class="space-y-4">
                    <p class="text-base-content/80 text-sm leading-relaxed">
                        Are you sure you want to delete: <br>
                        <span class="text-base-content font-bold italic">"{{ taskDescription }}"</span>
                    </p>

                    <div>
                        <label class="block text-[11px] font-bold text-base-content/60 uppercase tracking-widest mb-2 ml-1" for="safe-delete-input">
                            Type <span class="text-error font-mono">delete</span> to confirm
                        </label>
                        <input
                            v-model="inputText"
                            @keyup.enter="confirm"
                            ref="confirmInput"
                            id="safe-delete-input"
                            type="text"
                            class="w-full bg-base-200 border border-base-300 text-base-content rounded-xl p-3 focus:ring-2 focus:ring-error/50 focus:border-error outline-none transition-all duration-300 shadow-sm placeholder:text-base-content/40 text-sm"
                            placeholder="Type 'delete'..."
                        >
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="flex flex-col sm:flex-row-reverse gap-2 mt-8">
                    <button
                        @click="confirm"
                        :disabled="inputText !== 'delete'"
                        type="button"
                        class="px-6 py-2.5 rounded-xl font-bold text-sm bg-error hover:bg-error/90 text-error-content shadow-lg shadow-error/20 disabled:opacity-30 disabled:cursor-not-allowed transition-all duration-300"
                    >
                        Delete Task
                    </button>
                    <button
                        @click="$emit('close')"
                        type="button"
                        class="px-6 py-2.5 rounded-xl font-semibold text-sm text-base-content/70 hover:text-base-content hover:bg-base-200 transition-all duration-200"
                    >
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref, watch, nextTick } from 'vue';

const props = defineProps<{
    isOpen: boolean;
    taskDescription: string;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'confirm'): void;
}>();

const inputText = ref('');
const confirmInput = ref<HTMLInputElement | null>(null);

watch(
    () => props.isOpen,
    (newVal) => {
        if (newVal) {
            inputText.value = '';
            nextTick(() => {
                confirmInput.value?.focus();
            });
        }
    }
);

const confirm = () => {
    if (inputText.value === 'delete') {
        emit('confirm');
    }
};
</script>
