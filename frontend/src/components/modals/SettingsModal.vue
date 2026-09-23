<template>
    <dialog
        :class="{ 'modal-open': isOpen }"
        id="settings_modal"
        class="modal"
    >
        <div
            class="modal-box bg-base-100 shadow-2xl border border-base-300 flex flex-col px-6 py-5 overflow-hidden"
            style="width: 680px; min-width: min(680px, 95vw); max-width: 95vw; height: 690px; min-height: min(690px, 90vh); max-height: calc(100vh - 2.5rem);"
        >
            <!-- Modal Header (Fixed at top) -->
            <div class="flex items-center justify-between pb-2.5 border-b border-base-300 mb-3 flex-shrink-0">
                <div class="flex items-center gap-2.5">
                    <span class="text-2xl">⚙️</span>
                    <div>
                        <h3 class="font-bold text-xl text-base-content leading-tight">
                            Settings
                        </h3>
                        <p class="text-xs text-base-content/60">
                            Configure your Gemini API key and account security
                        </p>
                    </div>
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

            <!-- Tab Navigation (Spaced apart with clear separation) -->
            <div role="tablist" class="grid grid-cols-2 gap-4 mb-3 p-1.5 bg-base-200/70 rounded-xl border border-base-300 flex-shrink-0">
                <button
                    role="tab"
                    type="button"
                    :class="[
                        'btn btn-sm h-11 gap-3 font-semibold transition-colors duration-150 rounded-lg text-sm flex items-center justify-center',
                        activeTab === 'api'
                            ? 'btn-primary shadow-sm'
                            : 'btn-ghost text-base-content/70 hover:text-base-content hover:bg-base-300/60'
                    ]"
                    @click="activeTab = 'api'"
                >
                    <span class="text-lg">🔑</span>
                    <span>API Key</span>
                </button>
                <button
                    role="tab"
                    type="button"
                    :class="[
                        'btn btn-sm h-11 gap-3 font-semibold transition-colors duration-150 rounded-lg text-sm flex items-center justify-center relative',
                        activeTab === 'password'
                            ? 'btn-primary shadow-sm'
                            : 'btn-ghost text-base-content/70 hover:text-base-content hover:bg-base-300/60'
                    ]"
                    @click="activeTab = 'password'"
                >
                    <span class="text-lg">🔒</span>
                    <span>Password</span>
                    <span
                        v-if="authUser?.must_change_password"
                        class="badge badge-warning badge-xs font-bold animate-pulse ml-1"
                        title="Initial password change recommended"
                    >!</span>
                </button>
            </div>

            <!-- Scrollable Tab Content Body (flex-1) with stable scrollbar-gutter to prevent width jumps -->
            <div class="flex-1 overflow-y-auto pr-1" style="scrollbar-gutter: stable;">
                <!-- TAB 1: API KEY -->
                <div v-show="activeTab === 'api'">
                    <!-- Loading Spinner for API Key -->
                    <div
                        v-if="loadingStatus"
                        class="flex flex-col items-center justify-center p-12 gap-3"
                    >
                        <span class="loading loading-spinner loading-lg text-primary"></span>
                        <span class="text-sm opacity-70">Loading API key status...</span>
                    </div>

                    <div
                        v-else
                        class="space-y-2.5"
                    >
                        <!-- Status Banner: ?student -> ?team -> :university -->
                        <div class="p-2.5 rounded-xl bg-base-200 border border-base-300 space-y-1.5">
                            <div class="flex items-center justify-between flex-wrap gap-2">
                                <span class="text-xs font-semibold text-base-content/80">Active key hierarchy:</span>
                                <div class="flex items-center gap-1.5 text-xs font-mono">
                                    <span :class="badgeClass('student')">?student</span>
                                    <span class="opacity-40">→</span>
                                    <span :class="badgeClass('team')">?team</span>
                                    <span class="opacity-40">→</span>
                                    <span :class="badgeClass('university')">:university</span>
                                </div>
                            </div>

                            <div class="flex items-center justify-between flex-wrap gap-3 pt-1.5 border-t border-base-300">
                                <div class="flex items-center gap-2">
                                    <span class="text-base">{{ activeSourceIcon }}</span>
                                    <div>
                                        <div class="text-[11px] opacity-60">Currently active source:</div>
                                        <div class="text-xs font-bold text-base-content">{{ activeSourceLabel }}</div>
                                    </div>
                                </div>

                                <div class="flex items-center gap-2 font-mono text-xs bg-base-300/60 px-2.5 py-1 rounded-lg">
                                    <span class="opacity-60 select-none">Key:</span>
                                    <span class="font-semibold">{{ status?.masked_key || 'Not configured' }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Alert Messages -->
                        <div
                            v-if="alertMessage"
                            :class="`alert alert-${alertType} text-xs py-1.5 px-3 shadow-sm flex items-center justify-between`"
                        >
                            <div class="flex items-center gap-2 flex-1">
                                <span v-if="alertType === 'success'">✅</span>
                                <span v-else-if="alertType === 'warning'">⚠️</span>
                                <span v-else-if="alertType === 'error'">❌</span>
                                <span v-else>ℹ️</span>
                                <span>{{ alertMessage }}</span>
                            </div>
                            <button
                                @click="dismissAlert"
                                type="button"
                                class="btn btn-ghost btn-xs btn-circle ml-2 opacity-60 hover:opacity-100 hover:text-error cursor-pointer"
                                title="Dismiss message"
                                aria-label="Dismiss message"
                            >
                                ✕
                            </button>
                        </div>

                        <!-- Drag & Drop Zone -->
                        <div class="space-y-1">
                            <div class="text-xs font-semibold text-base-content flex items-center justify-between">
                                <span>Drop key file (.apikey, .apikeyusb, *.txt)</span>
                                <span class="text-[11px] font-normal opacity-60">Max. 1 KB</span>
                            </div>

                            <div
                                @dragover.prevent="isDragging = true"
                                @dragleave.prevent="isDragging = false"
                                @drop.prevent="handleFileDrop"
                                @click="triggerFileInput"
                                :class="[
                                    'border-2 border-dashed rounded-xl py-2 px-3 text-center cursor-pointer transition-all duration-200 flex flex-col items-center justify-center gap-0.5 select-none',
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

                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 opacity-70 text-primary">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m6.75 12l-3-3m0 0l-3 3m3-3v6m-1.5-15H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                                </svg>

                                <div class="text-xs font-medium">
                                    Drop your <code class="bg-base-300 px-1 py-0.5 rounded text-primary">.apikey</code> or <code class="bg-base-300 px-1 py-0.5 rounded text-accent">.apikeyusb</code> file here
                                </div>
                                <div class="text-[11px] opacity-60">
                                    or click here to browse
                                </div>
                            </div>
                        </div>

                        <!-- Detected Mode Indicator -->
                        <div
                            v-if="detectedSource === 'usb'"
                            class="alert alert-warning text-xs py-2"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-4 w-4" fill="none" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                            <div class="text-[11px]">
                                <strong>🔌 .apikeyusb detected (Lab / Shared Machine Mode):</strong>
                                The key is stored strictly in current session memory. Database storage is disabled for security.
                            </div>
                        </div>

                        <!-- API Key Text Input -->
                        <div class="space-y-1">
                            <label for="settings-api-key-input" class="text-xs font-semibold text-base-content flex items-center justify-between cursor-pointer">
                                <span>Enter API Key manually / paste</span>
                                <span
                                    v-if="apiKeyInput"
                                    :class="isKeyFormatValid ? 'text-success' : 'text-error'"
                                    class="text-[11px]"
                                >
                                    {{ isKeyFormatValid ? '✓ Valid format' : '⚠ AIza... format required' }}
                                </span>
                            </label>

                            <div class="relative">
                                <input
                                    v-model="apiKeyInput"
                                    :type="showKeyPlain ? 'text' : 'password'"
                                    id="settings-api-key-input"
                                    class="input input-bordered input-sm w-full font-mono text-xs pr-10"
                                    placeholder="AIzaSy..."
                                    autocomplete="off"
                                    spellcheck="false"
                                >
                                <button
                                    @click="showKeyPlain = !showKeyPlain"
                                    :title="showKeyPlain ? 'Hide key' : 'Show key'"
                                    type="button"
                                    class="btn btn-ghost btn-xs btn-circle absolute right-2 top-1/2 -translate-y-1/2 opacity-70 hover:opacity-100"
                                >
                                    <span v-if="showKeyPlain">👁️</span>
                                    <span v-else>🔒</span>
                                </button>
                            </div>
                        </div>

                        <!-- Target Selection (Always visible) -->
                        <div class="flex items-center gap-4 bg-base-200/60 p-2.5 rounded-lg text-xs">
                            <span class="font-medium text-[11px] opacity-70">Assign key to:</span>
                            <label class="label cursor-pointer gap-2 py-0">
                                <input
                                    v-model="keyTarget"
                                    type="radio"
                                    name="key_target"
                                    value="student"
                                    class="radio radio-xs radio-primary cursor-pointer"
                                >
                                <span class="label-text text-xs">
                                    Personal ({{ authUser?.is_instructor ? 'Instructor' : 'Student' }})
                                </span>
                            </label>
                            <label
                                :class="[
                                    'label gap-2 py-0',
                                    status?.team_id ? 'cursor-pointer' : 'cursor-not-allowed opacity-50'
                                ]"
                                :title="teamTooltip"
                            >
                                <input
                                    v-model="keyTarget"
                                    type="radio"
                                    name="key_target"
                                    value="team"
                                    :disabled="!status?.team_id"
                                    class="radio radio-xs radio-secondary"
                                >
                                <span class="label-text text-xs">
                                    Shared with Team {{ teamDisplayLabel }}
                                </span>
                            </label>
                        </div>

                        <!-- Remember in Database Checkbox (Only if NOT .apikeyusb) -->
                        <div
                            v-if="detectedSource !== 'usb'"
                            class="p-2 bg-base-200/50 rounded-lg border border-base-300/70"
                        >
                            <label class="label cursor-pointer justify-start gap-2.5 py-0">
                                <input
                                    v-model="rememberInDb"
                                    type="checkbox"
                                    class="checkbox checkbox-primary checkbox-xs"
                                >
                                <div class="flex flex-col">
                                    <span class="label-text font-semibold text-xs">Remember this key</span>
                                    <span class="label-text-alt opacity-70 text-[11px]">
                                        Save to university database with OpenSSL AES-256 encryption (persists beyond current session)
                                    </span>
                                </div>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- TAB 2: PASSWORD -->
                <div v-show="activeTab === 'password'" class="space-y-3.5">
                    <!-- Security Notice Banner -->
                    <div
                        v-if="authUser?.must_change_password"
                        class="alert alert-warning text-xs py-2 shadow-sm flex items-start gap-2.5"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-4 w-4 mt-0.5" fill="none" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        <div>
                            <div class="font-bold text-xs">Security Notice: Default Password Detected</div>
                            <div class="text-[11px] opacity-90 mt-0.5">
                                You are currently signed in with a temporary default password. For your security and privacy, it is strongly recommended to change your password immediately.
                            </div>
                        </div>
                    </div>

                    <!-- Password Alerts -->
                    <div
                        v-if="pwAlertMessage"
                        :class="`alert alert-${pwAlertType} text-xs py-2 shadow-sm flex items-center justify-between`"
                    >
                        <div class="flex items-center gap-2 flex-1">
                            <span v-if="pwAlertType === 'success'">✅</span>
                            <span v-else-if="pwAlertType === 'error'">❌</span>
                            <span v-else>ℹ️</span>
                            <span>{{ pwAlertMessage }}</span>
                        </div>
                        <button
                            @click="dismissPwAlert"
                            type="button"
                            class="btn btn-ghost btn-xs btn-circle ml-2 opacity-60 hover:opacity-100 hover:text-error cursor-pointer"
                            title="Dismiss message"
                            aria-label="Dismiss message"
                        >
                            ✕
                        </button>
                    </div>

                    <!-- Current Password -->
                    <div class="space-y-1">
                        <label for="current-password-input" class="text-xs font-semibold text-base-content">
                            Current Password
                        </label>
                        <div class="relative">
                            <input
                                v-model="currentPassword"
                                :type="showCurrentPw ? 'text' : 'password'"
                                id="current-password-input"
                                class="input input-bordered input-sm w-full text-xs pr-10 font-mono"
                                placeholder="Enter current password"
                                autocomplete="current-password"
                            >
                            <button
                                @click="showCurrentPw = !showCurrentPw"
                                :title="showCurrentPw ? 'Hide password' : 'Show password'"
                                type="button"
                                class="btn btn-ghost btn-xs btn-circle absolute right-2 top-1/2 -translate-y-1/2 opacity-70 hover:opacity-100"
                            >
                                <span v-if="showCurrentPw">👁️</span>
                                <span v-else>🔒</span>
                            </button>
                        </div>
                    </div>

                    <!-- New Password -->
                    <div class="space-y-1">
                        <div class="flex items-center justify-between">
                            <label for="new-password-input" class="text-xs font-semibold text-base-content">
                                New Password
                            </label>
                            <span
                                v-if="newPassword"
                                :class="newPassword.length >= 8 && newPassword.length <= 31 ? 'text-success' : 'text-error'"
                                class="text-[11px] font-semibold"
                            >
                                {{ newPassword.length >= 8 && newPassword.length <= 31 ? '✓ Valid length' : '8–31 characters required' }}
                            </span>
                            <span v-else class="text-[11px] opacity-60">8–31 characters</span>
                        </div>
                        <div class="relative">
                            <input
                                v-model="newPassword"
                                :type="showNewPw ? 'text' : 'password'"
                                id="new-password-input"
                                class="input input-bordered input-sm w-full text-xs pr-10 font-mono"
                                placeholder="Enter new password"
                                autocomplete="new-password"
                            >
                            <button
                                @click="showNewPw = !showNewPw"
                                :title="showNewPw ? 'Hide password' : 'Show password'"
                                type="button"
                                class="btn btn-ghost btn-xs btn-circle absolute right-2 top-1/2 -translate-y-1/2 opacity-70 hover:opacity-100"
                            >
                                <span v-if="showNewPw">👁️</span>
                                <span v-else>🔒</span>
                            </button>
                        </div>
                    </div>

                    <!-- Confirm New Password -->
                    <div class="space-y-1">
                        <div class="flex items-center justify-between">
                            <label for="confirm-password-input" class="text-xs font-semibold text-base-content">
                                Confirm New Password
                            </label>
                            <span
                                v-if="confirmPassword"
                                :class="isPwMatch ? 'text-success' : 'text-error'"
                                class="text-[11px] font-semibold"
                            >
                                {{ isPwMatch ? '✓ Passwords match' : '✕ Passwords do not match' }}
                            </span>
                        </div>
                        <div class="relative">
                            <input
                                v-model="confirmPassword"
                                :type="showConfirmPw ? 'text' : 'password'"
                                id="confirm-password-input"
                                class="input input-bordered input-sm w-full text-xs pr-10 font-mono"
                                placeholder="Re-enter new password"
                                autocomplete="new-password"
                            >
                            <button
                                @click="showConfirmPw = !showConfirmPw"
                                :title="showConfirmPw ? 'Hide password' : 'Show password'"
                                type="button"
                                class="btn btn-ghost btn-xs btn-circle absolute right-2 top-1/2 -translate-y-1/2 opacity-70 hover:opacity-100"
                            >
                                <span v-if="showConfirmPw">👁️</span>
                                <span v-else>🔒</span>
                            </button>
                        </div>
                    </div>

                    <!-- Password Guidelines Card -->
                    <div class="p-3 rounded-xl bg-base-200/60 border border-base-300/80 text-xs space-y-1 mt-1">
                        <div class="font-semibold text-base-content/80 flex items-center gap-1.5 text-xs">
                            <span>🛡️</span>
                            <span>Password Guidelines</span>
                        </div>
                        <ul class="space-y-0.5 text-base-content/70 list-disc list-inside text-[11px]">
                            <li>Length must be between <strong>8 and 31</strong> characters.</li>
                            <li>Use letters, numbers, and symbols for enhanced account protection.</li>
                            <li>Your new password takes effect immediately upon updating.</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Modal Footer (Fixed at the bottom of the modal box, single row, no wrapping) -->
            <div id="settings-modal-footer" class="flex items-center justify-between flex-nowrap gap-3 pt-3.5 border-t border-base-300 flex-shrink-0 min-h-[56px] h-14 mt-3">
                <!-- Left: Tab 1 options (Clear / Delete) -->
                <div class="flex items-center gap-2 min-h-[32px]">
                    <template v-if="activeTab === 'api'">
                        <button
                            @click="clearSession"
                            :disabled="actionLoading || !hasSessionKeyActive"
                            :class="[
                                'btn btn-xs sm:btn-sm btn-ghost gap-1.5 transition-all duration-150',
                                hasSessionKeyActive
                                    ? 'text-warning hover:bg-warning/10 cursor-pointer'
                                    : 'text-base-content/40 cursor-not-allowed opacity-50'
                            ]"
                            :title="hasSessionKeyActive ? 'Clear temporary session memory' : 'No temporary session key currently in memory'"
                            aria-label="Clear temporary session memory"
                            type="button"
                        >
                            <span>🧹</span>
                            <span>Clear Session</span>
                        </button>

                        <button
                            @click="deleteSavedKey"
                            :disabled="actionLoading || !hasSavedKeyActive"
                            :class="[
                                'btn btn-xs sm:btn-sm btn-ghost gap-1.5 transition-all duration-150',
                                hasSavedKeyActive
                                    ? 'text-error hover:bg-error/10 cursor-pointer'
                                    : 'text-base-content/40 cursor-not-allowed opacity-50'
                            ]"
                            :title="hasSavedKeyActive ? 'Permanently delete saved key from database' : 'No saved key found in database to delete'"
                            aria-label="Permanently delete saved key from database"
                            type="button"
                        >
                            <span>🗑️</span>
                            <span>Delete DB Key</span>
                        </button>
                    </template>
                </div>

                <!-- Right: Cancel & Submit buttons -->
                <div class="flex items-center gap-2.5 ml-auto flex-shrink-0">
                    <button
                        @click="closeModal"
                        type="button"
                        class="btn btn-sm btn-ghost cursor-pointer text-base-content/70 hover:text-error hover:underline hover:decoration-error hover:decoration-2 underline-offset-4 hover:bg-error/10 transition-all duration-150"
                        title="Cancel and close settings"
                    >
                        Cancel
                    </button>

                    <!-- Submit for API Tab -->
                    <button
                        v-if="activeTab === 'api'"
                        @click="applyKey"
                        :disabled="actionLoading || !apiKeyInput || !isKeyFormatValid"
                        type="button"
                        class="btn btn-sm btn-primary gap-1.5 cursor-pointer shadow-sm hover:shadow-md hover:brightness-110 hover:ring-2 hover:ring-primary/40 active:scale-[0.98] transition-all duration-150 disabled:cursor-not-allowed disabled:opacity-50 disabled:shadow-none disabled:hover:brightness-100 disabled:hover:ring-0"
                        title="Apply API key"
                    >
                        <span
                            v-if="actionLoading"
                            class="loading loading-spinner loading-xs">
                        </span>
                        <span>{{ rememberInDb ? 'Encrypt & Save' : 'Apply (Session Only)' }}</span>
                    </button>

                    <!-- Submit for Password Tab -->
                    <button
                        v-if="activeTab === 'password'"
                        @click="handlePasswordSubmit"
                        :disabled="pwLoading || !canSubmitPassword"
                        type="button"
                        class="btn btn-sm btn-primary gap-1.5 cursor-pointer shadow-sm hover:shadow-md hover:brightness-110 hover:ring-2 hover:ring-primary/40 active:scale-[0.98] transition-all duration-150 disabled:cursor-not-allowed disabled:opacity-50 disabled:shadow-none disabled:hover:brightness-100 disabled:hover:ring-0"
                        title="Update account password"
                    >
                        <span v-if="pwLoading" class="loading loading-spinner loading-xs"></span>
                        <span>Update Password</span>
                    </button>
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

const emit = defineEmits(['close', 'show-notification', 'password-changed']);

// Active Tab ('api' | 'password')
const activeTab = ref(props.authUser?.must_change_password ? 'password' : 'api');

// States for API Key Tab
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

// States for Password Tab
const currentPassword = ref('');
const newPassword = ref('');
const confirmPassword = ref('');
const showCurrentPw = ref(false);
const showNewPw = ref(false);
const showConfirmPw = ref(false);
const pwLoading = ref(false);
const pwAlertMessage = ref(null);
const pwAlertType = ref('info');

const isPwMatch = computed(() => {
    return newPassword.value === confirmPassword.value;
});

const canSubmitPassword = computed(() => {
    return (
        currentPassword.value.length > 0 &&
        newPassword.value.length >= 8 &&
        newPassword.value.length <= 31 &&
        isPwMatch.value
    );
});

const hasSessionKeyActive = computed(() => {
    return !!(status.value?.has_session_key || hasLocalSessionKey.value || status.value?.has_team_session_key);
});

const hasSavedKeyActive = computed(() => {
    return !!(status.value?.has_saved_user_key || (status.value?.team_id && status.value?.has_team_saved_key));
});

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
            return status.value?.team_name
                ? `Team Session Key (${status.value.team_name} - Session only)`
                : 'Team Session Key (Session only)';
        case 'team_database':
            return status.value?.team_name
                ? `Team Saved Key (${status.value.team_name} - AES-256 Encrypted DB)`
                : 'Team Saved Key (AES-256 Encrypted DB)';
        case 'university':
            return 'University Central Key (.env fallback)';
        default:
            return 'No active key configured';
    }
});

const teamDisplayLabel = computed(() => {
    if (!status.value?.team_id) {
        return '(No team assigned)';
    }
    if (status.value?.team_name) {
        return `(${status.value.team_name})`;
    }
    return `(Team #${status.value.team_id})`;
});

const teamTooltip = computed(() => {
    if (!status.value?.team_id) {
        return 'No team assigned. Join a team to enable shared team keys.';
    }
    if (status.value?.team_name) {
        return `Share key with team: ${status.value.team_name} (Team #${status.value.team_id})`;
    }
    return `Share key with team #${status.value.team_id}`;
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

let alertTimer = null;
const showAlert = (message, type = 'info') => {
    alertMessage.value = message;
    alertType.value = type;
    if (alertTimer) clearTimeout(alertTimer);
    alertTimer = setTimeout(() => {
        if (alertMessage.value === message) {
            alertMessage.value = null;
        }
    }, 20000);
};

const dismissAlert = () => {
    if (alertTimer) clearTimeout(alertTimer);
    alertMessage.value = null;
};

let pwAlertTimer = null;
const showPwAlert = (message, type = 'info') => {
    pwAlertMessage.value = message;
    pwAlertType.value = type;
    if (pwAlertTimer) clearTimeout(pwAlertTimer);
    pwAlertTimer = setTimeout(() => {
        if (pwAlertMessage.value === message) {
            pwAlertMessage.value = null;
        }
    }, 20000);
};

const dismissPwAlert = () => {
    if (pwAlertTimer) clearTimeout(pwAlertTimer);
    pwAlertMessage.value = null;
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

const handlePasswordSubmit = async () => {
    if (!canSubmitPassword.value) return;

    pwLoading.value = true;
    dismissPwAlert();

    try {
        const res = await api.changePassword(currentPassword.value, newPassword.value);
        if (res.success) {
            showPwAlert(res.message || 'Password updated successfully!', 'success');
            currentPassword.value = '';
            newPassword.value = '';
            confirmPassword.value = '';
            emit('password-changed');
            emit('show-notification', 'Password updated successfully! Your account is now secured.', 'success');
        } else {
            showPwAlert(res.error || 'Failed to update password.', 'error');
        }
    } catch (e) {
        showPwAlert(e.response?.data?.error || e.message || 'Failed to update password.', 'error');
    } finally {
        pwLoading.value = false;
    }
};

const closeModal = () => {
    apiKeyInput.value = '';
    dismissAlert();
    currentPassword.value = '';
    newPassword.value = '';
    confirmPassword.value = '';
    dismissPwAlert();
    emit('close');
};

// Refetch status and handle tab switching whenever modal is opened
watch(() => props.isOpen, (newVal) => {
    if (newVal) {
        if (props.authUser?.must_change_password) {
            activeTab.value = 'password';
        }
        fetchStatus();
    } else {
        currentPassword.value = '';
        newPassword.value = '';
        confirmPassword.value = '';
        dismissPwAlert();
    }
});
</script>
