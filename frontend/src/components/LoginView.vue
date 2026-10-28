<template>
    <div class="min-h-[calc(100vh-140px)] flex items-center justify-center bg-base-200/50 p-4">
        <div class="w-full max-w-md bg-base-100/90 backdrop-blur-xl shadow-2xl rounded-2xl border border-base-300/80 p-8 animate-in fade-in zoom-in duration-200">
            <div class="flex flex-col items-center mb-6 text-center">
                <div class="w-14 h-14 rounded-2xl bg-gradient-to-tr from-primary to-purple-600 flex items-center justify-center text-primary-content text-2xl font-black shadow-lg shadow-primary/25 mb-3">
                    T
                </div>
                <h2 class="text-2xl font-black tracking-tight text-base-content">
                    {{ isLogin ? 'Welcome Back' : 'Create Account' }}
                </h2>
                <p class="text-xs text-base-content/60 mt-1">
                    {{ isLogin ? 'Sign in to access your TAIPO Kanban workspaces' : 'Register a new student or instructor workspace profile' }}
                </p>
            </div>

            <form @submit.prevent="handleSubmit" class="space-y-4">
                <div class="form-control">
                    <label class="block text-[11px] font-bold text-base-content/70 uppercase tracking-widest mb-1.5" for="username-input">
                        Username
                    </label>
                    <input
                        v-model="username"
                        :minlength="config?.minUsernameLength || 3"
                        :maxlength="16"
                        type="text"
                        placeholder="Enter your username"
                        id="username-input"
                        class="input input-bordered w-full bg-base-200/50 border-base-300 text-sm focus:border-primary focus:ring-2 focus:ring-primary/20 rounded-xl transition-all"
                        required
                    >
                </div>

                <div class="form-control">
                    <label class="block text-[11px] font-bold text-base-content/70 uppercase tracking-widest mb-1.5" for="password-input">
                        Password
                    </label>
                    <input
                        v-model="password"
                        :minlength="config?.minPasswordLength || 6"
                        :maxlength="31"
                        type="password"
                        placeholder="••••••••"
                        id="password-input"
                        class="input input-bordered w-full bg-base-200/50 border-base-300 text-sm focus:border-primary focus:ring-2 focus:ring-primary/20 rounded-xl transition-all"
                        required
                    >
                </div>

                <div
                    v-if="error"
                    class="alert alert-error text-xs py-2 px-3 rounded-xl shadow-sm flex items-center gap-2"
                >
                    <span>⚠️</span>
                    <span>{{ error }}</span>
                </div>

                <p
                    v-if="!isLogin"
                    class="text-[11px] text-base-content/70 text-center leading-relaxed"
                >
                    <strong>Privacy Notice:</strong> All data is stored locally on this instance.
                    By registering, you agree to local storage of your tasks and projects.
                </p>

                <div class="text-center">
                    <button
                        @click="$emit('open-privacy-modal')"
                        type="button"
                        class="link link-hover text-xs text-primary"
                    >
                        Read our Privacy Policy & Terms
                    </button>
                </div>

                <button
                    :disabled="loading"
                    type="submit"
                    class="btn btn-primary w-full rounded-xl shadow-lg shadow-primary/20 font-bold transition-all"
                >
                    <span
                        v-if="loading"
                        class="loading loading-spinner loading-sm"
                    >
                    </span>
                    {{ isLogin ? 'Sign In to TAIPO' : 'Create TAIPO Account' }}
                </button>
            </form>

            <div class="divider text-xs text-base-content/40 my-6">OR</div>

            <div class="text-center">
                <button
                    @click="toggleMode"
                    type="button"
                    class="text-xs font-semibold text-primary hover:underline"
                >
                    {{ isLogin ? "Don't have an account? Register here" : 'Already have an account? Sign in' }}
                </button>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import { api } from '../services/api';
import type { AppConfig, AuthUser } from '../types';

const props = withDefaults(
    defineProps<{
        config?: Partial<AppConfig>;
    }>(),
    {
        config: () => ({ minUsernameLength: 3, minPasswordLength: 6 })
    }
);

const emit = defineEmits<{
    (e: 'auth-success', user: AuthUser): void;
    (e: 'open-privacy-modal'): void;
}>();

const isLogin = ref<boolean>(true);
const username = ref<string>('');
const password = ref<string>('');
const error = ref<string>('');
const loading = ref<boolean>(false);

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

        if (res.success && res.user) {
            emit('auth-success', res.user);
        } else {
            error.value = res.error || 'Authentication failed.';
        }
    } catch (e: any) {
        error.value = e.response?.data?.error || 'Network error communicating with server.';
    } finally {
        loading.value = false;
    }
};
</script>
