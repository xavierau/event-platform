<template>
    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                {{ title }}
            </h3>
            <div class="text-sm text-gray-500 dark:text-gray-400">
                {{ subtitle }}
            </div>
        </div>

        <div class="h-64 w-full">
            <canvas ref="chartCanvas"></canvas>
        </div>

        <div v-if="showSummary" class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-600">
            <div class="grid grid-cols-3 gap-4 text-center">
                <div>
                    <div class="text-2xl font-semibold text-gray-900 dark:text-white">
                        {{ totalNewMembers }}
                    </div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">
                        Total New Members
                    </div>
                </div>
                <div>
                    <div class="text-2xl font-semibold text-gray-900 dark:text-white">
                        {{ averagePerMonth }}
                    </div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">
                        Avg. per Month
                    </div>
                </div>
                <div>
                    <div class="text-2xl font-semibold" :class="growthTrendClass">
                        {{ growthTrend }}%
                    </div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">
                        Growth Trend
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref, onMounted, computed, nextTick, watch } from 'vue';
import {
    Chart,
    CategoryScale,
    LinearScale,
    PointElement,
    LineElement,
    LineController,
    Title,
    Tooltip,
    Legend,
    Filler
} from 'chart.js';

// Register Chart.js components
Chart.register(
    CategoryScale,
    LinearScale,
    PointElement,
    LineElement,
    LineController,
    Title,
    Tooltip,
    Legend,
    Filler
);

interface GrowthData {
    month: string;
    month_name: string;
    count: number;
}

interface Props {
    data: GrowthData[];
    title?: string;
    subtitle?: string;
    showSummary?: boolean;
    color?: string;
}

const props = withDefaults(defineProps<Props>(), {
    title: 'Member Growth Trend',
    subtitle: 'Last 12 months',
    showSummary: true,
    color: '#3B82F6'
});

const chartCanvas = ref<HTMLCanvasElement>();
let chart: Chart | null = null;

const totalNewMembers = computed(() => {
    return props.data.reduce((sum, item) => sum + item.count, 0);
});

const averagePerMonth = computed(() => {
    const total = totalNewMembers.value;
    const months = props.data.length || 1;
    return Math.round(total / months);
});

const growthTrend = computed(() => {
    if (props.data.length < 2) return 0;

    const firstHalf = props.data.slice(0, Math.floor(props.data.length / 2));
    const secondHalf = props.data.slice(Math.floor(props.data.length / 2));

    const firstHalfAvg = firstHalf.reduce((sum, item) => sum + item.count, 0) / firstHalf.length;
    const secondHalfAvg = secondHalf.reduce((sum, item) => sum + item.count, 0) / secondHalf.length;

    if (firstHalfAvg === 0) return 0;

    return Math.round(((secondHalfAvg - firstHalfAvg) / firstHalfAvg) * 100);
});

const growthTrendClass = computed(() => {
    const trend = growthTrend.value;
    if (trend > 0) return 'text-green-600 dark:text-green-400';
    if (trend < 0) return 'text-red-600 dark:text-red-400';
    return 'text-gray-600 dark:text-gray-400';
});

const createChart = () => {
    if (!chartCanvas.value || !props.data.length) return;

    const ctx = chartCanvas.value.getContext('2d');
    if (!ctx) return;

    // Destroy existing chart
    if (chart) {
        chart.destroy();
    }

    chart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: props.data.map(item => item.month_name),
            datasets: [{
                label: 'New Members',
                data: props.data.map(item => item.count),
                borderColor: props.color,
                backgroundColor: props.color + '20',
                borderWidth: 2,
                fill: true,
                tension: 0.4,
                pointBackgroundColor: props.color,
                pointBorderColor: '#ffffff',
                pointBorderWidth: 2,
                pointRadius: 4,
                pointHoverRadius: 6,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                intersect: false,
                mode: 'index',
            },
            plugins: {
                legend: {
                    display: false,
                },
                tooltip: {
                    backgroundColor: 'rgba(0, 0, 0, 0.8)',
                    titleColor: '#ffffff',
                    bodyColor: '#ffffff',
                    borderColor: props.color,
                    borderWidth: 1,
                    cornerRadius: 6,
                    displayColors: false,
                    callbacks: {
                        title: (context) => {
                            return context[0].label;
                        },
                        label: (context) => {
                            return `${context.parsed.y} new members`;
                        }
                    }
                }
            },
            scales: {
                x: {
                    grid: {
                        display: false,
                    },
                    border: {
                        display: false,
                    },
                    ticks: {
                        color: '#6B7280',
                        font: {
                            size: 12,
                        }
                    }
                },
                y: {
                    beginAtZero: true,
                    grid: {
                        color: 'rgba(107, 114, 128, 0.1)',
                    },
                    border: {
                        display: false,
                    },
                    ticks: {
                        color: '#6B7280',
                        font: {
                            size: 12,
                        },
                        callback: function(value) {
                            return Number(value).toFixed(0);
                        }
                    }
                }
            }
        }
    });
};

onMounted(async () => {
    await nextTick();
    createChart();
});

watch(() => props.data, () => {
    nextTick(() => {
        createChart();
    });
}, { deep: true });
</script>