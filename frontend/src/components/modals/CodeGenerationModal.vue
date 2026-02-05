<template>
    <div class="modal modal-bottom sm:modal-middle" :class="{ 'modal-open': isOpen }">
        <div class="modal-box relative">
            <button class="btn btn-sm btn-circle absolute right-2 top-2" @click="$emit('close')">✕</button>
            <h3 class="font-bold text-lg">Generated Code</h3>

            <div v-if="loading" class="flex flex-col items-center justify-center p-8">
                <span class="loading loading-spinner loading-lg text-primary"></span>
                <p class="mt-4 text-sm opacity-70">AI is writing your code...</p>
            </div>

            <div v-else-if="error" class="alert alert-error mt-4">
                {{ error }}
            </div>

            <div v-else class="mt-4">
                <div class="mockup-code bg-neutral text-neutral-content max-h-[60vh] overflow-y-auto">
                    <pre><code>{{ code }}</code></pre>
                </div>
            </div>

            <div class="modal-action">
                <button class="btn" @click="$emit('close')">Close</button>
            </div>
        </div>
        <form method="dialog" class="modal-backdrop">
            <button @click="$emit('close')">close</button>
        </form>
    </div>
</template>

<script setup>
import { defineProps } from 'vue';

const props = defineProps({
    isOpen: Boolean,
    loading: Boolean,
    code: String,
    error: String
});
</script>
