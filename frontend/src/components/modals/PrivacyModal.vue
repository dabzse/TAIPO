<template>
    <dialog
        :class="['modal', { 'modal-open': isOpen }]"
        @close="$emit('close')"
    >
        <div class="modal-box w-11/12 max-w-2xl">
            <h3 class="font-bold text-xl mb-4 text-primary">
                Privacy Policy & Terms of Service
            </h3>

            <div class="space-y-4 text-sm text-base-content/80 overflow-y-auto max-h-[60vh] pr-2">
                <section>
                    <h4 class="font-semibold text-base text-base-content mb-1">
                        1. Local Storage
                    </h4>
                    <p>
                        This application (TAIPO) is designed to run locally.
                        All of your user data, projects, tasks, and settings
                        are stored locally on the server instance running this
                        application in a SQLite database.
                    </p>
                </section>

                <section>
                    <h4 class="font-semibold text-base text-base-content mb-1">
                        2. Third-Party Services (Google Gemini API)
                    </h4>
                    <p>
                        Certain features in this application utilize the Google
                        Gemini API to generate content, such as projecting
                        tasks, answering questions, and generating code. When
                        you use these specific AI-assisted features, the
                        relevant prompts, project titles, and task descriptions
                        you provide are sent securely to Google's servers.
                    </p>
                    <p class="mt-2 text-warning font-semibold">
                        Warning: Do not include Personally Identifiable
                        Information (PII) or strictly confidential data in your
                        project names, task descriptions, or AI prompts.
                    </p>
                </section>

                <section>
                    <h4 class="font-semibold text-base text-base-content mb-1">
                        3. Cookies
                    </h4>
                    <p>
                        We use a single, strictly necessary session cookie
                        (<code>PHPSESSID</code>) to keep you securely logged in
                        during your session. This cookie does not track you
                        across the internet and is required for the application
                        to function correctly.
                    </p>
                </section>

                <section>
                    <h4 class="font-semibold text-base text-base-content mb-1">
                        4. GitHub Integration
                    </h4>
                    <p>
                        If you configure a GitHub token in the backend settings,
                        the application will communicate with the GitHub API to
                        commit generated code. Your token and repository details
                        are stored securely in your local environment.
                    </p>
                </section>
            </div>

            <div class="modal-action">
                <form method="dialog">
                    <button class="btn btn-outline" @click="$emit('close')">
                        Close
                    </button>

                    <button class="btn btn-primary ml-2" @click="handleAccept">
                        I Understand
                    </button>
                </form>
            </div>
        </div>
        <form method="dialog" class="modal-backdrop">
            <button @click="$emit('close')">close</button>
        </form>
    </dialog>
</template>

<script setup>
const props = defineProps({
    isOpen: Boolean,
});

const emit = defineEmits(['close', 'accept']);

const handleAccept = () => {
    emit('accept');
    emit('close');
};
</script>
