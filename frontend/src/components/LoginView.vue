<template>
    <div class="min-h-[calc(100vh-140px)] flex items-center justify-center bg-base-200">
        <div class="card w-96 bg-base-100 shadow-xl">
            <div class="card-body">
                <h2 class="card-title justify-center text-2xl mb-4 font-bold text-primary">
                    {{ isLogin ? 'TAIPO Login' : 'TAIPO Register' }}
                </h2>

                <form @submit.prevent="handleSubmit">
                    <div class="form-control mb-4">
                        <label class="label" for="username-input">
                            <span class="label-text">Username</span>
                        </label>
                        <input
                            v-model="username"
                            :minlength="config?.minUsernameLength || 3"
                            :maxlength="50"
                            type="text"
                            placeholder="username"
                            id="username-input"
                            class="input input-bordered w-full"
                            required
                        >
                    </div>

                    <div class="form-control mb-6">
                        <label class="label" for="password-input">
                            <span class="label-text">Password</span>
                        </label>
                        <input
                            v-model="password"
                            :minlength="config?.minPasswordLength || 6"
                            type="password"
                            placeholder="••••••••"
                            id="password-input"
                            class="input input-bordered w-full"
                            required
                        >
                    </div>

                    <div
                        v-if="error"
                        class="alert alert-error mb-4 py-2 text-sm"
                    >
                        {{ error }}
                    </div>

                    <p
                        v-if="!isLogin"
                        class="text-xs text-base-content/70 mb-4 text-center"
                    >
                        <b>Privacy Notice:</b> All data is stored locally on this instance.
                        By registering, you agree to local storage of your tasks and projects.
                    </p>

                    <div class="form-control mt-2">
                        <button
                            :disabled="loading"
                            type="submit"
                            class="btn btn-primary w-full"
                        >
                            <span
                                v-if="loading"
                                class="loading loading-spinner loading-sm"
                            >
                            </span>
                            {{ isLogin ? 'Login' : 'Register' }}
                        </button>
                    </div>
                </form>

                <div class="divider text-sm my-6">OR</div>

                <div class="text-center">
                    <button
                        @click="toggleMode"
                        class="btn btn-link link-hover text-sm h-auto min-h-0"
                    >
                        {{ isLogin ? 'Need an account? Register' : 'Already have an account? Login' }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import { api } from '../services/api';

const props = defineProps({
    config: {
        type: Object,
        default: () => ({ minUsernameLength: 3, minPasswordLength: 6 })
    }
});

const emit = defineEmits(['auth-success']);

const isLogin = ref(true);
const username = ref('');
const password = ref('');
const error = ref('');
const loading = ref(false);

const toggleMode = () => {
    isLogin.value = !isLogin.value;
    error.value = '';
};

const handleSubmit = async () => {
    error.value = '';
    loading.value = true;

    try {
        let res;
        if (isLogin.value) {
            res = await api.login(username.value, password.value);
        } else {
            res = await api.register(username.value, password.value);
        }

        if (res.success) {
            emit('auth-success', res.user);
        } else {
            error.value = res.error || 'Authentication failed.';
        }
    } catch (e) {
        error.value = e.response?.data?.error || 'Network error communicating with server.';
    } finally {
        loading.value = false;
    }
};
</script>
