<template>
    <Head :title="`Registration Page: ${getTranslation(page.title)}`" />
    <AppLayout>
        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                <!-- Page Header -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
                    <div class="p-6 lg:p-8 bg-white dark:bg-gray-800 dark:bg-gradient-to-bl dark:from-gray-700/50 dark:via-transparent border-b border-gray-200 dark:border-gray-700">
                        <PageHeader :title="getTranslation(page.title)">
                            <template #actions>
                                <Link
                                    :href="route('admin.temporary-registration.edit', page.id)"
                                    class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-500 active:bg-indigo-700 focus:outline-none focus:border-indigo-700 focus:ring focus:ring-indigo-200 disabled:opacity-25 transition"
                                >
                                    Edit
                                </Link>
                                <button
                                    @click="toggleActive"
                                    :class="page.is_active
                                        ? 'bg-yellow-600 hover:bg-yellow-500'
                                        : 'bg-green-600 hover:bg-green-500'"
                                    class="inline-flex items-center px-4 py-2 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest focus:outline-none focus:ring focus:ring-gray-200 disabled:opacity-25 transition"
                                >
                                    {{ page.is_active ? 'Deactivate' : 'Activate' }}
                                </button>
                                <button
                                    @click="showRegenerateDialog = true"
                                    class="inline-flex items-center px-4 py-2 bg-orange-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-orange-500 focus:outline-none focus:ring focus:ring-orange-200 disabled:opacity-25 transition"
                                >
                                    Regenerate Token
                                </button>
                                <button
                                    @click="showDeleteDialog = true"
                                    class="inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-500 focus:outline-none focus:ring focus:ring-red-200 disabled:opacity-25 transition"
                                >
                                    Delete
                                </button>
                            </template>
                        </PageHeader>

                        <!-- Banner Image -->
                        <div v-if="page.banner_url" class="mt-6">
                            <img :src="page.banner_url" alt="Banner" class="w-full max-h-48 object-cover rounded-lg" />
                        </div>

                        <!-- Description -->
                        <div v-if="getTranslation(page.description)" class="mt-6">
                            <p class="text-gray-600 dark:text-gray-400">{{ getTranslation(page.description) }}</p>
                        </div>
                    </div>
                </div>

                <!-- Status & Public URL -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Status Badges -->
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg p-6">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Status</h3>
                        <div class="flex flex-wrap gap-2">
                            <Badge :variant="page.is_active ? 'success' : 'secondary'" class="text-sm">
                                {{ page.is_active ? 'Active' : 'Inactive' }}
                            </Badge>
                            <Badge v-if="page.is_expired" variant="destructive" class="text-sm">
                                Expired
                            </Badge>
                            <Badge v-if="page.is_full" variant="warning" class="text-sm">
                                Full
                            </Badge>
                            <Badge v-if="page.is_available" variant="success" class="text-sm">
                                Available
                            </Badge>
                            <Badge v-else-if="!page.is_expired && !page.is_full" variant="secondary" class="text-sm">
                                Not Available
                            </Badge>
                        </div>
                    </div>

                    <!-- Public URL -->
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg p-6">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Public URL</h3>
                        <div class="flex items-center space-x-2">
                            <code class="flex-1 bg-gray-100 dark:bg-gray-700 px-3 py-2 rounded text-sm font-mono text-gray-800 dark:text-gray-200 truncate">
                                {{ page.public_url }}
                            </code>
                            <Button variant="outline" size="sm" @click="copyUrl">
                                {{ urlCopied ? 'Copied!' : 'Copy' }}
                            </Button>
                            <a :href="page.public_url" target="_blank" class="text-indigo-600 hover:text-indigo-500">
                                <ArrowTopRightOnSquareIcon class="h-5 w-5" />
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Details Grid -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- Membership Level -->
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg p-6">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Membership Level</h3>
                        <div class="space-y-2">
                            <p class="text-xl font-medium text-indigo-600 dark:text-indigo-400">
                                {{ getTranslation(page.membership_level?.name) }}
                            </p>
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                Duration: {{ page.membership_level?.duration_months }} months
                            </p>
                        </div>
                    </div>

                    <!-- Registration Stats -->
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg p-6">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Registration Stats</h3>
                        <div class="space-y-2">
                            <div class="flex justify-between">
                                <span class="text-gray-500 dark:text-gray-400">Current</span>
                                <span class="font-medium text-gray-900 dark:text-white">{{ page.registrations_count }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-500 dark:text-gray-400">Maximum</span>
                                <span class="font-medium text-gray-900 dark:text-white">
                                    {{ page.max_registrations || 'Unlimited' }}
                                </span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-500 dark:text-gray-400">Remaining</span>
                                <span class="font-medium" :class="page.remaining_slots === 0 ? 'text-red-600' : 'text-green-600'">
                                    {{ page.remaining_slots !== null ? page.remaining_slots : 'Unlimited' }}
                                </span>
                            </div>
                        </div>
                        <!-- Progress bar for registrations -->
                        <div v-if="page.max_registrations" class="mt-4">
                            <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                                <div
                                    class="h-2 rounded-full transition-all"
                                    :class="registrationPercentage >= 90 ? 'bg-red-600' : registrationPercentage >= 70 ? 'bg-yellow-600' : 'bg-green-600'"
                                    :style="{ width: `${registrationPercentage}%` }"
                                ></div>
                            </div>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                {{ registrationPercentage.toFixed(0) }}% filled
                            </p>
                        </div>
                    </div>

                    <!-- Expiration -->
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg p-6">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Expiration</h3>
                        <div class="space-y-2">
                            <template v-if="page.duration_days">
                                <p class="text-xl font-medium text-indigo-600 dark:text-indigo-400">
                                    {{ page.duration_days }} days trial
                                </p>
                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                    Users get {{ page.duration_days }} days of premium access after registration
                                </p>
                            </template>
                            <template v-else-if="page.expires_at_formatted">
                                <p class="text-xl font-medium" :class="page.is_expired ? 'text-red-600' : 'text-gray-900 dark:text-white'">
                                    {{ page.expires_at_formatted }}
                                </p>
                                <p v-if="page.is_expired" class="text-sm text-red-500">
                                    This registration page has expired
                                </p>
                            </template>
                            <template v-else>
                                <p class="text-xl font-medium text-gray-900 dark:text-white">
                                    Never
                                </p>
                            </template>
                        </div>
                    </div>
                </div>

                <!-- Metadata -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Additional Information</h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Created By</dt>
                            <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ page.created_by?.name || 'N/A' }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Created At</dt>
                            <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ formatDate(page.created_at) }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">URL Type</dt>
                            <dd class="mt-1 text-sm text-gray-900 dark:text-white">
                                {{ page.use_slug ? 'Custom Slug' : 'Token-based' }}
                            </dd>
                        </div>
                    </div>
                </div>

                <!-- Registered Users Table -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
                    <div class="border-b border-gray-200 bg-white p-6 lg:p-8 dark:border-gray-700 dark:bg-gray-800 dark:bg-gradient-to-bl dark:from-gray-700/50 dark:via-transparent">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                            Registered Users ({{ page.registered_users?.length || 0 }})
                        </h3>

                        <AdminDataTable v-if="page.registered_users && page.registered_users.length > 0">
                            <template #header>
                                <TableHead>Name</TableHead>
                                <TableHead>Email</TableHead>
                                <TableHead>Registered At</TableHead>
                            </template>

                            <template #body>
                                <TableRow v-for="user in page.registered_users" :key="user.id">
                                    <TableCell class="font-medium text-gray-900 dark:text-white">
                                        {{ user.name }}
                                    </TableCell>
                                    <TableCell>{{ user.email }}</TableCell>
                                    <TableCell>{{ formatDate(user.registered_at) }}</TableCell>
                                </TableRow>
                            </template>
                        </AdminDataTable>

                        <div v-else class="text-center py-8 text-gray-500 dark:text-gray-400">
                            No users have registered through this page yet.
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Regenerate Token Confirmation Dialog -->
        <Dialog v-model:open="showRegenerateDialog">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Regenerate Access Token</DialogTitle>
                    <DialogDescription>
                        Are you sure you want to regenerate the access token? The current token-based URL will stop working
                        and a new one will be generated. Users with the old link will no longer be able to access this page.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button variant="outline" @click="showRegenerateDialog = false">Cancel</Button>
                    <Button variant="default" @click="regenerateToken" :disabled="isRegenerating">
                        {{ isRegenerating ? 'Regenerating...' : 'Regenerate Token' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Delete Confirmation Dialog -->
        <Dialog v-model:open="showDeleteDialog">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Delete Registration Page</DialogTitle>
                    <DialogDescription>
                        Are you sure you want to delete this registration page? This action cannot be undone.
                        All registration data will be permanently removed.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button variant="outline" @click="showDeleteDialog = false">Cancel</Button>
                    <Button variant="destructive" @click="deletePage" :disabled="isDeleting">
                        {{ isDeleting ? 'Deleting...' : 'Delete' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/components/Shared/PageHeader.vue';
import AdminDataTable from '@/components/Shared/AdminDataTable.vue';
import { TableCell, TableHead, TableRow } from '@/components/ui/table';
import Badge from '@/components/ui/badge/Badge.vue';
import Button from '@/components/ui/button/Button.vue';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { ArrowTopRightOnSquareIcon } from '@heroicons/vue/24/outline';
import { getTranslation } from '@/Utils/i18n';

interface MembershipLevel {
    id: number;
    name: Record<string, string>;
    duration_months: number;
}

interface RegisteredUser {
    id: number;
    name: string;
    email: string;
    registered_at: string;
}

interface CreatedBy {
    id: number;
    name: string;
}

interface TemporaryRegistrationPage {
    id: number;
    title: Record<string, string>;
    description: Record<string, string> | null;
    slug: string | null;
    token: string;
    public_url: string;
    membership_level: MembershipLevel;
    expires_at: string | null;
    expires_at_formatted: string | null;
    duration_days: number | null;
    max_registrations: number | null;
    registrations_count: number;
    remaining_slots: number | null;
    is_active: boolean;
    is_available: boolean;
    is_expired: boolean;
    is_full: boolean;
    use_slug: boolean;
    banner_url: string | null;
    created_by: CreatedBy;
    registered_users: RegisteredUser[];
    created_at: string;
}

interface Props {
    page: TemporaryRegistrationPage;
}

const props = defineProps<Props>();

const showRegenerateDialog = ref(false);
const showDeleteDialog = ref(false);
const isRegenerating = ref(false);
const isDeleting = ref(false);
const urlCopied = ref(false);

const registrationPercentage = computed(() => {
    if (!props.page.max_registrations) return 0;
    return (props.page.registrations_count / props.page.max_registrations) * 100;
});

const formatDate = (dateString: string): string => {
    const options: Intl.DateTimeFormatOptions = {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    };
    return new Date(dateString).toLocaleDateString('en-US', options);
};

const copyUrl = async () => {
    try {
        await navigator.clipboard.writeText(props.page.public_url);
        urlCopied.value = true;
        setTimeout(() => {
            urlCopied.value = false;
        }, 2000);
    } catch (err) {
        console.error('Failed to copy URL:', err);
    }
};

const toggleActive = () => {
    router.patch(
        route('admin.temporary-registration.toggle-active', props.page.id),
        {},
        {
            preserveScroll: true,
        }
    );
};

const regenerateToken = () => {
    isRegenerating.value = true;
    router.patch(
        route('admin.temporary-registration.regenerate-token', props.page.id),
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                showRegenerateDialog.value = false;
            },
            onFinish: () => {
                isRegenerating.value = false;
            },
        }
    );
};

const deletePage = () => {
    isDeleting.value = true;
    router.delete(route('admin.temporary-registration.destroy', props.page.id), {
        onSuccess: () => {
            // Redirect will be handled by the controller
        },
        onFinish: () => {
            isDeleting.value = false;
        },
    });
};
</script>
