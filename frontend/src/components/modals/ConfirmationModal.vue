<template>
    <div
        v-if="isOpen"
        @click.self="handleCancel"
        class="fixed inset-0 bg-neutral/60 backdrop-blur-sm z-[9999] flex items-center justify-center p-4 animate-in fade-in duration-200"
    >
        <div class="relative w-full max-w-md bg-base-100 border border-base-300 shadow-2xl rounded-2xl overflow-hidden animate-in zoom-in duration-200">
            <div class="p-6">
                <!-- Icon and Title -->
                <div class="flex items-center gap-4 mb-4">
                    <div
                        :class="[
                            'w-12 h-12 rounded-full flex items-center justify-center shrink-0',
                            isDanger ? 'bg-error/10 text-error' : 'bg-primary/10 text-primary'
                        ]"
                    >
                        <svg v-if="isDanger" xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        <svg v-else xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-xl font-bold text-base-content leading-tight">{{ title }}</h3>
                        <p
                            v-if="message"
                            class="mt-1 text-base-content/70 text-sm leading-relaxed"
                        >
                            {{ message }}
                        </p>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="flex flex-col sm:flex-row-reverse gap-2 mt-6">
                    <button
                        @click="handleConfirm"
                        type="button"
                        :class="[
                            'px-6 py-2.5 rounded-xl font-bold text-sm transition-all duration-200 shadow-lg',
                            isDanger 
                                ? 'bg-error hover:bg-error/90 text-error-content shadow-error/20'
                                : 'bg-primary hover:bg-primary/90 text-primary-content shadow-primary/20'
                        ]"
                    >
                        {{ confirmText }}
                    </button>
                    <button
                        v-if="!isAlert"
                        @click="handleCancel"
                        type="button"
                        class="px-6 py-2.5 rounded-xl font-semibold text-sm text-base-content/70 hover:text-base-content hover:bg-base-200 transition-all duration-200"
                    >
                        {{ cancelText }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { onMounted, onUnmounted } from 'vue';

const props = withDefaults(
    defineProps<{
        isOpen: boolean;
        title?: string;
        message?: string;
        confirmText?: string;
        cancelText?: string;
        isDanger?: boolean;
        isAlert?: boolean;
    }>(),
    {
        title: 'Confirmation',
        confirmText: 'Confirm',
        cancelText: 'Cancel',
        isDanger: false,
        isAlert: false,
    }
);

const emit = defineEmits<{
    (e: 'confirm'): void;
    (e: 'cancel'): void;
    (e: 'close'): void;
}>();

const handleConfirm = () => {
    emit('confirm');
    emit('close');
};

const handleCancel = () => {
    emit('cancel');
    emit('close');
};

const handleEsc = (e: KeyboardEvent) => {
    if (e.key === 'Escape' && props.isOpen) {
        handleCancel();
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
