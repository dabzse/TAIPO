<template>
    <dialog
        :class="['modal z-50', { 'modal-open': isOpen }]"
        :open="isOpen"
        @close="$emit('close')"
    >
        <div class="modal-box w-11/12 max-w-2xl bg-base-100 rounded-2xl border border-base-300 shadow-2xl">
            <h3 class="font-bold text-xl mb-4 text-primary flex items-center gap-2">
                <span>🛡️</span>
                <span>Privacy Policy & Terms of Service</span>
            </h3>

            <div class="space-y-4 text-xs text-base-content/80 overflow-y-auto max-h-[60vh] pr-2 custom-scrollbar">
                <section class="p-3 rounded-xl bg-base-200/50 border border-base-300/60">
                    <h4 class="font-bold text-sm text-base-content mb-1">
                        1. Local Storage
                    </h4>
                    <p class="leading-relaxed">
                        This application (TAIPO) runs locally and self-hosted. All user accounts, projects, tasks, and settings are stored locally on the server instance in a SQLite/MariaDB database.
                    </p>
                </section>

                <section class="p-3 rounded-xl bg-base-200/50 border border-base-300/60">
                    <h4 class="font-bold text-sm text-base-content mb-1">
                        2. Third-Party Services (Google Gemini API)
                    </h4>
                    <p class="leading-relaxed">
                        AI features (backlog generation, task decomposition, PO check-in simulation, and code prototyping) connect to the Google Gemini API. Relevant prompts and descriptions are transmitted securely to Google servers.
                    </p>
                    <p class="mt-2 text-warning font-semibold">
                        ⚠️ Important: Do not include Personally Identifiable Information (PII) or confidential credentials in project tasks or AI prompts.
                    </p>
                </section>

                <section class="p-3 rounded-xl bg-base-200/50 border border-base-300/60">
                    <h4 class="font-bold text-sm text-base-content mb-1">
                        3. Cookies & Sessions
                    </h4>
                    <p class="leading-relaxed">
                        We use a strictly necessary HTTP session cookie (<code>PHPSESSID</code>) to maintain authentication. No third-party ad tracking or cross-site tracking cookies are used.
                    </p>
                </section>

                <section class="p-3 rounded-xl bg-base-200/50 border border-base-300/60">
                    <h4 class="font-bold text-sm text-base-content mb-1">
                        4. GitHub API
                    </h4>
                    <p class="leading-relaxed">
                        If GitHub token authentication is used, tokens remain in volatile memory or local environment configuration to commit prototype code.
                    </p>
                </section>
            </div>

            <div class="modal-action">
                <form method="dialog" class="flex gap-2">
                    <button class="btn btn-outline btn-sm" @click="$emit('close')" type="button">
                        Close
                    </button>
                    <button class="btn btn-primary btn-sm px-6 font-bold shadow-md shadow-primary/20" @click="handleAccept" type="button">
                        I Understand
                    </button>
                </form>
            </div>
        </div>
        <form method="dialog" class="modal-backdrop">
            <button @click="$emit('close')" type="button">close</button>
        </form>
    </dialog>
</template>

<script setup lang="ts">
defineProps<{
    isOpen: boolean;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'accept'): void;
}>();

const handleAccept = () => {
    emit('accept');
    emit('close');
};
</script>
