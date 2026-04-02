<template>
    <ConfirmationModal
        :is-open="isAlertOpen"
        :title="'Team Management'"
        :message="alertMessage"
        :is-danger="isAlertDanger"
        is-alert
        @close="isAlertOpen = false"
    />

    <dialog
        :class="{ 'modal-open': isOpen }"
        id="team_modal"
        class="modal"
    >
        <div class="modal-box w-11/12 max-w-5xl">
            <h3 class="font-bold text-lg mb-4">Team Management</h3>

            <div class="flex flex-col md:flex-row gap-4 h-[60vh]">
                <!-- Teams List (Left Panel) -->
                <div class="w-full md:w-1/3 border-r pr-4 overflow-y-auto">
                    <div class="flex justify-between items-center mb-4">
                        <h4 class="font-bold">Teams</h4>
                    </div>

                    <div class="form-control mb-4">
                        <div class="input-group flex">
                            <input
                                v-model="newTeamName"
                                type="text"
                                class="input input-bordered input-sm w-full"
                                placeholder="New team name..."
                            >
                            <button
                                @click="createTeam"
                                :disabled="!newTeamName"
                                class="btn btn-sm btn-primary ml-2"
                            >
                                Add
                            </button>
                        </div>
                    </div>

                    <ul class="menu bg-base-200 w-full rounded-box">
                        <li
                            v-if="loadingTeams"
                            class="disabled"
                        >
                            <a>Loading teams...</a>
                        </li>
                        <li
                            v-else-if="teams.length === 0"
                            class="disabled"
                        >
                            <a>No teams found.</a>
                        </li>
                        <li
                            v-for="team in teams"
                            :key="team.id"
                        >
                            <a
                                :class="{ 'active': selectedTeam && selectedTeam.id === team.id }"
                                @click="selectTeam(team)"
                            >
                                {{ team.name }}
                            </a>
                        </li>
                    </ul>
                </div>

                <!-- Team Details (Right Panel) -->
                <div class="w-full md:w-2/3 pl-4 overflow-y-auto relative">
                    <div
                        v-if="!selectedTeam"
                        class="flex items-center justify-center h-full opacity-50"
                    >
                        Select a team to view details.
                    </div>
                    <div
                        v-else
                    >
                        <div class="flex items-center justify-between mb-4">
                            <div
                                v-if="!isEditingTeamName"
                                class="flex items-center gap-2"
                            >
                                <h4 class="font-bold text-xl">{{ selectedTeam.name }} Members</h4>
                                <button
                                    @click="startEditingName"
                                    class="btn btn-ghost btn-xs"
                                    title="Rename Team"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                    </svg>
                                </button>
                            </div>
                            <div
                                v-else
                                class="flex items-center gap-2 w-full"
                            >
                                <input
                                    v-model="editingTeamName"
                                    @keyup.enter="renameTeam"
                                    type="text"
                                    class="input input-bordered input-sm flex-grow"
                                    placeholder="Enter new team name..."
                                >
                                <button
                                    @click="renameTeam"
                                    class="btn btn-sm btn-primary"
                                >
                                    Save
                                </button>
                                <button
                                    @click="isEditingTeamName = false"
                                    class="btn btn-sm btn-ghost"
                                >
                                    Cancel
                                </button>
                            </div>
                        </div>

                        <!-- Add Member Form -->
                        <div class="bg-base-200 p-4 rounded-lg mb-6 flex gap-2 items-end">
                            <div class="form-control w-1/3">
                                <label class="label" for="assignUserIdInput">
                                    <span class="label-text font-semibold text-base">User</span>
                                </label>
                                <input
                                    v-model="assignUserId"
                                    id="assignUserIdInput"
                                    type="text"
                                    class="input input-sm input-bordered"
                                    placeholder="ID or Username"
                                />
                            </div>
                            <div class="form-control w-1/3">
                                <label class="label p-1" for="assignRoleIdSelect">
                                    <span class="label-text font-semibold text-base">Role</span>
                                </label>
                                <select
                                    v-model="assignRoleId"
                                    id="assignRoleIdSelect"
                                    class="select select-sm select-bordered w-full"
                                >
                                    <option
                                        v-for="role in roles"
                                        :key="role.id"
                                        :value="role.id"
                                    >
                                        {{ role.name }}
                                    </option>
                                </select>
                            </div>
                            <button
                                @click="assignUser"
                                :disabled="!assignUserId || !assignRoleId"
                                class="btn btn-sm btn-primary w-1/3"
                            >
                                Assign Member
                            </button>
                        </div>

                        <!-- Members List -->
                        <div
                            v-if="loadingMembers"
                            class="flex justify-center p-4"
                        >
                            <span class="loading loading-spinner"></span>
                        </div>
                        <div
                            v-else-if="error"
                            class="alert alert-error"
                        >
                            {{ error }}
                        </div>
                        <table
                            v-else
                            class="table table-zebra w-full"
                        >
                            <thead>
                                <tr class="text-base">
                                    <th>User ID</th>
                                    <th>Username</th>
                                    <th>Role</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-if="teamUsers.length === 0"
                                >
                                    <td colspan="4" class="text-center opacity-50">No members assigned to this team.</td>
                                </tr>
                                <tr
                                    v-for="user in teamUsers"
                                    :key="user.user_id"
                                >
                                    <td>{{ user.user_id }}</td>
                                    <td>
                                        <div class="font-bold text-base">{{ user.username }}</div>
                                    </td>
                                    <td>
                                        <select
                                            v-model="user.role_id"
                                            @change="changeRole(user.user_id, user.role_id)"
                                            class="select select-bordered select-xs w-full max-w-xs text-base"
                                        >
                                            <option
                                                v-for="role in roles"
                                                :key="role.id"
                                                :value="role.id"
                                            >
                                                {{ role.name }}
                                            </option>
                                        </select>
                                    </td>
                                    <td>
                                        <button
                                            @click="removeUser(user.user_id)"
                                            class="btn btn-xs btn-error btn-outline"
                                        >
                                            Remove
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>

                        <!-- Assigned Projects Section -->
                        <div class="divider mt-8">Assigned Projects</div>

                        <div class="bg-base-200 p-4 rounded-lg mb-6 flex gap-2 items-end">
                            <div class="form-control w-2/3">
                                <label class="label" for="assignProjectSelect">
                                    <span class="label-text">Select Project to Assign</span>
                                </label>
                                <select
                                    v-model="selectedProjectToAssign"
                                    id="assignProjectSelect"
                                    class="select select-sm select-bordered w-full"
                                >
                                    <option
                                        :value="null"
                                    >
                                        -- Select Project --
                                    </option>
                                    <option
                                        v-for="proj in allProjects"
                                        :key="proj.id"
                                        :value="proj.id"
                                    >
                                        {{ proj.name }} {{ proj.team_id ? '(Assigned to another team)' : '' }}
                                    </option>
                                </select>
                            </div>
                            <button
                                @click="assignProject"
                                :disabled="!selectedProjectToAssign"
                                class="btn btn-sm btn-primary w-1/3"
                            >
                                Assign Project
                            </button>
                        </div>

                        <div
                            v-if="loadingProjects"
                            class="flex justify-center p-2"
                        >
                            <span class="loading loading-spinner loading-sm"></span>
                        </div>
                        <ul
                            v-else
                            class="menu bg-base-100 rounded-box w-full border"
                        >
                            <li
                                v-if="teamProjects.length === 0"
                                class="disabled text-center"
                            >
                                <a>No projects assigned to this team.</a>
                            </li>
                            <li
                                v-for="proj in teamProjects"
                                :key="proj.id"
                            >
                                <div class="flex justify-between items-center px-4 py-2 hover:bg-base-200 rounded-lg">
                                    <span>{{ proj.name }}</span>
                                    <button
                                        @click="unassignProject(proj.id)"
                                        class="btn btn-xs btn-outline"
                                    >
                                        Unassign
                                    </button>
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="modal-action">
                <button
                    @click="closeModal"
                    class="btn"
                >
                    Close
                </button>
            </div>
        </div>
    </dialog>
</template>

<script setup>
import { ref, watch } from 'vue';
import { api } from '../../services/api';
import ConfirmationModal from './ConfirmationModal.vue';

const props = defineProps({
    isOpen: {
        type: Boolean,
        default: false
    }
});

const emit = defineEmits(['close']);

const teams = ref([]);
const roles = ref([]);
const teamUsers = ref([]);
const allProjects = ref([]);
const teamProjects = ref([]);
const selectedProjectToAssign = ref(null);

const selectedTeam = ref(null);
const newTeamName = ref('');
const isEditingTeamName = ref(false);
const editingTeamName = ref('');
const assignUserId = ref('');
const assignRoleId = ref('');

const loadingTeams = ref(false);
const loadingMembers = ref(false);
const loadingProjects = ref(false);
const error = ref(null);

const isAlertOpen = ref(false);
const alertMessage = ref("");
const isAlertDanger = ref(false);

const showAlert = (message, isDanger = false) => {
    alertMessage.value = message;
    isAlertDanger.value = isDanger;
    isAlertOpen.value = true;
};

watch(() => props.isOpen, async (newVal) => {
    if (newVal) {
        await Promise.all([
            fetchTeamsAndRoles(),
            fetchAllProjects()
        ]);
    }
});

const fetchTeamsAndRoles = async () => {
    loadingTeams.value = true;
    try {
        const [teamsRes, rolesRes] = await Promise.all([
            api.listTeams(),
            api.listRoles()
        ]);

        if (teamsRes.success) teams.value = teamsRes.data;
        if (rolesRes.success) roles.value = rolesRes.data;

        if (roles.value.length > 0) {
            assignRoleId.value = roles.value[0].id;
        }
    } catch (e) {
        console.error("Failed to load teams/roles", e);
    } finally {
        loadingTeams.value = false;
    }
};

const createTeam = async () => {
    if (!newTeamName.value) return;
    try {
        await api.createTeam(newTeamName.value);
        newTeamName.value = '';
        await fetchTeamsAndRoles();
    } catch (e) {
        showAlert("Failed to create team: " + (e.response?.data?.error || e.message), true);
    }
};

const selectTeam = async (team) => {
    selectedTeam.value = team;
    isEditingTeamName.value = false;
    await fetchTeamUsers(team.id);
    updateTeamProjects();
};

const startEditingName = () => {
    editingTeamName.value = selectedTeam.value.name;
    isEditingTeamName.value = true;
};

const renameTeam = async () => {
    if (!editingTeamName.value || !selectedTeam.value) return;
    try {
        await api.updateTeam(selectedTeam.value.id, editingTeamName.value);
        selectedTeam.value.name = editingTeamName.value;
        isEditingTeamName.value = false;
        await fetchTeamsAndRoles();
    } catch (e) {
        showAlert("Failed to rename team: " + (e.response?.data?.error || e.message), true);
    }
};

const fetchAllProjects = async () => {
    try {
        const res = await api.getProjects();
        // Backend returns an array of objects for instructors
        allProjects.value = Array.isArray(res) ? res : (res.projects || []);
    } catch (e) {
        console.error("Failed to fetch all projects", e);
    }
};

const updateTeamProjects = () => {
    if (!selectedTeam.value) {
        teamProjects.value = [];
        return;
    }
    teamProjects.value = allProjects.value.filter(p => p.team_id === selectedTeam.value.id);
};

const assignProject = async () => {
    if (!selectedTeam.value || !selectedProjectToAssign.value) return;
    try {
        await api.setProjectTeam(selectedProjectToAssign.value, selectedTeam.value.id);
        selectedProjectToAssign.value = null;
        await fetchAllProjects();
        updateTeamProjects();
    } catch (e) {
        showAlert("Failed to assign project: " + (e.response?.data?.error || e.message), true);
    }
};

const unassignProject = async (projectId) => {
    try {
        await api.setProjectTeam(projectId, null);
        await fetchAllProjects();
        updateTeamProjects();
    } catch (e) {
        showAlert("Failed to unassign project: " + (e.response?.data?.error || e.message), true);
    }
};

const fetchTeamUsers = async (teamId) => {
    loadingMembers.value = true;
    error.value = null;
    try {
        const res = await api.listTeamUsers(teamId);
        if (res.success) {
            teamUsers.value = res.data;
        } else {
            error.value = res.error;
        }
    } catch (e) {
        console.error(e);
        error.value = "Failed to load members";
    } finally {
        loadingMembers.value = false;
    }
};

const assignUser = async () => {
    if (!selectedTeam.value || !assignUserId.value || !assignRoleId.value) return;

    try {
        await api.assignTeamUser(selectedTeam.value.id, assignUserId.value, assignRoleId.value);
        assignUserId.value = '';
        await fetchTeamUsers(selectedTeam.value.id);
    } catch (e) {
        showAlert("Failed to assign user: " + (e.response?.data?.error || e.message), true);
    }
};

const changeRole = async (userId, roleId) => {
    if (!selectedTeam.value) return;
    try {
        await api.updateTeamUserRole(selectedTeam.value.id, userId, roleId);
        await fetchTeamUsers(selectedTeam.value.id);
    } catch (e) {
        showAlert("Failed to modify role: " + (e.response?.data?.error || e.message), true);
    }
};

const removeUser = async (userId) => {
    if (!selectedTeam.value) return;
    try {
        await api.removeTeamUser(selectedTeam.value.id, userId);
        await fetchTeamUsers(selectedTeam.value.id);
    } catch (e) {
        showAlert("Failed to remove user: " + (e.response?.data?.error || e.message), true);
    }
};

const closeModal = () => {
    selectedTeam.value = null;
    teamUsers.value = [];
    emit('close');
};
</script>
