<template>
    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
        <div class="p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="flex items-center justify-center w-12 h-12 rounded-md" :class="iconBgClass">
                        <component :is="icon" class="w-6 h-6" :class="iconClass" />
                    </div>
                </div>
                <div class="ml-5">
                    <dl>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">
                            {{ title }}
                        </dt>
                        <dd class="text-lg font-medium text-gray-900 dark:text-white">
                            {{ formattedValue }}
                        </dd>
                    </dl>
                </div>
            </div>

            <div v-if="trend" class="mt-4">
                <div class="flex items-center text-sm">
                    <span :class="trendClass" class="flex items-center font-medium">
                        <component :is="trendIcon" class="w-4 h-4 mr-1" />
                        {{ trendText }}
                    </span>
                    <span class="ml-2 text-gray-500 dark:text-gray-400">
                        {{ trendDescription }}
                    </span>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import {
    UsersIcon,
    UserGroupIcon,
    ArrowTrendingUpIcon,
    ArrowTrendingDownIcon,
    ChartBarIcon,
    CheckCircleIcon,
    XCircleIcon
} from '@heroicons/vue/24/outline';

interface Props {
    title: string;
    value: number | string;
    icon?: any;
    color?: 'blue' | 'green' | 'yellow' | 'red' | 'purple' | 'indigo';
    trend?: {
        value: number;
        isPositive: boolean;
        description: string;
    };
    format?: 'number' | 'percentage' | 'currency';
}

const props = withDefaults(defineProps<Props>(), {
    icon: UsersIcon,
    color: 'blue',
    format: 'number'
});

const colorClasses = {
    blue: {
        iconBg: 'bg-blue-100 dark:bg-blue-900',
        iconText: 'text-blue-600 dark:text-blue-400'
    },
    green: {
        iconBg: 'bg-green-100 dark:bg-green-900',
        iconText: 'text-green-600 dark:text-green-400'
    },
    yellow: {
        iconBg: 'bg-yellow-100 dark:bg-yellow-900',
        iconText: 'text-yellow-600 dark:text-yellow-400'
    },
    red: {
        iconBg: 'bg-red-100 dark:bg-red-900',
        iconText: 'text-red-600 dark:text-red-400'
    },
    purple: {
        iconBg: 'bg-purple-100 dark:bg-purple-900',
        iconText: 'text-purple-600 dark:text-purple-400'
    },
    indigo: {
        iconBg: 'bg-indigo-100 dark:bg-indigo-900',
        iconText: 'text-indigo-600 dark:text-indigo-400'
    }
};

const iconBgClass = computed(() => colorClasses[props.color].iconBg);
const iconClass = computed(() => colorClasses[props.color].iconText);

const formattedValue = computed(() => {
    if (typeof props.value === 'string') return props.value;

    switch (props.format) {
        case 'percentage':
            return `${props.value}%`;
        case 'currency':
            return new Intl.NumberFormat('en-US', {
                style: 'currency',
                currency: 'USD'
            }).format(props.value);
        default:
            return new Intl.NumberFormat().format(props.value);
    }
});

const trendIcon = computed(() => {
    return props.trend?.isPositive ? ArrowTrendingUpIcon : ArrowTrendingDownIcon;
});

const trendClass = computed(() => {
    return props.trend?.isPositive
        ? 'text-green-600 dark:text-green-400'
        : 'text-red-600 dark:text-red-400';
});

const trendText = computed(() => {
    if (!props.trend) return '';
    const sign = props.trend.isPositive ? '+' : '';
    return `${sign}${props.trend.value}%`;
});

const trendDescription = computed(() => {
    return props.trend?.description || '';
});
</script>