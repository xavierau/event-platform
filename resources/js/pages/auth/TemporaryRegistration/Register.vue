<script setup lang="ts">
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthBase from '@/layouts/AuthLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { LoaderCircle, Clock, Users, CheckCircle } from 'lucide-vue-next';
// @ts-expect-error - vue-i18n has no type definitions
import { useI18n } from 'vue-i18n';

interface MembershipLevel {
    name: Record<string, string>;
    description: Record<string, string> | null;
    benefits: Record<string, string[]> | null;
    duration_months: number;
}

interface TemporaryRegistrationPage {
    id: number;
    title: Record<string, string>;
    description: Record<string, string> | null;
    banner_url: string | null;
    membership_level: MembershipLevel;
    remaining_slots: number | null;
}

interface Props {
    page: TemporaryRegistrationPage;
    identifier: string;
}

const props = defineProps<Props>();

const { t, locale } = useI18n();

const form = useForm({
    name: '',
    email: '',
    mobile_number: '',
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('register.temporary.store', props.identifier), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};

// Computed properties for localized content
const pageTitle = computed(() => {
    return props.page.title[locale.value] || props.page.title.en || '';
});

const pageDescription = computed(() => {
    if (!props.page.description) return null;
    return props.page.description[locale.value] || props.page.description.en || null;
});

const membershipName = computed(() => {
    return props.page.membership_level.name[locale.value]
        || props.page.membership_level.name.en
        || '';
});

const membershipDescription = computed(() => {
    if (!props.page.membership_level.description) return null;
    return props.page.membership_level.description[locale.value]
        || props.page.membership_level.description.en
        || null;
});

const membershipBenefits = computed(() => {
    if (!props.page.membership_level.benefits) return [];
    return props.page.membership_level.benefits[locale.value]
        || props.page.membership_level.benefits.en
        || [];
});

const durationText = computed(() => {
    const months = props.page.membership_level.duration_months;
    if (months === 1) {
        return t('membership.duration.one_month');
    } else if (months === 12) {
        return t('membership.duration.one_year');
    } else {
        return t('membership.duration.months', { count: months });
    }
});

const hasLimitedSlots = computed(() => {
    return props.page.remaining_slots !== null;
});

const isLowOnSlots = computed(() => {
    return hasLimitedSlots.value && props.page.remaining_slots! <= 10;
});
</script>

<template>
    <AuthBase :title="pageTitle" :description="pageDescription ?? undefined">
        <Head :title="pageTitle" />

        <div class="flex flex-col gap-6">
            <!-- Banner Image -->
            <div v-if="page.banner_url" class="-mx-6 -mt-6 mb-2">
                <img
                    :src="page.banner_url"
                    :alt="pageTitle"
                    class="w-full h-48 object-cover rounded-t-lg"
                />
            </div>

            <!-- Membership Level Info Card -->
            <div class="rounded-lg border bg-muted/50 p-4">
                <div class="flex items-start gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-primary/10">
                        <CheckCircle class="h-5 w-5 text-primary" />
                    </div>
                    <div class="flex-1 space-y-2">
                        <div class="flex items-center justify-between">
                            <h3 class="font-semibold text-foreground">{{ membershipName }}</h3>
                            <div class="flex items-center gap-1 text-sm text-muted-foreground">
                                <Clock class="h-4 w-4" />
                                <span>{{ durationText }}</span>
                            </div>
                        </div>

                        <p v-if="membershipDescription" class="text-sm text-muted-foreground">
                            {{ membershipDescription }}
                        </p>

                        <!-- Benefits List -->
                        <ul v-if="membershipBenefits.length > 0" class="space-y-1 pt-2">
                            <li
                                v-for="(benefit, index) in membershipBenefits"
                                :key="index"
                                class="flex items-center gap-2 text-sm text-foreground"
                            >
                                <CheckCircle class="h-4 w-4 shrink-0 text-green-500" />
                                <span>{{ benefit }}</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Remaining Slots Indicator -->
            <div
                v-if="hasLimitedSlots"
                :class="[
                    'flex items-center gap-2 rounded-lg px-4 py-3 text-sm',
                    isLowOnSlots
                        ? 'bg-amber-50 text-amber-800 dark:bg-amber-900/20 dark:text-amber-200'
                        : 'bg-blue-50 text-blue-800 dark:bg-blue-900/20 dark:text-blue-200'
                ]"
            >
                <Users class="h-4 w-4 shrink-0" />
                <span>
                    {{ t('temporary_registration.remaining_slots', { count: page.remaining_slots }) }}
                </span>
            </div>

            <!-- Registration Form -->
            <form @submit.prevent="submit" class="flex flex-col gap-6">
                <div class="grid gap-6">
                    <div class="grid gap-2">
                        <Label for="name">{{ t('forms.fields.name.label') }}</Label>
                        <Input
                            id="name"
                            type="text"
                            required
                            autofocus
                            :tabindex="1"
                            autocomplete="name"
                            v-model="form.name"
                            :placeholder="t('forms.fields.name.placeholder')"
                        />
                        <InputError :message="form.errors.name" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="email">{{ t('forms.fields.email.label') }}</Label>
                        <Input
                            id="email"
                            type="email"
                            required
                            :tabindex="2"
                            autocomplete="email"
                            v-model="form.email"
                            :placeholder="t('forms.fields.email.placeholder')"
                        />
                        <InputError :message="form.errors.email" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="mobile_number">{{ t('forms.fields.mobile_number.label') }}</Label>
                        <Input
                            id="mobile_number"
                            type="tel"
                            required
                            :tabindex="3"
                            autocomplete="tel"
                            v-model="form.mobile_number"
                            :placeholder="t('forms.fields.mobile_number.placeholder')"
                        />
                        <InputError :message="form.errors.mobile_number" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="password">{{ t('forms.fields.password.label') }}</Label>
                        <Input
                            id="password"
                            type="password"
                            required
                            :tabindex="4"
                            autocomplete="new-password"
                            v-model="form.password"
                            :placeholder="t('forms.fields.password.placeholder')"
                        />
                        <InputError :message="form.errors.password" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="password_confirmation">{{ t('forms.fields.password_confirmation.label') }}</Label>
                        <Input
                            id="password_confirmation"
                            type="password"
                            required
                            :tabindex="5"
                            autocomplete="new-password"
                            v-model="form.password_confirmation"
                            :placeholder="t('forms.fields.password_confirmation.placeholder')"
                        />
                        <InputError :message="form.errors.password_confirmation" />
                    </div>

                    <Button type="submit" class="mt-2 w-full" tabindex="6" :disabled="form.processing">
                        <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
                        {{ t('auth.register.submit') }}
                    </Button>
                </div>

                <div class="text-muted-foreground text-center text-sm">
                    {{ t('auth.have_account') }}
                    <TextLink :href="route('login')" class="underline underline-offset-4" :tabindex="7">
                        {{ t('auth.login.submit') }}
                    </TextLink>
                </div>
            </form>
        </div>
    </AuthBase>
</template>
