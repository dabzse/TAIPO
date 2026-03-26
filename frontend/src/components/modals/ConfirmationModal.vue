<template>
    <div
        v-if="isOpen"
        @click.self="handleCancel"
        class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-[100] flex items-center justify-center p-4 animate-in fade-in duration-200"
    >
        <div class="relative w-full max-w-md bg-slate-800 border border-slate-700/50 shadow-2xl rounded-2xl overflow-hidden animate-in zoom-in duration-200">
            <div class="p-6">
                <!-- Icon and Title -->
                <div class="flex items-center gap-4 mb-4">
                    <div
                        :class="[
                            'w-12 h-12 rounded-full flex items-center justify-center shrink-0',
                            isDanger ? 'bg-red-500/10 text-red-500' : 'bg-indigo-500/10 text-indigo-500'
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
                        <h3 class="text-xl font-bold text-white leading-tight">{{ title }}</h3>
                        <p
                            v-if="message"
                            class="mt-1 text-slate-400 text-sm leading-relaxed"
                        >
                            {{ message }}
                        </p>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="flex flex-col sm:flex-row-reverse gap-2 mt-6">
                    <button
                        @click="handleConfirm"
                        :class="[
                            'px-6 py-2.5 rounded-xl font-bold text-sm transition-all duration-200 shadow-lg',
                            isDanger 
                                ? 'bg-red-600 hover:bg-red-500 active:bg-red-700 text-white shadow-red-900/20'
                                : 'bg-indigo-600 hover:bg-indigo-500 active:bg-indigo-700 text-white shadow-indigo-900/20'
                        ]"
                    >
                        {{ confirmText }}
                    </button>
                    <button
                        v-if="!isAlert"
                        @click="handleCancel"
                        class="px-6 py-2.5 rounded-xl font-semibold text-sm text-slate-400 hover:text-white hover:bg-slate-700/50 transition-all duration-200"
                    >
                        {{ cancelText }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { onMounted, onUnmounted } from 'vue';

const props = defineProps({
    isOpen: Boolean,
    title: {
        type: String,
        default: 'Confirmation'
    },
    message: String,
    confirmText: {
        type: String,
        default: 'Confirm'
    },
    cancelText: {
        type: String,
        default: 'Cancel'
    },
    isDanger: {
        type: Boolean,
        default: false
    },
    isAlert: {
        type: Boolean,
        default: false
    }
});

const emit = defineEmits(['confirm', 'cancel', 'close']);

const handleConfirm = () => {
    emit('confirm');
    emit('close');
};

const handleCancel = () => {
    emit('cancel');
    emit('close');
};

const handleEsc = (e) => {
    if (e.key === 'Escape' && props.isOpen) {
        handleCancel();
    }
};

onMounted(() => {
    globalThis.addEventListener('keydown', handleEsc);
});

onUnmounted(() => {
    globalThis.removeEventListener('keydown', handleEsc);
});
</script>
