<template>
    <div class="flex flex-nowrap overflow-x-auto gap-4 p-4 h-[calc(100vh-140px)]">
        <div v-for="(style, title) in columns" :key="title" class="min-w-[280px] flex flex-col bg-base-100 rounded-box shadow-xl h-full">

            <!-- Column Header -->
            <div :class="`p-4 rounded-t-box font-bold flex justify-between items-center bg-${getResultingColor(style)} text-primary-content`">
                <span>{{ title }}</span>
                <div class="badge badge-ghost">{{ tasks[title]?.length || 0 }}</div>
            </div>

            <!-- Add Task Button (Top - Only for Backlog) -->
            <div v-if="title.includes('BACKLOG')" class="p-2">
                <button
                    class="w-full bg-blue-500 text-white py-2 px-4 rounded hover:bg-blue-600 transition duration-300"
                    @click="openAddTaskModal"
                >
                    Add Task
                </button>
            </div>

            <!-- Task List (Draggable) -->
            <draggable
                v-if="tasks[title]"
                v-model="tasks[title]"
                group="tasks"
                @change="onDraggableChange($event, title)"
                item-key="id"
                class="flex-1 overflow-y-auto p-2 space-y-2 min-h-[100px]"
                ghost-class="opacity-50"
            >
                <template #item="{ element }">
                    <TaskCard
                        :task="element"
                        @delete="$emit('task-deleted', element.id)"
                        @toggle-imp="$emit('task-updated')"
                        @task-updated="$emit('task-updated')"
                        @decompose="$emit('decompose', element)"
                        @generate-code="$emit('generate-code', element)"
                    />
                </template>
            </draggable>
            <!-- Fallback if tasks[title] is undefined/null to prevent draggable error -->
            <div v-else class="flex-1 overflow-y-auto p-2 space-y-2 min-h-[100px] flex items-center justify-center text-base-content/50 italic">
                No tasks data
            </div>

            <!-- Add Task Button (Bottom - Only for Backlog) -->
            <div v-if="title.includes('BACKLOG')" class="p-2 mt-auto">
                <button
                    class="w-full bg-blue-500 text-white py-2 px-4 rounded hover:bg-blue-600 transition duration-300"
                    @click="openAddTaskModal"
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
    </div>
</template>

<script setup>
import { ref, defineProps, defineEmits } from 'vue';
import draggable from 'vuedraggable';
import TaskCard from './TaskCard.vue';
import TaskModal from './modals/TaskModal.vue';
import { api } from '../services/api';

const props = defineProps({
    columns: Object,
    tasks: Object,
    currentProject: String
});

const emit = defineEmits(['task-updated', 'task-deleted', 'task-added', 'decompose', 'generate-code']);

const isTaskModalOpen = ref(false);

const getResultingColor = (style) => {
    // Mapping internal style names to DaisyUI/Tailwind colors if needed
    if (style === 'danger') return 'error';
    return style;
};

const onDraggableChange = async (event, newStatus) => {
    if (event.added) {
        const task = event.added.element;
        try {
            await api.updateStatus(task.id, newStatus, props.currentProject);
            emit('task-updated');
        } catch (e) {
            console.error("Failed to update status", e);
            alert(e.response?.data?.error || "Failed to move task");
            emit('task-updated'); // Revert by refreshing from server
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

    if (typeof payload === 'object') {
        description = payload.description;
        priority = payload.priority;
    } else {
        description = payload;
        priority = 0;
    }

    if (!description) return;
    try {
        await api.addTask(props.currentProject, description, priority);
        emit('task-added');
    } catch (e) {
        alert("Failed to add task: " + e.message);
    }
};
</script>
