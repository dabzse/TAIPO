<template>
    <div class="flex flex-nowrap overflow-x-auto gap-4 min-h-[calc(100vh-140px)]">
        <div
            v-for="(style, title) in columns"
            :key="title"
            class="min-w-[280px] flex flex-col bg-base-100 rounded-box shadow-xl"
        >
            <!-- Column Header -->
            <div
                :class="`p-4 rounded-t-box font-bold flex justify-between items-center bg-${getResultingColor(style)} text-primary-content`"
            >
                <span>{{ formatColumnTitle(title) }}</span>
                <div v-if="parseWipLimit(title) === Infinity" class="badge badge-ghost">
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
                class="flex-1 p-3 flex flex-col gap-3 min-h-[100px]"
            >
                <template #item="{ element }">
                    <TaskCard
                        :task="element"
                        @request-delete="confirmDeleteTask(element)"
                        @toggle-imp="$emit('task-updated')"
                        @task-updated="$emit('task-updated')"
                        @decompose="$emit('decompose', element)"
                        @generate-code="$emit('generate-code', element)"
                        @query-task="$emit('query-task', element)"
                        @request-edit="openEditTaskModal(element)"
                        @request-view="openViewTaskModal(element)"
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
            :task="taskToEdit"
            :is-read-only="isTaskModalReadOnly"
            :max-title-length="maxTitleLength"
            :max-description-length="maxDescriptionLength"
            @close="closeTaskModal"
            @save="handleSaveTask"
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
    maxTitleLength: Number,
    maxDescriptionLength: Number,
});

const emit = defineEmits([
    "task-updated",
    "task-deleted",
    "task-added",
    "decompose",
    "generate-code",
    "show-notification",
    "query-task",
    "request-view",
]);

const isTaskModalOpen = ref(false);
const taskToEdit = ref(null);
const isTaskModalReadOnly = ref(false);

const getResultingColor = (style) => {
    // Mapping internal style names to DaisyUI/Tailwind colors if needed
    if (style === "danger") return "error";
    return style;
};

const onDraggableChange = async (event, newStatus) => {
    let task = null;
    let needsUpdate = false;

    // Handle 'added' (moved from another column)
    if (event.added) {
        task = event.added.element;
        needsUpdate = true;
    }
    // Handle 'moved' (reordered within same column)
    else if (event.moved) {
        task = event.moved.element;
        needsUpdate = true;
    }

    const limit = parseWipLimit(newStatus);
    if (limit !== Infinity && props.tasks[newStatus].length > limit) {
        emit("show-notification", `WIP limit of ${limit} reached for column "${formatColumnTitle(newStatus)}"!`, 'error');
        emit("task-updated"); // Revert UI by refreshing from server
        return;
    }

    if (needsUpdate && task) {
        // Get the new order of IDs in this column
        const newTaskIds = props.tasks[newStatus].map(t => t.id);

        try {
            // We use reorderTasks for both cases because it updates status AND position
            await api.reorderTasks(props.currentProject, newStatus, newTaskIds);
            emit("task-updated");
        } catch (e) {
            console.error("Failed to update status/order", e);
            alert(e.response?.data?.error || "Failed to move/reorder task");
            emit("task-updated"); // Revert by refreshing from server
        }
    }
};

const parseWipLimit = (columnTitle) => {
    const match = columnTitle.match(/WIP:(\d+)/);
    return match ? Number.parseInt(match[1], 10) : Infinity;
};

const formatColumnTitle = (title) => {
    const limit = parseWipLimit(title);
    if (limit === Infinity) return title;

    const baseTitle = title.replace(/WIP:\d+/, '').trim();
    const count = props.tasks[title]?.length || 0;
    return `${baseTitle} - WIP: ${count} / ${limit}`;
};

const openAddTaskModal = () => {
    taskToEdit.value = null;
    isTaskModalReadOnly.value = false;
    isTaskModalOpen.value = true;
};

const openEditTaskModal = (task) => {
    taskToEdit.value = task;
    isTaskModalReadOnly.value = false;
    isTaskModalOpen.value = true;
};

const openViewTaskModal = (task) => {
    taskToEdit.value = task;
    isTaskModalReadOnly.value = true;
    isTaskModalOpen.value = true;
};

const closeTaskModal = () => {
    isTaskModalOpen.value = false;
    taskToEdit.value = null;
    isTaskModalReadOnly.value = false;
};

const handleSaveTask = async (payload) => {
    // Close modal immediately
    closeTaskModal();

    let title, description, priority;

    if (typeof payload === "object") {
        title = payload.title;
        description = payload.description;
        priority = payload.priority;
    } else {
        // Fallback for simple string (legacy)
        title = payload;
        description = "";
        priority = 0;
    }

    if (!title) return;
    try {
        if (taskToEdit.value) {
            await api.editTask(taskToEdit.value.id, title, description);
            emit("task-updated");
        } else {
            await api.addTask(props.currentProject, title, description, priority);
            emit("task-added");
        }
    } catch (e) {
        alert("Failed to save task: " + (e.response?.data?.error || e.message));
    }
};

// Safe Delete Logic
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
        alert("Failed to delete task: " + (e.response?.data?.error || e.message));
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
