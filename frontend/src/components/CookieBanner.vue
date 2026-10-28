<template>
    <div
        v-if="!hasConsented"
        class="fixed bottom-4 left-4 right-4 md:left-auto md:right-4 md:max-w-md p-4 bg-base-100/95 backdrop-blur-md text-base-content z-[100] shadow-2xl rounded-2xl border border-base-300/80 flex flex-col gap-3 animate-in fade-in slide-in-from-bottom-4 duration-300"
    >
        <div class="flex items-start gap-3">
            <span class="text-2xl">🍪</span>
            <div class="text-xs leading-relaxed text-base-content/80">
                <p class="font-bold text-base-content mb-1">Local Session & Privacy Notice</p>
                <span>
                    This application uses strictly necessary cookies to keep you
                    logged in. Any AI prompts or task data submitted to AI features
                    will be sent to the Google Gemini API. Please avoid submitting
                    Personally Identifiable Information (PII).
                </span>
                <button
                    @click="$emit('open-privacy-modal')"
                    type="button"
                    class="link link-hover link-primary ml-1 font-semibold"
                >
                    Read our Privacy Policy
                </button>
            </div>
        </div>
        <div class="flex justify-end gap-2">
            <button
                @click="acceptConsent"
                type="button"
                class="btn btn-primary btn-xs px-4 rounded-lg font-bold shadow-md shadow-primary/20"
            >
                I Understand
            </button>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue';

const hasConsented = ref<boolean>(false);

defineEmits<{
    (e: 'open-privacy-modal'): void;
}>();

onMounted(() => {
    const consent = localStorage.getItem('cookie-consent-accepted');
    if (consent === 'true') {
        hasConsented.value = true;
    }
});

const acceptConsent = () => {
    localStorage.setItem('cookie-consent-accepted', 'true');
    hasConsented.value = true;
};
</script>
