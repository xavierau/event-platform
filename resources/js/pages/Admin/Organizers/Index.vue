<template>
    <Head :title="t('organizers.index_title')" />
    <AppLayout>
        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
                    <div class="p-6 lg:p-8 bg-white dark:bg-gray-800 dark:bg-gradient-to-bl dark:from-gray-700/50 dark:via-transparent border-b border-gray-200 dark:border-gray-700">
                        <PageHeader :title="t('organizers.index_title')" :subtitle="t('organizers.index_subtitle')">
                            <template #actions>
                                <Link :href="route('admin.organizers.create')" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-500 active:bg-indigo-700 focus:outline-none focus:border-indigo-700 focus:ring focus:ring-indigo-200 disabled:opacity-25 transition">
                                    {{ t('organizers.create_button') }}
                                </Link>
                            </template>
                        </PageHeader>

                        <AdminDataTable>
                            <!-- Filters Slot -->
                            <template #filters>
                                <div>
                                    <label for="search" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('fields.search') }}</label>
                                    <input type="text" v-model="filterForm.search" @input="searchOrganizers" id="search" :placeholder="t('organizers.search_placeholder')" class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                </div>
                                <div>
                                    <label for="is_active" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('fields.status') }}</label>
                                    <select v-model="filterForm.is_active" @change="searchOrganizers" id="is_active" class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                        <option value="">{{ t('filters.all_statuses') }}</option>
                                        <option value="1">{{ t('status.active') }}</option>
                                        <option value="0">{{ t('status.inactive') }}</option>
                                    </select>
                                </div>
                                <div>
                                    <label for="sort" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('fields.sort_by') }}</label>
                                    <select v-model="filterForm.sort" @change="searchOrganizers" id="sort" class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                        <option value="name">{{ t('fields.name') }}</option>
                                        <option value="created_at">{{ t('fields.created_date') }}</option>
                                        <option value="events_count">{{ t('fields.events_count') }}</option>
                                    </select>
                                </div>
                                <div>
                                    <label for="direction" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('fields.order') }}</label>
                                    <select v-model="filterForm.direction" @change="searchOrganizers" id="direction" class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                        <option value="asc">{{ t('filters.ascending') }}</option>
                                        <option value="desc">{{ t('filters.descending') }}</option>
                                    </select>
                                </div>
                            </template>

                            <!-- Header Slot -->
                            <template #header>
                                <TableHead>{{ t('fields.logo') }}</TableHead>
                                <TableHead>{{ t('fields.name') }}</TableHead>
                                <TableHead>{{ t('fields.email') }}</TableHead>
                                <TableHead>{{ t('fields.location') }}</TableHead>
                                <TableHead>{{ t('fields.team') }}</TableHead>
                                <TableHead>{{ t('fields.events') }}</TableHead>
                                <TableHead>{{ t('fields.status') }}</TableHead>
                                <TableHead>{{ t('fields.created_date') }}</TableHead>
                                <TableHead class="text-right">{{ t('fields.actions') }}</TableHead>
                            </template>

                            <!-- Body Slot -->
                            <template #body>
                                <TableRow v-if="organizers.data.length === 0">
                                    <TableCell colspan="9" class="text-center">{{ t('organizers.no_organizers_found') }}</TableCell>
                                </TableRow>
                                <TableRow v-for="organizer in organizers.data" :key="organizer.id">
                                    <TableCell>
                                        <div class="flex-shrink-0 h-10 w-10">
                                            <img v-if="organizer.logo" :src="organizer.logo.url" :alt="getTranslation(organizer.name, 'en') + ' Logo'" class="h-10 w-10 rounded-full object-cover">
                                            <div v-else class="h-10 w-10 rounded-full bg-gray-300 dark:bg-gray-600 flex items-center justify-center">
                                                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ getInitials(organizer.name) }}</span>
                                            </div>
                                        </div>
                                    </TableCell>
                                    <TableCell class="font-medium text-gray-900 dark:text-white">
                                        <div>
                                            <div class="text-sm font-medium">{{ getTranslation(organizer.name, currentLocale) }}</div>
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        <div class="text-sm text-gray-900 dark:text-white">{{ organizer.contact_email }}</div>
                                        <div v-if="organizer.contact_phone" class="text-sm text-gray-500 dark:text-gray-400">{{ organizer.contact_phone }}</div>
                                    </TableCell>
                                    <TableCell>
                                        <div class="text-sm text-gray-900 dark:text-white">
                                            <div v-if="organizer.city">{{ organizer.city }}</div>
                                            <div v-if="organizer.state && organizer.state.name" class="text-xs text-gray-500 dark:text-gray-400">
                                                {{ getTranslation(organizer.state.name, currentLocale) }}
                                            </div>
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-800 dark:text-blue-200">
                                            {{ t('organizers.team_members_count', { count: organizer.team_count || 0 }) }}
                                        </span>
                                    </TableCell>
                                    <TableCell>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-200">
                                            {{ t('organizers.events_count', { count: organizer.events_count || 0 }) }}
                                        </span>
                                    </TableCell>
                                    <TableCell>
                                        <span :class="organizer.is_active ? 'bg-green-100 text-green-800 dark:bg-green-700 dark:text-green-200' : 'bg-red-100 text-red-800 dark:bg-red-700 dark:text-red-200'" class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full">
                                            {{ organizer.is_active ? t('status.active') : t('status.inactive') }}
                                        </span>
                                    </TableCell>
                                    <TableCell>{{ formatDate(organizer.created_at) }}</TableCell>
                                    <TableCell class="text-right">
                                        <Link :href="route('admin.organizers.show', organizer.id)" class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-600 mr-3">{{ t('actions.view') }}</Link>
                                        <Link :href="route('admin.organizers.edit', organizer.id)" class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-600 mr-3">{{ t('actions.edit') }}</Link>
                                        <button @click="confirmDeleteOrganizer(organizer.id, getTranslation(organizer.name, 'en'))" class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-600">{{ t('actions.delete') }}</button>
                                    </TableCell>
                                </TableRow>
                            </template>
                        </AdminDataTable>

                        <!-- Pagination -->
                        <AdminPagination :links="organizers.links" :from="organizers.from" :to="organizers.to" :total="organizers.total" />
                    </div>
                </div>
            </div>
        </div>

        <!-- Delete Confirmation Modal -->
        <Dialog :open="showConfirmDeleteModal" @update:open="showConfirmDeleteModal = $event">
            <DialogContent class="sm:max-w-[425px]">
                <DialogHeader>
                    <DialogTitle>{{ t('organizers.delete_title') }}</DialogTitle>
                </DialogHeader>
                <p class="py-4 text-sm text-gray-600 dark:text-gray-400">
                    {{ t('organizers.delete_confirmation', { name: organizerToDelete?.name }) }}
                </p>
                <DialogFooter>
                    <Button variant="outline" @click="closeDeleteModal">{{ t('actions.cancel') }}</Button>
                    <Button variant="destructive" @click="deleteOrganizer" class="ml-3">{{ t('organizers.delete_button') }}</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

    </AppLayout>
</template>

<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, useForm, router, usePage } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { useI18n } from 'vue-i18n';
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
import PageHeader from '@/components/Shared/PageHeader.vue';
import AdminPagination from '@/components/Shared/AdminPagination.vue';
import { TableHead, TableRow, TableCell } from '@/components/ui/table';
import AdminDataTable from '@/components/Shared/AdminDataTable.vue';

const { t } = useI18n();
const page = usePage();
const currentLocale = computed(() => page.props.locale as 'en' | 'zh-HK' | 'zh-CN');

interface MediaData {
    id: number;
    url: string;
    name: string;
}

interface State {
    name: Record<string, string> | string;
}

interface Organizer {
    id: number;
    name: Record<string, string> | string;
    contact_email: string;
    contact_phone?: string;
    is_active: boolean;
    logo?: MediaData;
    city?: string;
    state?: State;
    team_count: number;
    events_count: number;
    created_at: string;
    updated_at: string;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginatedOrganizers {
    data: Organizer[];
    links: PaginationLink[];
    from: number;
    to: number;
    total: number;
}

interface Filters {
    search?: string;
    is_active?: string;
    sort?: string;
    direction?: string;
}

const props = defineProps<{
    organizers: PaginatedOrganizers;
    filters: Filters;
}>();

const filterForm = useForm({
    search: props.filters.search || '',
    is_active: props.filters.is_active || '',
    sort: props.filters.sort || 'name',
    direction: props.filters.direction || 'asc',
});

const showConfirmDeleteModal = ref(false);
const organizerToDelete = ref<{ id: number; name: string } | null>(null);

const getInitials = (name: Record<string, string> | string) => {
    const nameStr = typeof name === 'string' ? name : (getTranslation(name, 'en') || '');
    if (!nameStr) return '';
    const words = nameStr.split(' ');
    if (words.length > 1) {
        return (words[0][0] + words[1][0]).toUpperCase();
    }
    return nameStr.substring(0, 2).toUpperCase();
};

const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString(currentLocale.value, {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });
};

const searchOrganizers = throttle(() => {
    filterForm.get(route('admin.organizers.index'), {
        preserveState: true,
        replace: true,
    });
}, 300);

const confirmDeleteOrganizer = (id: number, name: string) => {
    organizerToDelete.value = { id, name };
    showConfirmDeleteModal.value = true;
};

const closeDeleteModal = () => {
    showConfirmDeleteModal.value = false;
    organizerToDelete.value = null;
};

const deleteOrganizer = () => {
    if (organizerToDelete.value) {
        router.delete(route('admin.organizers.destroy', organizerToDelete.value.id), {
            onSuccess: () => closeDeleteModal(),
            preserveState: false,
        });
    }
};
</script>

<style scoped>
/* Scoped styles if needed */
</style>
