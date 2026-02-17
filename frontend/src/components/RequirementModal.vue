<template>
    <div
        v-if="isOpen"
        @click.self="close"
        class="modal-overlay"
    >
        <div class="modal-content">
            <h3>Project Requirements</h3>
            <div class="form-group">
                <div class="flex justify-between items-center">
                    <label for="requirement-content" class="mb-0">Requirements (Text/Markdown)</label>
                    <div class="file-input-wrapper">
                        <input
                            @change="handleFileUpload"
                            type="file"
                            id="file-upload"
                            class="hidden"
                            accept=".txt,.md"
                        >
                        <label for="file-upload" class="btn btn-primary btn-outline">
                            Import File
                        </label>
                    </div>
                </div>
                <textarea
                    v-model="content"
                    id="requirement-content"
                    rows="10"
                    placeholder="Enter project requirements here or import a file..."
                >
                </textarea>
            </div>
            <div class="modal-actions">
                <button
                    @click="close"
                    class="btn-secondary"
                >
                    Cancel
                </button>
                <button
                    @click="save"
                    :disabled="isSaving"
                    class="btn-primary"
                >
                    {{ isSaving ? 'Saving...' : 'Save Requirements' }}
                </button>
            </div>
            <div
                v-if="message"
                :class="['message', messageType]"
            >
                {{ message }}
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, watch } from 'vue';
import { api } from '../services/api';

const props = defineProps({
    isOpen: Boolean,
    projectName: String
});

const emit = defineEmits(['close']);

const content = ref('');
const isSaving = ref(false);
const message = ref('');
const messageType = ref('');

const close = () => {
    emit('close');
    message.value = '';
};

const handleFileUpload = (event) => {
    const file = event.target.files[0];
    if (!file) return;

    if (file.size > 1024 * 1024) { // 1MB limit check
        message.value = "File is too large (max 1MB).";
        messageType.value = "error";
        return;
    }

    // Use Blob.text() instead of FileReader as per lint suggestion
    file.text().then(text => {
        content.value = text;
        message.value = `Loaded ${file.name}`;
        messageType.value = "success";

        // Clear success message after a bit
        setTimeout(() => {
            if (messageType.value === 'success') message.value = '';
        }, 3000);
    }).catch(() => {
        message.value = "Failed to read file.";
        messageType.value = "error";
    });

    // Reset input so same file can be selected again if needed
    event.target.value = '';
};

const loadRequirements = async () => {
    if (!props.projectName) return;

    try {
        const result = await api.getRequirements(props.projectName);
        if (result.success && result.data && result.data.length > 0) {
            // For now, we just show the latest requirement.
            // If there are multiple, we might want to append them or show a list.
            // But the story says "System accepts requirement documentation as input (text format)"
            // So simple text input/output is fine.
            content.value = result.data[0].content;
        } else {
            content.value = '';
        }
    } catch (error) {
        console.error("Failed to load requirements", error);
    }
};

watch(() => props.isOpen, (newVal) => {
    if (newVal) {
        loadRequirements();
    }
});

const save = async () => {
    if (!content.value.trim()) {
        message.value = "Content cannot be empty.";
        messageType.value = "error";
        return;
    }

    isSaving.value = true;
    message.value = '';

    try {
        const result = await api.saveRequirement(props.projectName, content.value);
        if (result.data.success) {
            message.value = 'Requirements saved successfully!';
            messageType.value = 'success';
            setTimeout(() => {
                close();
            }, 1500);
        } else {
            message.value = 'Error saving requirements: ' + (result.data.error || 'Unknown error');
            messageType.value = 'error';
        }
    } catch (error) {
        message.value = 'Error saving requirements: ' + error.message;
        messageType.value = 'error';
    } finally {
        isSaving.value = false;
    }
};
</script>

<style scoped>
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 1000;
}

.modal-content {
    background-color: #000;
    padding: 2rem;
    border-radius: 8px;
    width: 90%;
    max-width: 600px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.form-group {
    margin-bottom: 1.5rem;
}

label {
    margin: 0.5rem 0;
    font-weight: 500;
}

textarea {
    width: 100%;
    padding: 0.75rem;
    border: 1px solid var(--border-color);
    border-radius: 4px;
    background-color: var(--bg-input);
    color: var(--text-primary);
    resize: vertical;
}

.modal-actions {
    display: flex;
    justify-content: flex-end;
    gap: 1rem;
}

.message {
    background-color: #000;
    border-radius: 4px;
    font-weight: bold;
    margin-top: 1rem;
    padding: 0.75rem;
    text-align: center;
}

.success {
    color: #10b981;
}

.error {
    color: #ef4444;
}

</style>
