<template>
    <Head title="Manage Membership Levels" />
    <AppLayout>
        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <PageHeader title="Membership Levels" subtitle="Manage subscription plans and pricing">
                    <template #actions>
                        <Link
                            :href="route('admin.membership-levels.create')"
                            class="inline-flex items-center rounded-md border border-transparent bg-indigo-600 px-4 py-2 text-xs font-semibold tracking-widest text-white uppercase transition hover:bg-indigo-500 focus:border-indigo-700 focus:ring focus:ring-indigo-200 focus:outline-none active:bg-indigo-700 disabled:opacity-25"
                        >
                            Create Membership Level
                        </Link>
                    </template>
                </PageHeader>

                <!-- Stats Cards -->
                <div class="grid gap-4 md:grid-cols-4">
                    <div class="overflow-hidden border border-gray-200 bg-white shadow-sm sm:rounded-lg dark:border-gray-700 dark:bg-gray-800">
                        <div class="p-4 text-gray-900 dark:text-gray-100">
                            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Users</div>
                            <div class="text-2xl font-bold">{{ stats.total_users.toLocaleString() }}</div>
                        </div>
                    </div>
                    <div class="overflow-hidden border border-gray-200 bg-white shadow-sm sm:rounded-lg dark:border-gray-700 dark:bg-gray-800">
                        <div class="p-4 text-gray-900 dark:text-gray-100">
                            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Active Users</div>
                            <div class="text-2xl font-bold">{{ stats.active_users.toLocaleString() }}</div>
                        </div>
                    </div>
                    <div class="overflow-hidden border border-gray-200 bg-white shadow-sm sm:rounded-lg dark:border-gray-700 dark:bg-gray-800">
                        <div class="p-4 text-gray-900 dark:text-gray-100">
                            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Monthly Revenue</div>
                            <div class="text-2xl font-bold">{{ stats.total_revenue_formatted }}</div>
                        </div>
                    </div>
                    <div class="overflow-hidden border border-gray-200 bg-white shadow-sm sm:rounded-lg dark:border-gray-700 dark:bg-gray-800">
                        <div class="p-4 text-gray-900 dark:text-gray-100">
                            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Conversion Rate</div>
                            <div class="text-2xl font-bold">
                                {{ stats.total_users > 0 ? Math.round((stats.active_users / stats.total_users) * 100) : 0 }}%
                            </div>
                        </div>
                    </div>
                </div>

                <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg dark:bg-gray-800">
                    <AdminDataTable>
                        <!-- Header Slot -->
                        <template #header>
                            <TableHead>Name</TableHead>
                            <TableHead>Price</TableHead>
                            <TableHead>Duration</TableHead>
                            <TableHead>Users</TableHead>
                            <TableHead>Status</TableHead>
                            <TableHead>Stripe</TableHead>
                            <TableHead class="text-right">Actions</TableHead>
                        </template>

                        <!-- Body Slot -->
                        <template #body>
                            <TableRow v-if="membershipLevels.length === 0">
                                <TableCell colspan="7" class="text-center">No membership levels found.</TableCell>
                            </TableRow>
                            <TableRow v-for="level in membershipLevels" :key="level.id">
                                <TableCell class="font-medium text-gray-900 dark:text-white">
                                    <div>
                                        <div class="font-medium">{{ getLevelName(level) }}</div>
                                        <div class="text-sm text-gray-500 dark:text-gray-400">{{ level.slug }}</div>
                                        <span
                                            v-if="level.metadata?.is_popular"
                                            class="mt-1 inline-flex items-center rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-medium text-blue-800 dark:bg-blue-900 dark:text-blue-200"
                                        >
                                            Popular
                                        </span>
                                    </div>
                                </TableCell>
                                <TableCell>
                                    <div class="font-medium">{{ level.price_formatted }}</div>
                                    <div v-if="level.duration_months" class="text-sm text-gray-500 dark:text-gray-400">
                                        per {{ level.duration_months === 1 ? 'month' : `${level.duration_months} months` }}
                                    </div>
                                </TableCell>
                                <TableCell>
                                    <span
                                        v-if="!level.duration_months"
                                        class="inline-flex items-center rounded-full bg-purple-100 px-2.5 py-0.5 text-xs font-medium text-purple-800 dark:bg-purple-900 dark:text-purple-200"
                                    >
                                        Lifetime
                                    </span>
                                    <span v-else>{{ level.duration_months }} month{{ level.duration_months !== 1 ? 's' : '' }}</span>
                                </TableCell>
                                <TableCell>
                                    <div>{{ level.active_user_memberships_count.toLocaleString() }} active</div>
                                    <div class="text-sm text-gray-500 dark:text-gray-400">
                                        {{ level.user_memberships_count.toLocaleString() }} total
                                    </div>
                                    <div v-if="level.max_users" class="text-sm text-gray-500 dark:text-gray-400">
                                        / {{ level.max_users.toLocaleString() }} max
                                    </div>
                                </TableCell>
                                <TableCell>
                                    <span
                                        :class="
                                            level.is_active
                                                ? 'bg-green-100 text-green-800 dark:bg-green-700 dark:text-green-200'
                                                : 'bg-gray-100 text-gray-800 dark:bg-gray-600 dark:text-gray-200'
                                        "
                                        class="inline-flex rounded-full px-2 text-xs leading-5 font-semibold"
                                    >
                                        {{ level.is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </TableCell>
                                <TableCell>
                                    <div v-if="level.stripe_price_id" class="rounded bg-gray-100 px-2 py-1 font-mono text-xs dark:bg-gray-700">
                                        {{ level.stripe_price_id }}
                                    </div>
                                    <div v-else class="text-sm text-gray-500 dark:text-gray-400">Not synced</div>
                                </TableCell>
                                <TableCell class="text-right">
                                    <Link
                                        :href="route('admin.membership-levels.show', level.id)"
                                        class="mr-3 text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-600"
                                    >
                                        View
                                    </Link>
                                    <Link
                                        :href="route('admin.membership-levels.edit', level.id)"
                                        class="mr-3 text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-600"
                                    >
                                        Edit
                                    </Link>
                                    <button
                                        @click="confirmDeleteLevel(level.id, getLevelName(level))"
                                        :disabled="level.user_memberships_count > 0"
                                        :class="
                                            level.user_memberships_count > 0
                                                ? 'cursor-not-allowed text-gray-400'
                                                : 'text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-600'
                                        "
                                    >
                                        Delete
                                    </button>
                                </TableCell>
                            </TableRow>
                        </template>
                    </AdminDataTable>
                </div>
            </div>
        </div>

        <!-- Delete Confirmation Modal -->
        <Dialog :open="showConfirmDeleteModal" @update:open="showConfirmDeleteModal = $event">
            <DialogContent class="sm:max-w-[425px]">
                <DialogHeader>
                    <DialogTitle>Delete Membership Level</DialogTitle>
                </DialogHeader>
                <p class="py-4 text-sm text-gray-600 dark:text-gray-400">
                    Are you sure you want to delete "{{ levelToDelete?.name }}"? This action cannot be undone.
                </p>
                <DialogFooter>
                    <Button variant="outline" @click="closeDeleteModal">Cancel</Button>
                    <Button variant="destructive" @click="deleteLevel" class="ml-3">Delete</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>

<script setup lang="ts">
import AdminDataTable from '@/components/Shared/AdminDataTable.vue';
import PageHeader from '@/components/Shared/PageHeader.vue';
import Button from '@/components/ui/button/Button.vue';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { TableCell, TableHead, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

interface MembershipLevel {
    id: number;
    name: Record<string, string>;
    slug: string;
    description: Record<string, string>;
    price: number;
    price_formatted: string;
    duration_months: number | null;
    stripe_product_id: string | null;
    stripe_price_id: string | null;
    is_active: boolean;
    sort_order: number;
    user_memberships_count: number;
    active_user_memberships_count: number;
    benefits: string[];
    max_users: number | null;
    available_slots: number | null;
    metadata: Record<string, any>;
    created_at: string;
    updated_at: string;
}

interface Stats {
    total_users: number;
    active_users: number;
    total_revenue: number;
    total_revenue_formatted: string;
}

const props = defineProps<{
    membershipLevels: MembershipLevel[];
    stats: Stats;
}>();

const showConfirmDeleteModal = ref(false);
const levelToDelete = ref<{ id: number; name: string } | null>(null);

const getLevelName = (level: MembershipLevel) => {
    return level.name.en || level.slug;
};

const confirmDeleteLevel = (id: number, name: string) => {
    levelToDelete.value = { id, name };
    showConfirmDeleteModal.value = true;
};

const closeDeleteModal = () => {
    showConfirmDeleteModal.value = false;
    levelToDelete.value = null;
};

const deleteLevel = () => {
    if (levelToDelete.value) {
        router.delete(route('admin.membership-levels.destroy', levelToDelete.value.id), {
            onSuccess: () => closeDeleteModal(),
            preserveState: false,
        });
    }
};
</script>
