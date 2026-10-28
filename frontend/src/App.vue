<template>
    <div
        :data-theme="theme"
        class="min-h-screen bg-base-200 flex flex-col font-sans text-base-content antialiased"
    >
        <!-- Project Drawer (Sidebar) -->
        <ProjectSidebar
            v-if="isAuthenticated"
            v-model="drawerOpen"
            :active-project-name="currentProject || undefined"
            @project-selected="handleProjectSelected"
            @open-github-modal="showGithubModal = true"
        />

        <!-- Authenticated Workspace Layout -->
        <div v-if="isAuthenticated" class="flex-1 flex flex-col h-screen overflow-hidden">
            <!-- Top Navigation Bar (matching taipo_preview.jpg) -->
            <header class="h-16 bg-base-100/90 backdrop-blur-md border-b border-base-300 px-4 flex items-center justify-between z-30 shrink-0">
                <!-- Left: Brand & Breadcrumbs -->
                <div class="flex items-center gap-3">
                    <!-- Project Drawer Toggle -->
                    <button
                        @click="drawerOpen = !drawerOpen"
                        aria-label="Toggle Project Menu"
                        class="btn btn-sm btn-ghost btn-square"
                        title="Projects drawer"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="w-5 h-5 stroke-current">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                        </svg>
                    </button>

                    <!-- TAIPO Logo -->
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-xl brand-gradient flex items-center justify-center text-white font-black text-base shadow-md shadow-primary/25">
                            T
                        </div>
                        <span class="font-extrabold text-lg tracking-tight bg-gradient-to-r from-base-content to-base-content/70 bg-clip-text text-transparent">
                            TAIPO
                        </span>
                    </div>

                    <!-- Breadcrumb Divider -->
                    <span class="text-base-content/30 font-mono text-sm select-none">::</span>

                    <!-- Active Project & Task Count Pill -->
                    <div class="flex items-center gap-2">
                        <button
                            @click="drawerOpen = true"
                            class="flex items-center gap-2 px-2.5 py-1 rounded-xl bg-base-200/80 hover:bg-base-200 border border-base-300 transition-all cursor-pointer group"
                            title="Click to switch projects"
                        >
                            <span class="text-xs font-bold text-base-content group-hover:text-primary transition-colors">
                                {{ currentProject || 'Select Project' }}
                            </span>
                            <span class="text-[10px] text-base-content/40">▾</span>
                        </button>

                        <span
                            v-if="currentProject"
                            class="text-[11px] font-bold px-2 py-0.5 rounded-full bg-primary/10 text-primary border border-primary/20"
                        >
                            {{ totalTasksCount }} tasks
                        </span>

                        <span
                            v-if="isRefreshingTasks"
                            class="loading loading-spinner loading-xs text-primary"
                            title="Syncing tasks..."
                        ></span>
                    </div>
                </div>

                <!-- Right: Header Controls -->
                <div class="flex items-center gap-2.5">
                    <!-- API Costs Pill -->
                    <button
                        @click="isApiCostModalOpen = true"
                        class="hidden md:flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-base-200 hover:bg-base-300/80 text-xs font-semibold text-base-content/80 transition-all border border-base-300"
                        title="API Cost Tracking"
                    >
                        <span>💰</span>
                        <span>API Costs</span>
                    </button>

                    <!-- Notifications Button (matching taipo_preview.jpg) -->
                    <button
                        @click="showNotification('TAIPO AI and project services are running normally.', 'info')"
                        class="hidden lg:flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-base-200/60 hover:bg-base-200 text-xs font-semibold text-base-content/80 transition-all border border-base-300 relative"
                        title="Notifications"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                        </svg>
                        <span>Notifications</span>
                        <span class="w-1.5 h-1.5 rounded-full bg-error animate-pulse"></span>
                    </button>

                    <!-- Help / Guide Button -->
                    <button
                        @click="isHelpModalOpen = true"
                        class="flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-base-200/60 hover:bg-base-200 text-xs font-semibold text-base-content/80 transition-all border border-base-300"
                        title="TAIPO Documentation & Guide"
                    >
                        <span class="w-4 h-4 rounded-full border border-base-content/30 flex items-center justify-center text-[10px] font-bold">?</span>
                        <span class="hidden sm:inline">Help</span>
                    </button>

                    <!-- AI Assistant Toggle Button (matching taipo_preview.jpg) -->
                    <button
                        @click="isAiPanelOpen = !isAiPanelOpen"
                        :class="[
                            'btn btn-sm gap-2 font-bold rounded-xl border transition-all duration-200 shadow-sm',
                            isAiPanelOpen
                                ? 'bg-primary text-primary-content border-primary shadow-primary/25'
                                : 'bg-base-200 hover:bg-base-300 text-base-content border-base-300'
                        ]"
                        title="Toggle TAIPO AI Assistant Panel"
                    >
                        <span class="text-sm">✨</span>
                        <span class="hidden sm:inline text-xs">AI Assistant</span>
                    </button>

                    <!-- User Profile Chip -->
                    <div class="dropdown dropdown-end">
                        <button
                            tabindex="0"
                            type="button"
                            aria-haspopup="true"
                            aria-label="User menu"
                            class="flex items-center gap-2.5 pl-2 pr-3 py-1 rounded-xl bg-base-200/80 hover:bg-base-200 border border-base-300 cursor-pointer transition-all"
                        >
                            <div class="w-7 h-7 rounded-lg bg-gradient-to-tr from-accent to-primary flex items-center justify-center text-primary-content font-bold text-xs shadow-sm">
                                {{ (authUser?.username || 'U')[0].toUpperCase() }}
                            </div>
                            <div class="hidden sm:flex flex-col text-left">
                                <span class="text-xs font-bold text-base-content leading-tight">
                                    {{ authUser?.username }}
                                </span>
                                <span class="text-[10px] text-base-content/60 leading-tight">
                                    {{ userRole || (authUser?.is_instructor ? 'Instructor' : 'Student') }}
                                </span>
                            </div>
                            <span class="text-[10px] opacity-40 ml-0.5">▾</span>
                        </button>
                        <ul class="dropdown-content z-[40] menu p-2 shadow-2xl bg-base-100 rounded-2xl w-52 border border-base-300 text-xs mt-2">
                            <li class="menu-title px-3 py-1 font-bold text-base-content/50 uppercase tracking-widest text-[10px]">
                                Account Settings
                            </li>
                            <li>
                                <a @mousedown.prevent="isSettingsModalOpen = true" @click="isSettingsModalOpen = true" class="flex items-center gap-2 py-2 cursor-pointer">
                                    <span>⚙️</span>
                                    <span>API Keys & Password</span>
                                    <span v-if="authUser?.must_change_password" class="badge badge-warning badge-xs">!</span>
                                </a>
                            </li>
                            <li v-if="authUser?.is_instructor">
                                <a @mousedown.prevent="isDashboardOpen = true" @click="isDashboardOpen = true" class="flex items-center gap-2 py-2 cursor-pointer">
                                    <span>📊</span>
                                    <span>Instructor Dashboard</span>
                                </a>
                            </li>
                            <li v-if="authUser?.is_instructor">
                                <a @mousedown.prevent="isTeamModalOpen = true" @click="isTeamModalOpen = true" class="flex items-center gap-2 py-2 cursor-pointer">
                                    <span>👥</span>
                                    <span>Manage Teams</span>
                                </a>
                            </li>
                            <div class="divider my-1"></div>
                            <li>
                                <a @mousedown.prevent="toggleTheme" @click="toggleTheme" class="flex items-center gap-2 py-2 cursor-pointer">
                                    <span>{{ theme === 'dark' ? '☀️' : '🌙' }}</span>
                                    <span>{{ theme === 'dark' ? 'Light Mode' : 'Dark Mode' }}</span>
                                </a>
                            </li>
                            <li>
                                <a @mousedown.prevent="handleLogout" @click="handleLogout" class="text-error flex items-center gap-2 py-2 cursor-pointer">
                                    <span>🚪</span>
                                    <span>Logout</span>
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
            </header>

            <!-- Security Alert Banner for Default Password -->
            <div
                v-if="authUser?.must_change_password"
                class="bg-warning/15 border-b border-warning/30 text-warning px-4 py-2 text-xs flex items-center justify-between z-20 shrink-0"
            >
                <div class="flex items-center gap-2">
                    <span class="text-sm">⚠️</span>
                    <span><strong>Security Notice:</strong> You are using an initial default password. Please change your password in Settings.</span>
                </div>
                <button
                    @click="isSettingsModalOpen = true"
                    class="btn btn-xs btn-warning font-bold"
                >
                    Change Password
                </button>
            </div>

            <!-- Workspace Body with Left Rail + Center Kanban + Right AI Panel -->
            <div class="flex-1 flex overflow-hidden relative">
                <!-- Left Icon Navigation Rail (matching taipo_preview.jpg) -->
                <aside class="w-16 bg-base-100 border-r border-base-300 flex flex-col items-center py-4 justify-between shrink-0 z-20">
                    <!-- Top Rail Actions -->
                    <div class="flex flex-col items-center gap-3 w-full">
                        <!-- Kanban Board (Active) -->
                        <button
                            class="w-10 h-10 rounded-xl bg-primary/10 text-primary flex items-center justify-center transition-all shadow-sm"
                            title="Active Kanban Board"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2" />
                            </svg>
                        </button>

                        <!-- Projects Drawer -->
                        <button
                            @click="drawerOpen = !drawerOpen"
                            class="w-10 h-10 rounded-xl text-base-content/60 hover:text-base-content hover:bg-base-200 flex items-center justify-center transition-all"
                            title="Projects & Workspaces"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
                            </svg>
                        </button>

                        <!-- Requirements -->
                        <button
                            v-if="currentProject"
                            @click="isRequirementModalOpen = true"
                            class="w-10 h-10 rounded-xl text-base-content/60 hover:text-base-content hover:bg-base-200 flex items-center justify-center transition-all"
                            title="Project Requirements (Markdown)"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                        </button>

                        <!-- Audit Log -->
                        <button
                            v-if="currentProject"
                            @click="isAuditLogModalOpen = true"
                            class="w-10 h-10 rounded-xl text-base-content/60 hover:text-base-content hover:bg-base-200 flex items-center justify-center transition-all"
                            title="Audit Log & History"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </button>

                        <!-- Teams -->
                        <button
                            v-if="authUser?.is_instructor"
                            @click="isTeamModalOpen = true"
                            class="w-10 h-10 rounded-xl text-base-content/60 hover:text-base-content hover:bg-base-200 flex items-center justify-center transition-all"
                            title="Manage Teams"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                            </svg>
                        </button>

                        <!-- Instructor Dashboard -->
                        <button
                            v-if="authUser?.is_instructor"
                            @click="isDashboardOpen = true"
                            class="w-10 h-10 rounded-xl text-base-content/60 hover:text-base-content hover:bg-base-200 flex items-center justify-center transition-all"
                            title="Instructor Dashboard"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                            </svg>
                        </button>

                        <!-- Settings -->
                        <button
                            @click="isSettingsModalOpen = true"
                            class="w-10 h-10 rounded-xl text-base-content/60 hover:text-base-content hover:bg-base-200 flex items-center justify-center transition-all relative"
                            title="Settings & API Key"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            <span v-if="authUser?.must_change_password" class="w-2 h-2 rounded-full bg-warning absolute top-2 right-2 animate-pulse"></span>
                        </button>
                    </div>

                    <!-- Bottom Rail Actions -->
                    <div class="flex flex-col items-center gap-3 w-full">
                        <!-- Theme Toggle -->
                        <button
                            @click="toggleTheme"
                            class="w-10 h-10 rounded-xl text-base-content/60 hover:text-base-content hover:bg-base-200 flex items-center justify-center transition-all"
                            :title="`Switch to ${theme === 'dark' ? 'Light' : 'Dark'} Mode`"
                        >
                            <span class="text-base">{{ theme === 'dark' ? '☀️' : '🌙' }}</span>
                        </button>

                        <!-- Logout -->
                        <button
                            @click="handleLogout"
                            class="w-10 h-10 rounded-xl text-error/70 hover:text-error hover:bg-error/10 flex items-center justify-center transition-all"
                            title="Sign Out"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                            </svg>
                        </button>
                    </div>
                </aside>

                <!-- Center Main Kanban Viewport -->
                <main class="flex-1 flex flex-col overflow-x-auto overflow-y-hidden p-6 bg-base-200/60 custom-scrollbar">
                    <div
                        v-if="loading"
                        class="flex flex-col items-center justify-center h-full gap-3"
                    >
                        <span class="loading loading-spinner loading-lg text-primary"></span>
                        <span class="text-xs font-semibold text-base-content/60 tracking-wider uppercase">Loading TAIPO Workspace...</span>
                    </div>

                    <div
                        v-else-if="error"
                        role="alert"
                        class="alert alert-error max-w-lg mx-auto my-auto shadow-xl"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span>Error: {{ error }}</span>
                    </div>

                    <KanbanBoard
                        v-else-if="currentProject"
                        :columns="columns"
                        :tasks="tasks"
                        :current-project="currentProject"
                        :max-title-length="appConfig.maxTitleLength"
                        :max-description-length="appConfig.maxDescriptionLength"
                        :user-role="userRole || undefined"
                        @task-updated="refreshTasks"
                        @task-deleted="refreshTasks"
                        @task-added="refreshTasks"
                        @decompose="handleDecompose"
                        @generate-code="handleGenerateCode"
                        @query-task="handleQueryTask"
                        @show-notification="showNotification"
                    />

                    <!-- Empty Workspace Prompt -->
                    <div
                        v-else
                        class="flex flex-col items-center justify-center h-full text-center p-8 max-w-md mx-auto"
                    >
                        <div class="w-16 h-16 rounded-3xl brand-gradient flex items-center justify-center text-white text-3xl font-black shadow-xl shadow-primary/25 mb-4">
                            T
                        </div>
                        <h3 class="text-xl font-black text-base-content mb-2">No Project Selected</h3>
                        <p class="text-xs text-base-content/60 mb-6 leading-relaxed">
                            Select an existing Agile workspace from the project menu or initialize a new software project to begin simulation.
                        </p>
                        <button
                            @click="drawerOpen = true"
                            class="btn btn-primary rounded-xl shadow-lg shadow-primary/25 font-bold text-xs"
                        >
                            Open Project Menu
                        </button>
                    </div>
                </main>

                <!-- Right Side: TAIPO AI Assistant Panel (matching taipo_preview.jpg) -->
                <AiAssistantPanel
                    :is-open="isAiPanelOpen"
                    :tasks="tasks"
                    :current-project="currentProject || undefined"
                    :user-role="userRole || undefined"
                    @close="isAiPanelOpen = false"
                    @open-query="handleOpenQueryFromAiPanel"
                    @open-generate="drawerOpen = true"
                    @decompose-requested="handleAiDecomposeRequest"
                    @review-requested="handleAiReviewRequest"
                    @refresh="refreshTasks"
                    @open-costs="isApiCostModalOpen = true"
                />
            </div>
        </div>

        <!-- Modals and Dialogs -->
        <CodeGenerationModal
            :is-open="isCodeModalOpen"
            :loading="codeLoading"
            :code="generatedCode"
            :error="codeError"
            :task="currentTaskForCode"
            @close="isCodeModalOpen = false"
            @regenerate="handleRegenerateCode"
            @commit="handleCommitToGithub"
        />

        <TaskQueryModal
            :is-open="isQueryModalOpen"
            :loading="queryLoading"
            :answer="queryAnswer"
            :error="queryError"
            :max-query-length="appConfig.maxQueryLength"
            @close="isQueryModalOpen = false"
            @submit="handleQueryTaskSubmit"
        />

        <RequirementModal
            :is-open="isRequirementModalOpen"
            :project-name="currentProject || undefined"
            @close="isRequirementModalOpen = false"
        />

        <AuditLogModal
            :is-open="isAuditLogModalOpen"
            :project-name="currentProject || undefined"
            @close="isAuditLogModalOpen = false"
        />

        <ApiCostModal
            :is-open="isApiCostModalOpen"
            @close="isApiCostModalOpen = false"
        />

        <TeamModal
            :is-open="isTeamModalOpen"
            @close="isTeamModalOpen = false"
        />

        <DashboardModal
            :is-open="isDashboardOpen"
            @close="isDashboardOpen = false"
            @view-board="handleViewBoard"
        />

        <SettingsModal
            :is-open="isSettingsModalOpen"
            :auth-user="authUser"
            @close="isSettingsModalOpen = false"
            @show-notification="showNotification"
            @password-changed="handlePasswordChanged"
        />

        <HelpModal
            :is-open="isHelpModalOpen"
            @close="isHelpModalOpen = false"
        />

        <!-- Global Toast Notification -->
        <div
            v-if="notification"
            class="toast toast-top toast-end z-50 font-bold"
        >
            <div
                :class="`alert alert-${notification.type} shadow-2xl rounded-2xl border border-base-300 text-xs`"
            >
                <span>{{ notification.message }}</span>
                <div
                    v-if="notification.details"
                    class="text-[11px] opacity-80 mt-1 whitespace-pre-wrap font-mono"
                >
                    {{ notification.details }}
                </div>
            </div>
        </div>

        <!-- Login View (Shown when not authenticated) -->
        <LoginView
            v-if="!isAuthenticated"
            :config="appConfig"
            @auth-success="handleAuthSuccess"
            @open-privacy-modal="isPrivacyModalOpen = true"
        />

        <CookieBanner
            v-if="!isPrivacyModalOpen"
            @open-privacy-modal="isPrivacyModalOpen = true"
        />

        <PrivacyModal
            :is-open="isPrivacyModalOpen"
            @close="isPrivacyModalOpen = false"
        />

        <ConfirmationModal
            :is-open="isDecomposeConfirmOpen"
            :message="`Are you sure you want to break down '${taskToDecompose?.description}' into smaller sub-tasks?` "
            @confirm="proceedDecompose"
            @close="isDecomposeConfirmOpen = false"
            confirm-text="Decompose"
            title="Decompose Task?"
        />
    </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import KanbanBoard from './components/KanbanBoard.vue';
import ProjectSidebar from './components/ProjectSidebar.vue';
import AiAssistantPanel from './components/AiAssistantPanel.vue';
import CodeGenerationModal from './components/modals/CodeGenerationModal.vue';
import TaskQueryModal from './components/modals/TaskQueryModal.vue';
import ApiCostModal from './components/modals/ApiCostModal.vue';
import RequirementModal from './components/RequirementModal.vue';
import AuditLogModal from './components/modals/AuditLogModal.vue';
import PrivacyModal from './components/modals/PrivacyModal.vue';
import TeamModal from './components/modals/TeamModal.vue';
import DashboardModal from './components/modals/DashboardModal.vue';
import SettingsModal from './components/modals/SettingsModal.vue';
import HelpModal from './components/modals/HelpModal.vue';
import LoginView from './components/LoginView.vue';
import CookieBanner from './components/CookieBanner.vue';
import ConfirmationModal from './components/modals/ConfirmationModal.vue';
import { api } from './services/api';
import type { Task, TasksByColumn, ColumnsMap, AuthUser, AppConfig, NotificationState, UserRole } from './types';

const isAuthenticated = ref<boolean>(false);
const authUser = ref<AuthUser | null>(null);
const isSettingsModalOpen = ref<boolean>(false);
const isHelpModalOpen = ref<boolean>(false);
const isAiPanelOpen = ref<boolean>(true); // Default open matching taipo_preview.jpg
const loading = ref<boolean>(true);
const isRefreshingTasks = ref<boolean>(false);
let refreshInFlight = false;
const error = ref<string | null>(null);
const tasks = ref<TasksByColumn>({});

const appConfig = ref<AppConfig>({
    projectName: "AI-Driven Kanban",
    maxTitleLength: 42,
    maxDescriptionLength: 512,
    maxQueryLength: 1320
});

const currentProject = ref<string | null>(null);
const userRole = ref<UserRole | null>(null);
const showGithubModal = ref<boolean>(false);
const drawerOpen = ref<boolean>(false);
const theme = ref<string>(globalThis?.localStorage?.getItem('theme') || 'dark');

const CACHE_PROJECT_KEY = 'taipo_last_active_project';
const CACHE_TASKS_PREFIX = 'taipo_cached_tasks_';

const getStorage = () => (typeof globalThis !== 'undefined' ? globalThis.localStorage : null);

// Calculated total tasks count across all columns
const totalTasksCount = computed(() => {
    let count = 0;
    for (const list of Object.values(tasks.value)) {
        count += (list || []).length;
    }
    return count;
});

const restoreLastState = (): boolean => {
    const storage = getStorage();
    if (!storage) return false;

    try {
        const savedProject = storage.getItem(CACHE_PROJECT_KEY);
        if (savedProject && !currentProject.value) {
            currentProject.value = savedProject;
        }

        const activeProj = currentProject.value;
        if (!activeProj) return false;

        const cached = storage.getItem(CACHE_TASKS_PREFIX + activeProj);
        if (!cached) return false;

        // Verify valid JSON prefix to avoid crashing if mock localStorage returns 'dark'
        if (!cached.startsWith('{') && !cached.startsWith('[')) return false;

        const parsed = JSON.parse(cached);
        if (parsed && typeof parsed === 'object') {
            tasks.value = parsed;
            enrichTasksWithSubtaskInfo();
            return true;
        }
    } catch (e) {
        console.warn("Could not restore last state from cache:", e);
    }
    return false;
};

const persistLastState = () => {
    const storage = getStorage();
    if (!storage || !currentProject.value) return;

    try {
        storage.setItem(CACHE_PROJECT_KEY, currentProject.value);
        if (tasks.value && Object.keys(tasks.value).length > 0) {
            storage.setItem(CACHE_TASKS_PREFIX + currentProject.value, JSON.stringify(tasks.value));
        }
    } catch (e) {
        console.warn("Could not persist state to cache:", e);
    }
};

const toggleTheme = () => {
    theme.value = theme.value === 'dark' ? 'cupcake' : 'dark';
    if (globalThis?.localStorage) {
        globalThis.localStorage.setItem('theme', theme.value);
    }
    if (globalThis?.document) {
        globalThis.document.documentElement.dataset.theme = theme.value;
    }
};

// Code Modal State
const isCodeModalOpen = ref<boolean>(false);
const codeLoading = ref<boolean>(false);
const generatedCode = ref<string>('');
const codeError = ref<string>('');
const currentTaskForCode = ref<Task | null>(null);

// Query Modal State
const isQueryModalOpen = ref<boolean>(false);
const queryLoading = ref<boolean>(false);
const queryAnswer = ref<string>('');
const queryError = ref<string>('');
const queryTaskTarget = ref<Task | null>(null);

// Local specialized modals
const isRequirementModalOpen = ref<boolean>(false);
const isAuditLogModalOpen = ref<boolean>(false);
const isApiCostModalOpen = ref<boolean>(false);
const isPrivacyModalOpen = ref<boolean>(false);
const isTeamModalOpen = ref<boolean>(false);
const isDashboardOpen = ref<boolean>(false);

// Decomposition Confirmation State
const isDecomposeConfirmOpen = ref<boolean>(false);
const taskToDecompose = ref<Task | null>(null);

// Global Notification State
const notification = ref<NotificationState | null>(null);

const showNotification = (
    message: string,
    type: 'info' | 'success' | 'warning' | 'error' | string = 'info',
    details: string | null = null,
    duration = 3000
) => {
    const validTypes: Array<'info' | 'success' | 'warning' | 'error'> = ['info', 'success', 'warning', 'error'];
    const safeType = (validTypes.includes(type as any) ? type : 'info') as 'info' | 'success' | 'warning' | 'error';
    notification.value = { message, type: safeType, details };
    setTimeout(() => {
        if (notification.value?.message === message) {
            notification.value = null;
        }
    }, duration);
};

const handlePasswordChanged = () => {
    if (authUser.value) {
        authUser.value.must_change_password = 0;
    }
};

const columns = ref<ColumnsMap>({
    'SPRINT BACKLOG': 'neutral',
    'IMPLEMENTATION WIP:3': 'primary',
    'TESTING WIP:2': 'warning',
    'REVIEW WIP:2': 'info',
    'DONE': 'success',
});

// Authentication Handlers
const checkAuth = async () => {
    const hasCache = restoreLastState();
    if (!hasCache) {
        loading.value = true;
    }
    try {
        const res = await api.checkAuth();
        if (res.config) {
            appConfig.value = { ...appConfig.value, ...res.config };
        }
        if (res.success && res.authenticated && res.user) {
            isAuthenticated.value = true;
            authUser.value = res.user;
            if (res.user.last_active_project) {
                currentProject.value = res.user.last_active_project;
            }
            restoreLastState();
            refreshTasks(false);
            if (res.user.must_change_password) {
                showNotification("Security Notice: You are using a temporary initial password. It is strongly recommended to change it in Settings.", "warning", null, 6000);
            }
        } else {
            isAuthenticated.value = false;
        }
    } catch (e) {
        console.error("Auth check failed:", e);
        isAuthenticated.value = false;
    } finally {
        loading.value = false;
    }
};

const handleAuthSuccess = async (user: AuthUser) => {
    isAuthenticated.value = true;
    authUser.value = user;
    if (user.last_active_project) {
        currentProject.value = user.last_active_project;
    }
    const hasCache = restoreLastState();
    if (!hasCache) {
        loading.value = true;
    }
    refreshTasks(false);
    if (user.must_change_password) {
        showNotification("Security Notice: You are using a temporary initial password. It is strongly recommended to change it in Settings.", "warning", null, 6000);
    }
};

const handleLogout = async () => {
    try {
        await api.logout();
        sessionStorage.removeItem('geminiApiKey');
        sessionStorage.removeItem('geminiApiKeySource');
        isAuthenticated.value = false;
        authUser.value = null;
        currentProject.value = null;
        tasks.value = {};
    } catch (e) {
        console.error("Logout failed:", e);
    }
};

const handleUnauthorized = () => {
    sessionStorage.removeItem('geminiApiKey');
    sessionStorage.removeItem('geminiApiKeySource');
    isAuthenticated.value = false;
    authUser.value = null;
    showNotification("Session expired. Please log in again.", "warning");
};

const handleGlobalEsc = (e: KeyboardEvent) => {
    if (e.key === "Escape") {
        isCodeModalOpen.value = false;
        isQueryModalOpen.value = false;
        isRequirementModalOpen.value = false;
        isAuditLogModalOpen.value = false;
        isApiCostModalOpen.value = false;
        isSettingsModalOpen.value = false;
        isHelpModalOpen.value = false;
        isPrivacyModalOpen.value = false;
        isTeamModalOpen.value = false;
        isDashboardOpen.value = false;
        isDecomposeConfirmOpen.value = false;
        drawerOpen.value = false;
    }
};

// Lifecycle Hooks
onMounted(() => {
    if (globalThis?.document) {
        globalThis.document.documentElement.dataset.theme = theme.value;
    }
    checkAuth();
    if (typeof globalThis !== 'undefined' && globalThis.window) {
        globalThis.window.addEventListener('taipo:unauthorized', handleUnauthorized);
        globalThis.window.addEventListener('keydown', handleGlobalEsc);
    }
});

onBeforeUnmount(() => {
    if (typeof globalThis !== 'undefined' && globalThis.window) {
        globalThis.window.removeEventListener('taipo:unauthorized', handleUnauthorized);
        globalThis.window.removeEventListener('keydown', handleGlobalEsc);
    }
});

const handleProjectSelected = async (projectName: string | null) => {
    if (!projectName) {
        currentProject.value = null;
        tasks.value = {};
        return;
    }
    const isSameProject = currentProject.value === projectName;
    currentProject.value = projectName;
    persistLastState();

    api.saveActiveProject(projectName).catch(e => console.error("Failed to save active project:", e));

    if (!isSameProject) {
        const hasCache = restoreLastState();
        refreshTasks(!hasCache);
    } else if (!refreshInFlight) {
        refreshTasks(false);
    }
};

const handleViewBoard = async (projectName: string) => {
    isDashboardOpen.value = false;
    await handleProjectSelected(projectName);
};

const refreshTasks = async (showFullLoader = false) => {
    if (!isAuthenticated.value) return;
    if (refreshInFlight) return;

    refreshInFlight = true;
    isRefreshingTasks.value = true;

    const hasVisibleTasks = tasks.value && Object.keys(tasks.value).some(k => tasks.value[k]?.length > 0);
    if (showFullLoader && !hasVisibleTasks) {
        loading.value = true;
    }

    try {
        let targetProject = currentProject.value;

        const data = await api.getKanbanTasks(targetProject);

        if (data.authenticated === false) {
            isAuthenticated.value = false;
            return;
        }

        if (!targetProject && data.existingProjects && data.existingProjects.length > 0) {
            targetProject = data.existingProjects[0];
            currentProject.value = targetProject;
        }

        tasks.value = data.tasks || {};

        if (data.userRole) {
            userRole.value = data.userRole as UserRole;
        }

        enrichTasksWithSubtaskInfo();
        persistLastState();

        if (data.config) {
            appConfig.value = { ...appConfig.value, ...data.config };
            if (data.config.projectName) {
                document.title = data.config.projectName;
            }
        }
    } catch (e: any) {
        console.error("Error fetching Kanban tasks:", e);
        error.value = e.response?.data?.error || e.message;
    } finally {
        loading.value = false;
        isRefreshingTasks.value = false;
        refreshInFlight = false;
    }
};

const enrichTasksWithSubtaskInfo = () => {
    const allTasks = Object.values(tasks.value).flat();
    const subtaskCounts: Record<number, number> = {};

    allTasks.forEach(task => {
        if (task.parent_id) {
            subtaskCounts[task.parent_id] = (subtaskCounts[task.parent_id] || 0) + 1;
        }
    });

    Object.keys(tasks.value).forEach(column => {
        tasks.value[column] = tasks.value[column].map(task => ({
            ...task,
            subtaskCount: subtaskCounts[task.id] || 0
        }));
    });
};

const handleDecompose = (task: Task) => {
    taskToDecompose.value = task;
    isDecomposeConfirmOpen.value = true;
};

const proceedDecompose = async () => {
    const task = taskToDecompose.value;
    if (!task) return;

    loading.value = true;
    try {
        await api.decomposeTask(task.id, task.description, currentProject.value || '');
        await refreshTasks();
        showNotification("Task decomposed successfully!", "success");
    } catch (e: any) {
        const errorMsg = e.response?.data?.error || e.message;
        const mainMsg = errorMsg.split(' - Response:')[0];
        const details = errorMsg.includes(' - Response:') ? errorMsg.split(' - Response:')[1] : null;
        showNotification("Failed to decompose task: " + mainMsg, "error", details);
    } finally {
        loading.value = false;
        isDecomposeConfirmOpen.value = false;
        taskToDecompose.value = null;
    }
};

const handleAiDecomposeRequest = () => {
    // Find the first backlog story without subtasks
    const backlog = tasks.value['SPRINT BACKLOG'] || [];
    const candidate = backlog.find(t => !t.is_subtask && (!t.subtaskCount || t.subtaskCount === 0));
    if (candidate) {
        handleDecompose(candidate);
    } else {
        showNotification("No eligible backlog user stories found to decompose.", "info");
    }
};

const handleAiReviewRequest = async () => {
    const reviewTasks = tasks.value['REVIEW WIP:2'] || tasks.value['TESTING WIP:2'] || [];
    if (reviewTasks.length === 0) {
        showNotification("No tasks currently in Review or Testing column.", "info");
        return;
    }
    const target = reviewTasks[0];
    try {
        showNotification(`Requesting PO review for task #${target.id}...`, "info");
        const res = await api.reviewTask(target.id);
        if (res.success) {
            showNotification(`PO completed review on task #${target.id}!`, "success");
            await refreshTasks();
        }
    } catch (e: any) {
        showNotification("PO Review failed: " + (e.response?.data?.error || e.message), "error");
    }
};

const handleOpenQueryFromAiPanel = () => {
    const all = Object.values(tasks.value).flat();
    if (all.length > 0) {
        handleQueryTask(all[0]);
    } else {
        showNotification("Select or create a task to ask TAIPO questions about it.", "info");
    }
};

const handleGenerateCode = async (task: Task) => {
    isCodeModalOpen.value = true;

    if (task.generated_code) {
        generatedCode.value = task.generated_code;
        codeLoading.value = false;
        codeError.value = '';
        currentTaskForCode.value = task;
        return;
    }

    currentTaskForCode.value = task;
    await proceedGenerateCode(task);
};

const handleRegenerateCode = async () => {
    if (!currentTaskForCode.value) return;
    await proceedGenerateCode(currentTaskForCode.value);
};

const proceedGenerateCode = async (task: Task) => {
    codeLoading.value = true;
    generatedCode.value = '';
    codeError.value = '';

    try {
        const res = await api.generateCode(task.id, task.description);
        if (res.success && res.code) {
            generatedCode.value = res.code;
            task.generated_code = res.code;
        } else {
            codeError.value = res.error || "Failed to generate code.";
        }
    } catch (e: any) {
        codeError.value = e.response?.data?.error || e.message;
    } finally {
        codeLoading.value = false;
        await refreshTasks();
    }
};

const handleQueryTask = (task: Task) => {
    queryTaskTarget.value = task;
    isQueryModalOpen.value = true;
    queryAnswer.value = '';
    queryError.value = '';
};

const handleQueryTaskSubmit = async (query: string) => {
    if (!queryTaskTarget.value) return;

    queryLoading.value = true;
    queryAnswer.value = '';
    queryError.value = '';

    try {
        const res = await api.queryTask(queryTaskTarget.value.id, query);
        if (res.success && res.answer) {
            queryAnswer.value = res.answer;
            await refreshTasks();
        } else {
            queryError.value = res.error || "Failed to get an answer.";
        }
    } catch (e: any) {
        queryError.value = e.response?.data?.error || e.message;
    } finally {
        queryLoading.value = false;
    }
};

const handleCommitToGithub = async () => {
    const task = currentTaskForCode.value;
    if (!task || !generatedCode.value) return;

    codeLoading.value = true;
    try {
        const res = await api.commitToGithub(task.id, generatedCode.value, task.description);
        if (res.success) {
            showNotification(`Code committed successfully to ${res.filePath}`, "success");
            isCodeModalOpen.value = false;
            await refreshTasks();
        } else {
            showNotification("Commit failed: " + res.error, "error");
        }
    } catch (e: any) {
        showNotification("Commit error: " + (e.response?.data?.error || e.message), "error");
    } finally {
        codeLoading.value = false;
    }
};
</script>
