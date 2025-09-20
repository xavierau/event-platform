<template>
    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6 mb-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                {{ t('users.filters.title') }}
            </h3>
            <button
                @click="clearFilters"
                type="button"
                class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
            >
                {{ t('users.filters.clear_all') }}
            </button>
        </div>

        <form @submit.prevent="applyFilters" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <!-- Search Input -->
                <div>
                    <label for="search" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        {{ t('users.filters.search') }}
                    </label>
                    <input
                        id="search"
                        v-model="localFilters.search"
                        type="text"
                        :placeholder="t('users.filters.search_placeholder')"
                        class="block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                    />
                </div>

                <!-- Membership Level Filter -->
                <div>
                    <label for="membership_level_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        {{ t('users.filters.membership_level') }}
                    </label>
                    <select
                        id="membership_level_id"
                        v-model="localFilters.membership_level_id"
                        class="block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                    >
                        <option value="">{{ t('users.filters.all_levels') }}</option>
                        <option
                            v-for="level in membershipLevels"
                            :key="level.id"
                            :value="level.id"
                        >
                            {{ getTranslation(level.name, currentLocale) }}
                        </option>
                    </select>
                </div>

                <!-- Has Membership Filter -->
                <div>
                    <label for="has_membership" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        {{ t('users.filters.membership_status') }}
                    </label>
                    <select
                        id="has_membership"
                        v-model="localFilters.has_membership"
                        class="block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                    >
                        <option value="">{{ t('users.filters.all_statuses') }}</option>
                        <option value="yes">{{ t('users.filters.with_membership') }}</option>
                        <option value="no">{{ t('users.filters.without_membership') }}</option>
                    </select>
                </div>

                <!-- Registration Date From -->
                <div>
                    <label for="registered_from" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        {{ t('users.filters.registered_from') }}
                    </label>
                    <input
                        id="registered_from"
                        v-model="localFilters.registered_from"
                        type="date"
                        class="block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                    />
                </div>

                <!-- Registration Date To -->
                <div>
                    <label for="registered_to" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        {{ t('users.filters.registered_to') }}
                    </label>
                    <input
                        id="registered_to"
                        v-model="localFilters.registered_to"
                        type="date"
                        class="block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                    />
                </div>
            </div>

            <!-- Filter Actions -->
            <div class="flex items-center justify-between pt-4">
                <div class="text-sm text-gray-500 dark:text-gray-400">
                    <span v-if="hasActiveFilters">
                        {{ t('users.filters.active_filters', { count: activeFilterCount }) }}
                    </span>
                </div>
                <div class="flex space-x-3">
                    <button
                        type="button"
                        @click="clearFilters"
                        class="inline-flex items-center px-3 py-2 border border-gray-300 dark:border-gray-600 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                    >
                        {{ t('users.filters.clear') }}
                    </button>
                    <button
                        type="submit"
                        class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                    >
                        {{ t('users.filters.apply') }}
                    </button>
                </div>
            </div>
        </form>
    </div>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { debounce } from 'lodash';

const { t, locale } = useI18n();

interface MembershipLevel {
    id: number;
    name: Record<string, string>;
}

interface Filters {
    search: string;
    membership_level_id: string;
    has_membership: string;
    registered_from: string;
    registered_to: string;
}

interface Props {
    membershipLevels: MembershipLevel[];
    initialFilters: Partial<Filters>;
}

const props = defineProps<Props>();

const currentLocale = computed(() => locale.value);

const localFilters = ref<Filters>({
    search: props.initialFilters.search || '',
    membership_level_id: props.initialFilters.membership_level_id || '',
    has_membership: props.initialFilters.has_membership || '',
    registered_from: props.initialFilters.registered_from || '',
    registered_to: props.initialFilters.registered_to || '',
});

const hasActiveFilters = computed(() => {
    return Object.values(localFilters.value).some(value => value !== '');
});

const activeFilterCount = computed(() => {
    return Object.values(localFilters.value).filter(value => value !== '').length;
});

const getTranslation = (translations: Record<string, string>, locale: string): string => {
    if (typeof translations === 'string') return translations;
    return translations?.[locale] || translations?.['en'] || '';
};

const applyFilters = () => {
    const cleanFilters = Object.fromEntries(
        Object.entries(localFilters.value).filter(([_, value]) => value !== '')
    );

    router.get(route('admin.users.index'), cleanFilters, {
        preserveState: true,
        replace: true,
    });
};

const clearFilters = () => {
    localFilters.value = {
        search: '',
        membership_level_id: '',
        has_membership: '',
        registered_from: '',
        registered_to: '',
    };

    router.get(route('admin.users.index'), {}, {
        preserveState: true,
        replace: true,
    });
};

// Debounced search
const debouncedApplyFilters = debounce(applyFilters, 300);

// Watch for search changes and apply debounced filtering
watch(() => localFilters.value.search, () => {
    debouncedApplyFilters();
});
</script>