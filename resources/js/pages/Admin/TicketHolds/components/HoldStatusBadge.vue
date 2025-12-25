<script setup lang="ts">
import { computed } from 'vue';

type HoldStatus = 'active' | 'expired' | 'released' | 'exhausted';

interface Props {
    status: HoldStatus;
}

const props = defineProps<Props>();

const statusConfig = computed(() => {
    const configs: Record<HoldStatus, { label: string; classes: string }> = {
        active: {
            label: 'Active',
            classes: 'bg-green-100 text-green-800 dark:bg-green-700 dark:text-green-200',
        },
        expired: {
            label: 'Expired',
            classes: 'bg-gray-100 text-gray-800 dark:bg-gray-600 dark:text-gray-200',
        },
        released: {
            label: 'Released',
            classes: 'bg-blue-100 text-blue-800 dark:bg-blue-700 dark:text-blue-200',
        },
        exhausted: {
            label: 'Exhausted',
            classes: 'bg-orange-100 text-orange-800 dark:bg-orange-700 dark:text-orange-200',
        },
    };
    return configs[props.status] || configs.expired;
});
</script>

<template>
    <span
        :class="statusConfig.classes"
        class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full"
    >
        {{ statusConfig.label }}
    </span>
</template>
