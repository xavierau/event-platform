<template>
    <Head title="Coupons" />
    <AppLayout>
        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
                    <div class="p-6 lg:p-8 bg-white dark:bg-gray-800 dark:bg-gradient-to-bl dark:from-gray-700/50 dark:via-transparent border-b border-gray-200 dark:border-gray-700">
                        <PageHeader title="Coupon Management" subtitle="Manage coupon templates and campaigns">
                            <template #actions>
                                <div class="flex gap-2">
                                    <Link :href="route('admin.coupons.scanner')" class="inline-flex items-center px-4 py-2 bg-purple-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-purple-500 active:bg-purple-700 focus:outline-none focus:border-purple-700 focus:ring focus:ring-purple-200 disabled:opacity-25 transition">
                                        <Percent class="w-4 h-4 mr-2" />
                                        Coupon Scanner
                                    </Link>
                                    <Link :href="route('admin.coupons.create')" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-500 active:bg-indigo-700 focus:outline-none focus:border-indigo-700 focus:ring focus:ring-indigo-200 disabled:opacity-25 transition">
                                        Create New Coupon
                                    </Link>
                                </div>
                            </template>
                        </PageHeader>

                        <AdminDataTable>
                            <!-- Filters Slot -->
                            <template #filters>
                                <div>
                                    <label for="search_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Search by Name or Code</label>
                                    <input type="text" v-model="filterForm.search" @input="searchCoupons" id="search_name" placeholder="Search coupons..." class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                </div>
                                <div>
                                    <label for="organizer_filter" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Organizer</label>
                                    <select v-model="filterForm.organizer_id" @change="searchCoupons" id="organizer_filter" class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                        <option value="">All Organizers</option>
                                        <option v-for="organizer in organizers" :key="organizer.id" :value="organizer.id">{{ getTranslation(organizer.name, currentLocale) }}</option>
                                    </select>
                                </div>
                                <div>
                                    <label for="type_filter" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Type</label>
                                    <select v-model="filterForm.type" @change="searchCoupons" id="type_filter" class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                        <option value="">All Types</option>
                                        <option value="single_use">Single Use</option>
                                        <option value="multi_use">Multi Use</option>
                                    </select>
                                </div>
<!--                                <div>-->
<!--                                    <label for="status_filter" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Status</label>-->
<!--                                    <select v-model="filterForm.status" @change="searchCoupons" id="status_filter" class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">-->
<!--                                        <option value="">All Status</option>-->
<!--                                        <option value="active">Active</option>-->
<!--                                        <option value="expired">Expired</option>-->
<!--                                        <option value="upcoming">Upcoming</option>-->
<!--                                    </select>-->
<!--                                </div>-->
                            </template>

                            <!-- Header Slot -->
                            <template #header>
                                <TableHead>Code</TableHead>
                                <TableHead>Name</TableHead>
                                <TableHead>Organizer</TableHead>
                                <TableHead>Type</TableHead>
                                <TableHead>Discount</TableHead>
                                <TableHead>Valid Period</TableHead>
                                <TableHead>Usage</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead class="text-right">Actions</TableHead>
                            </template>

                            <!-- Body Slot -->
                            <template #body>
                                <TableRow v-if="coupons.data && coupons.data.length === 0">
                                    <TableCell colspan="9" class="text-center">No coupons found</TableCell>
                                </TableRow>
                                <TableRow v-for="coupon in coupons.data" :key="coupon.id">
                                    <TableCell class="font-mono text-sm font-semibold text-indigo-600 dark:text-indigo-400">{{ coupon.code }}</TableCell>
                                    <TableCell class="font-medium text-gray-900 dark:text-white">{{ coupon.name }}</TableCell>
                                    <TableCell>{{ coupon.organizer ? getTranslation(coupon.organizer.name, currentLocale) : 'N/A' }}</TableCell>
                                    <TableCell>
                                        <span :class="typeClass(coupon.type)" class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full">
                                            {{ formatType(coupon.type) }}
                                        </span>
                                    </TableCell>
                                    <TableCell>{{ formatDiscount(coupon.discount_value, coupon.discount_type) }}</TableCell>
                                    <TableCell class="text-sm">
                                        <div v-if="coupon.valid_from || coupon.expires_at">
                                            <div v-if="coupon.valid_from" class="text-gray-600 dark:text-gray-400">From: {{ formatDate(coupon.valid_from) }}</div>
                                            <div v-if="coupon.expires_at" class="text-gray-600 dark:text-gray-400">Until: {{ formatDate(coupon.expires_at) }}</div>
                                        </div>
                                        <div v-else class="text-gray-500">No restrictions</div>
                                    </TableCell>
                                    <TableCell>
                                        <div class="text-sm">
                                            <div class="text-gray-900 dark:text-white">{{ getCouponUsage(coupon) }}</div>
                                            <div v-if="coupon.max_issuance" class="text-gray-500">of {{ coupon.max_issuance }} max</div>
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        <span :class="statusClass(getStatusText(coupon))" class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full">
                                            {{ getStatusText(coupon) }}
                                        </span>
                                    </TableCell>
                                    <TableCell class="text-right">
                                        <Link :href="route('admin.coupons.show', coupon.id)" class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-600 mr-3">View</Link>
                                        <Link :href="route('admin.coupons.edit', coupon.id)" class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-600 mr-3">Edit</Link>
                                        <button @click="confirmDeleteCoupon(coupon.id, coupon.name)" class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-600">Delete</button>
                                    </TableCell>
                                </TableRow>
                            </template>
                        </AdminDataTable>

                        <!-- Pagination -->
                        <AdminPagination :links="coupons.links" :from="coupons.from" :to="coupons.to" :total="coupons.total" />
                    </div>
                </div>
            </div>
        </div>

        <!-- Delete Confirmation Modal -->
        <Dialog :open="showConfirmDeleteModal" @update:open="showConfirmDeleteModal = $event">
            <DialogContent class="sm:max-w-[425px]">
                <DialogHeader>
                    <DialogTitle>Delete Coupon</DialogTitle>
                </DialogHeader>
                <p class="py-4 text-sm text-gray-600 dark:text-gray-400">
                    Are you sure you want to delete the coupon "{{ couponToDelete?.name }}"? This action cannot be undone.
                </p>
                <DialogFooter>
                    <Button variant="outline" @click="closeDeleteModal">Cancel</Button>
                    <Button variant="destructive" @click="deleteCoupon" class="ml-3">Delete Coupon</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

    </AppLayout>
</template>

<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, useForm, router, usePage } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogFooter
} from '@/components/ui/dialog';
import Button from '@/components/ui/button/Button.vue';
import { getTranslation } from '@/Utils/i18n';
import { throttle } from 'lodash';
import { Percent } from 'lucide-vue-next';
import PageHeader from '@/components/Shared/PageHeader.vue';
import AdminPagination from '@/components/Shared/AdminPagination.vue';
import { TableHead, TableRow, TableCell } from '@/components/ui/table';
import AdminDataTable from '@/components/Shared/AdminDataTable.vue';

const page = usePage();
const currentLocale = computed(() => page.props.locale as 'en' | 'zh-HK' | 'zh-CN');

interface Organizer {
    id: number;
    name: Record<string, string> | string;
}

interface Coupon {
    id: number;
    name: string;
    code: string;
    type: 'single_use' | 'multi_use';
    discount_value: number;
    discount_type: 'fixed' | 'percentage';
    max_issuance: number | null;
    valid_from: string | null;
    expires_at: string | null;
    created_at: string;
    organizer?: Organizer;
    user_coupons_count?: number;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginatedCoupons {
    data: Coupon[];
    links: PaginationLink[];
    from: number;
    to: number;
    total: number;
    per_page: number;
}

interface Filters {
    search?: string;
    organizer_id?: string;
    type?: string;
    // status?: string;
}

const props = defineProps<{
    coupons: PaginatedCoupons;
    organizers: Organizer[];
    filters?: Filters;
}>();

const filterForm = useForm({
    search: props.filters?.search || '',
    organizer_id: props.filters?.organizer_id || '',
    type: props.filters?.type || '',
    // status: props.filters?.status || '',
    per_page: props.coupons.per_page || 15,
});

const showConfirmDeleteModal = ref(false);
const couponToDelete = ref<{ id: number; name: string } | null>(null);

const searchCoupons = throttle(() => {
    filterForm.get(route('admin.coupons.index'), {
        preserveState: true,
        replace: true,
    });
}, 300);

const confirmDeleteCoupon = (id: number, name: string) => {
    couponToDelete.value = { id, name };
    showConfirmDeleteModal.value = true;
};

const closeDeleteModal = () => {
    showConfirmDeleteModal.value = false;
    couponToDelete.value = null;
};

const deleteCoupon = () => {
    if (couponToDelete.value) {
        router.delete(route('admin.coupons.destroy', couponToDelete.value.id), {
            onSuccess: () => closeDeleteModal(),
            preserveState: false,
        });
    }
};

const formatDate = (dateString: string | null): string => {
    if (!dateString) return 'N/A';
    const options: Intl.DateTimeFormatOptions = { year: 'numeric', month: 'short', day: 'numeric' };
    return new Date(dateString).toLocaleDateString(currentLocale.value, options);
};

const formatType = (type: string): string => {
    return type === 'single_use' ? 'Single Use' : 'Multi Use';
};

const formatDiscount = (value: number, type: string): string => {
    if (type === 'percentage') {
        return `${value}%`;
    } else {
        // Convert cents to dollars/currency
        return `$${(value / 100).toFixed(2)}`;
    }
};

const getCouponUsage = (coupon: Coupon): string => {
    return coupon.user_coupons_count?.toString() || '0';
};

const getStatusText = (coupon: Coupon): string => {
    const now = new Date();

    if (coupon.valid_from && new Date(coupon.valid_from) > now) {
        return 'Upcoming';
    }

    if (coupon.expires_at && new Date(coupon.expires_at) < now) {
        return 'Expired';
    }

    return 'Active';
};

const typeClass = (type: string): string => {
    switch (type) {
        case 'single_use': return 'bg-blue-100 text-blue-800 dark:bg-blue-700 dark:text-blue-200';
        case 'multi_use': return 'bg-green-100 text-green-800 dark:bg-green-700 dark:text-green-200';
        default: return 'bg-gray-100 text-gray-800 dark:bg-gray-600 dark:text-gray-200';
    }
};

const statusClass = (status: string): string => {
    switch (status.toLowerCase()) {
        case 'active': return 'bg-green-100 text-green-800 dark:bg-green-700 dark:text-green-200';
        case 'expired': return 'bg-red-100 text-red-800 dark:bg-red-600 dark:text-red-100';
        case 'upcoming': return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-600 dark:text-yellow-100';
        default: return 'bg-gray-100 text-gray-800 dark:bg-gray-500 dark:text-gray-100';
    }
};
</script>

<style scoped>
/* Scoped styles if needed */
</style>
