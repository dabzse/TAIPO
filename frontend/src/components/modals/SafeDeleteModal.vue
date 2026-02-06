<template>
    <div
        v-if="isOpen"
        @click.self="$emit('close')"
        class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50 flex items-center justify-center"
    >
        <div class="relative p-5 border w-96 shadow-lg rounded-md bg-base-100">
            <h3 class="text-lg font-bold mb-4 text-error">⚠️ Confirm Deletion</h3>
            <p class="mb-4 text-sm">Are you sure you want to delete this task?<br>
                <span class="font-semibold italic">"{{ taskDescription }}"</span>
            </p>
            <p class="mb-2 text-xs text-base-content/70">To confirm, type
                <span class="font-mono font-bold">delete</span> below:
            </p>

            <input
                v-model="inputText"
                @keyup.enter="confirm"
                ref="confirmInput"
                type="text"
                class="input input-bordered w-full mb-4"
                placeholder="Type 'delete'"
            >

            <div class="flex justify-end space-x-2">
                <button
                    @click="$emit('close')"
                    class="btn btn-ghost"
                >
                    Cancel
                </button>
                <button
                    @click="confirm"
                    :disabled="inputText !== 'delete'"
                    class="btn btn-error"
                >
                    Delete
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, watch, nextTick } from "vue";

const props = defineProps({
    isOpen: Boolean,
    taskDescription: String,
});

const emit = defineEmits(["close", "confirm"]);

const inputText = ref("");
const confirmInput = ref(null);

watch(
    () => props.isOpen,
    (newVal) => {
        if (newVal) {
            inputText.value = "";
            nextTick(() => {
                confirmInput.value?.focus();
            });
        }
    },
);

const confirm = () => {
    if (inputText.value === "delete") {
        emit("confirm");
    }
};
</script>
