<template>
    <dialog
        :class="{ 'modal-open': isOpen }"
        class="modal"
    >
        <div class="modal-box w-11/12 max-w-2xl bg-base-100">
            <h3 class="font-bold text-lg mb-4 text-primary">API Cost Tracking</h3>

            <div
                v-if="loading"
                class="flex justify-center p-4"
            >
                <span class="loading loading-spinner loading-md text-primary"></span>
            </div>

            <div
                v-else-if="error"
                class="alert alert-error"
            >
                <span>{{ error }}</span>
            </div>

            <div
                v-else
            >
                <div
                    v-if="usage.length === 0"
                    class="p-4 text-center opacity-70"
                >
                    No API usage recorded yet.
                </div>

                <div
                    v-for="item in usage"
                    :key="item.model"
                    class="mb-8 last:mb-0"
                >
                    <h4 class="font-bold text-lg mb-2 text-secondary">{{ item.model }} Usage</h4>

                    <div class="stats shadow w-full bg-base-200">
                        <div class="stat place-items-center">
                            <div class="stat-title">Prompt Tokens</div>
                            <div class="stat-value">{{ item.prompt_tokens.toLocaleString() }}</div>
                        </div>

                        <div class="stat place-items-center">
                            <div class="stat-title">Candidate Tokens</div>
                            <div class="stat-value text-secondary">{{ item.candidate_tokens.toLocaleString() }}</div>
                        </div>

                        <div class="stat place-items-center">
                            <div class="stat-title">Total Tokens</div>
                            <div class="stat-value text-accent">{{ item.total_tokens.toLocaleString() }}</div>
                        </div>
                    </div>

                    <div class="mt-4 flex flex-col gap-2">
                        <p class="text-sm opacity-70 mb-2">
                            Costs are calculated based on configured pricing (${{ config[item.model]?.promptCostPerMillion || 0 }} / 1M prompt, ${{ config[item.model]?.candidateCostPerMillion || 0 }} / 1M candidate).
                        </p>
                        <table class="table table-zebra w-full hidden md:table">
                            <thead>
                                <tr>
                                    <th>Type</th>
                                    <th>Tokens Used</th>
                                    <th>Rate (per 1M)</th>
                                    <th>Total Cost</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>Prompt</td>
                                    <td>{{ item.prompt_tokens.toLocaleString() }}</td>
                                    <td>${{ (config[item.model]?.promptCostPerMillion || 0).toFixed(3) }}</td>
                                    <td>${{ (item.prompt_tokens / 1000000 * (config[item.model]?.promptCostPerMillion || 0)).toFixed(4) }}</td>
                                </tr>
                                <tr>
                                    <td>Candidate</td>
                                    <td>{{ item.candidate_tokens.toLocaleString() }}</td>
                                    <td>${{ (config[item.model]?.candidateCostPerMillion || 0).toFixed(3) }}</td>
                                    <td>${{ (item.candidate_tokens / 1000000 * (config[item.model]?.candidateCostPerMillion || 0)).toFixed(4) }}</td>
                                </tr>
                                <tr class="font-bold">
                                    <td>Total</td>
                                    <td>{{ item.total_tokens.toLocaleString() }}</td>
                                    <td>-</td>
                                    <td>${{ ((item.prompt_tokens / 1000000 * (config[item.model]?.promptCostPerMillion || 0)) + (item.candidate_tokens / 1000000 * (config[item.model]?.candidateCostPerMillion || 0))).toFixed(4) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="modal-action">
                <button
                    @click="$emit('close')"
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

const props = defineProps({
    isOpen: Boolean
});

const emit = defineEmits(['close']);

const loading = ref(false);
const error = ref(null);
const usage = ref([]);
const config = ref({});

watch(() => props.isOpen, async (newVal) => {
    if (newVal) {
        await fetchUsage();
    }
});

const fetchUsage = async () => {
    loading.value = true;
    error.value = null;
    try {
        const res = await api.getApiUsage();
        if (res.success && res.data) {
            usage.value = res.data;
            if (res.config) {
                config.value = res.config;
            }
        } else {
            error.value = res.error || "Failed to load API usage.";
        }
    } catch (e) {
        error.value = e.response?.data?.error || e.message;
    } finally {
        loading.value = false;
    }
};
</script>
