<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
// @ts-expect-error - vue-i18n has no type definitions
import { useI18n } from 'vue-i18n';
import { getTranslation } from '@/Utils/i18n';
import { throttle } from 'lodash';
import PageHeader from '@/components/Shared/PageHeader.vue';
import AdminPagination from '@/components/Shared/AdminPagination.vue';
import { TableHead, TableRow, TableCell } from '@/components/ui/table';
import AdminDataTable from '@/components/Shared/AdminDataTable.vue';
import { Card, CardContent } from '@/components/ui/card';
import { Ticket, CheckCircle, Clock, DollarSign } from 'lucide-vue-next';
import { useCurrency } from '@/composables/useCurrency';

const { t } = useI18n();
const page = usePage();
const { formatPrice } = useCurrency();
const currentLocale = computed(() => page.props.locale as 'en' | 'zh-HK' | 'zh-CN');

// Interfaces
interface Booking {
    id: number;
    booking_number: string;
    event_name: string;
    user_name: string;
    user_email: string;
    status: string;
    status_value: string;
    created_at: string;
    total_price_formatted: string;
    transaction: {
        id: number;
        payment_gateway: string;
        status: string;
        status_value: string;
    } | null;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginatedBookings {
    data: Booking[];
    links: PaginationLink[];
    from: number;
    to: number;
    total: number;
    per_page: number;
}

interface EventOption {
    id: number;
    name: Record<string, string> | string;
}

interface StatusOption {
    value: string;
    label: string;
}

interface Statistics {
    total_bookings: number;
    confirmed_bookings: number;
    pending_bookings: number;
    total_revenue: {
        [key: string]: number;
    };
    default_currency: string;
}

interface Filters {
    search?: string;
    status?: string;
    event_id?: string;
    per_page?: number;
    date_from?: string;
    date_to?: string;
}

// Props
const props = defineProps<{
    bookings: PaginatedBookings;
    events: EventOption[];
    statuses: StatusOption[];
    statistics: Statistics;
    filters: Filters;
}>();

// Form
const filterForm = useForm({
    search: props.filters.search || '',
    status: props.filters.status || '',
    event_id: props.filters.event_id || '',
    date_from: props.filters.date_from || '',
    date_to: props.filters.date_to || '',
    per_page: props.bookings.per_page || 15,
});

// Methods
const searchBookings = throttle(() => {
    filterForm.get(route('admin.bookings.index'), {
        preserveState: true,
        replace: true,
    });
}, 300);

const formatDate = (dateString: string | null): string => {
    if (!dateString) return 'N/A';
    const options: Intl.DateTimeFormatOptions = { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' };
    return new Date(dateString).toLocaleDateString(currentLocale.value, options);
};

const statusClass = (status: string | null): string => {
    switch (status) {
        case 'confirmed': return 'bg-green-100 text-green-800 dark:bg-green-700 dark:text-green-200';
        case 'pending_confirmation': return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-600 dark:text-yellow-100';
        case 'cancelled': return 'bg-red-100 text-red-800 dark:bg-red-600 dark:text-red-100';
        case 'used': return 'bg-blue-100 text-blue-800 dark:bg-blue-600 dark:text-blue-100';
        case 'expired': return 'bg-gray-100 text-gray-800 dark:bg-gray-600 dark:text-gray-200';
        default: return 'bg-gray-200 text-gray-800 dark:bg-gray-500 dark:text-gray-100';
    }
};
</script>

<template>
    <Head :title="t('bookings.index_title')" />
    <AppLayout>
        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
                    <div class="p-6 lg:p-8 bg-white dark:bg-gray-800 dark:bg-gradient-to-bl dark:from-gray-700/50 dark:via-transparent border-b border-gray-200 dark:border-gray-700">
                        <PageHeader :title="t('bookings.index_title')" :subtitle="t('bookings.index_subtitle')">
                            <template #actions>
                                <Link :href="route('admin.qr-scanner.index')" class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-500 active:bg-blue-700 focus:outline-none focus:border-blue-700 focus:ring focus:ring-blue-200 disabled:opacity-25 transition">
                                    {{ t('bookings.scan_qr_button') }}
                                </Link>
                            </template>
                        </PageHeader>

                        <!-- Statistics Cards -->
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                            <Card>
                                <CardContent class="pt-6">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 bg-blue-500 p-3 rounded-full">
                                            <Ticket class="h-6 w-6 text-white" />
                                        </div>
                                        <div class="ml-4">
                                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">{{ t('bookings.stats.total_bookings') }}</p>
                                            <p class="text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ statistics.total_bookings }}</p>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                            <Card>
                                <CardContent class="pt-6">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 bg-green-500 p-3 rounded-full">
                                            <CheckCircle class="h-6 w-6 text-white" />
                                        </div>
                                        <div class="ml-4">
                                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">{{ t('bookings.stats.confirmed_bookings') }}</p>
                                            <p class="text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ statistics.confirmed_bookings }}</p>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                            <Card>
                                <CardContent class="pt-6">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 bg-yellow-500 p-3 rounded-full">
                                            <Clock class="h-6 w-6 text-white" />
                                        </div>
                                        <div class="ml-4">
                                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">{{ t('bookings.stats.pending_bookings') }}</p>
                                            <p class="text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ statistics.pending_bookings }}</p>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                            <Card>
                                <CardContent class="pt-6">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 bg-purple-500 p-3 rounded-full">
                                            <DollarSign class="h-6 w-6 text-white" />
                                        </div>
                                        <div class="ml-4">
                                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">{{ t('bookings.stats.total_revenue') }}</p>
                                            <p class="text-2xl font-semibold text-gray-900 dark:text-gray-100">
                                                {{ formatPrice(statistics.total_revenue[statistics.default_currency] ?? 0, statistics.default_currency) }}
                                            </p>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>

                        <AdminDataTable>
                            <!-- Filters Slot -->
                            <template #filters>
                                <div>
                                    <label for="search" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('fields.search') }}</label>
                                    <input type="text" v-model="filterForm.search" @input="searchBookings" id="search" :placeholder="t('bookings.search_placeholder')" class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                </div>
                                <div>
                                    <label for="status" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('fields.status') }}</label>
                                    <select v-model="filterForm.status" @change="searchBookings" id="status" class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                        <option value="">{{ t('filters.all_statuses') }}</option>
                                        <option v-for="status in statuses" :key="status.value" :value="status.value">{{ status.label }}</option>
                                    </select>
                                </div>
                                 <div>
                                    <label for="event_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('fields.event') }}</label>
                                    <select v-model="filterForm.event_id" @change="searchBookings" id="event_id" class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                        <option value="">{{ t('filters.all_events') }}</option>
                                        <option v-for="event in events" :key="event.id" :value="event.id">{{ getTranslation(event.name, currentLocale) }}</option>
                                    </select>
                                </div>
                                <div>
                                    <label for="date_from" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('fields.date_from') }}</label>
                                    <input type="date" v-model="filterForm.date_from" @change="searchBookings" id="date_from" class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                </div>
                                <div>
                                    <label for="date_to" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('fields.date_to') }}</label>
                                    <input type="date" v-model="filterForm.date_to" @change="searchBookings" id="date_to" class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                </div>
                            </template>

                            <!-- Header Slot -->
                            <template #header>
                                <TableHead>{{ t('fields.booking_number') }}</TableHead>
                                <TableHead>{{ t('fields.event') }}</TableHead>
                                <TableHead>{{ t('fields.customer') }}</TableHead>
                                <TableHead>{{ t('fields.status') }}</TableHead>
                                <TableHead>{{ t('fields.transaction') }}</TableHead>
                                <TableHead>{{ t('fields.total_price') }}</TableHead>
                                <TableHead>{{ t('fields.date') }}</TableHead>
                                <TableHead class="text-right">{{ t('fields.actions') }}</TableHead>
                            </template>

                            <!-- Body Slot -->
                            <template #body>
                                <TableRow v-if="bookings.data && bookings.data.length === 0">
                                    <TableCell colspan="7" class="text-center">{{ t('bookings.no_bookings_found') }}</TableCell>
                                </TableRow>
                                <TableRow v-for="booking in bookings.data" :key="booking.id">
                                    <TableCell class="font-medium text-gray-900 dark:text-white">
                                         <Link :href="route('admin.bookings.show', booking.id)" class="hover:underline">{{ booking.booking_number }}</Link>
                                    </TableCell>
                                    <TableCell>{{ booking.event_name }}</TableCell>
                                    <TableCell>
                                        <div>{{ booking.user_name }}</div>
                                        <div class="text-sm text-gray-500">{{ booking.user_email }}</div>
                                    </TableCell>
                                    <TableCell>
                                        <span :class="statusClass(booking.status_value)" class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full">
                                            {{ booking.status }}
                                        </span>
                                    </TableCell>
                                    <TableCell>
                                        <div v-if="booking.transaction">
                                            <div class="font-medium">{{ booking.transaction.id }}</div>
                                            <div class="text-sm text-gray-500">{{ booking.transaction.payment_gateway }}</div>
                                        </div>
                                        <div v-else>N/A</div>
                                    </TableCell>
                                    <TableCell>{{ booking.total_price_formatted }}</TableCell>
                                    <TableCell>{{ formatDate(booking.created_at) }}</TableCell>
                                    <TableCell class="text-right">
                                        <Link :href="route('admin.bookings.show', booking.id)" class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-600 mr-3">{{ t('actions.view') }}</Link>
                                    </TableCell>
                                </TableRow>
                            </template>
                        </AdminDataTable>

                        <!-- Pagination -->
                        <AdminPagination :links="bookings.links" :from="bookings.from" :to="bookings.to" :total="bookings.total" />
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
