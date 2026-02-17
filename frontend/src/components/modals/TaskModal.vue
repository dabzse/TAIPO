<template>
    <!-- Overlay -->
    <div
        v-if="isOpen"
        @click.self="$emit('close')"
        class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50 flex items-center justify-center translate-y-0"
    >
        <!-- Modal Content -->
        <div class="relative p-5 border w-182 shadow-lg rounded-md bg-gray-500">
            <div class="flex items-center mb-4">
                <h3 class="text-lg font-semibold text-white mr-auto">{{ isReadOnly ? 'View Task' : (isEditMode ? 'Edit Task' : 'Add New Task') }}</h3>
                <div
                    @mouseleave="hoverPriority = 0"
                    class="flex space-x-1"
                >
                    <button
                        v-for="i in 3"
                        :key="i"
                        :disabled="isReadOnly"
                        @click="setPriority(i)"
                        @mouseover="hoverPriority = i"
                        class="focus:outline-none transition-colors duration-200"
                    >
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            class="h-6 w-6"
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
            </div>
            <div class="mb-4 relative">
                <label class="label font-bold text-sm text-gray-200" for="task-title">Task Title</label>
                <input
                    v-if="!isReadOnly"
                    v-model="title"
                    @keyup.enter="save"
                    ref="titleInput"
                    type="text"
                    maxlength="42"
                    id="task-title"
                    class="w-full p-2 border rounded pr-16"
                    placeholder="Task Title"
                >
                <div
                    v-else
                    class="w-full p-2 bg-gray-600 text-white rounded font-bold text-lg min-h-[40px]"
                >
                    {{ title || 'Untitled' }}
                </div>
                <div
                    v-if="!isReadOnly"
                    class="absolute right-2 bottom-2 bg-gray-100 text-green-800 text-xs px-1 rounded"
                >
                    {{ 42 - title.length }}
                </div>
            </div>

            <div class="mb-4 relative">
                <label class="label font-bold text-sm text-gray-200" for="task-desc">Description</label>
                <textarea
                    v-if="!isReadOnly"
                    v-model="description"
                    maxlength="512"
                    id="task-desc"
                    class="w-full p-2 border rounded h-24 pb-6"
                    placeholder="Task Description"
                >
                </textarea>
                <div
                    v-else
                    class="w-full p-2 bg-gray-600 text-white rounded whitespace-pre-wrap min-h-[6rem]"
                >
                    {{ description || 'No description' }}
                </div>
                <div
                    v-if="!isReadOnly"
                    class="absolute right-2 bottom-2 bg-gray-100 text-green-800 text-xs px-1 rounded"
                >
                    {{ 512 - description.length }}
                </div>
            </div>

            <!-- TAIPO Feedback (Read-Only) -->
            <div
                v-if="isReadOnly && task?.po_comments"
                class="mb-4 text-sm bg-gray-700 p-3 rounded border border-gray-400 text-white"
            >
                <div class="font-bold mb-2 text-indigo-300 flex items-center gap-2">
                    <span class="text-xl">🤖</span> TAIPO Feedback
                </div>
                <!-- Using same formatting logic as TaskCard -->
                <div
                    v-html="formattedPoComments"
                    class="prose prose-sm prose-invert max-w-none"
                >
                </div>
            </div>

            <div class="flex justify-end">
                <button
                    @click="$emit('close')"
                    class="bg-orange-500 hover:bg-orange-600 text-white font-bold py-2 px-4 rounded mr-2"
                >
                    {{ isReadOnly ? 'Close' : 'Cancel' }}
                </button>
                <button
                    v-if="!isReadOnly"
                    @click="save"
                    :disabled="!title"
                    class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    {{ isEditMode ? 'Save Changes' : 'Add Task' }}
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, watch, nextTick, computed } from "vue";

const props = defineProps({
    isOpen: Boolean,
    task: Object,
    isReadOnly: Boolean,
});

const isEditMode = computed(() => !!props.task);

const emit = defineEmits(["close", "save"]);

const priority = ref(0);
const hoverPriority = ref(0);
const title = ref("");
const description = ref("");
const titleInput = ref(null);

const formattedPoComments = computed(() => {
    if (!props.task?.po_comments) return "";
    let text = props.task.po_comments;
    // Escape HTML (basic)
    text = text.replaceAll("&", "&amp;").replaceAll("<", "&lt;").replaceAll(">", "&gt;");
    // Bold: **text** -> <b>text</b>
    text = text.replaceAll(/\*\*(.*?)\*\*/g, "<b>$1</b>");
    // Separator: --- -> <hr>
    text = text.replaceAll("\n\n---\n\n", '<hr class="my-2 border-white/20" />');
    // Newlines: \n -> <br>
    text = text.replaceAll("\n", "<br>");
    return text;
});

watch(
    () => props.isOpen,
    (newVal) => {
        if (newVal) {
            if (props.task) {
                // Edit mode
                title.value = props.task.title || '';
                // If title was auto-generated from description before migration, it might be messy, but assuming clean state.
                description.value = props.task.description || '';
                priority.value = Number(props.task.is_important) || 0;
            } else {
                // Add mode
                title.value = "";
                description.value = "";
                priority.value = 0;
            }
            hoverPriority.value = 0;
            nextTick(() => {
                titleInput.value?.focus();
            });
        }
    },
);

const getStarColor = (index) => {
    if (index === 1) return "#EAB308"; // yellow-500
    if (index === 2) return "#F97316"; // orange-500
    if (index === 3) return "#EF4444"; // red-500
    return "currentColor";
};

const setPriority = (p) => {
    if (priority.value === p) {
        priority.value = 0;
    } else {
        priority.value = p;
    }
};

const save = () => {
    if (!title.value) return;

    emit("save", { title: title.value, description: description.value, priority: priority.value });
};
</script>
