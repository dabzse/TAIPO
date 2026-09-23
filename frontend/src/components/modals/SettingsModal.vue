<template>
    <dialog
        :class="{ 'modal-open': isOpen }"
        id="settings_modal"
        class="modal"
    >
        <div class="modal-box w-11/12 max-w-2xl bg-base-100 shadow-2xl border border-base-300">
            <!-- Modal Header -->
            <div class="flex items-center justify-between pb-3 border-b border-base-300 mb-4">
                <div class="flex items-center gap-2">
                    <span class="text-2xl">⚙️</span>
                    <h3 class="font-bold text-xl text-base-content">
                        Settings & Gemini API Key
                    </h3>
                </div>
                <button
                    @click="closeModal"
                    class="btn btn-sm btn-circle btn-ghost"
                    title="Close"
                    aria-label="Close modal"
                >
                    ✕
                </button>
            </div>

            <!-- Loading Spinner -->
            <div
                v-if="loadingStatus"
                class="flex flex-col items-center justify-center p-8 gap-3"
            >
                <span class="loading loading-spinner loading-lg text-primary"></span>
                <span class="text-sm opacity-70">Loading API key status...</span>
            </div>

            <div
                v-else
                class="space-y-6"
            >
                <!-- Status Banner: ?student -> ?team -> :university -->
                <div class="p-4 rounded-xl bg-base-200 border border-base-300 space-y-3">
                    <div class="flex items-center justify-between flex-wrap gap-2">
                        <span class="text-sm font-semibold text-base-content/80">Active key hierarchy:</span>
                        <div class="flex items-center gap-1.5 text-xs font-mono">
                            <span :class="badgeClass('student')">?student</span>
                            <span class="opacity-40">→</span>
                            <span :class="badgeClass('team')">?team</span>
                            <span class="opacity-40">→</span>
                            <span :class="badgeClass('university')">:university</span>
                        </div>
                    </div>

                    <div class="flex items-center justify-between flex-wrap gap-3 pt-2 border-t border-base-300">
                        <div class="flex items-center gap-2">
                            <span class="text-base">{{ activeSourceIcon }}</span>
                            <div>
                                <div class="text-xs opacity-60">Currently active source:</div>
                                <div class="text-sm font-bold text-base-content">{{ activeSourceLabel }}</div>
                            </div>
                        </div>

                        <div class="flex items-center gap-2 font-mono text-sm bg-base-300/60 px-3 py-1.5 rounded-lg">
                            <span class="opacity-60 text-xs select-none">Key:</span>
                            <span class="font-semibold">{{ status?.masked_key || 'Not configured' }}</span>
                        </div>
                    </div>
                </div>

                <!-- Alert Messages -->
                <div
                    v-if="alertMessage"
                    :class="`alert alert-${alertType} text-sm py-2 shadow-sm`"
                >
                    <span>{{ alertMessage }}</span>
                </div>

                <!-- Drag & Drop Zone -->
                <div class="space-y-2">
                    <div class="text-sm font-semibold text-base-content flex items-center justify-between">
                        <span>Drop key file (.apikey, .apikeyusb, *.txt)</span>
                        <span class="text-xs font-normal opacity-60">Max. 1 KB</span>
                    </div>

                    <div
                        @dragover.prevent="isDragging = true"
                        @dragleave.prevent="isDragging = false"
                        @drop.prevent="handleFileDrop"
                        @click="triggerFileInput"
                        :class="[
                            'border-2 border-dashed rounded-xl p-6 text-center cursor-pointer transition-all duration-200 flex flex-col items-center justify-center gap-2 select-none',
                            isDragging
                                ? 'border-primary bg-primary/10 scale-[1.01]'
                                : 'border-base-300 hover:border-primary/60 bg-base-200/50 hover:bg-base-200'
                        ]"
                    >
                        <input
                            @change="handleFileInputChange"
                            id="settings-file-input"
                            ref="fileInputRef"
                            type="file"
                            accept=".apikey*,.txt,text/plain"
                            class="hidden"
                            aria-label="Select API key file (.apikey, .apikeyusb, .txt)"
                        >

                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-8 h-8 opacity-70 text-primary">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m6.75 12l-3-3m0 0l-3 3m3-3v6m-1.5-15H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                        </svg>

                        <div class="text-sm font-medium">
                            Drop your <code class="bg-base-300 px-1.5 py-0.5 rounded text-primary">.apikey</code> or <code class="bg-base-300 px-1.5 py-0.5 rounded text-accent">.apikeyusb</code> file here
                        </div>
                        <div class="text-xs opacity-60">
                            or click here to browse
                        </div>
                    </div>
                </div>

                <!-- Detected Mode Indicator -->
                <div
                    v-if="detectedSource === 'usb'"
                    class="alert alert-warning text-xs py-2"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-5 w-5" fill="none" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    <div>
                        <strong>🔌 .apikeyusb detected (Lab / Shared Machine Mode):</strong>
                        The key is stored strictly in current session memory. Database storage is disabled for security.
                    </div>
                </div>

                <!-- API Key Text Input -->
                <div class="space-y-2">
                    <label for="settings-api-key-input" class="text-sm font-semibold text-base-content flex items-center justify-between cursor-pointer">
                        <span>Enter API Key manually / paste</span>
                        <span
                            v-if="apiKeyInput"
                            :class="isKeyFormatValid ? 'text-success' : 'text-error'"
                            class="text-xs"
                        >
                            {{ isKeyFormatValid ? '✓ Valid format' : '⚠ AIza... format required' }}
                        </span>
                    </label>

                    <div class="relative">
                        <input
                            v-model="apiKeyInput"
                            :type="showKeyPlain ? 'text' : 'password'"
                            id="settings-api-key-input"
                            class="input input-bordered w-full font-mono text-sm pr-12"
                            placeholder="AIzaSy..."
                            autocomplete="off"
                            spellcheck="false"
                        >
                        <button
                            @click="showKeyPlain = !showKeyPlain"
                            :title="showKeyPlain ? 'Hide key' : 'Show key'"
                            type="button"
                            class="btn btn-ghost btn-xs btn-circle absolute right-3 top-1/2 -translate-y-1/2 opacity-70 hover:opacity-100"
                        >
                            <span v-if="showKeyPlain">👁️</span>
                            <span v-else>🔒</span>
                        </button>
                    </div>
                </div>

                <!-- Target Selection (if user belongs to a team) -->
                <div
                    v-if="status?.team_id"
                    class="flex items-center gap-4 bg-base-200/60 p-3 rounded-lg text-sm"
                >
                    <span class="font-medium text-xs opacity-70">Assign key to:</span>
                    <label class="label cursor-pointer gap-2 py-0">
                        <input
                            v-model="keyTarget"
                            type="radio"
                            name="key_target"
                            value="student"
                            class="radio radio-xs radio-primary"
                        >
                        <span class="label-text text-xs">Personal (Student)</span>
                    </label>
                    <label class="label cursor-pointer gap-2 py-0">
                        <input
                            v-model="keyTarget"
                            type="radio"
                            name="key_target"
                            value="team"
                            class="radio radio-xs radio-secondary"
                        >
                        <span class="label-text text-xs">Shared with Team (Team #{{ status.team_id }})</span>
                    </label>
                </div>

                <!-- Remember in Database Checkbox (Only if NOT .apikeyusb) -->
                <div
                    v-if="detectedSource !== 'usb'"
                    class="p-3 bg-base-200/50 rounded-lg border border-base-300/70"
                >
                    <label class="label cursor-pointer justify-start gap-3 py-0">
                        <input
                            v-model="rememberInDb"
                            type="checkbox"
                            class="checkbox checkbox-primary checkbox-sm"
                        >
                        <div class="flex flex-col">
                            <span class="label-text font-semibold text-sm">Remember this key</span>
                            <span class="label-text-alt opacity-70 text-xs">
                                Save to university database with OpenSSL AES-256 encryption (persists beyond current session)
                            </span>
                        </div>
                    </label>
                </div>

                <!-- Modal Actions -->
                <div class="flex items-center justify-between flex-wrap gap-2 pt-4 border-t border-base-300">
                    <!-- Left: Clear/Delete buttons -->
                    <div class="flex items-center gap-2">
                        <button
                            v-if="status?.has_session_key || hasLocalSessionKey"
                            @click="clearSession"
                            :disabled="actionLoading"
                            class="btn btn-sm btn-ghost text-warning"
                            title="Clear temporary session memory"
                        >
                            Clear Session Key
                        </button>

                        <button
                            v-if="status?.has_saved_user_key || (status?.team_id && status?.has_team_saved_key)"
                            @click="deleteSavedKey"
                            :disabled="actionLoading"
                            class="btn btn-sm btn-ghost text-error"
                            title="Permanently delete saved key from database"
                        >
                            Delete Database Key
                        </button>
                    </div>

                    <!-- Right: Save / Close buttons -->
                    <div class="flex items-center gap-2">
                        <button
                            @click="closeModal"
                            class="btn btn-sm btn-ghost"
                        >
                            Cancel
                        </button>

                        <button
                            @click="applyKey"
                            :disabled="actionLoading || !apiKeyInput || !isKeyFormatValid"
                            class="btn btn-sm btn-primary gap-1.5"
                        >
                            <span
                                v-if="actionLoading"
                                class="loading loading-spinner loading-xs">
                            </span>
                            <span>{{ rememberInDb ? 'Encrypt & Save' : 'Apply (Session Only)' }}</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </dialog>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import { api } from '../../services/api';

const props = defineProps({
    isOpen: {
        type: Boolean,
        default: false
    },
    authUser: {
        type: Object,
        default: null
    }
});

const emit = defineEmits(['close', 'show-notification']);

// States
const loadingStatus = ref(false);
const actionLoading = ref(false);
const status = ref(null);
const apiKeyInput = ref('');
const showKeyPlain = ref(false);
const rememberInDb = ref(false);
const detectedSource = ref('manual'); // 'manual' | 'file' | 'usb'
const keyTarget = ref('student'); // 'student' | 'team'
const isDragging = ref(false);
const fileInputRef = ref(null);
const alertMessage = ref(null);
const alertType = ref('info');

const hasLocalSessionKey = computed(() => {
    try {
        return !!sessionStorage.getItem('geminiApiKey');
    } catch (e) {
        return false;
    }
});

const isKeyFormatValid = computed(() => {
    if (!apiKeyInput.value) return false;
    const trimmed = apiKeyInput.value.trim();
    return trimmed.startsWith('AIza') && trimmed.length >= 35;
});

const activeSourceIcon = computed(() => {
    const src = status.value?.active_source;
    if (src === 'student_usb') return '🔌';
    if (src === 'student_session' || src === 'team_session') return '⚡';
    if (src === 'student_database' || src === 'team_database') return '💾';
    if (src === 'university') return '🏛️';
    return '❌';
});

const activeSourceLabel = computed(() => {
    const src = status.value?.active_source;
    switch (src) {
        case 'student_usb':
            return 'Student Temporary Key (USB - Session only)';
        case 'student_session':
            return 'Student Session Key (Session only)';
        case 'student_database':
            return 'Student Saved Key (AES-256 Encrypted DB)';
        case 'team_session':
            return 'Team Session Key (Session only)';
        case 'team_database':
            return 'Team Saved Key (AES-256 Encrypted DB)';
        case 'university':
            return 'University Central Key (.env fallback)';
        default:
            return 'No active key configured';
    }
});

const badgeClass = (tier) => {
    const active = status.value?.active_source || 'none';
    const isTierActive =
        (tier === 'student' && active.startsWith('student')) ||
        (tier === 'team' && active.startsWith('team')) ||
        (tier === 'university' && active === 'university');

    if (isTierActive) {
        return 'badge badge-sm badge-primary font-bold';
    }
    return 'badge badge-sm badge-ghost opacity-60';
};

const showAlert = (message, type = 'info') => {
    alertMessage.value = message;
    alertType.value = type;
    setTimeout(() => {
        if (alertMessage.value === message) {
            alertMessage.value = null;
        }
    }, 6000);
};

const fetchStatus = async () => {
    loadingStatus.value = true;
    alertMessage.value = null;
    try {
        const res = await api.getApiKeyStatus();
        if (res.success) {
            status.value = res;
        }
    } catch (e) {
        console.error("Failed to load API key status:", e);
    } finally {
        loadingStatus.value = false;
    }
};

const triggerFileInput = () => {
    fileInputRef.value?.click();
};

const handleFileInputChange = (e) => {
    const files = e.target?.files;
    if (files && files.length > 0) {
        processFile(files[0]);
    }
};

const handleFileDrop = (e) => {
    isDragging.value = false;
    const files = e.dataTransfer?.files;
    if (files && files.length > 0) {
        processFile(files[0]);
    }
};

const processFile = async (file) => {
    alertMessage.value = null;

    // File size check (max 1 KB)
    if (file.size > 1024) {
        showAlert("File size is too large (maximum 1 KB allowed).", "error");
        return;
    }

    try {
        const text = await file.text();
        const cleanKey = text.trim();

        if (!cleanKey.startsWith('AIza') || cleanKey.length < 35) {
            showAlert("Invalid Gemini API key! File content does not contain a valid key (must start with AIza...).", "error");
            return;
        }

        apiKeyInput.value = cleanKey;
        const fileName = file.name.toLowerCase();

        // Check if .apikeyusb
        if (fileName.includes('.apikeyusb') || fileName.endsWith('.apikeyusb')) {
            detectedSource.value = 'usb';
            rememberInDb.value = false;
            showAlert("USB key detected (.apikeyusb): Stored in current session memory only.", "warning");
        } else {
            detectedSource.value = 'file';
            showAlert(`File read successfully (${file.name})!`, "success");
        }
    } catch (e) {
        showAlert("Error reading file: " + e.message, "error");
    }
};

const applyKey = async () => {
    if (!isKeyFormatValid.value) return;

    actionLoading.value = true;
    alertMessage.value = null;
    const cleanKey = apiKeyInput.value.trim();

    try {
        if (rememberInDb.value && detectedSource.value !== 'usb') {
            // Save to database with AES-256 encryption
            const res = await api.saveUserApiKey(cleanKey, keyTarget.value);
            if (res.success) {
                sessionStorage.setItem('geminiApiKey', cleanKey);
                sessionStorage.setItem('geminiApiKeySource', detectedSource.value);
                emit('show-notification', 'API key encrypted with AES-256 and saved successfully!', 'success');
                showAlert('API key successfully encrypted and stored in database.', 'success');
            } else {
                showAlert(res.error || 'Failed to save API key.', 'error');
            }
        } else {
            // Session memory only
            const res = await api.setSessionApiKey(cleanKey, detectedSource.value, keyTarget.value);
            if (res.success) {
                sessionStorage.setItem('geminiApiKey', cleanKey);
                sessionStorage.setItem('geminiApiKeySource', detectedSource.value);
                emit('show-notification', 'API key activated for current session (will be cleared on logout).', 'success');
                showAlert('API key successfully loaded into session memory.', 'success');
            } else {
                showAlert(res.error || 'Failed to set session API key.', 'error');
            }
        }

        apiKeyInput.value = '';
        await fetchStatus();
    } catch (e) {
        showAlert('Error: ' + (e.response?.data?.error || e.message), 'error');
    } finally {
        actionLoading.value = false;
    }
};

const clearSession = async () => {
    actionLoading.value = true;
    try {
        await api.clearSessionApiKey('all');
        sessionStorage.removeItem('geminiApiKey');
        sessionStorage.removeItem('geminiApiKeySource');
        emit('show-notification', 'Session API key cleared successfully.', 'info');
        showAlert('Session key cleared.', 'info');
        await fetchStatus();
    } catch (e) {
        showAlert('Error clearing session key: ' + (e.response?.data?.error || e.message), 'error');
    } finally {
        actionLoading.value = false;
    }
};

const deleteSavedKey = async () => {
    actionLoading.value = true;
    try {
        await api.deleteUserApiKey(keyTarget.value);
        sessionStorage.removeItem('geminiApiKey');
        sessionStorage.removeItem('geminiApiKeySource');
        emit('show-notification', 'Saved API key deleted from database.', 'info');
        showAlert('Saved API key deleted from database.', 'info');
        await fetchStatus();
    } catch (e) {
        showAlert('Error deleting saved key: ' + (e.response?.data?.error || e.message), 'error');
    } finally {
        actionLoading.value = false;
    }
};

const closeModal = () => {
    apiKeyInput.value = '';
    alertMessage.value = null;
    emit('close');
};

// Refetch status whenever modal is opened
watch(() => props.isOpen, (newVal) => {
    if (newVal) {
        fetchStatus();
    }
});
</script>
