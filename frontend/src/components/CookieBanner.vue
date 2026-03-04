<template>
    <div
        v-if="!hasConsented"
        class="fixed bottom-0 left-0 right-0 p-4 bg-neutral text-neutral-content z-[100] shadow-xl border-t border-neutral-focus flex flex-col sm:flex-row items-center justify-between gap-4"
    >
        <div class="text-sm">
            <span>
                This application uses strictly necessary cookies to keep you
                logged in. Any AI prompts or task data submitted to AI features
                will be sent to the Google Gemini API. Please avoid submitting
                Personally Identifiable Information (PII).
            </span>
            <button
                @click="$emit('open-privacy-modal')"
                class="link link-hover link-primary ml-1"
            >
                Read our Privacy Policy
            </button>
        </div>
        <div class="flex-none flex gap-2 w-full sm:w-auto">
            <button
                @click="acceptConsent"
                class="btn btn-primary btn-sm flex-1 sm:flex-auto"
            >
                I Understand
            </button>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from "vue";

const hasConsented = ref(false); // Default to false (EU requirement)

const emit = defineEmits(["open-privacy-modal"]);

onMounted(() => {
    const consent = localStorage.getItem("cookie-consent-accepted");
    if (consent === "true") {
        hasConsented.value = true;
    }
});

const acceptConsent = () => {
    localStorage.setItem("cookie-consent-accepted", "true");
    hasConsented.value = true;
};
</script>
