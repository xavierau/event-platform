<script setup lang="ts">
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { Copy, Plus, Edit2, XCircle, Trash2 } from 'lucide-vue-next';
import { useTicketHoldFormatters } from '@/composables/useTicketHoldFormatters';
import Button from '@/components/ui/button/Button.vue';
import { Table, TableHeader, TableRow, TableHead, TableBody, TableCell } from '@/components/ui/table';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogFooter,
    DialogDescription,
} from '@/components/ui/dialog';
import LinkStatusBadge from './LinkStatusBadge.vue';
import PurchaseLinkForm from './PurchaseLinkForm.vue';

type LinkStatus = 'active' | 'expired' | 'revoked' | 'exhausted';
type QuantityMode = 'fixed' | 'maximum' | 'unlimited';

interface User {
    id: number;
    name: string;
    email: string;
}

interface PurchaseLink {
    id: number;
    uuid: string;
    code: string;
    name: string | null;
    status: LinkStatus;
    quantity_mode: QuantityMode;
    quantity_limit: number | null;
    quantity_purchased: number;
    assigned_user_id: number | null;
    assigned_user?: User | null;
    expires_at: string | null;
    notes: string | null;
    full_url: string;
    created_at: string;
}

interface Props {
    links: PurchaseLink[];
    ticketHoldId: number;
}

const props = defineProps<Props>();

const { formatDate } = useTicketHoldFormatters();

const showFormModal = ref(false);
const showDeleteModal = ref(false);
const showRevokeModal = ref(false);
const selectedLink = ref<PurchaseLink | null>(null);
const isEditMode = ref(false);

const openCreateModal = () => {
    selectedLink.value = null;
    isEditMode.value = false;
    showFormModal.value = true;
};

const openEditModal = (link: PurchaseLink) => {
    selectedLink.value = link;
    isEditMode.value = true;
    showFormModal.value = true;
};

const closeFormModal = () => {
    showFormModal.value = false;
    selectedLink.value = null;
    isEditMode.value = false;
};

const confirmRevoke = (link: PurchaseLink) => {
    selectedLink.value = link;
    showRevokeModal.value = true;
};

const confirmDelete = (link: PurchaseLink) => {
    selectedLink.value = link;
    showDeleteModal.value = true;
};

const revokeLink = () => {
    if (selectedLink.value) {
        router.post(route('admin.purchase-links.revoke', selectedLink.value.id), {}, {
            preserveState: false,
            onSuccess: () => {
                showRevokeModal.value = false;
                selectedLink.value = null;
            },
        });
    }
};

const deleteLink = () => {
    if (selectedLink.value) {
        router.delete(route('admin.purchase-links.destroy', selectedLink.value.id), {
            preserveState: false,
            onSuccess: () => {
                showDeleteModal.value = false;
                selectedLink.value = null;
            },
        });
    }
};

const copyToClipboard = async (url: string) => {
    try {
        await navigator.clipboard.writeText(url);
        // Could add a toast notification here
    } catch {
        // Silently fail - clipboard operations may not be available in all contexts
    }
};

const formatQuantityUsed = (link: PurchaseLink): string => {
    if (link.quantity_mode === 'unlimited') {
        return `${link.quantity_purchased} / Unlimited`;
    }
    return `${link.quantity_purchased} / ${link.quantity_limit || 0}`;
};

const getLinkName = (link: PurchaseLink): string => {
    return link.name || `Link ${link.code.substring(0, 8)}...`;
};
</script>

<template>
    <div class="space-y-4">
        <div class="flex justify-between items-center">
            <h4 class="text-lg font-medium text-gray-900 dark:text-white">Purchase Links</h4>
            <Button @click="openCreateModal" size="sm">
                <Plus class="w-4 h-4 mr-1" />
                Create Link
            </Button>
        </div>

        <div v-if="links.length === 0" class="text-sm text-gray-500 dark:text-gray-400 py-8 text-center border border-dashed border-gray-300 dark:border-gray-600 rounded-lg">
            No purchase links created yet. Click "Create Link" to generate private purchase links.
        </div>

        <Table v-else>
            <TableHeader>
                <TableRow>
                    <TableHead>Name</TableHead>
                    <TableHead>Code</TableHead>
                    <TableHead>Status</TableHead>
                    <TableHead>User</TableHead>
                    <TableHead>Quantity Used</TableHead>
                    <TableHead>Expires</TableHead>
                    <TableHead class="text-right">Actions</TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                <TableRow v-for="link in links" :key="link.id">
                    <TableCell class="font-medium text-gray-900 dark:text-white">
                        {{ getLinkName(link) }}
                    </TableCell>
                    <TableCell class="font-mono text-sm text-indigo-600 dark:text-indigo-400">
                        {{ link.code.substring(0, 8) }}...
                    </TableCell>
                    <TableCell>
                        <LinkStatusBadge :status="link.status" />
                    </TableCell>
                    <TableCell>
                        <template v-if="link.assigned_user">
                            {{ link.assigned_user.name }}
                        </template>
                        <span v-else class="text-gray-500 dark:text-gray-400">Anonymous</span>
                    </TableCell>
                    <TableCell>{{ formatQuantityUsed(link) }}</TableCell>
                    <TableCell>{{ formatDate(link.expires_at) }}</TableCell>
                    <TableCell class="text-right">
                        <div class="flex justify-end space-x-2">
                            <button
                                @click="copyToClipboard(link.full_url)"
                                class="text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-200"
                                title="Copy URL"
                            >
                                <Copy class="w-4 h-4" />
                            </button>
                            <button
                                @click="openEditModal(link)"
                                class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300"
                                title="Edit"
                            >
                                <Edit2 class="w-4 h-4" />
                            </button>
                            <button
                                v-if="link.status === 'active'"
                                @click="confirmRevoke(link)"
                                class="text-yellow-600 hover:text-yellow-900 dark:text-yellow-400 dark:hover:text-yellow-300"
                                title="Revoke"
                            >
                                <XCircle class="w-4 h-4" />
                            </button>
                            <button
                                @click="confirmDelete(link)"
                                class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300"
                                title="Delete"
                            >
                                <Trash2 class="w-4 h-4" />
                            </button>
                        </div>
                    </TableCell>
                </TableRow>
            </TableBody>
        </Table>

        <!-- Create/Edit Link Modal -->
        <Dialog :open="showFormModal" @update:open="(v) => !v && closeFormModal()">
            <DialogContent class="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>{{ isEditMode ? 'Edit Purchase Link' : 'Create Purchase Link' }}</DialogTitle>
                </DialogHeader>
                <PurchaseLinkForm
                    :ticket-hold-id="ticketHoldId"
                    :link="selectedLink"
                    :is-edit="isEditMode"
                    @close="closeFormModal"
                />
            </DialogContent>
        </Dialog>

        <!-- Revoke Confirmation Modal -->
        <Dialog :open="showRevokeModal" @update:open="showRevokeModal = $event">
            <DialogContent class="sm:max-w-[425px]">
                <DialogHeader>
                    <DialogTitle>Revoke Purchase Link</DialogTitle>
                    <DialogDescription>
                        Are you sure you want to revoke this purchase link? Users will no longer be able to use this link to purchase tickets.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button variant="outline" @click="showRevokeModal = false">Cancel</Button>
                    <Button variant="destructive" @click="revokeLink" class="ml-3">Revoke Link</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Delete Confirmation Modal -->
        <Dialog :open="showDeleteModal" @update:open="showDeleteModal = $event">
            <DialogContent class="sm:max-w-[425px]">
                <DialogHeader>
                    <DialogTitle>Delete Purchase Link</DialogTitle>
                    <DialogDescription>
                        Are you sure you want to delete this purchase link? This action cannot be undone.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button variant="outline" @click="showDeleteModal = false">Cancel</Button>
                    <Button variant="destructive" @click="deleteLink" class="ml-3">Delete Link</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
