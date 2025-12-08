<script setup lang="ts">
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import AuthBase from '@/layouts/AuthLayout.vue';
import { Head } from '@inertiajs/vue3';
import { Clock, Users, XCircle, Home, UserPlus } from 'lucide-vue-next';
// @ts-expect-error - vue-i18n has no type definitions
import { useI18n } from 'vue-i18n';

type UnavailableReason = 'inactive' | 'expired' | 'full';

interface Props {
    reason: UnavailableReason;
    message: string;
}

const props = defineProps<Props>();

const { t } = useI18n();

const iconComponent = computed(() => {
    switch (props.reason) {
        case 'expired':
            return Clock;
        case 'full':
            return Users;
        case 'inactive':
        default:
            return XCircle;
    }
});

const iconColorClass = computed(() => {
    switch (props.reason) {
        case 'expired':
            return 'text-amber-500 dark:text-amber-400';
        case 'full':
            return 'text-blue-500 dark:text-blue-400';
        case 'inactive':
        default:
            return 'text-red-500 dark:text-red-400';
    }
});

const bgColorClass = computed(() => {
    switch (props.reason) {
        case 'expired':
            return 'bg-amber-100 dark:bg-amber-900/20';
        case 'full':
            return 'bg-blue-100 dark:bg-blue-900/20';
        case 'inactive':
        default:
            return 'bg-red-100 dark:bg-red-900/20';
    }
});

const pageTitle = computed(() => {
    return t('temporary_registration.unavailable.title');
});
</script>

<template>
    <AuthBase
        :title="pageTitle"
        :description="t('temporary_registration.unavailable.description')"
    >
        <Head :title="pageTitle" />

        <div class="flex flex-col items-center gap-6 text-center">
            <!-- Icon -->
            <div
                :class="[
                    'flex h-20 w-20 items-center justify-center rounded-full',
                    bgColorClass
                ]"
            >
                <component
                    :is="iconComponent"
                    :class="['h-10 w-10', iconColorClass]"
                />
            </div>

            <!-- Message -->
            <div class="space-y-2">
                <p class="text-muted-foreground">
                    {{ message }}
                </p>
            </div>

            <!-- Action Buttons -->
            <div class="flex flex-col gap-3 w-full max-w-xs">
                <Button
                    :href="route('register')"
                    class="w-full"
                    size="lg"
                >
                    <UserPlus class="mr-2 h-4 w-4" />
                    {{ t('temporary_registration.unavailable.register_normally') }}
                </Button>

                <Button
                    :href="route('home')"
                    variant="outline"
                    class="w-full"
                >
                    <Home class="mr-2 h-4 w-4" />
                    {{ t('temporary_registration.unavailable.go_home') }}
                </Button>
            </div>
        </div>
    </AuthBase>
</template>
