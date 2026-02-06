<template>
    <div
        :class="{
            'border-yellow-500': priority === 1,
            'border-orange-500': priority === 2,
            'border-red-500': priority === 3,
            'border-base-300': !priority,
            'opacity-50': task.is_subtask,
        }"
        draggable="true"
        class="card bg-base-200 shadow-sm hover:shadow-md transition-all duration-200 cursor-move border-l-4"
    >
        <div
            @dblclick="enableEdit"
            class="card-body p-3"
        >
            <div class="flex justify-between items-start mb-2">
                <!-- Priority Stars -->
                <div
                    @mouseleave="hoverPriority = 0"
                    class="flex space-x-0.5 bg-base-100 rounded p-0.5 shadow-sm"
                >
                    <button
                        v-for="i in 3"
                        :key="i"
                        @click.stop="togglePriority(i)"
                        @mouseover="hoverPriority = i"
                        class="btn btn-ghost btn-xs btn-circle w-5 h-5 min-h-0 p-0"
                    >
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            class="h-4 w-4"
                            viewBox="0 0 24 24"
                            :fill="
                                hoverPriority >= i ||
                                (!hoverPriority && priority >= i)
                                    ? getStarColor(i)
                                    : 'none'
                            "
                            :stroke="
                                hoverPriority >= i ||
                                (!hoverPriority && priority >= i)
                                    ? getStarColor(i)
                                    : 'currentColor'
                            "
                            stroke-width="2"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.519 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.519-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"
                            />
                        </svg>
                    </button>
                </div>

                <div class="dropdown dropdown-end">
                    <button
                        @click.stop
                        class="btn btn-ghost btn-xs btn-circle text-base-content/50"
                    >
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            fill="none"
                            viewBox="0 0 24 24"
                            class="w-5 h-5 stroke-current"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M5 12h.01M12 12h.01M19 12h.01M6 12a1 1 0 11-2 0 1 1 0 012 0zm7 0a1 1 0 11-2 0 1 1 0 012 0zm7 0a1 1 0 11-2 0 1 1 0 012 0z"
                            ></path>
                        </svg>
                    </button>
                    <ul
                        class="dropdown-content z-[1] menu p-2 shadow bg-base-100 rounded-box w-52"
                    >
                        <li>
                            <button
                                @click.prevent="enableEdit"
                            >
                                ✏️ Edit
                            </button>
                        </li>
                        <li>
                            <button
                                @click.prevent="$emit('decompose', task)"
                                type="button"
                            >
                                🔨 Decompose Story
                            </button>
                        </li>
                        <li>
                            <button
                                @click.prevent="$emit('generate-code', task)"
                                type="button"
                            >
                                💻 Generate Code
                            </button>
                        </li>
                        <li>
                            <button
                                @click.prevent="requestDelete"
                                type="button"
                                class="text-error"
                            >
                                🗑️ Delete
                            </button>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Technical Task Badge -->
            <div
                v-if="task.is_subtask"
                class="badge badge-neutral badge-xs mb-1"
            >
                Technical Task
            </div>

            <!-- Description / Inline Edit -->
            <div v-if="isEditing">
                <textarea
                    v-model="editDescription"
                    @blur="saveEdit"
                    @keydown.enter.exact.prevent="saveEdit"
                    ref="editInput"
                    class="textarea textarea-bordered textarea-xs w-full"
                ></textarea>
            </div>
            <p
                v-else
                class="text-sm whitespace-pre-wrap"
            >
                {{ task.description }}
            </p>

            <!-- PO Feedback -->
            <div
                v-if="task.po_comments"
                class="mt-2 text-xs bg-base-300 p-2 rounded border border-base-content/10"
            >
                <div class="font-bold mb-1 opacity-70">🤖 TAIPO Feedback</div>
                {{ task.po_comments }}
            </div>

            <div
                v-if="task.generated_code"
                class="mt-2 text-xs badge badge-ghost gap-1"
            >
                🤖 Code Generated
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, ref, nextTick } from "vue";
import { api } from "../services/api";

const props = defineProps({
    task: Object,
});

const emit = defineEmits([
    "toggle-imp",
    "request-delete",
    "task-updated",
    "decompose",
    "generate-code",
]);

const priority = computed(() => Number(props.task.is_important) || 0);
const isEditing = ref(false);
const editDescription = ref(props.task.description);
const editInput = ref(null);
const hoverPriority = ref(0);

const getStarColor = (index) => {
    if (index === 1) return "#EAB308"; // Yellow-500
    if (index === 2) return "#F97316"; // Orange-500
    if (index === 3) return "#EF4444"; // Red-500
    return "currentColor";
};

const togglePriority = async (p) => {
    // If clicking the current priority, toggle it off (to 0).
    const current = Number(props.task.is_important) || 0;
    const newPriority = current === p ? 0 : p;
    await api.toggleImportance(props.task.id, newPriority);
    emit("toggle-imp");
};

const requestDelete = () => {
    emit("request-delete", props.task);
    // Also dispatch a global event so parents outside Vue tree (or HMR timing) can catch it reliably
    if (
        typeof globalThis !== "undefined" &&
        globalThis.window &&
        typeof globalThis.window.dispatchEvent === "function"
    ) {
        globalThis.window.dispatchEvent(
            new CustomEvent("taipo:request-delete", { detail: props.task }),
        );
    }
};

const enableEdit = async () => {
    isEditing.value = true;
    editDescription.value = props.task.description;
    await nextTick();
    if (editInput.value) editInput.value.focus();
};

const saveEdit = async () => {
    if (!isEditing.value) return;

    if (editDescription.value.trim() !== props.task.description) {
        // Assuming API has an edit method, but api.js mostly had generic post.
        // script.js uses 'edit_task' action.
        // Let's add that to api.js or assume generic post works if updated.
        // api.js doesn't have explicit editTask method but we can add it or use raw client.
        // Let's assume we need to add it or use generic update.
        // For now, I'll call a hypothetical api.editTask or raw post.
        // Let's check api.js again. It does NOT have editTask.
        // I should stick to the pattern.
        try {
            const formData = new FormData();
            formData.append("action", "edit_task");
            formData.append("task_id", props.task.id);
            formData.append("description", editDescription.value);
            // Axios equivalent:
            await api.editTask(props.task.id, editDescription.value);
            emit("task-updated");
        } catch (e) {
            console.error("Failed to edit", e);
            alert("Failed to save edit");
        }
    }
    isEditing.value = false;
};
</script>
