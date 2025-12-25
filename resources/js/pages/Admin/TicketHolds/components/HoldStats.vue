<script setup lang="ts">
import { computed } from 'vue';

interface HoldAnalytics {
    totalAllocated: number;
    totalPurchased: number;
    totalRemaining: number;
    utilizationRate: number;
    linkCount: number;
    activeLinkCount: number;
}

interface Props {
    analytics: HoldAnalytics;
}

const props = defineProps<Props>();

const utilizationPercentage = computed(() => {
    return `${props.analytics.utilizationRate.toFixed(1)}%`;
});

const utilizationColor = computed(() => {
    const rate = props.analytics.utilizationRate;
    if (rate >= 80) return 'text-red-600 dark:text-red-400';
    if (rate >= 50) return 'text-yellow-600 dark:text-yellow-400';
    return 'text-green-600 dark:text-green-400';
});
</script>

<template>
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Total Allocated -->
        <div class="bg-gray-50 dark:bg-gray-900/50 p-4 rounded-lg">
            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Allocated</dt>
            <dd class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white">
                {{ analytics.totalAllocated }}
            </dd>
        </div>

        <!-- Total Purchased -->
        <div class="bg-gray-50 dark:bg-gray-900/50 p-4 rounded-lg">
            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Purchased</dt>
            <dd class="mt-1 text-2xl font-semibold text-indigo-600 dark:text-indigo-400">
                {{ analytics.totalPurchased }}
            </dd>
        </div>

        <!-- Remaining -->
        <div class="bg-gray-50 dark:bg-gray-900/50 p-4 rounded-lg">
            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Remaining</dt>
            <dd class="mt-1 text-2xl font-semibold text-blue-600 dark:text-blue-400">
                {{ analytics.totalRemaining }}
            </dd>
        </div>

        <!-- Utilization Rate -->
        <div class="bg-gray-50 dark:bg-gray-900/50 p-4 rounded-lg">
            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Utilization</dt>
            <dd class="mt-1 text-2xl font-semibold" :class="utilizationColor">
                {{ utilizationPercentage }}
            </dd>
        </div>
    </div>
</template>
