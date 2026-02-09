<template>
    <div class="flex flex-nowrap overflow-x-auto gap-4 p-4 h-[calc(100vh-140px)]">
        <div
            v-for="(style, title) in columns"
            :key="title"
            class="min-w-[280px] flex flex-col bg-base-100 rounded-box shadow-xl h-full"
        >
            <!-- Column Header -->
            <div
                :class="`p-4 rounded-t-box font-bold flex justify-between items-center bg-${getResultingColor(style)} text-primary-content`"
            >
                <span>{{ title }}</span>
                <div class="badge badge-ghost">
                    {{ tasks[title]?.length || 0 }}
                </div>
            </div>

            <!-- Add Task Button (Top - Only for Backlog) -->
            <div
                v-if="title.includes('BACKLOG')"
                class="p-2"
            >
                <button
                    @click="openAddTaskModal"
                    class="w-full bg-blue-500 text-white py-2 px-4 rounded hover:bg-blue-600 transition duration-300"
                >
                    Add Task
                </button>
            </div>

            <!-- Task List (Draggable) -->
            <draggable
                v-if="tasks[title]"
                v-model="tasks[title]"
                @change="onDraggableChange($event, title)"
                group="tasks"
                ghost-class="opacity-50"
                item-key="id"
                class="flex-1 overflow-y-auto p-2 space-y-2 min-h-[100px]"
            >
                <template #item="{ element }">
                    <TaskCard
                        :task="element"
                        @request-delete="confirmDeleteTask(element)"
                        @toggle-imp="$emit('task-updated')"
                        @task-updated="$emit('task-updated')"
                        @decompose="$emit('decompose', element)"
                        @generate-code="$emit('generate-code', element)"
                    />
                </template>
            </draggable>
            <!-- Fallback if tasks[title] is undefined/null to prevent draggable error -->
            <div
                v-else
                class="flex-1 overflow-y-auto p-2 space-y-2 min-h-[100px] flex items-center justify-center text-base-content/50 italic"
            >
                No tasks data
            </div>

            <!-- Add Task Button (Bottom - Only for Backlog) -->
            <div
                v-if="title.includes('BACKLOG')"
                class="p-2 mt-auto"
            >
                <button
                    @click="openAddTaskModal"
                    class="w-full bg-blue-500 text-white py-2 px-4 rounded hover:bg-blue-600 transition duration-300"
                >
                    Add Task
                </button>
            </div>
        </div>

        <TaskModal
            :is-open="isTaskModalOpen"
            @close="isTaskModalOpen = false"
            @save="handleAddTask"
        />

        <Teleport to="body">
            <SafeDeleteModal
                :is-open="isDeleteModalOpen"
                :task-description="taskToDelete?.description || ''"
                @close="isDeleteModalOpen = false"
                @confirm="handleTaskDeleted"
            />
        </Teleport>
    </div>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount } from "vue";
import draggable from "vuedraggable";
import TaskCard from "./TaskCard.vue";
import TaskModal from "./modals/TaskModal.vue";
import SafeDeleteModal from "./modals/SafeDeleteModal.vue";
import { api } from "../services/api";

const props = defineProps({
    columns: Object,
    tasks: Object,
    currentProject: String,
});

const emit = defineEmits([
    "task-updated",
    "task-deleted",
    "task-added",
    "decompose",
    "generate-code",
]);

const isTaskModalOpen = ref(false);

const getResultingColor = (style) => {
    // Mapping internal style names to DaisyUI/Tailwind colors if needed
    if (style === "danger") return "error";
    return style;
};

const onDraggableChange = async (event, newStatus) => {
    if (event.added) {
        const task = event.added.element;
        try {
            await api.updateStatus(task.id, newStatus, props.currentProject);
            emit("task-updated");
        } catch (e) {
            console.error("Failed to update status", e);
            alert(e.response?.data?.error || "Failed to move task");
            emit("task-updated"); // Revert by refreshing from server
        }
    }
};

const openAddTaskModal = () => {
    isTaskModalOpen.value = true;
};

const handleAddTask = async (payload) => {
    // Close modal immediately
    isTaskModalOpen.value = false;

    let description, priority;

    if (typeof payload === "object") {
        description = payload.description;
        priority = payload.priority;
    } else {
        description = payload;
        priority = 0;
    }

    if (!description) return;
    try {
        await api.addTask(props.currentProject, description, priority);
        emit("task-added");
    } catch (e) {
        alert("Failed to add task: " + e.message);
    }
};

// --- Safe Delete Logic ---
const isDeleteModalOpen = ref(false);
const taskToDelete = ref(null);

const confirmDeleteTask = (task) => {
    console.log("confirmDeleteTask called", task);
    taskToDelete.value = task;
    isDeleteModalOpen.value = true;
    // expose a simple global flag for E2E checks (guarded)
    if (globalThis.window !== undefined) {
        globalThis.window.__taipo_delete_state = { open: true, task };
    }
};

const handleGlobalDelete = (e) => {
    confirmDeleteTask(e.detail);
};

onMounted(() => {
    if (globalThis !== undefined && globalThis.window) {
        globalThis.window.addEventListener(
            "taipo:request-delete",
            handleGlobalDelete,
        );
    }
});

onBeforeUnmount(() => {
    if (globalThis !== undefined && globalThis.window) {
        globalThis.window.removeEventListener(
            "taipo:request-delete",
            handleGlobalDelete,
        );
    }
});

const handleTaskDeleted = async () => {
    if (!taskToDelete.value) return;

    try {
        await api.deleteTask(taskToDelete.value.id);
        emit("task-deleted", taskToDelete.value.id); // Or just trigger refresh
    } catch (e) {
        console.error("Failed to delete task", e);
        alert("Failed to delete task: " + e.message);
    } finally {
        isDeleteModalOpen.value = false;
        taskToDelete.value = null;
        // clear the global flag (guarded)
        if (globalThis !== undefined && globalThis.window) {
            globalThis.window.__taipo_delete_state = {
                open: false,
                task: null,
            };
        }
    }
};
</script>
