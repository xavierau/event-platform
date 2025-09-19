<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import BottomNavbar from '../../components/Public/BottomNavbar.vue';
import { User } from '@/types/index';
import { useI18n } from 'vue-i18n';
import FrontendFooter from '@/components/FrontendFooter.vue';

const { t } = useI18n();

const props = defineProps({
    membership: {
        type: Object,
        default: null,
    },
});

const page = usePage()
const auth = computed(() => page.props.auth as { user?: User });

const stripePricingTable = ref<HTMLElement | null>(null);
const hasMembership = computed(() => props.membership && Object.keys(props.membership).length > 0);

onMounted(() => {
    if (!stripePricingTable.value) {
        console.error('Stripe pricing table element is not available.');
        return;
    }
    stripePricingTable.value.innerHTML = `<stripe-pricing-table pricing-table-id="prctbl_1RlPYoGkGJbeDaIk9zSYLgBl" publishable-key="pk_live_51REfHHGkGJbeDaIkqgQEUJfKfY0GdGTWyoISqmf3cMksLLnN1G8PNr5nGuhdRmq2njIf0zPYsZtTmpjKT7Pb9z5d00vibNZZxN" client-reference-id="${auth.value?.user?.id ?? ''}"></stripe-pricing-table>`;
});
</script>

<template>
    <Head :title="t('navigation.my_membership')" />

    <div class="min-h-screen bg-gray-100 dark:bg-gray-900">
        <!-- Header Section -->
        <header class="sticky top-0 z-50 border-b bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <div class="relative container mx-auto flex items-center p-4">
                <Link href="/" class="absolute left-4 text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300">
                    {{ t('common.back_arrow') }}
                </Link>
                <h1 class="flex-1 text-center text-xl font-semibold text-gray-900 dark:text-gray-100">{{ t('navigation.my_membership') }}</h1>
            </div>
        </header>

        <main class="container mx-auto px-4 py-6 pb-24">
            <!-- Current Membership Section -->
            <section class="mb-8 rounded-lg bg-white p-6 shadow dark:bg-gray-800">
                <h2 class="mb-4 text-xl font-semibold text-gray-800 dark:text-gray-200">{{ t('membership.current_membership') }}</h2>

                <div v-if="hasMembership" class="space-y-3">
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600 dark:text-gray-400">{{ t('membership.fields.plan') }}:</span>
                        <span class="font-medium text-gray-900 dark:text-gray-100">{{ membership.level.name.en }}</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600 dark:text-gray-400">{{ t('status.label') }}:</span>
                        <span class="font-medium text-gray-900 dark:text-gray-100">{{ membership.status }}</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600 dark:text-gray-400">{{ t('membership.fields.expires_on') }}:</span>
                        <span class="font-medium text-gray-900 dark:text-gray-100">{{ new Date(membership.expires_at).toLocaleDateString() }}</span>
                    </div>
                </div>
                <div v-else class="text-center py-8">
                    <div class="mb-4 text-6xl">&#x1F4B3;</div>
                    <h3 class="mb-2 text-xl font-semibold text-gray-900 dark:text-gray-100">{{ t('membership.no_active_membership.title') }}</h3>
                    <p class="text-gray-600 dark:text-gray-300">{{ t('membership.no_active_membership.description') }}</p>
                </div>
            </section>

            <!-- Membership Plans Section -->
            <section class="mb-8 rounded-lg bg-white p-6 shadow dark:bg-gray-800">
                <h2 class="mb-4 text-xl font-semibold text-gray-800 dark:text-gray-200">{{ t('membership.available_plans') }}</h2>
                <div ref="stripePricingTable"></div>
            </section>
        </main>

        <FrontendFooter />


<!--        <BottomNavbar />-->
    </div>
</template>

<style scoped>
.shadow-top-lg {
    box-shadow:
        0 -4px 6px -1px rgb(0 0 0 / 0.05),
        0 -2px 4px -2px rgb(0 0 0 / 0.05);
}
</style>
