<template>
    <Head :title="t('users.index_title')" />
    <AppLayout>
        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8 space-y-6">
                <!-- Page Header -->
                <div class="flex items-center justify-between">
                    <PageHeader :title="t('users.index_title')" :subtitle="t('users.index_subtitle')" />
                    <Link
                        :href="route('admin.users.create')"
                        class="inline-flex items-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 dark:bg-indigo-500 dark:hover:bg-indigo-400"
                    >
                        {{ t('actions.create_user') }}
                    </Link>
                </div>

                <!-- Metrics Dashboard -->
                <div v-if="metrics" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <UserMetricsCard
                        :title="t('users.metrics.total_users')"
                        :value="metrics.total_users"
                        :icon="UsersIcon"
                        color="blue"
                    />
                    <UserMetricsCard
                        :title="t('users.metrics.with_membership')"
                        :value="metrics.membership_status.with_membership"
                        :icon="CheckCircleIcon"
                        color="green"
                    />
                    <UserMetricsCard
                        :title="t('users.metrics.without_membership')"
                        :value="metrics.membership_status.without_membership"
                        :icon="XCircleIcon"
                        color="red"
                    />
                    <UserMetricsCard
                        :title="t('users.metrics.growth_rate')"
                        :value="growthPercentage + '%'"
                        :icon="ArrowTrendingUpIcon"
                        :color="growthPercentage >= 0 ? 'green' : 'red'"
                    />
                </div>

                <!-- Membership Distribution & Growth Chart -->
                <div v-if="metrics" class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Membership Distribution -->
                    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
                            {{ t('users.metrics.membership_distribution') }}
                        </h3>
                        <div class="space-y-3">
                            <div
                                v-for="level in metrics.membership_distribution"
                                :key="level.id"
                                class="flex items-center justify-between"
                            >
                                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
                                    {{ getTranslation(level.name, currentLocale) }}
                                </span>
                                <div class="flex items-center space-x-2">
                                    <span class="text-sm text-gray-500 dark:text-gray-400">
                                        {{ level.count }}
                                    </span>
                                    <div class="w-16 bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                                        <div
                                            class="bg-indigo-600 h-2 rounded-full"
                                            :style="{ width: `${(level.count / metrics.total_users) * 100}%` }"
                                        ></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Growth Chart -->
                    <MemberGrowthChart
                        v-if="metrics.member_growth"
                        :data="metrics.member_growth"
                        :title="t('users.metrics.member_growth')"
                        :subtitle="t('users.metrics.last_12_months')"
                    />
                </div>

                <!-- Filters -->
                <UserFilters
                    :membership-levels="membershipLevels"
                    :initial-filters="filters"
                />

                <!-- Users Table -->
                <div class="overflow-hidden bg-white shadow-xl sm:rounded-lg dark:bg-gray-800">
                    <div
                        class="border-b border-gray-200 bg-white p-6 lg:p-8 dark:border-gray-700 dark:bg-gray-800 dark:bg-gradient-to-bl dark:from-gray-700/50 dark:via-transparent"
                    >

                        <AdminDataTable>
                            <!-- Header Slot -->
                            <template #header>
                                <TableHead>{{ t('fields.name') }}</TableHead>
                                <TableHead>{{ t('fields.email') }}</TableHead>
                                <TableHead>{{ t('fields.commenting_blocked') }}</TableHead>
                                <TableHead>{{ t('fields.membership_level') }}</TableHead>
                                <TableHead>{{ t('fields.organization') }}</TableHead>
                                <TableHead class="text-right">{{ t('fields.actions') }}</TableHead>
                            </template>

                            <!-- Body Slot -->
                            <template #body>
                                <TableRow v-if="users.data && users.data.length === 0">
                                    <TableCell colspan="6" class="text-center">{{ t('users.no_users_found') }} </TableCell>
                                </TableRow>
                                <TableRow v-for="user in users.data" :key="user.id">
                                    <TableCell class="font-medium text-gray-900 dark:text-white">{{ user.name }} </TableCell>
                                    <TableCell>{{ user.email }}</TableCell>
                                    <TableCell>
                                        <span :class="user.is_commenting_blocked ? 'text-red-500' : 'text-green-500'">
                                            {{ user.is_commenting_blocked ? t('common.yes') : t('common.no') }}
                                        </span>
                                    </TableCell>
                                    <TableCell>{{ user.membership_level }}</TableCell>
                                    <TableCell>{{ user.organizer_info }}</TableCell>
                                    <TableCell class="text-right">
                                        <Link
                                            :href="route('admin.users.show', user.id)"
                                            class="mr-3 text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-600"
                                        >
                                            {{ t('actions.view') }}
                                        </Link>
                                        <Link
                                            :href="route('admin.users.edit', user.id)"
                                            class="mr-3 text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-600"
                                        >
                                            {{ t('actions.edit') }}
                                        </Link>
                                    </TableCell>
                                </TableRow>
                            </template>
                        </AdminDataTable>

                        <!-- Pagination -->
                        <AdminPagination :links="users.links" :from="users.from" :to="users.to" :total="users.total" />
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup lang="ts">
import AdminDataTable from '@/components/Shared/AdminDataTable.vue';
import AdminPagination from '@/components/Shared/AdminPagination.vue';
import PageHeader from '@/components/Shared/PageHeader.vue';
import UserMetricsCard from '@/components/Admin/UserMetricsCard.vue';
import MemberGrowthChart from '@/components/Admin/MemberGrowthChart.vue';
import UserFilters from '@/components/Admin/UserFilters.vue';
import { TableCell, TableHead, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import type { User } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { computed, onMounted, ref } from 'vue';
import {
    UsersIcon,
    CheckCircleIcon,
    XCircleIcon,
    ArrowTrendingUpIcon
} from '@heroicons/vue/24/outline';
import axios from 'axios';

const { t, locale } = useI18n();

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginatedUsers {
    data: User[];
    links: PaginationLink[];
    from: number;
    to: number;
    total: number;
    per_page: number;
}

interface MembershipLevel {
    id: number;
    name: Record<string, string>;
}

interface UserMetrics {
    total_users: number;
    membership_distribution: Array<{
        id: number;
        name: Record<string, string>;
        count: number;
    }>;
    member_growth: Array<{
        month: string;
        month_name: string;
        count: number;
    }>;
    membership_status: {
        with_membership: number;
        without_membership: number;
    };
}

interface Props {
    users: PaginatedUsers;
    membershipLevels: MembershipLevel[];
    filters: Record<string, any>;
}

const props = defineProps<Props>();

const metrics = ref<UserMetrics | null>(null);
const currentLocale = computed(() => locale.value);

const growthPercentage = computed(() => {
    if (!metrics.value?.member_growth || metrics.value.member_growth.length < 2) {
        return 0;
    }

    const data = metrics.value.member_growth;
    const firstHalf = data.slice(0, Math.floor(data.length / 2));
    const secondHalf = data.slice(Math.floor(data.length / 2));

    const firstHalfAvg = firstHalf.reduce((sum, item) => sum + item.count, 0) / firstHalf.length;
    const secondHalfAvg = secondHalf.reduce((sum, item) => sum + item.count, 0) / secondHalf.length;

    if (firstHalfAvg === 0) return 0;

    return Math.round(((secondHalfAvg - firstHalfAvg) / firstHalfAvg) * 100);
});

const getTranslation = (translations: Record<string, string>, locale: string): string => {
    if (typeof translations === 'string') return translations;
    return translations?.[locale] || translations?.['en'] || '';
};

const fetchMetrics = async () => {
    try {
        const response = await axios.get(route('admin.users.metrics'));
        metrics.value = response.data;
    } catch (error) {
        console.error('Failed to fetch user metrics:', error);
    }
};

onMounted(() => {
    fetchMetrics();
});
</script>
