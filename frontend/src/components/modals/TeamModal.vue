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
        :open="isOpen"
        id="team_modal"
        class="modal z-50"
    >
        <div class="modal-box w-11/12 max-w-5xl bg-base-100 rounded-2xl border border-base-300 shadow-2xl">
            <h3 class="font-bold text-lg mb-4 text-primary flex items-center gap-2">
                <span>👥</span>
                <span>Team & Role Management</span>
            </h3>

            <div class="flex flex-col md:flex-row gap-4 h-[60vh]">
                <!-- Teams List (Left Panel) -->
                <div class="w-full md:w-1/3 border-r border-base-300 pr-4 overflow-y-auto custom-scrollbar">
                    <div class="flex justify-between items-center mb-4">
                        <h4 class="font-bold text-sm">Teams</h4>
                    </div>

                    <div class="form-control mb-4">
                        <div class="input-group flex gap-2">
                            <label for="new-team-name-input" class="sr-only">New team name</label>
                            <input
                                id="new-team-name-input"
                                v-model="newTeamName"
                                type="text"
                                class="input input-bordered input-sm w-full text-xs"
                                placeholder="New team name..."
                            >
                            <button
                                @click="createTeam"
                                :disabled="!newTeamName"
                                class="btn btn-sm btn-primary"
                                type="button"
                            >
                                Add
                            </button>
                        </div>
                    </div>

                    <ul class="menu bg-base-200/60 w-full rounded-xl text-xs gap-1 p-2 border border-base-300">
                        <li
                            v-if="loadingTeams"
                            class="disabled"
                        >
                            <a>Loading teams...</a>
                        </li>
                        <li
                            v-else-if="teams.length === 0"
                            class="disabled italic opacity-50"
                        >
                            <a>No teams found.</a>
                        </li>
                        <li
                            v-for="team in teams"
                            :key="team.id"
                        >
                            <a
                                :class="{ 'active font-bold': selectedTeam && selectedTeam.id === team.id }"
                                @click="selectTeam(team)"
                            >
                                {{ team.name }}
                            </a>
                        </li>
                    </ul>
                </div>

                <!-- Team Details (Right Panel) -->
                <div class="w-full md:w-2/3 pl-4 overflow-y-auto relative custom-scrollbar">
                    <div
                        v-if="!selectedTeam"
                        class="flex items-center justify-center h-full opacity-50 text-xs italic"
                    >
                        Select a team on the left to view members and settings.
                    </div>
                    <div
                        v-else
                    >
                        <div class="flex items-center justify-between mb-4">
                            <div
                                v-if="!isEditingTeamName"
                                class="flex items-center gap-2"
                            >
                                <h4 class="font-bold text-lg">{{ selectedTeam.name }} Members</h4>
                                <button
                                    @click="startEditingName"
                                    class="btn btn-ghost btn-xs"
                                    title="Rename Team"
                                    type="button"
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
                                <label for="editing-team-name-input" class="sr-only">Edit team name</label>
                                <input
                                    id="editing-team-name-input"
                                    v-model="editingTeamName"
                                    type="text"
                                    class="input input-sm input-bordered flex-1 text-xs"
                                >
                                <button
                                    @click="renameTeam"
                                    class="btn btn-sm btn-primary"
                                    type="button"
                                >
                                    Save
                                </button>
                                <button
                                    @click="isEditingTeamName = false"
                                    class="btn btn-sm btn-ghost"
                                    type="button"
                                >
                                    Cancel
                                </button>
                            </div>
                        </div>

                        <!-- Add Member Section -->
                        <div class="bg-base-200/50 p-3 rounded-xl mb-4 border border-base-300 flex flex-wrap gap-2 items-end">
                            <div class="form-control flex-1 min-w-[140px]">
                                <label for="assign-user-input" class="label p-0 pb-1 text-[11px] font-semibold">User (Username or ID)</label>
                                <input
                                    id="assign-user-input"
                                    v-model="assignUserId"
                                    type="text"
                                    placeholder="Username or ID"
                                    class="input input-bordered input-sm w-full text-xs"
                                >
                            </div>
                            <div class="form-control w-32">
                                <label for="assign-role-select" class="label p-0 pb-1 text-[11px] font-semibold">Role</label>
                                <select
                                    id="assign-role-select"
                                    v-model="assignRoleId"
                                    class="select select-bordered select-sm w-full text-xs"
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
                                class="btn btn-sm btn-primary"
                                type="button"
                            >
                                Assign
                            </button>
                        </div>

                        <!-- Members Table -->
                        <div class="overflow-x-auto border border-base-300 rounded-xl mb-6">
                            <table class="table table-xs w-full">
                                <thead>
                                    <tr>
                                        <th>User</th>
                                        <th>Role</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-if="loadingMembers">
                                        <td colspan="3" class="text-center py-4">
                                            <span class="loading loading-spinner loading-xs"></span>
                                        </td>
                                    </tr>
                                    <tr v-else-if="teamUsers.length === 0">
                                        <td colspan="3" class="text-center py-4 opacity-50 italic">
                                            No members assigned to this team.
                                        </td>
                                    </tr>
                                    <tr
                                        v-for="member in teamUsers"
                                        :key="member.id || member.user_id"
                                    >
                                        <td class="font-medium">{{ member.username }}</td>
                                        <td>
                                            <label :for="`member-role-select-${member.id || member.user_id}`" class="sr-only">Role</label>
                                            <select
                                                :id="`member-role-select-${member.id || member.user_id}`"
                                                :value="member.role_id"
                                                @change="changeRole(member.user_id, Number(($event.target as HTMLSelectElement).value))"
                                                class="select select-xs select-bordered"
                                            >
                                                <option
                                                    v-for="r in roles"
                                                    :key="r.id"
                                                    :value="r.id"
                                                >
                                                    {{ r.name }}
                                                </option>
                                            </select>
                                        </td>
                                        <td>
                                            <button
                                                @click="removeUser(member.user_id)"
                                                class="btn btn-xs btn-error btn-ghost"
                                                type="button"
                                            >
                                                Remove
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Assigned Projects Section -->
                        <div class="divider text-xs font-bold uppercase tracking-wider text-base-content/60 mt-6">Assigned Projects</div>

                        <div class="bg-base-200/50 p-3 rounded-xl mb-4 border border-base-300 flex gap-2 items-end">
                            <div class="form-control flex-1">
                                <label class="label p-0 pb-1 text-[11px] font-semibold" for="assignProjectSelect">Select Project to Assign</label>
                                <select
                                    v-model="selectedProjectToAssign"
                                    id="assignProjectSelect"
                                    class="select select-sm select-bordered w-full text-xs"
                                >
                                    <option :value="null">-- Select Project --</option>
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
                                class="btn btn-sm btn-primary"
                                type="button"
                            >
                                Assign
                            </button>
                        </div>

                        <ul class="menu bg-base-100 rounded-xl w-full border border-base-300 text-xs p-1">
                            <li
                                v-if="teamProjects.length === 0"
                                class="disabled text-center py-2 opacity-50 italic"
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
                                        type="button"
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
                    class="btn btn-sm px-6"
                    type="button"
                >
                    Close
                </button>
            </div>
        </div>
        <form method="dialog" class="modal-backdrop">
            <button @click="closeModal" type="button">close</button>
        </form>
    </dialog>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue';
import { api } from '../../services/api';
import ConfirmationModal from './ConfirmationModal.vue';

interface TeamItem {
    id: number;
    name: string;
    sim_min_feedback_sec?: number | null;
    sim_max_feedback_sec?: number | null;
    sim_min_cr_sec?: number | null;
    sim_max_cr_sec?: number | null;
}

interface RoleItem {
    id: number;
    name: string;
}

interface TeamMember {
    id?: number;
    user_id: number;
    username: string;
    role_id: number;
}

interface ProjectItem {
    id: number;
    name: string;
    team_id?: number | null;
}

const props = withDefaults(
    defineProps<{
        isOpen?: boolean;
    }>(),
    {
        isOpen: false,
    }
);

const emit = defineEmits<{
    (e: 'close'): void;
}>();

const teams = ref<TeamItem[]>([]);
const roles = ref<RoleItem[]>([]);
const teamUsers = ref<TeamMember[]>([]);
const allProjects = ref<ProjectItem[]>([]);
const teamProjects = ref<ProjectItem[]>([]);
const selectedProjectToAssign = ref<number | null>(null);

const selectedTeam = ref<TeamItem | null>(null);
const newTeamName = ref('');
const isEditingTeamName = ref(false);
const editingTeamName = ref('');
const assignUserId = ref('');
const assignRoleId = ref<number | string>('');

const loadingTeams = ref(false);
const loadingMembers = ref(false);
const error = ref<string | null>(null);

const isAlertOpen = ref(false);
const alertMessage = ref('');
const isAlertDanger = ref(false);

const showAlert = (message: string, isDanger: boolean = false) => {
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
        const [teamsRes, rolesRes]: [any, any] = await Promise.all([
            api.listTeams(),
            api.listRoles()
        ]);

        if (teamsRes.success) teams.value = teamsRes.data;
        if (rolesRes.success) roles.value = rolesRes.data;

        if (roles.value.length > 0) {
            assignRoleId.value = roles.value[0].id;
        }
    } catch (e) {
        console.error('Failed to load teams/roles', e);
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
    } catch (e: any) {
        showAlert('Failed to create team: ' + (e.response?.data?.error || e.message), true);
    }
};

const selectTeam = async (team: TeamItem) => {
    selectedTeam.value = team;
    isEditingTeamName.value = false;
    await fetchTeamUsers(team.id);
    updateTeamProjects();
};

const startEditingName = () => {
    if (selectedTeam.value) {
        editingTeamName.value = selectedTeam.value.name;
        isEditingTeamName.value = true;
    }
};

const renameTeam = async () => {
    if (!editingTeamName.value || !selectedTeam.value) return;
    try {
        await api.updateTeam(selectedTeam.value.id, editingTeamName.value);
        selectedTeam.value.name = editingTeamName.value;
        isEditingTeamName.value = false;
        await fetchTeamsAndRoles();
    } catch (e: any) {
        showAlert('Failed to rename team: ' + (e.response?.data?.error || e.message), true);
    }
};

const fetchAllProjects = async () => {
    try {
        const res: any = await api.getProjects();
        allProjects.value = Array.isArray(res) ? res : (res.projects || []);
    } catch (e) {
        console.error('Failed to fetch all projects', e);
    }
};

const updateTeamProjects = () => {
    if (!selectedTeam.value) {
        teamProjects.value = [];
        return;
    }
    teamProjects.value = allProjects.value.filter(p => p.team_id === selectedTeam.value?.id);
};

const assignProject = async () => {
    if (!selectedTeam.value || !selectedProjectToAssign.value) return;
    try {
        await api.setProjectTeam(selectedProjectToAssign.value, selectedTeam.value.id);
        selectedProjectToAssign.value = null;
        await fetchAllProjects();
        updateTeamProjects();
    } catch (e: any) {
        showAlert('Failed to assign project: ' + (e.response?.data?.error || e.message), true);
    }
};

const unassignProject = async (projectId: number) => {
    try {
        await api.setProjectTeam(projectId, null);
        await fetchAllProjects();
        updateTeamProjects();
    } catch (e: any) {
        showAlert('Failed to unassign project: ' + (e.response?.data?.error || e.message), true);
    }
};

const fetchTeamUsers = async (teamId: number) => {
    loadingMembers.value = true;
    error.value = null;
    try {
        const res: any = await api.listTeamUsers(teamId);
        if (res.success) {
            teamUsers.value = res.data;
        } else {
            error.value = res.error;
        }
    } catch (e) {
        console.error(e);
        error.value = 'Failed to load members';
    } finally {
        loadingMembers.value = false;
    }
};

const assignUser = async () => {
    if (!selectedTeam.value || !assignUserId.value || !assignRoleId.value) return;

    try {
        await api.assignTeamUser(selectedTeam.value.id, assignUserId.value, Number(assignRoleId.value));
        assignUserId.value = '';
        await fetchTeamUsers(selectedTeam.value.id);
    } catch (e: any) {
        showAlert('Failed to assign user: ' + (e.response?.data?.error || e.message), true);
    }
};

const changeRole = async (userId: number, roleId: number) => {
    if (!selectedTeam.value) return;
    try {
        await api.updateTeamUserRole(selectedTeam.value.id, userId, roleId);
        await fetchTeamUsers(selectedTeam.value.id);
    } catch (e: any) {
        showAlert('Failed to modify role: ' + (e.response?.data?.error || e.message), true);
    }
};

const removeUser = async (userId: number) => {
    if (!selectedTeam.value) return;
    try {
        await api.removeTeamUser(selectedTeam.value.id, userId);
        await fetchTeamUsers(selectedTeam.value.id);
    } catch (e: any) {
        showAlert('Failed to remove user: ' + (e.response?.data?.error || e.message), true);
    }
};

const closeModal = () => {
    selectedTeam.value = null;
    teamUsers.value = [];
    emit('close');
};
</script>
