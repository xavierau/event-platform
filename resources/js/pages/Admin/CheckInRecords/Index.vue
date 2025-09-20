<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { computed, ref, watch } from 'vue';
import { debounce } from 'lodash-es';

// Types
interface MemberCheckInRecord {
    id: number;
    scanned_at: string;
    location?: string;
    notes?: string;
    device_identifier?: string;
    member: {
        id: number;
        name: string;
        email: string;
    };
    scanner: {
        id: number;
        name: string;
        email: string;
    };
    membership_data: {
        userId: number;
        userName: string;
        email: string;
        membershipLevel: string;
        membershipStatus?: string;
        expiresAt?: string;
        timestamp: string;
    };
    event?: {
        id: number;
        name: string | object;
        organizer_id: number;
    };
    event_occurrence?: {
        id: number;
        name: string | object;
        start_at: string;
        end_at: string;
        venue_name?: string;
    };
}

interface PaginatedRecords {
    data: MemberCheckInRecord[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from?: number;
    to?: number;
}

interface Stats {
    total: number;
    unique_members: number;
    today: number;
    filtered_results: number;
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
        scanner_id?: string;
        location?: string;
        start_date?: string;
        end_date?: string;
        organization_id?: string;
        event_id?: string;
    };
    availableEvents: Array<{ id: number; name: any; organizer_id: number }>;
    availableOrganizers: Array<{ id: number; name: any; slug: string }>;
    availableScanners: Array<{ id: number; name: string; email: string }>;
    availableLocations: Array<{ value: string; label: string }>;
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
const scannerFilter = ref(props.filters.scanner_id || '');
const locationFilter = ref(props.filters.location || '');
const startDateFilter = ref(props.filters.start_date || '');
const endDateFilter = ref(props.filters.end_date || '');
const organizationFilter = ref(props.filters.organization_id || '');
const eventFilter = ref(props.filters.event_id || '');
const exportLoading = ref(false);

// Computed
const activeFiltersCount = computed(() => {
    let count = 0;
    if (searchInput.value) count++;
    if (scannerFilter.value) count++;
    if (locationFilter.value) count++;
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

const getMembershipLevelBadgeClass = (level: string) => {
    switch (level.toLowerCase()) {
        case 'premium':
            return 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-300';
        case 'gold':
            return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300';
        case 'silver':
            return 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-300';
        case 'bronze':
            return 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-300';
        case 'basic':
        default:
            return 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300';
    }
};

const getMembershipStatusBadgeClass = (status?: string) => {
    if (!status) return 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-300';
    switch (status.toLowerCase()) {
        case 'active':
            return 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300';
        case 'expired':
            return 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300';
        case 'suspended':
            return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300';
        default:
            return 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-300';
    }
};

const isExpiringSoon = (expiresAt?: string) => {
    if (!expiresAt) return false;
    const expireDate = new Date(expiresAt);
    const now = new Date();
    const thirtyDaysFromNow = new Date(now.getTime() + (30 * 24 * 60 * 60 * 1000));
    return expireDate <= thirtyDaysFromNow && expireDate > now;
};

// Debounced search function
const debouncedSearch = debounce(() => {
    applyFilters();
}, 300);

// Filter functions
const applyFilters = () => {
    const filters: any = {
        search: searchInput.value || undefined,
        scanner_id: scannerFilter.value || undefined,
        location: locationFilter.value || undefined,
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
    scannerFilter.value = '';
    locationFilter.value = '';
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
        scanner_id: scannerFilter.value || undefined,
        location: locationFilter.value || undefined,
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
watch([scannerFilter, locationFilter, startDateFilter, endDateFilter, organizationFilter, eventFilter], applyFilters);
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
                        <div class="flex items-center">
                            <div class="flex-1">
                                <div class="text-2xl font-bold text-gray-900 dark:text-white">
                                    {{ stats.total.toLocaleString() }}
                                </div>
                                <div class="text-sm text-gray-600 dark:text-gray-400">Total Member Scans</div>
                            </div>
                            <div class="text-2xl text-indigo-500">📊</div>
                        </div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
                        <div class="flex items-center">
                            <div class="flex-1">
                                <div class="text-2xl font-bold text-green-600 dark:text-green-400">
                                    {{ stats.unique_members.toLocaleString() }}
                                </div>
                                <div class="text-sm text-gray-600 dark:text-gray-400">Unique Members Scanned</div>
                            </div>
                            <div class="text-2xl text-green-500">👥</div>
                        </div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
                        <div class="flex items-center">
                            <div class="flex-1">
                                <div class="text-2xl font-bold text-blue-600 dark:text-blue-400">
                                    {{ stats.today.toLocaleString() }}
                                </div>
                                <div class="text-sm text-gray-600 dark:text-gray-400">Today's Scans</div>
                            </div>
                            <div class="text-2xl text-blue-500">📱</div>
                        </div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
                        <div class="flex items-center">
                            <div class="flex-1">
                                <div class="text-2xl font-bold text-indigo-600 dark:text-indigo-400">
                                    {{ records.total.toLocaleString() }}
                                </div>
                                <div class="text-sm text-gray-600 dark:text-gray-400">Filtered Results</div>
                            </div>
                            <div class="text-2xl text-indigo-500">🔍</div>
                        </div>
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
                                <label for="search" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Search Members</label>
                                <input
                                    id="search"
                                    v-model="searchInput"
                                    type="text"
                                    placeholder="Member name, email..."
                                    class="block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                />
                            </div>

                            <!-- Scanner Filter -->
                            <div>
                                <label for="scanner" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Scanner</label>
                                <select
                                    id="scanner"
                                    v-model="scannerFilter"
                                    class="block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                >
                                    <option value="">All Scanners</option>
                                    <option v-for="scanner in availableScanners" :key="scanner.id" :value="scanner.id">
                                        {{ scanner.name }}
                                    </option>
                                </select>
                            </div>

                            <!-- Location Filter -->
                            <div>
                                <label for="location" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Location</label>
                                <select
                                    id="location"
                                    v-model="locationFilter"
                                    class="block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                >
                                    <option value="">All Locations</option>
                                    <option v-for="location in availableLocations" :key="location.value" :value="location.value">
                                        {{ location.label }}
                                    </option>
                                </select>
                            </div>

                            <!-- Event Filter -->
                            <div>
                                <label for="event" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Event Context</label>
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
                                {{ exportLoading ? 'Exporting...' : `Export Member Scans CSV (${records.total})` }}
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
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Scan Time</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Member</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Membership</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Scanner</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Location</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Event Context</th>
                                    <th v-if="user.is_platform_admin" scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Organization</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Notes</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                <tr v-if="!hasRecords">
                                    <td :colspan="user.is_platform_admin ? 8 : 7" class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400 text-center">
                                        <div class="py-8">
                                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                                <path vector-effect="non-scaling-stroke" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z" />
                                            </svg>
                                            <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No member scan records found</h3>
                                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                                {{ activeFiltersCount > 0 ? 'Try adjusting your filters to see more results.' : 'Member scan records will appear here once members start scanning their QR codes.' }}
                                            </p>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-for="record in records.data" :key="record.id" class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                    <!-- Scan Time -->
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                        <div class="flex items-center">
                                            <span class="text-lg mr-2">📱</span>
                                            <div>
                                                <div class="font-medium">{{ formatDateTime(record.scanned_at) }}</div>
                                                <div v-if="record.device_identifier" class="text-xs text-gray-500 dark:text-gray-400">
                                                    {{ record.device_identifier }}
                                                </div>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Member -->
                                    <td class="px-6 py-4">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0 h-10 w-10">
                                                <div class="h-10 w-10 rounded-full bg-gray-300 dark:bg-gray-600 flex items-center justify-center">
                                                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
                                                        {{ record.member.name.charAt(0).toUpperCase() }}
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="ml-4">
                                                <div class="text-sm font-medium text-gray-900 dark:text-white">
                                                    {{ record.member.name }}
                                                </div>
                                                <div class="text-sm text-gray-500 dark:text-gray-400">
                                                    {{ record.member.email }}
                                                </div>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Membership -->
                                    <td class="px-6 py-4">
                                        <div class="space-y-1">
                                            <div class="flex items-center space-x-2">
                                                <span :class="getMembershipLevelBadgeClass(record.membership_data.membershipLevel)" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium">
                                                    {{ record.membership_data.membershipLevel }}
                                                </span>
                                                <span v-if="record.membership_data.membershipStatus" :class="getMembershipStatusBadgeClass(record.membership_data.membershipStatus)" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium">
                                                    {{ record.membership_data.membershipStatus }}
                                                </span>
                                            </div>
                                            <div v-if="record.membership_data.expiresAt" class="text-xs text-gray-500 dark:text-gray-400">
                                                <span :class="isExpiringSoon(record.membership_data.expiresAt) ? 'text-orange-600 dark:text-orange-400 font-medium' : ''">
                                                    Expires: {{ formatDate(record.membership_data.expiresAt) }}
                                                </span>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Scanner -->
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900 dark:text-white">
                                            {{ record.scanner.name }}
                                        </div>
                                        <div class="text-sm text-gray-500 dark:text-gray-400">
                                            {{ record.scanner.email }}
                                        </div>
                                    </td>

                                    <!-- Location -->
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div v-if="record.location" class="flex items-center">
                                            <span class="text-lg mr-2">📍</span>
                                            <span class="text-sm text-gray-900 dark:text-white">
                                                {{ record.location }}
                                            </span>
                                        </div>
                                        <div v-else class="text-sm text-gray-500 dark:text-gray-400">
                                            Not specified
                                        </div>
                                    </td>

                                    <!-- Event Context -->
                                    <td class="px-6 py-4">
                                        <div v-if="record.event">
                                            <div class="text-sm font-medium text-gray-900 dark:text-white max-w-xs truncate" :title="getTranslation(record.event.name)">
                                                {{ getTranslation(record.event.name) }}
                                            </div>
                                            <div v-if="record.event_occurrence" class="text-sm text-gray-500 dark:text-gray-400 max-w-xs truncate" :title="getTranslation(record.event_occurrence.name)">
                                                {{ getTranslation(record.event_occurrence.name) }}
                                            </div>
                                            <div v-if="record.event_occurrence" class="text-xs text-gray-400">
                                                {{ formatDate(record.event_occurrence.start_at) }}
                                            </div>
                                        </div>
                                        <div v-else class="text-sm text-gray-500 dark:text-gray-400">
                                            General access
                                        </div>
                                    </td>

                                    <!-- Organization (Platform Admin Only) -->
                                    <td v-if="user.is_platform_admin" class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                        <div v-if="record.event" class="text-gray-500 dark:text-gray-400">
                                            Org ID: {{ record.event.organizer_id }}
                                        </div>
                                        <div v-else class="text-gray-500 dark:text-gray-400">
                                            N/A
                                        </div>
                                    </td>

                                    <!-- Notes -->
                                    <td class="px-6 py-4">
                                        <div v-if="record.notes" class="text-sm text-gray-900 dark:text-white max-w-xs truncate" :title="record.notes">
                                            {{ record.notes }}
                                        </div>
                                        <div v-else class="text-sm text-gray-500 dark:text-gray-400">
                                            —
                                        </div>
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