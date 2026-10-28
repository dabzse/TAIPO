<template>
    <dialog
        :class="{ 'modal-open': isOpen }"
        :open="isOpen"
        id="settings_modal"
        class="modal"
    >
        <div
            class="modal-box bg-base-100 shadow-2xl border border-base-300 flex flex-col px-6 py-5 overflow-hidden relative"
            style="width: 720px; min-width: min(720px, 95vw); max-width: 95vw; height: 720px; min-height: min(720px, 92vh); max-height: calc(100vh - 2.5rem);"
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
                            Configure your AI keys, GitHub credentials, and account security
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

            <!-- Tab Navigation (3 tabs: Gemini AI, GitHub, Password) -->
            <div role="tablist" class="grid grid-cols-3 gap-2.5 mb-3 p-1.5 bg-base-200/70 rounded-xl border border-base-300 flex-shrink-0">
                <button
                    role="tab"
                    type="button"
                    :class="[
                        'btn btn-sm h-11 gap-2 font-semibold transition-colors duration-150 rounded-lg text-xs sm:text-sm flex items-center justify-center',
                        activeTab === 'api'
                            ? 'btn-primary shadow-sm'
                            : 'btn-ghost text-base-content/70 hover:text-base-content hover:bg-base-300/60'
                    ]"
                    @click="activeTab = 'api'"
                >
                    <img src="../../images/gemini.svg" alt="Gemini AI" class="w-5 h-5 shrink-0 object-contain" />
                    <span>Gemini AI</span>
                </button>
                <button
                    role="tab"
                    type="button"
                    :class="[
                        'btn btn-sm h-11 gap-2 font-semibold transition-colors duration-150 rounded-lg text-xs sm:text-sm flex items-center justify-center relative',
                        activeTab === 'github'
                            ? 'btn-primary shadow-sm'
                            : 'btn-ghost text-base-content/70 hover:text-base-content hover:bg-base-300/60'
                    ]"
                    @click="activeTab = 'github'"
                >
                    <img src="../../images/github.svg" alt="GitHub" class="w-5 h-5 shrink-0 object-contain brightness-0 invert" />
                    <span>GitHub</span>
                    <span
                        v-if="githubStatus?.has_session_github"
                        class="badge badge-success badge-xs font-bold ml-0.5"
                        title="GitHub connected in current session"
                    >✓</span>
                </button>
                <button
                    role="tab"
                    type="button"
                    :class="[
                        'btn btn-sm h-11 gap-2 font-semibold transition-colors duration-150 rounded-lg text-xs sm:text-sm flex items-center justify-center relative',
                        activeTab === 'password'
                            ? 'btn-primary shadow-sm'
                            : 'btn-ghost text-base-content/70 hover:text-base-content hover:bg-base-300/60'
                    ]"
                    @click="activeTab = 'password'"
                >
                    <img src="../../images/password.svg" alt="Password" class="w-5 h-5 shrink-0 object-contain" />
                    <span>Password</span>
                    <span
                        v-if="authUser?.must_change_password"
                        class="badge badge-warning badge-xs font-bold animate-pulse ml-0.5"
                        title="Initial password change recommended"
                    >!</span>
                </button>
            </div>

            <!-- Scrollable Tab Content Body (flex-1) with stable scrollbar-gutter to prevent width jumps -->
            <div class="flex-1 overflow-y-auto pr-1" style="scrollbar-gutter: stable;">
                <!-- TAB 1: GEMINI AI -->
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

                            <!-- GitHub Status Quick Link Row -->
                            <div class="flex items-center justify-between pt-1 border-t border-base-300/60 text-[11px]">
                                <div class="flex items-center gap-1.5 text-base-content/75">
                                    <span>🐙 GitHub session:</span>
                                    <span
                                        v-if="githubStatus?.has_session_github"
                                        class="badge badge-success badge-xs font-mono gap-1"
                                    >
                                        @{{ githubStatus.github_username }}
                                    </span>
                                    <span v-else class="badge badge-ghost badge-xs opacity-60">Not connected</span>
                                </div>
                                <button
                                    @click="activeTab = 'github'"
                                    type="button"
                                    class="text-[11px] text-primary hover:underline cursor-pointer"
                                >
                                    Manage GitHub →
                                </button>
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
                                <span>Drop credentials file (.apikey, .apikeyusb, *.txt)</span>
                                <span class="text-[11px] font-normal opacity-60">Max. 4 KB</span>
                            </div>

                            <div
                                @dragover.prevent="isDragging = true"
                                @dragleave.prevent="isDragging = false"
                                @drop.prevent="handleFileDrop"
                                @click="triggerFileInput"
                                :class="[
                                    'border-2 border-dashed rounded-xl py-2.5 px-3 text-center cursor-pointer transition-all duration-200 flex flex-col items-center justify-center gap-0.5 select-none',
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
                                    aria-label="Select credentials file (.apikey, .apikeyusb, .txt)"
                                >

                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 opacity-70 text-primary">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m6.75 12l-3-3m0 0l-3 3m3-3v6m-1.5-15H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                                </svg>

                                <div class="text-xs font-medium">
                                    Drop your <code class="bg-base-300 px-1 py-0.5 rounded text-primary">.apikey</code> or <code class="bg-base-300 px-1 py-0.5 rounded text-accent">.apikeyusb</code> file here
                                </div>
                                <div class="text-[11px] opacity-60">
                                    Supports multi-key Gemini &amp; GitHub credentials or single-key formats
                                </div>
                            </div>
                        </div>

                        <!-- Detected Mode Indicator -->
                        <div
                            v-if="detectedSource === 'usb'"
                            class="alert alert-warning text-xs py-2 shadow-xs"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-4 w-4" fill="none" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                            <div class="text-[11px]">
                                <strong>🔌 .apikeyusb detected (Lab / Shared Workstation Mode):</strong>
                                Credentials will remain strictly in temporary session memory and are destroyed upon logout. Database saving is disabled for security.
                            </div>
                        </div>

                        <!-- API Key Text Input -->
                        <div class="space-y-1">
                            <label for="settings-api-key-input" class="text-xs font-semibold text-base-content flex items-center justify-between cursor-pointer">
                                <span>Enter Gemini API Key manually / paste</span>
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

                <!-- TAB 2: GITHUB INTEGRATION -->
                <div v-show="activeTab === 'github'" class="space-y-3">
                    <!-- Status Card -->
                    <div
                        :class="[
                            'p-3 rounded-xl border flex items-center justify-between gap-3',
                            githubStatus?.has_session_github
                                ? 'bg-success/10 border-success/30'
                                : 'bg-base-200 border-base-300'
                        ]"
                    >
                        <div class="flex items-center gap-3">
                            <span class="text-2xl">{{ githubStatus?.has_session_github ? '🐙' : '⚪' }}</span>
                            <div>
                                <div class="text-xs font-bold text-base-content flex items-center gap-2">
                                    <span>{{ githubStatus?.has_session_github ? 'Connected to GitHub' : 'GitHub Not Connected' }}</span>
                                    <span
                                        v-if="githubStatus?.has_session_github"
                                        class="badge badge-success badge-xs font-mono"
                                    >
                                        Active
                                    </span>
                                </div>
                                <div class="text-[11px] opacity-70">
                                    <template v-if="githubStatus?.has_session_github">
                                        User: <strong class="font-mono text-base-content">@{{ githubStatus.github_username }}</strong>
                                        <span v-if="githubStatus.github_repo" class="ml-2">| Repo: <strong class="font-mono text-base-content">{{ githubStatus.github_repo }}</strong></span>
                                        <span v-if="githubStatus.masked_github_token" class="ml-2">| Token: <code class="font-mono text-[10px]">{{ githubStatus.masked_github_token }}</code></span>
                                    </template>
                                    <template v-else>
                                        Push generated code directly to your GitHub repository from the Code Generation modal.
                                    </template>
                                </div>
                            </div>
                        </div>

                        <button
                            v-if="githubStatus?.has_session_github"
                            @click="clearGitHubSession"
                            :disabled="ghLoading"
                            type="button"
                            class="btn btn-xs btn-error btn-outline cursor-pointer"
                            title="Disconnect GitHub session"
                        >
                            Disconnect
                        </button>
                    </div>

                    <!-- Ephemeral Notice Banner -->
                    <div class="alert alert-info text-xs py-2 shadow-xs flex items-start gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-4 w-4 mt-0.5" fill="none" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <div class="text-[11px]">
                            <strong>University Lab Safe:</strong> GitHub PAT and credentials are kept strictly in ephemeral session memory. They are never written to the permanent database and are automatically destroyed when you log out or close the browser tab.
                        </div>
                    </div>

                    <!-- Direct Helper Link Card -->
                    <div class="p-3 rounded-xl bg-base-200/60 border border-base-300 flex items-center justify-between gap-3">
                        <div class="space-y-0.5">
                            <div class="text-xs font-semibold text-base-content flex items-center gap-1.5">
                                <span>🔗</span>
                                <span>Need a GitHub Personal Access Token?</span>
                            </div>
                            <div class="text-[11px] opacity-70">
                                Generate a token with pre-configured <code class="bg-base-300 px-1 py-0.2 rounded font-mono text-[10px]">repo</code> scope in one click.
                            </div>
                        </div>
                        <a
                            href="https://github.com/settings/tokens/new?scopes=repo&description=TAIPO%20Lab%20Token"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="btn btn-xs btn-primary gap-1 cursor-pointer flex-shrink-0"
                            title="Open GitHub token generator in new tab"
                        >
                            <span>Create Token</span>
                            <span>↗</span>
                        </a>
                    </div>

                    <!-- Manual GitHub Configuration Form -->
                    <div class="space-y-2.5 p-3 rounded-xl bg-base-200/40 border border-base-300/80">
                        <div class="text-xs font-semibold text-base-content">
                            {{ githubStatus?.has_session_github ? 'Update Session GitHub Credentials' : 'Enter GitHub Credentials Manually' }}
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                            <!-- Username -->
                            <div class="space-y-1">
                                <label for="gh-username-input" class="text-[11px] font-semibold text-base-content/80 block">
                                    GitHub Username *
                                </label>
                                <input
                                    v-model="ghUsernameInput"
                                    id="gh-username-input"
                                    type="text"
                                    class="input input-bordered input-sm w-full font-mono text-xs"
                                    placeholder="e.g. dabzse"
                                    autocomplete="off"
                                    spellcheck="false"
                                >
                            </div>

                            <!-- Target Repo -->
                            <div class="space-y-1">
                                <label for="gh-repo-input" class="text-[11px] font-semibold text-base-content/80 block">
                                    Repository (Optional)
                                </label>
                                <input
                                    v-model="ghRepoInput"
                                    id="gh-repo-input"
                                    type="text"
                                    class="input input-bordered input-sm w-full font-mono text-xs"
                                    placeholder="e.g. TAIPO"
                                    autocomplete="off"
                                    spellcheck="false"
                                >
                            </div>
                        </div>

                        <!-- Token -->
                        <div class="space-y-1">
                            <div class="flex items-center justify-between">
                                <label for="gh-pat-input" class="text-[11px] font-semibold text-base-content/80">
                                    Personal Access Token (PAT) *
                                </label>
                                <span v-if="ghPatInput" class="text-[10px] text-success">
                                    ✓ Ready to apply
                                </span>
                            </div>
                            <div class="relative">
                                <input
                                    v-model="ghPatInput"
                                    :type="showGhPat ? 'text' : 'password'"
                                    id="gh-pat-input"
                                    class="input input-bordered input-sm w-full font-mono text-xs pr-10"
                                    placeholder="ghp_... or github_pat_..."
                                    autocomplete="off"
                                    spellcheck="false"
                                >
                                <button
                                    @click="showGhPat = !showGhPat"
                                    :title="showGhPat ? 'Hide token' : 'Show token'"
                                    type="button"
                                    class="btn btn-ghost btn-xs btn-circle absolute right-2 top-1/2 -translate-y-1/2 opacity-70 hover:opacity-100"
                                >
                                    <span v-if="showGhPat">👁️</span>
                                    <span v-else>🔒</span>
                                </button>
                            </div>
                        </div>

                        <div class="flex items-center justify-between pt-1">
                            <span class="text-[10px] opacity-60">
                                * Stored only in current session (cleared on tab close / logout)
                            </span>
                            <button
                                @click="applyGitHubSession"
                                :disabled="ghLoading || !ghPatInput || !ghUsernameInput"
                                type="button"
                                class="btn btn-xs btn-primary gap-1 cursor-pointer"
                            >
                                <span v-if="ghLoading" class="loading loading-spinner loading-xs"></span>
                                <span>Apply to Session</span>
                            </button>
                        </div>
                    </div>

                    <!-- USB Flash Drive Info Box -->
                    <div class="p-2.5 rounded-lg bg-base-300/30 border border-base-300/60 text-[11px] text-base-content/70 flex items-start gap-2">
                        <span class="text-base">🔌</span>
                        <div>
                            <strong>Flash Drive Auto-Provisioning:</strong> You can place both Gemini and GitHub keys into your <code class="bg-base-300 px-1 py-0.2 rounded font-mono text-accent">.apikeyusb</code> file on a USB stick:
                            <pre class="bg-base-300/70 p-1.5 rounded mt-1 font-mono text-[10px] text-base-content/90 select-all overflow-x-auto">GEMINI_KEY = "AIzaSy..."
GH_UNAME = "your-github-username"
GH_PAT = "ghp_yourpersonalaccesstoken"
GH_REPO = "optional-repo-name"</pre>
                            Drop it in the <strong>Gemini AI</strong> tab dropzone to activate both keys at once!
                        </div>
                    </div>
                </div>

                <!-- TAB 3: PASSWORD -->
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

            <!-- Key Conflict Resolution Modal Overlay -->
            <div
                v-if="showConflictModal"
                class="absolute inset-0 z-50 bg-black/60 backdrop-blur-sm flex items-center justify-center p-4"
            >
                <div class="bg-base-100 rounded-2xl shadow-2xl border border-warning/50 max-w-lg w-full p-5 space-y-4 animate-in fade-in zoom-in duration-200">
                    <div class="flex items-start gap-3">
                        <div class="w-10 h-10 rounded-xl bg-warning/20 border border-warning/30 flex items-center justify-center text-xl flex-shrink-0">
                            ⚠️
                        </div>
                        <div>
                            <h4 class="font-bold text-base text-base-content">
                                Gemini API Key Conflict Detected
                            </h4>
                            <p class="text-xs text-base-content/70 mt-0.5">
                                Your <code class="font-mono text-accent font-semibold">.apikeyusb</code> contains a Gemini API key that differs from the personal key currently saved in the university database.
                            </p>
                        </div>
                    </div>

                    <div v-if="status?.has_team_saved_key" class="p-2.5 rounded-lg bg-secondary/10 border border-secondary/20 text-xs">
                        <span class="font-semibold text-secondary">👥 Team Quota Available:</span>
                        <span class="opacity-80 ml-1">You are a member of <strong>{{ status?.team_name || 'your team' }}</strong> with a shared team quota key configured.</span>
                    </div>

                    <div class="space-y-2">
                        <div class="text-xs font-semibold text-base-content/80">Choose how to proceed:</div>

                        <!-- Option 1: Session Only (Recommended for Lab) -->
                        <button
                            @click="resolveConflictSessionOnly"
                            type="button"
                            class="w-full text-left p-3 rounded-xl border border-primary/40 bg-primary/10 hover:bg-primary/20 transition-all flex items-start gap-3 cursor-pointer group"
                        >
                            <span class="text-xl mt-0.5">⚡</span>
                            <div class="flex-1">
                                <div class="font-bold text-xs text-primary group-hover:underline">
                                    Use USB Key for this Lab Session Only (Recommended)
                                </div>
                                <div class="text-[11px] opacity-75 mt-0.5">
                                    Overrides key in memory for this session. Your saved database key remains completely unchanged.
                                </div>
                            </div>
                        </button>

                        <!-- Option 2: Update Database Key -->
                        <button
                            @click="resolveConflictUpdateDb"
                            type="button"
                            class="w-full text-left p-3 rounded-xl border border-base-300 hover:border-warning/60 bg-base-200/50 hover:bg-warning/10 transition-all flex items-start gap-3 cursor-pointer group"
                        >
                            <span class="text-xl mt-0.5">💾</span>
                            <div class="flex-1">
                                <div class="font-bold text-xs text-base-content group-hover:text-warning">
                                    Update Saved Key in Database
                                </div>
                                <div class="text-[11px] opacity-75 mt-0.5">
                                    Encrypts this new USB key with AES-256 and replaces your personal saved key in the database.
                                </div>
                            </div>
                        </button>

                        <!-- Option 3: Keep Database Key -->
                        <button
                            @click="resolveConflictKeepDb"
                            type="button"
                            class="w-full text-left p-3 rounded-xl border border-base-300 hover:border-base-content/30 bg-base-200/30 hover:bg-base-200/60 transition-all flex items-start gap-3 cursor-pointer group"
                        >
                            <span class="text-xl mt-0.5">🔒</span>
                            <div class="flex-1">
                                <div class="font-bold text-xs text-base-content">
                                    Keep Saved Database Key
                                </div>
                                <div class="text-[11px] opacity-75 mt-0.5">
                                    Ignores the USB Gemini key and continues using your existing personal key.
                                </div>
                            </div>
                        </button>

                        <!-- Option 4: Team Quota if available -->
                        <button
                            v-if="status?.has_team_saved_key"
                            @click="resolveConflictTeamQuota"
                            type="button"
                            class="w-full text-left p-2.5 rounded-xl border border-secondary/30 hover:bg-secondary/10 transition-all flex items-start gap-3 cursor-pointer group"
                        >
                            <span class="text-xl mt-0.5">👥</span>
                            <div class="flex-1">
                                <div class="font-bold text-xs text-secondary group-hover:underline">
                                    Use Team Shared Quota ({{ status?.team_name || 'Team' }})
                                </div>
                                <div class="text-[11px] opacity-75 mt-0.5">
                                    Switch to shared team quota key for this session.
                                </div>
                            </div>
                        </button>
                    </div>

                    <div class="text-[11px] text-base-content/60 pt-1 border-t border-base-300 flex items-center justify-between">
                        <span>🐙 GitHub credentials from USB are already active in session.</span>
                        <button
                            @click="showConflictModal = false"
                            type="button"
                            class="btn btn-ghost btn-xs text-base-content/70 hover:text-base-content cursor-pointer"
                        >
                            Dismiss
                        </button>
                    </div>
                </div>
            </div>

            <!-- Modal Footer (Fixed at the bottom of the modal box, single row, no wrapping) -->
            <div id="settings-modal-footer" class="flex items-center justify-between flex-nowrap gap-3 pt-3.5 border-t border-base-300 flex-shrink-0 min-h-[56px] h-14 mt-3">
                <!-- Left: Tab specific options (Clear / Delete / Disconnect) -->
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

                    <template v-else-if="activeTab === 'github'">
                        <button
                            v-if="githubStatus?.has_session_github"
                            @click="clearGitHubSession"
                            :disabled="ghLoading"
                            class="btn btn-xs sm:btn-sm btn-ghost text-error hover:bg-error/10 cursor-pointer gap-1.5"
                            type="button"
                            title="Disconnect and clear GitHub credentials from session"
                        >
                            <span>🗑️</span>
                            <span>Disconnect GitHub</span>
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

                    <!-- Submit for GitHub Tab -->
                    <button
                        v-if="activeTab === 'github'"
                        @click="applyGitHubSession"
                        :disabled="ghLoading || !ghPatInput || !ghUsernameInput"
                        type="button"
                        class="btn btn-sm btn-primary gap-1.5 cursor-pointer shadow-sm hover:shadow-md hover:brightness-110 hover:ring-2 hover:ring-primary/40 active:scale-[0.98] transition-all duration-150 disabled:cursor-not-allowed disabled:opacity-50 disabled:shadow-none disabled:hover:brightness-100 disabled:hover:ring-0"
                        title="Save GitHub credentials to session"
                    >
                        <span v-if="ghLoading" class="loading loading-spinner loading-xs"></span>
                        <span>Apply to Session</span>
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
        <form method="dialog" class="modal-backdrop" @submit.prevent="closeModal">
            <button type="button" @click="closeModal">close</button>
        </form>
    </dialog>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { api } from '../../services/api';
import type { AuthUser, ApiKeyStatusResponse, GitHubSessionStatus } from '../../types';

interface ParsedConfigFile {
    geminiKey: string | null;
    githubUsername: string | null;
    githubPat: string | null;
    githubRepo: string | null;
    isMultiKey: boolean;
}

const props = withDefaults(
    defineProps<{
        isOpen?: boolean;
        authUser?: AuthUser | null;
    }>(),
    {
        isOpen: false,
        authUser: null
    }
);

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'show-notification', message: string, type?: string): void;
    (e: 'password-changed'): void;
}>();

// Active Tab ('api' | 'github' | 'password')
const activeTab = ref<'api' | 'github' | 'password'>(props.authUser?.must_change_password ? 'password' : 'api');

// States for API Key Tab
const loadingStatus = ref<boolean>(false);
const actionLoading = ref<boolean>(false);
const status = ref<ApiKeyStatusResponse | null>(null);
const apiKeyInput = ref<string>('');
const showKeyPlain = ref<boolean>(false);
const rememberInDb = ref<boolean>(false);
const detectedSource = ref<'manual' | 'file' | 'usb'>('manual');
const keyTarget = ref<'student' | 'team'>('student');
const isDragging = ref<boolean>(false);
const fileInputRef = ref<HTMLInputElement | null>(null);
const alertMessage = ref<string | null>(null);
const alertType = ref<string>('info');

// States for GitHub Tab
const githubStatus = ref<GitHubSessionStatus | null>(null);
const ghUsernameInput = ref<string>('');
const ghPatInput = ref<string>('');
const ghRepoInput = ref<string>('');
const showGhPat = ref<boolean>(false);
const ghLoading = ref<boolean>(false);

// States for Conflict Modal
const showConflictModal = ref<boolean>(false);
const pendingGeminiKey = ref<string | null>(null);

// States for Password Tab
const currentPassword = ref<string>('');
const newPassword = ref<string>('');
const confirmPassword = ref<string>('');
const showCurrentPw = ref<boolean>(false);
const showNewPw = ref<boolean>(false);
const showConfirmPw = ref<boolean>(false);
const pwLoading = ref<boolean>(false);
const pwAlertMessage = ref<string | null>(null);
const pwAlertType = ref<string>('info');

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
    } catch {
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

const badgeClass = (tier: string) => {
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

let alertTimer: ReturnType<typeof setTimeout> | null = null;
const showAlert = (message: string, type: string = 'info') => {
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

let pwAlertTimer: ReturnType<typeof setTimeout> | null = null;
const showPwAlert = (message: string, type: string = 'info') => {
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

const syncFromSessionStorage = () => {
    try {
        const sToken = sessionStorage.getItem('githubToken');
        const sUser = sessionStorage.getItem('githubUsername');
        const sRepo = sessionStorage.getItem('githubRepo');
        if (sToken && sUser) {
            githubStatus.value = {
                has_session_github: true,
                github_username: sUser,
                github_repo: sRepo || null,
                masked_github_token: sToken.length > 8
                    ? `${sToken.substring(0, 4)}...${sToken.substring(sToken.length - 4)}`
                    : '***'
            };
            if (!ghUsernameInput.value) ghUsernameInput.value = sUser;
            if (sRepo && !ghRepoInput.value) ghRepoInput.value = sRepo;
        }
    } catch (e) {
        console.error("Session storage read error:", e);
    }
};

const fetchStatus = async () => {
    loadingStatus.value = true;
    alertMessage.value = null;
    try {
        const res = await api.getApiKeyStatus();
        if (res.success) {
            status.value = res;
            if (res.github_status) {
                githubStatus.value = res.github_status;
                if (res.github_status.github_username && !ghUsernameInput.value) {
                    ghUsernameInput.value = res.github_status.github_username;
                }
                if (res.github_status.github_repo && !ghRepoInput.value) {
                    ghRepoInput.value = res.github_status.github_repo;
                }
            }
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

const handleFileInputChange = (e: Event) => {
    const target = e.target as HTMLInputElement;
    const files = target.files;
    if (files && files.length > 0) {
        processFile(files[0]);
    }
};

const handleFileDrop = (e: DragEvent) => {
    isDragging.value = false;
    const files = e.dataTransfer?.files;
    if (files && files.length > 0) {
        processFile(files[0]);
    }
};

const stripQuotes = (val: string): string => {
    if ((val.startsWith('"') && val.endsWith('"')) || (val.startsWith("'") && val.endsWith("'"))) {
        return val.substring(1, val.length - 1).trim();
    }
    return val;
};

const assignConfigKey = (key: string, val: string, result: ParsedConfigFile): void => {
    switch (key) {
        case 'GEMINI_KEY':
        case 'API_KEY':
        case 'GEMINI_API_KEY':
            result.geminiKey = val;
            break;
        case 'GH_UNAME':
        case 'GITHUB_USERNAME':
        case 'GH_USERNAME':
            result.githubUsername = val;
            break;
        case 'GH_PAT':
        case 'GITHUB_TOKEN':
        case 'GH_TOKEN':
        case 'GITHUB_PAT':
            result.githubPat = val;
            break;
        case 'GH_REPO':
        case 'GITHUB_REPO':
        case 'REPO':
            result.githubRepo = val;
            break;
    }
};

const parseKeyValueLine = (rawLine: string, result: ParsedConfigFile): boolean => {
    const commentIdx = rawLine.indexOf('#');
    const line = (commentIdx !== -1 ? rawLine.substring(0, commentIdx) : rawLine).trim();
    if (!line) return false;

    let eqIdx = line.indexOf('=');
    const colonIdx = line.indexOf(':');
    if (colonIdx !== -1 && (eqIdx === -1 || colonIdx < eqIdx)) {
        eqIdx = colonIdx;
    }
    if (eqIdx === -1) return false;

    const key = line.substring(0, eqIdx).trim().toUpperCase();
    const val = stripQuotes(line.substring(eqIdx + 1).trim());
    assignConfigKey(key, val, result);
    return true;
};

const parseConfigFile = (content: string): ParsedConfigFile => {
    const lines = content.split(/\r?\n/);
    const result: ParsedConfigFile = {
        geminiKey: null,
        githubUsername: null,
        githubPat: null,
        githubRepo: null,
        isMultiKey: false
    };

    let hasKeyValue = false;
    for (const line of lines) {
        if (parseKeyValueLine(line, result)) {
            hasKeyValue = true;
        }
    }

    if (hasKeyValue) {
        result.isMultiKey = true;
        return result;
    }

    // Fallback: single-line raw string for backwards compatibility
    const trimmed = content.trim();
    if (trimmed.startsWith('AIza') && trimmed.length >= 35) {
        result.geminiKey = trimmed;
    }

    return result;
};

const computeSha256 = async (text: string): Promise<string> => {
    try {
        if (typeof crypto !== 'undefined' && crypto.subtle && typeof crypto.subtle.digest === 'function') {
            const encoder = new TextEncoder();
            const data = encoder.encode(text);
            const hashBuffer = await crypto.subtle.digest('SHA-256', data);
            const hashArray = Array.from(new Uint8Array(hashBuffer));
            return hashArray.map(b => b.toString(16).padStart(2, '0')).join('');
        }
    } catch (e) {
        console.warn("crypto.subtle error:", e);
    }
    return '';
};

const activateGeminiSession = async (key: string, source: string, target: string) => {
    const res = await api.setSessionApiKey(key, source, target);
    if (res.success) {
        sessionStorage.setItem('geminiApiKey', key);
        sessionStorage.setItem('geminiApiKeySource', source);
        await fetchStatus();
    }
};

const processGitHubCredentials = async (parsed: ParsedConfigFile): Promise<boolean> => {
    if (!parsed.githubPat || !parsed.githubUsername) {
        return false;
    }
    try {
        const res = await api.setSessionGitHubKeys(
            parsed.githubPat,
            parsed.githubUsername,
            parsed.githubRepo || ''
        );
        if (res.success) {
            sessionStorage.setItem('githubToken', parsed.githubPat);
            sessionStorage.setItem('githubUsername', parsed.githubUsername);
            if (parsed.githubRepo) {
                sessionStorage.setItem('githubRepo', parsed.githubRepo);
            } else {
                sessionStorage.removeItem('githubRepo');
            }
            githubStatus.value = {
                has_session_github: true,
                github_username: parsed.githubUsername,
                github_repo: parsed.githubRepo || null,
                masked_github_token: parsed.githubPat.length > 8
                    ? `${parsed.githubPat.substring(0, 4)}...${parsed.githubPat.substring(parsed.githubPat.length - 4)}`
                    : '***'
            };
            return true;
        }
    } catch (err: any) {
        console.error("Error setting session GitHub keys:", err);
    }
    return false;
};

const processUsbGeminiKey = async (geminiKey: string, ghProcessed: boolean, username: string | null): Promise<void> => {
    const hash = await computeSha256(geminiKey);
    const hasUserKey = !!status.value?.has_saved_user_key;
    const userHash = status.value?.saved_user_key_hash;
    const isMatchingUserKey = hasUserKey && !!userHash && !!hash && hash === userHash;
    const isConflictingUserKey = hasUserKey && !!userHash && !!hash && hash !== userHash;

    if (isMatchingUserKey) {
        // Case 1: Silent activation if matches saved user DB key ("nincs gond")
        await activateGeminiSession(geminiKey, 'usb', 'student');
        apiKeyInput.value = '';
        const msg = ghProcessed
            ? `USB credentials verified! Gemini key matches saved database key. Both Gemini and GitHub (@${username}) active in session.`
            : "USB key verified: Gemini key matches database key and is active for session.";
        showAlert(msg, "success");
        emit('show-notification', 'USB credentials verified and active for session!', 'success');
        return;
    }

    if (isConflictingUserKey || status.value?.has_team_saved_key) {
        // Case 2 & 3: Conflict with saved personal key or team key exists
        pendingGeminiKey.value = geminiKey;
        showConflictModal.value = true;
        if (ghProcessed) {
            showAlert(`GitHub (@${username}) connected. Please choose how to resolve the Gemini key conflict.`, "warning");
        }
        return;
    }

    // Case 4: No DB key at all: activate into session
    await activateGeminiSession(geminiKey, 'usb', 'student');
    apiKeyInput.value = '';
    const msg = ghProcessed
        ? `USB credentials loaded! GitHub (@${username}) and Gemini active in session memory.`
        : "USB key detected (.apikeyusb): Stored in current session memory only.";
    showAlert(msg, "warning");
    emit('show-notification', 'USB credentials active in session memory.', 'success');
};

const handleMissingOrInvalidGeminiKey = (parsed: ParsedConfigFile, fileName: string, ghProcessed: boolean): boolean => {
    if (!parsed.geminiKey) {
        if (ghProcessed) {
            showAlert(`GitHub credentials loaded from ${fileName} (@${parsed.githubUsername})!`, "success");
            emit('show-notification', 'GitHub connected from file for current session!', 'success');
        } else {
            showAlert("No valid Gemini API key (AIza...) or GitHub credentials found in file.", "error");
        }
        return false;
    }

    const isValid = parsed.geminiKey.startsWith('AIza') && parsed.geminiKey.length >= 35;
    if (!isValid) {
        const msg = ghProcessed
            ? "GitHub credentials connected, but Gemini key format in file was invalid."
            : "Invalid Gemini API key! (Must start with AIza...)";
        showAlert(msg, ghProcessed ? "warning" : "error");
        return false;
    }

    return true;
};

const applyParsedConfig = async (
    parsed: ParsedConfigFile,
    fileName: string,
    isUsb: boolean,
    ghProcessed: boolean
): Promise<void> => {
    if (!handleMissingOrInvalidGeminiKey(parsed, fileName, ghProcessed)) {
        return;
    }

    const geminiKey = parsed.geminiKey as string;
    apiKeyInput.value = geminiKey;

    if (isUsb) {
        await processUsbGeminiKey(geminiKey, ghProcessed, parsed.githubUsername);
    } else if (ghProcessed) {
        showAlert(`File read successfully! Gemini key and GitHub (@${parsed.githubUsername}) loaded.`, "success");
    } else {
        showAlert(`File read successfully (${fileName})!`, "success");
    }
};

const processFile = async (file: File) => {
    alertMessage.value = null;

    if (file.size > 4096) {
        showAlert("File size is too large (maximum 4 KB allowed).", "error");
        return;
    }

    try {
        const text = await file.text();
        const parsed = parseConfigFile(text);
        const fileName = file.name.toLowerCase();
        const isUsb = fileName.includes('.apikeyusb') || fileName.endsWith('.apikeyusb');

        detectedSource.value = isUsb ? 'usb' : 'file';
        if (isUsb) rememberInDb.value = false;

        const ghProcessed = await processGitHubCredentials(parsed);
        await applyParsedConfig(parsed, file.name, isUsb, ghProcessed);
    } catch (e: any) {
        showAlert("Error reading file: " + e.message, "error");
    }
};

const resolveConflictSessionOnly = async () => {
    if (!pendingGeminiKey.value) return;
    actionLoading.value = true;
    try {
        await activateGeminiSession(pendingGeminiKey.value, 'usb', 'student');
        showConflictModal.value = false;
        pendingGeminiKey.value = null;
        apiKeyInput.value = '';
        showAlert("Using USB key for this lab session only. Saved database key preserved.", "success");
        emit('show-notification', 'USB key active for this session only.', 'success');
    } catch (e: any) {
        showAlert("Failed to activate session key: " + e.message, "error");
    } finally {
        actionLoading.value = false;
    }
};

const resolveConflictUpdateDb = async () => {
    if (!pendingGeminiKey.value) return;
    actionLoading.value = true;
    try {
        const res = await api.saveUserApiKey(pendingGeminiKey.value, 'student');
        if (res.success) {
            sessionStorage.setItem('geminiApiKey', pendingGeminiKey.value);
            sessionStorage.setItem('geminiApiKeySource', 'student_database');
            showConflictModal.value = false;
            pendingGeminiKey.value = null;
            apiKeyInput.value = '';
            showAlert("Database key updated with USB key (AES-256 encrypted).", "success");
            emit('show-notification', 'Saved key updated in database!', 'success');
            await fetchStatus();
        } else {
            showAlert(res.error || "Failed to update database key.", "error");
        }
    } catch (e: any) {
        showAlert("Failed to update database key: " + e.message, "error");
    } finally {
        actionLoading.value = false;
    }
};

const resolveConflictKeepDb = () => {
    showConflictModal.value = false;
    pendingGeminiKey.value = null;
    apiKeyInput.value = '';
    showAlert("Kept existing saved database key. GitHub credentials remain active in session.", "info");
    emit('show-notification', 'Database key kept active.', 'info');
};

const resolveConflictTeamQuota = async () => {
    actionLoading.value = true;
    try {
        await api.clearSessionApiKey('student');
        sessionStorage.removeItem('geminiApiKey');
        sessionStorage.removeItem('geminiApiKeySource');
        keyTarget.value = 'team';
        showConflictModal.value = false;
        pendingGeminiKey.value = null;
        apiKeyInput.value = '';
        showAlert(`Active key set to Team Quota (${status.value?.team_name || 'Team'}).`, 'success');
        emit('show-notification', 'Using team shared quota!', 'success');
        await fetchStatus();
    } catch (e: any) {
        showAlert("Error switching to team quota: " + e.message, "error");
    } finally {
        actionLoading.value = false;
    }
};

const applyKey = async () => {
    if (!isKeyFormatValid.value) return;

    actionLoading.value = true;
    alertMessage.value = null;
    const cleanKey = apiKeyInput.value.trim();

    try {
        if (rememberInDb.value && detectedSource.value !== 'usb') {
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
    } catch (e: any) {
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
    } catch (e: any) {
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
    } catch (e: any) {
        showAlert('Error deleting saved key: ' + (e.response?.data?.error || e.message), 'error');
    } finally {
        actionLoading.value = false;
    }
};

const applyGitHubSession = async () => {
    if (!ghPatInput.value || !ghUsernameInput.value) return;
    ghLoading.value = true;
    try {
        const token = ghPatInput.value.trim();
        const username = ghUsernameInput.value.trim();
        const repo = ghRepoInput.value.trim();

        const res = await api.setSessionGitHubKeys(token, username, repo);
        if (res.success) {
            sessionStorage.setItem('githubToken', token);
            sessionStorage.setItem('githubUsername', username);
            if (repo) {
                sessionStorage.setItem('githubRepo', repo);
            } else {
                sessionStorage.removeItem('githubRepo');
            }
            githubStatus.value = {
                has_session_github: true,
                github_username: username,
                github_repo: repo || null,
                masked_github_token: token.length > 8
                    ? `${token.substring(0, 4)}...${token.substring(token.length - 4)}`
                    : '***'
            };
            ghPatInput.value = '';
            showAlert('GitHub credentials loaded into session memory.', 'success');
            emit('show-notification', 'GitHub connected for current session!', 'success');
        } else {
            showAlert(res.error || 'Failed to set GitHub session credentials.', 'error');
        }
    } catch (e: any) {
        showAlert('Error connecting GitHub: ' + (e.response?.data?.error || e.message), 'error');
    } finally {
        ghLoading.value = false;
    }
};

const clearGitHubSession = async () => {
    ghLoading.value = true;
    try {
        await api.clearSessionGitHubKeys();
        sessionStorage.removeItem('githubToken');
        sessionStorage.removeItem('githubUsername');
        sessionStorage.removeItem('githubRepo');
        githubStatus.value = {
            has_session_github: false,
            github_username: null,
            github_repo: null,
            masked_github_token: null
        };
        ghPatInput.value = '';
        showAlert('GitHub session credentials disconnected.', 'info');
        emit('show-notification', 'GitHub session disconnected.', 'info');
    } catch (e: any) {
        showAlert('Error disconnecting GitHub: ' + (e.response?.data?.error || e.message), 'error');
    } finally {
        ghLoading.value = false;
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
    } catch (e: any) {
        showPwAlert(e.response?.data?.error || e.message || 'Failed to update password.', 'error');
    } finally {
        pwLoading.value = false;
    }
};

const closeModal = () => {
    apiKeyInput.value = '';
    ghPatInput.value = '';
    showConflictModal.value = false;
    pendingGeminiKey.value = null;
    dismissAlert();
    currentPassword.value = '';
    newPassword.value = '';
    confirmPassword.value = '';
    dismissPwAlert();
    emit('close');
};

watch(() => props.isOpen, (newVal) => {
    if (newVal) {
        if (props.authUser?.must_change_password) {
            activeTab.value = 'password';
        }
        syncFromSessionStorage();
        fetchStatus();
    } else {
        currentPassword.value = '';
        newPassword.value = '';
        confirmPassword.value = '';
        showConflictModal.value = false;
        pendingGeminiKey.value = null;
        dismissPwAlert();
    }
});
</script>
