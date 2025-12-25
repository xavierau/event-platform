<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { throttle } from 'lodash';
import Button from '@/components/ui/button/Button.vue';
import { Switch } from '@/components/ui/switch';
import { Label } from '@/components/ui/label';

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
    ticketHoldId: number;
    link?: PurchaseLink | null;
    isEdit?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    link: null,
    isEdit: false,
});

const emit = defineEmits<{
    (e: 'close'): void;
}>();

const quantityModes = [
    { value: 'fixed', label: 'Exact Quantity', description: 'User must purchase exactly this amount' },
    { value: 'maximum', label: 'Up to Maximum', description: 'User can purchase up to this amount' },
    { value: 'unlimited', label: 'Unlimited (from pool)', description: 'No limit on link, draws from hold pool' },
];

const formatDateForInput = (dateString: string | null): string => {
    if (!dateString) return '';
    const date = new Date(dateString);
    date.setMinutes(date.getMinutes() - date.getTimezoneOffset());
    return date.toISOString().slice(0, 16);
};

const form = useForm({
    name: props.link?.name || '',
    is_assigned: !!props.link?.assigned_user_id,
    assigned_user_id: props.link?.assigned_user_id || null,
    quantity_mode: props.link?.quantity_mode || 'maximum',
    quantity_limit: props.link?.quantity_limit || null,
    expires_at: formatDateForInput(props.link?.expires_at || null),
    notes: props.link?.notes || '',
});

const userSearchQuery = ref('');
const searchResults = ref<User[]>([]);
const isSearching = ref(false);
const selectedUser = ref<User | null>(props.link?.assigned_user || null);

const showQuantityLimit = computed(() => {
    return form.quantity_mode !== 'unlimited';
});

const searchUsers = throttle(async (query: string) => {
    if (query.length < 2) {
        searchResults.value = [];
        return;
    }

    isSearching.value = true;
    try {
        const response = await fetch(route('admin.api.users.search', { q: query }));
        const data = await response.json();
        searchResults.value = data;
    } catch {
        // Silently handle search errors - user can retry
        searchResults.value = [];
    } finally {
        isSearching.value = false;
    }
}, 300);

watch(userSearchQuery, (newQuery) => {
    searchUsers(newQuery);
});

const selectUser = (user: User) => {
    selectedUser.value = user;
    form.assigned_user_id = user.id;
    userSearchQuery.value = '';
    searchResults.value = [];
};

const clearSelectedUser = () => {
    selectedUser.value = null;
    form.assigned_user_id = null;
};

watch(() => form.is_assigned, (isAssigned) => {
    if (!isAssigned) {
        clearSelectedUser();
    }
});

const submit = () => {
    if (props.isEdit && props.link) {
        form.put(route('admin.purchase-links.update', props.link.id), {
            preserveState: false,
            onSuccess: () => emit('close'),
        });
    } else {
        form.post(route('admin.ticket-holds.purchase-links.store', props.ticketHoldId), {
            preserveState: false,
            onSuccess: () => emit('close'),
        });
    }
};
</script>

<template>
    <form @submit.prevent="submit" class="space-y-4">
        <!-- Name -->
        <div>
            <label for="link_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                Link Name (Optional)
            </label>
            <input
                type="text"
                v-model="form.name"
                id="link_name"
                maxlength="255"
                class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                :class="{ 'border-red-500': form.errors.name }"
                placeholder="e.g., VIP Guest Link, Press Pass"
            />
            <p v-if="form.errors.name" class="mt-1 text-sm text-red-600 dark:text-red-400">
                {{ form.errors.name }}
            </p>
        </div>

        <!-- User Assignment Toggle -->
        <div class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
            <div>
                <Label for="is_assigned" class="text-sm font-medium text-gray-900 dark:text-white">
                    Assign to Specific User
                </Label>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    {{ form.is_assigned ? 'Only the assigned user can use this link' : 'Anyone with the link can use it' }}
                </p>
            </div>
            <Switch
                id="is_assigned"
                v-model:checked="form.is_assigned"
            />
        </div>

        <!-- User Search (when assigned) -->
        <div v-if="form.is_assigned" class="space-y-2">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                Assigned User
            </label>

            <!-- Selected User Display -->
            <div v-if="selectedUser" class="flex items-center justify-between p-3 bg-indigo-50 dark:bg-indigo-900/30 rounded-lg">
                <div>
                    <p class="text-sm font-medium text-gray-900 dark:text-white">{{ selectedUser.name }}</p>
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ selectedUser.email }}</p>
                </div>
                <button
                    type="button"
                    @click="clearSelectedUser"
                    class="text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300 text-sm"
                >
                    Remove
                </button>
            </div>

            <!-- User Search Input -->
            <div v-else class="relative">
                <input
                    type="text"
                    v-model="userSearchQuery"
                    class="block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                    :class="{ 'border-red-500': form.errors.assigned_user_id }"
                    placeholder="Search users by name or email..."
                />

                <!-- Search Results Dropdown -->
                <div v-if="searchResults.length > 0" class="absolute z-10 mt-1 w-full bg-white dark:bg-gray-800 shadow-lg rounded-md border border-gray-200 dark:border-gray-700 max-h-48 overflow-y-auto">
                    <button
                        v-for="user in searchResults"
                        :key="user.id"
                        type="button"
                        @click="selectUser(user)"
                        class="w-full text-left px-4 py-2 hover:bg-gray-100 dark:hover:bg-gray-700"
                    >
                        <p class="text-sm font-medium text-gray-900 dark:text-white">{{ user.name }}</p>
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ user.email }}</p>
                    </button>
                </div>

                <!-- Loading Indicator -->
                <div v-if="isSearching" class="absolute right-3 top-1/2 transform -translate-y-1/2">
                    <svg class="animate-spin h-4 w-4 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                </div>
            </div>
            <p v-if="form.errors.assigned_user_id" class="mt-1 text-sm text-red-600 dark:text-red-400">
                {{ form.errors.assigned_user_id }}
            </p>
        </div>

        <!-- Quantity Mode -->
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                Quantity Mode
            </label>
            <div class="space-y-2">
                <label
                    v-for="mode in quantityModes"
                    :key="mode.value"
                    class="flex items-start p-3 border rounded-lg cursor-pointer"
                    :class="{
                        'border-indigo-500 bg-indigo-50 dark:bg-indigo-900/30': form.quantity_mode === mode.value,
                        'border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800': form.quantity_mode !== mode.value
                    }"
                >
                    <input
                        type="radio"
                        v-model="form.quantity_mode"
                        :value="mode.value"
                        class="mt-1 form-radio text-indigo-600"
                    />
                    <div class="ml-3">
                        <span class="text-sm font-medium text-gray-900 dark:text-white">{{ mode.label }}</span>
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ mode.description }}</p>
                    </div>
                </label>
            </div>
        </div>

        <!-- Quantity Limit -->
        <div v-if="showQuantityLimit">
            <label for="quantity_limit" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                Quantity Limit *
            </label>
            <input
                type="number"
                v-model.number="form.quantity_limit"
                id="quantity_limit"
                min="1"
                class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                :class="{ 'border-red-500': form.errors.quantity_limit }"
                placeholder="Enter quantity"
            />
            <p v-if="form.errors.quantity_limit" class="mt-1 text-sm text-red-600 dark:text-red-400">
                {{ form.errors.quantity_limit }}
            </p>
        </div>

        <!-- Expires At -->
        <div>
            <label for="link_expires_at" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                Expiration Date (Optional)
            </label>
            <input
                type="datetime-local"
                v-model="form.expires_at"
                id="link_expires_at"
                class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                :class="{ 'border-red-500': form.errors.expires_at }"
            />
            <p v-if="form.errors.expires_at" class="mt-1 text-sm text-red-600 dark:text-red-400">
                {{ form.errors.expires_at }}
            </p>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Leave empty for no expiration (will follow hold expiration)
            </p>
        </div>

        <!-- Notes -->
        <div>
            <label for="link_notes" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                Notes (Optional)
            </label>
            <textarea
                v-model="form.notes"
                id="link_notes"
                rows="2"
                maxlength="5000"
                class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                :class="{ 'border-red-500': form.errors.notes }"
                placeholder="Internal notes about this link..."
            ></textarea>
            <p v-if="form.errors.notes" class="mt-1 text-sm text-red-600 dark:text-red-400">
                {{ form.errors.notes }}
            </p>
        </div>

        <!-- Form Actions -->
        <div class="flex justify-end space-x-3 pt-4 border-t border-gray-200 dark:border-gray-700">
            <Button type="button" variant="outline" @click="$emit('close')">
                Cancel
            </Button>
            <Button type="submit" :disabled="form.processing">
                <svg v-if="form.processing" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                {{ form.processing ? (isEdit ? 'Updating...' : 'Creating...') : (isEdit ? 'Update Link' : 'Create Link') }}
            </Button>
        </div>
    </form>
</template>
