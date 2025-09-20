<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { computed, ref, watch } from 'vue';
import { debounce } from 'lodash-es';

// Types
interface CheckInRecord {
    id: number;
    check_in_timestamp: string;
    status: string;
    method: string;
    location_description?: string;
    notes?: string;
    booking: {
        id: number;
        booking_number: string;
        status: string;
        quantity: number;
        user: {
            id: number;
            name: string;
            email: string;
        };
    };
    event: {
        id: number;
        name: any;
        organizer_id: number;
    };
    event_occurrence: {
        id: number;
        name: any;
        start_at: string;
        end_at: string;
        venue_name?: string;
    };
    operator?: {
        id: number;
        name: string;
        email: string;
    };
    organizer: {
        id: number;
        name: any;
        slug: string;
    };
}

interface PaginatedRecords {
    data: CheckInRecord[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from?: number;
    to?: number;
}

interface Stats {
    total: number;
    successful: number;
    failed: number;
    today: number;
    success_rate: number;
}

interface FilterOption {
    value: string;
    label: string;
}

interface Props {
    records: PaginatedRecords;
    stats: Stats;
    filters: {
        search?: string;
        status?: string;
        method?: string;
        start_date?: string;
        end_date?: string;
        organization_id?: string;
        event_id?: string;
    };
    availableEvents: Array<{ id: number; name: any; organizer_id: number }>;
    availableOrganizers: Array<{ id: number; name: any; slug: string }>;
    statusOptions: FilterOption[];
    methodOptions: FilterOption[];
    user: {
        id: number;
        name: string;
        email: string;
        roles: string[];
        is_platform_admin: boolean;
    };
    pageTitle: string;
    breadcrumbs: Array<{ text: string; href?: string }>;
}

const props = defineProps<Props>();

// Reactive state
const filtersExpanded = ref(false);
const searchInput = ref(props.filters.search || '');
const statusFilter = ref(props.filters.status || '');
const methodFilter = ref(props.filters.method || '');
const startDateFilter = ref(props.filters.start_date || '');
const endDateFilter = ref(props.filters.end_date || '');
const organizationFilter = ref(props.filters.organization_id || '');
const eventFilter = ref(props.filters.event_id || '');
const exportLoading = ref(false);

// Computed
const activeFiltersCount = computed(() => {
    let count = 0;
    if (searchInput.value) count++;
    if (statusFilter.value) count++;
    if (methodFilter.value) count++;
    if (startDateFilter.value) count++;
    if (endDateFilter.value) count++;
    if (organizationFilter.value && props.user.is_platform_admin) count++;
    if (eventFilter.value) count++;
    return count;
});

const hasRecords = computed(() => props.records.data.length > 0);

// Helper functions
const getTranslation = (translations: any, locale: string = 'en', fallback: string = 'en') => {
    if (typeof translations === 'string') return translations;
    if (!translations) return '';
    return translations[locale] || translations[fallback] || Object.values(translations)[0] || '';
};

const formatDateTime = (dateString: string) => {
    try {
        return new Date(dateString).toLocaleString(undefined, {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    } catch {
        return dateString;
    }
};

const formatDate = (dateString: string) => {
    try {
        return new Date(dateString).toLocaleDateString(undefined, {
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        });
    } catch {
        return dateString;
    }
};

const getStatusIcon = (status: string) => {
    switch (status) {
        case 'SUCCESSFUL':
            return '✓';
        case 'FAILED_INVALID_CODE':
        case 'FAILED_ALREADY_USED':
        case 'FAILED_EXPIRED':
        case 'FAILED_NOT_STARTED':
        case 'FAILED_CANCELLED':
        default:
            return '✗';
    }
};

const getStatusClass = (status: string) => {
    switch (status) {
        case 'SUCCESSFUL':
            return 'text-green-600 dark:text-green-400';
        default:
            return 'text-red-600 dark:text-red-400';
    }
};

const getMethodIcon = (method: string) => {
    switch (method) {
        case 'QR_SCAN':
            return '📱';
        case 'MANUAL_ENTRY':
            return '✋';
        case 'API_INTEGRATION':
            return '🔗';
        default:
            return '❓';
    }
};

// Debounced search function
const debouncedSearch = debounce(() => {
    applyFilters();
}, 300);

// Filter functions
const applyFilters = () => {
    const filters: any = {
        search: searchInput.value || undefined,
        status: statusFilter.value || undefined,
        method: methodFilter.value || undefined,
        start_date: startDateFilter.value || undefined,
        end_date: endDateFilter.value || undefined,
        event_id: eventFilter.value || undefined,
    };

    if (props.user.is_platform_admin && organizationFilter.value) {
        filters.organization_id = organizationFilter.value;
    }

    // Remove undefined values
    Object.keys(filters).forEach(key => {
        if (filters[key] === undefined) {
            delete filters[key];
        }
    });

    router.get(route('admin.check-in-records.index'), filters, {
        preserveState: true,
        preserveScroll: true,
    });
};

const clearAllFilters = () => {
    searchInput.value = '';
    statusFilter.value = '';
    methodFilter.value = '';
    startDateFilter.value = '';
    endDateFilter.value = '';
    organizationFilter.value = '';
    eventFilter.value = '';
    applyFilters();
};

const exportToCsv = () => {
    exportLoading.value = true;

    const filters: any = {
        search: searchInput.value || undefined,
        status: statusFilter.value || undefined,
        method: methodFilter.value || undefined,
        start_date: startDateFilter.value || undefined,
        end_date: endDateFilter.value || undefined,
        event_id: eventFilter.value || undefined,
    };

    if (props.user.is_platform_admin && organizationFilter.value) {
        filters.organization_id = organizationFilter.value;
    }

    // Remove undefined values
    Object.keys(filters).forEach(key => {
        if (filters[key] === undefined) {
            delete filters[key];
        }
    });

    const params = new URLSearchParams(filters).toString();
    const url = route('admin.check-in-records.export') + (params ? `?${params}` : '');

    // Create a temporary link and click it to download
    const link = document.createElement('a');
    link.href = url;
    link.style.display = 'none';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);

    exportLoading.value = false;
};

// Watchers
watch(searchInput, debouncedSearch);
watch([statusFilter, methodFilter, startDateFilter, endDateFilter, organizationFilter, eventFilter], applyFilters);
</script>

<template>
    <Head :title="pageTitle" />

    <AppLayout>
        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Breadcrumbs -->
                <nav class="mb-6 text-sm" aria-label="Breadcrumb">
                    <ol class="list-none p-0 inline-flex">
                        <li v-for="(item, index) in breadcrumbs" :key="index" class="flex items-center">
                            <a v-if="item.href" :href="item.href" class="text-indigo-600 hover:text-indigo-500 dark:text-indigo-400">
                                {{ item.text }}
                            </a>
                            <span v-else class="text-gray-500 dark:text-gray-400">{{ item.text }}</span>
                            <svg v-if="index < breadcrumbs.length - 1" class="flex-shrink-0 mx-2 h-4 w-4 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M5.555 17.776l8-16 .894.448-8 16-.894-.448z"/>
                            </svg>
                        </li>
                    </ol>
                </nav>

                <!-- Statistics Dashboard -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                    <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
                        <div class="text-2xl font-bold text-gray-900 dark:text-white">
                            {{ stats.total.toLocaleString() }}
                        </div>
                        <div class="text-sm text-gray-600 dark:text-gray-400">Total Records</div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
                        <div class="text-2xl font-bold text-green-600 dark:text-green-400">
                            {{ stats.success_rate }}%
                        </div>
                        <div class="text-sm text-gray-600 dark:text-gray-400">Success Rate</div>
                        <div class="text-xs text-gray-500 dark:text-gray-500 mt-1">
                            {{ stats.successful }} successful, {{ stats.failed }} failed
                        </div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
                        <div class="text-2xl font-bold text-blue-600 dark:text-blue-400">
                            {{ stats.today.toLocaleString() }}
                        </div>
                        <div class="text-sm text-gray-600 dark:text-gray-400">Today's Check-ins</div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
                        <div class="text-2xl font-bold text-indigo-600 dark:text-indigo-400">
                            {{ records.total.toLocaleString() }}
                        </div>
                        <div class="text-sm text-gray-600 dark:text-gray-400">Filtered Results</div>
                    </div>
                </div>

                <!-- Filters Panel -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-6">
                    <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                        <button
                            @click="filtersExpanded = !filtersExpanded"
                            class="flex items-center justify-between w-full text-left"
                        >
                            <span class="text-sm font-medium text-gray-900 dark:text-white flex items-center">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.707A1 1 0 013 7V4z"></path>
                                </svg>
                                Filters
                                <span v-if="activeFiltersCount > 0" class="ml-2 inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-300">
                                    {{ activeFiltersCount }}
                                </span>
                            </span>
                            <svg :class="filtersExpanded ? 'rotate-180' : ''" class="w-4 h-4 transform transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </button>
                    </div>

                    <div v-show="filtersExpanded" class="p-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 mb-4">
                            <!-- Search -->
                            <div>
                                <label for="search" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Search</label>
                                <input
                                    id="search"
                                    v-model="searchInput"
                                    type="text"
                                    placeholder="Attendee, email, booking, event..."
                                    class="block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                />
                            </div>

                            <!-- Status Filter -->
                            <div>
                                <label for="status" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Status</label>
                                <select
                                    id="status"
                                    v-model="statusFilter"
                                    class="block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                >
                                    <option v-for="option in statusOptions" :key="option.value" :value="option.value">
                                        {{ option.label }}
                                    </option>
                                </select>
                            </div>

                            <!-- Method Filter -->
                            <div>
                                <label for="method" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Method</label>
                                <select
                                    id="method"
                                    v-model="methodFilter"
                                    class="block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                >
                                    <option v-for="option in methodOptions" :key="option.value" :value="option.value">
                                        {{ option.label }}
                                    </option>
                                </select>
                            </div>

                            <!-- Event Filter -->
                            <div>
                                <label for="event" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Event</label>
                                <select
                                    id="event"
                                    v-model="eventFilter"
                                    class="block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                >
                                    <option value="">All Events</option>
                                    <option v-for="event in availableEvents" :key="event.id" :value="event.id">
                                        {{ getTranslation(event.name) }}
                                    </option>
                                </select>
                            </div>

                            <!-- Start Date -->
                            <div>
                                <label for="start_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Start Date</label>
                                <input
                                    id="start_date"
                                    v-model="startDateFilter"
                                    type="date"
                                    class="block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                />
                            </div>

                            <!-- End Date -->
                            <div>
                                <label for="end_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">End Date</label>
                                <input
                                    id="end_date"
                                    v-model="endDateFilter"
                                    type="date"
                                    class="block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                />
                            </div>

                            <!-- Organization Filter (Platform Admin Only) -->
                            <div v-if="user.is_platform_admin">
                                <label for="organization" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Organization</label>
                                <select
                                    id="organization"
                                    v-model="organizationFilter"
                                    class="block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                >
                                    <option value="">All Organizations</option>
                                    <option v-for="org in availableOrganizers" :key="org.id" :value="org.id">
                                        {{ getTranslation(org.name) }}
                                    </option>
                                </select>
                            </div>
                        </div>

                        <!-- Filter Actions -->
                        <div class="flex items-center justify-between pt-4 border-t border-gray-200 dark:border-gray-700">
                            <button
                                @click="clearAllFilters"
                                :disabled="activeFiltersCount === 0"
                                class="text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                Clear all filters
                            </button>

                            <button
                                @click="exportToCsv"
                                :disabled="exportLoading || !hasRecords"
                                class="inline-flex items-center px-4 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-500 active:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed transition ease-in-out duration-150"
                            >
                                <svg v-if="exportLoading" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <svg v-else class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                                {{ exportLoading ? 'Exporting...' : `Export CSV (${records.total})` }}
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Records Table -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Check-in Time</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Attendee</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Event</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Booking</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Method</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Operator</th>
                                    <th v-if="user.is_platform_admin" scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Organization</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                <tr v-if="!hasRecords">
                                    <td :colspan="user.is_platform_admin ? 8 : 7" class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400 text-center">
                                        <div class="py-8">
                                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                                <path vector-effect="non-scaling-stroke" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                            </svg>
                                            <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No check-in records found</h3>
                                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                                {{ activeFiltersCount > 0 ? 'Try adjusting your filters to see more results.' : 'Check-in records will appear here once events start.' }}
                                            </p>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-for="record in records.data" :key="record.id" class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                    <!-- Status -->
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <span :class="getStatusClass(record.status)" class="text-lg mr-2">
                                                {{ getStatusIcon(record.status) }}
                                            </span>
                                            <span :class="record.status === 'SUCCESSFUL' ? 'text-green-800 bg-green-100 dark:bg-green-900 dark:text-green-300' : 'text-red-800 bg-red-100 dark:bg-red-900 dark:text-red-300'" class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full">
                                                {{ record.status === 'SUCCESSFUL' ? 'Success' : 'Failed' }}
                                            </span>
                                        </div>
                                    </td>

                                    <!-- Check-in Time -->
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                        {{ formatDateTime(record.check_in_timestamp) }}
                                    </td>

                                    <!-- Attendee -->
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900 dark:text-white">
                                            {{ record.booking.user.name }}
                                        </div>
                                        <div class="text-sm text-gray-500 dark:text-gray-400">
                                            {{ record.booking.user.email }}
                                        </div>
                                    </td>

                                    <!-- Event -->
                                    <td class="px-6 py-4">
                                        <div class="text-sm font-medium text-gray-900 dark:text-white max-w-xs truncate" :title="getTranslation(record.event.name)">
                                            {{ getTranslation(record.event.name) }}
                                        </div>
                                        <div class="text-sm text-gray-500 dark:text-gray-400 max-w-xs truncate" :title="getTranslation(record.event_occurrence.name)">
                                            {{ getTranslation(record.event_occurrence.name) }}
                                        </div>
                                        <div class="text-xs text-gray-400">
                                            {{ formatDate(record.event_occurrence.start_at) }}
                                        </div>
                                    </td>

                                    <!-- Booking -->
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900 dark:text-white">
                                            {{ record.booking.booking_number }}
                                        </div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">
                                            Qty: {{ record.booking.quantity }}
                                        </div>
                                    </td>

                                    <!-- Method -->
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <span class="text-lg mr-2">{{ getMethodIcon(record.method) }}</span>
                                            <span class="text-sm text-gray-900 dark:text-white">
                                                {{ record.method.replace('_', ' ') }}
                                            </span>
                                        </div>
                                    </td>

                                    <!-- Operator -->
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                        {{ record.operator?.name || 'System' }}
                                    </td>

                                    <!-- Organization (Platform Admin Only) -->
                                    <td v-if="user.is_platform_admin" class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                        {{ getTranslation(record.organizer.name) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div v-if="hasRecords" class="bg-white dark:bg-gray-800 px-4 py-3 flex items-center justify-between border-t border-gray-200 dark:border-gray-700 sm:px-6">
                        <div class="flex-1 flex justify-between sm:hidden">
                            <a
                                v-if="records.current_page > 1"
                                :href="route('admin.check-in-records.index', { ...filters, page: records.current_page - 1 })"
                                class="relative inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600"
                            >
                                Previous
                            </a>
                            <a
                                v-if="records.current_page < records.last_page"
                                :href="route('admin.check-in-records.index', { ...filters, page: records.current_page + 1 })"
                                class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600"
                            >
                                Next
                            </a>
                        </div>
                        <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                            <div>
                                <p class="text-sm text-gray-700 dark:text-gray-300">
                                    Showing
                                    <span class="font-medium">{{ records.from || 0 }}</span>
                                    to
                                    <span class="font-medium">{{ records.to || 0 }}</span>
                                    of
                                    <span class="font-medium">{{ records.total }}</span>
                                    results
                                </p>
                            </div>
                            <div>
                                <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                                    <a
                                        v-if="records.current_page > 1"
                                        :href="route('admin.check-in-records.index', { ...filters, page: records.current_page - 1 })"
                                        class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm font-medium text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-600"
                                    >
                                        <span class="sr-only">Previous</span>
                                        <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                            <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
                                        </svg>
                                    </a>

                                    <template v-for="page in Math.min(records.last_page, 10)" :key="page">
                                        <a
                                            v-if="page === records.current_page"
                                            href="#"
                                            aria-current="page"
                                            class="relative inline-flex items-center px-4 py-2 border border-indigo-500 bg-indigo-50 dark:bg-indigo-900 text-sm font-medium text-indigo-600 dark:text-indigo-300"
                                        >
                                            {{ page }}
                                        </a>
                                        <a
                                            v-else
                                            :href="route('admin.check-in-records.index', { ...filters, page })"
                                            class="relative inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600"
                                        >
                                            {{ page }}
                                        </a>
                                    </template>

                                    <a
                                        v-if="records.current_page < records.last_page"
                                        :href="route('admin.check-in-records.index', { ...filters, page: records.current_page + 1 })"
                                        class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm font-medium text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-600"
                                    >
                                        <span class="sr-only">Next</span>
                                        <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                            <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                                        </svg>
                                    </a>
                                </nav>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>