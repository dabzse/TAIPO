<template>
    <div
        v-if="isOpen"
        @click.self="$emit('close')"
        class="fixed inset-0 bg-neutral/60 backdrop-blur-sm overflow-y-auto h-full w-full z-50 flex items-center justify-center p-4 transition-all duration-300"
    >
        <div class="relative w-full max-w-2xl flex flex-col shadow-2xl rounded-2xl bg-base-100 border border-base-300 overflow-hidden max-h-[min(90vh,750px)] animate-in fade-in zoom-in duration-200">
            <!-- Modal Header -->
            <div class="px-6 py-4 border-b border-base-300 flex items-center justify-between bg-base-100/80 backdrop-blur-md">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-xl brand-gradient flex items-center justify-center text-white font-bold text-lg shadow-md">
                        ?
                    </div>
                    <div>
                        <h3 class="text-lg font-bold text-base-content">
                            TAIPO Documentation & Guide
                        </h3>
                        <p class="text-xs text-base-content/60">
                            Teaching AI Product Owner & Agile Kanban Simulation
                        </p>
                    </div>
                </div>
                <button
                    @click="$emit('close')"
                    class="btn btn-ghost btn-sm btn-circle"
                    aria-label="Close"
                >
                    ✕
                </button>
            </div>

            <!-- Content -->
            <div class="p-6 overflow-y-auto space-y-5 text-sm custom-scrollbar">
                <!-- Section 1: Overview -->
                <div class="p-4 rounded-xl bg-base-200/50 border border-base-300/60 space-y-2">
                    <h4 class="font-bold text-primary flex items-center gap-2">
                        <span>🤖 What is TAIPO?</span>
                    </h4>
                    <p class="text-xs text-base-content/80 leading-relaxed">
                        TAIPO simulates a real-world Product Owner powered by the <strong>Google Gemini API</strong>. It brainstorms project user stories, decomposes high-level stories into technical tasks, provides periodic sprint feedback check-ins, and introduces realistic Change Requests based on authentic agile data from the TAWOS dataset.
                    </p>
                </div>

                <!-- Section 2: Agile Workflow & WIP Limits -->
                <div class="p-4 rounded-xl bg-base-200/50 border border-base-300/60 space-y-2">
                    <h4 class="font-bold text-info flex items-center gap-2">
                        <span>📊 Kanban Stages & WIP Limits</span>
                    </h4>
                    <p class="text-xs text-base-content/80 leading-relaxed">
                        Tasks move across 5 stages by drag-and-drop:
                    </p>
                    <ul class="text-xs space-y-1 text-base-content/80 list-disc list-inside">
                        <li><strong>SPRINT BACKLOG:</strong> User stories and technical tasks ready for implementation.</li>
                        <li><strong>IMPLEMENTATION (WIP: 3):</strong> Active coding phase. Maximum 3 simultaneous tasks to maintain focus.</li>
                        <li><strong>TESTING (WIP: 2):</strong> Automated and manual verification. Maximum 2 tasks.</li>
                        <li><strong>REVIEW (WIP: 2):</strong> Code review and PO approval. Maximum 2 tasks.</li>
                        <li><strong>DONE:</strong> Completed and committed features.</li>
                    </ul>
                    <div class="p-2.5 rounded-lg bg-warning/10 border border-warning/20 text-xs text-warning mt-2 leading-relaxed">
                        ⚠️ <strong>WIP Enforcement:</strong> Moving a task into a column that has reached its WIP limit is blocked until active tasks are progressed.
                    </div>
                </div>

                <!-- Section 3: Gemini Key Hierarchy -->
                <div class="p-4 rounded-xl bg-base-200/50 border border-base-300/60 space-y-2">
                    <h4 class="font-bold text-accent flex items-center gap-2">
                        <span>🔑 Gemini Key Hierarchy (?student → ?team → :university)</span>
                    </h4>
                    <p class="text-xs text-base-content/80 leading-relaxed">
                        You can manage your key in <strong>⚙️ Settings</strong>:
                    </p>
                    <ul class="text-xs space-y-1 text-base-content/80 list-disc list-inside">
                        <li><strong>?student:</strong> Your personal key (paste or drag <code>.apikey</code> / <code>.apikeyusb</code>). USB mode runs strictly in session memory without disk persistence.</li>
                        <li><strong>?team:</strong> Shared team key for collaborative team projects.</li>
                        <li><strong>:university:</strong> Central university server fallback key configured in the environment.</li>
                    </ul>
                </div>

                <!-- Section 4: Keyboard Shortcuts -->
                <div class="p-4 rounded-xl bg-base-200/50 border border-base-300/60 space-y-2">
                    <h4 class="font-bold text-secondary flex items-center gap-2">
                        <span>⌨️ Keyboard Shortcuts</span>
                    </h4>
                    <div class="grid grid-cols-2 gap-2 text-xs">
                        <div class="flex items-center justify-between p-2 rounded bg-base-100 border border-base-300">
                            <span>Close any modal</span>
                            <kbd class="kbd kbd-sm">Esc</kbd>
                        </div>
                        <div class="flex items-center justify-between p-2 rounded bg-base-100 border border-base-300">
                            <span>Toggle Projects Drawer</span>
                            <kbd class="kbd kbd-sm">Alt + P</kbd>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="px-6 py-3 border-t border-base-300 flex justify-end bg-base-100">
                <button
                    @click="$emit('close')"
                    class="btn btn-primary btn-sm px-6"
                >
                    Got It
                </button>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
defineProps<{
    isOpen: boolean;
}>();

defineEmits<{
    (e: 'close'): void;
}>();
</script>
