<template>
    <dialog
        :class="{ 'modal-open': isOpen }"
        :open="isOpen"
        class="modal z-50"
    >
        <div class="modal-box w-11/12 max-w-2xl bg-base-100 rounded-2xl border border-base-300 shadow-2xl">
            <div class="flex items-center justify-between mb-4">
                <h3 class="font-bold text-lg text-primary flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-primary animate-pulse"></span>
                    API Cost & Token Tracking
                </h3>
                <button
                    @click="$emit('close')"
                    class="btn btn-sm btn-circle btn-ghost"
                    type="button"
                    aria-label="Close"
                >
                    ✕
                </button>
            </div>

            <div
                v-if="loading"
                class="flex justify-center p-8"
            >
                <span class="loading loading-spinner loading-md text-primary"></span>
            </div>

            <div
                v-else-if="error"
                class="alert alert-error text-xs"
            >
                <span>{{ error }}</span>
            </div>

            <div
                v-else
            >
                <div
                    v-if="usage.length === 0"
                    class="p-8 text-center opacity-60 text-xs italic"
                >
                    No API usage recorded yet.
                </div>

                <div
                    v-for="item in usage"
                    :key="item.model"
                    class="mb-6 last:mb-0"
                >
                    <h4 class="font-bold text-sm mb-2 text-secondary flex items-center gap-1.5">
                        <span>🤖 {{ item.model }}</span>
                    </h4>

                    <div class="stats shadow-sm w-full bg-base-200/70 border border-base-300">
                        <div class="stat place-items-center py-2 px-3">
                            <div class="stat-title text-[11px]">Prompt Tokens</div>
                            <div class="stat-value text-base font-bold">{{ item.prompt_tokens.toLocaleString() }}</div>
                        </div>

                        <div class="stat place-items-center py-2 px-3">
                            <div class="stat-title text-[11px]">Candidate Tokens</div>
                            <div class="stat-value text-base font-bold text-secondary">{{ item.candidate_tokens.toLocaleString() }}</div>
                        </div>

                        <div class="stat place-items-center py-2 px-3">
                            <div class="stat-title text-[11px]">Total Tokens</div>
                            <div class="stat-value text-base font-bold text-accent">{{ item.total_tokens.toLocaleString() }}</div>
                        </div>
                    </div>

                    <div class="mt-3 flex flex-col gap-2">
                        <p class="text-[11px] opacity-70">
                            Rates: ${{ config[item.model]?.promptCostPerMillion || 0 }}/1M input, ${{ config[item.model]?.candidateCostPerMillion || 0 }}/1M output.
                        </p>
                        <table class="table table-zebra table-xs w-full">
                            <thead>
                                <tr>
                                    <th>Type</th>
                                    <th>Tokens</th>
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
                    class="btn btn-sm px-6"
                    type="button"
                >
                    Close
                </button>
            </div>
        </div>
        <form method="dialog" class="modal-backdrop">
            <button @click="$emit('close')" type="button">close</button>
        </form>
    </dialog>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue';
import { api } from '../../services/api';

interface UsageItem {
    model: string;
    prompt_tokens: number;
    candidate_tokens: number;
    total_tokens: number;
}

const props = defineProps<{
    isOpen: boolean;
}>();

defineEmits<{
    (e: 'close'): void;
}>();

const loading = ref(false);
const error = ref<string | null>(null);
const usage = ref<UsageItem[]>([]);
const config = ref<Record<string, { promptCostPerMillion: number; candidateCostPerMillion: number }>>({});

watch(() => props.isOpen, async (newVal) => {
    if (newVal) {
        await fetchUsage();
    }
});

const fetchUsage = async () => {
    loading.value = true;
    error.value = null;
    try {
        const res: any = await api.getApiUsage();
        if (res.success && res.data) {
            usage.value = res.data;
            if (res.config) {
                config.value = res.config;
            }
        } else {
            error.value = res.error || 'Failed to load API usage.';
        }
    } catch (e: any) {
        error.value = e.response?.data?.error || e.message;
    } finally {
        loading.value = false;
    }
};
</script>
