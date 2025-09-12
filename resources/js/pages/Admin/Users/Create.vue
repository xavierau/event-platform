<template>
    <Head :title="t('users.create_title')" />
    <AppLayout>
        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="overflow-hidden bg-white shadow-xl sm:rounded-lg dark:bg-gray-800">
                    <div class="border-b border-gray-200 bg-white p-6 lg:p-8 dark:border-gray-700 dark:bg-gray-800 dark:bg-gradient-to-bl dark:from-gray-700/50 dark:via-transparent">
                        <PageHeader :title="t('users.create_title')" :subtitle="t('users.create_subtitle')" />

                        <form @submit.prevent="submit" class="mt-6 space-y-6">
                            <div>
                                <Label for="name" required>{{ t('fields.name') }}</Label>
                                <Input 
                                    id="name" 
                                    v-model="form.name"
                                    type="text" 
                                    class="mt-1 block w-full" 
                                    required 
                                    :placeholder="t('forms.fields.name.placeholder')"
                                />
                                <div v-if="form.errors.name" class="text-sm text-red-600">
                                    {{ form.errors.name }}
                                </div>
                            </div>

                            <div>
                                <Label for="email" required>{{ t('fields.email') }}</Label>
                                <Input 
                                    id="email" 
                                    v-model="form.email"
                                    type="email" 
                                    class="mt-1 block w-full" 
                                    required 
                                    :placeholder="t('forms.fields.email.placeholder')"
                                />
                                <div v-if="form.errors.email" class="text-sm text-red-600">
                                    {{ form.errors.email }}
                                </div>
                            </div>

                            <div>
                                <Label for="mobile_number">{{ t('fields.mobile_number') }}</Label>
                                <Input 
                                    id="mobile_number" 
                                    v-model="form.mobile_number"
                                    type="tel" 
                                    class="mt-1 block w-full" 
                                    :placeholder="t('forms.fields.mobile_number.placeholder')"
                                />
                                <div v-if="form.errors.mobile_number" class="text-sm text-red-600">
                                    {{ form.errors.mobile_number }}
                                </div>
                            </div>

                            <div>
                                <Label for="password" required>{{ t('fields.password') }}</Label>
                                <Input 
                                    id="password" 
                                    v-model="form.password"
                                    type="password" 
                                    class="mt-1 block w-full" 
                                    required 
                                    :placeholder="t('forms.fields.password.placeholder')"
                                />
                                <div v-if="form.errors.password" class="text-sm text-red-600">
                                    {{ form.errors.password }}
                                </div>
                            </div>

                            <div>
                                <Label for="password_confirmation" required>{{ t('fields.password_confirmation') }}</Label>
                                <Input 
                                    id="password_confirmation" 
                                    v-model="form.password_confirmation"
                                    type="password" 
                                    class="mt-1 block w-full" 
                                    required 
                                    :placeholder="t('forms.fields.password_confirmation.placeholder')"
                                />
                                <div v-if="form.errors.password_confirmation" class="text-sm text-red-600">
                                    {{ form.errors.password_confirmation }}
                                </div>
                            </div>

                            <!-- Membership Level Selection -->
                            <div>
                                <Label for="membership_level_id">{{ t('fields.assign_membership_level') }}</Label>
                                <select
                                    id="membership_level_id"
                                    v-model="form.membership_level_id"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500 sm:text-sm dark:border-gray-600 dark:bg-gray-700"
                                >
                                    <option :value="null">{{ t('common.no_membership') }}</option>
                                    <option v-for="level in membershipLevels" :key="level.id" :value="level.id">
                                        {{ level.name[locale] || level.name.en }} 
                                        ({{ level.duration_months }} {{ t('common.months') }})
                                        <span v-if="level.price > 0"> - ${{ level.price }}</span>
                                        <span v-else> - {{ t('common.free') }}</span>
                                    </option>
                                </select>
                                <div v-if="form.errors.membership_level_id" class="text-sm text-red-600">
                                    {{ form.errors.membership_level_id }}
                                </div>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                    {{ t('helpers.optional_membership_assignment') }}
                                </p>
                            </div>

                            <!-- Custom Duration (if membership selected) -->
                            <div v-if="form.membership_level_id">
                                <Label for="membership_duration_months">{{ t('fields.custom_duration_months') }}</Label>
                                <Input
                                    id="membership_duration_months"
                                    v-model="form.membership_duration_months"
                                    type="number"
                                    class="mt-1 block w-full"
                                    min="1"
                                    max="120"
                                    :placeholder="t('forms.fields.membership_duration_months.placeholder')"
                                />
                                <div v-if="form.errors.membership_duration_months" class="text-sm text-red-600">
                                    {{ form.errors.membership_duration_months }}
                                </div>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                    {{ t('helpers.custom_duration_help') }}
                                </p>
                            </div>

                            <!-- Admin Notes/Reason -->
                            <div>
                                <Label for="reason">{{ t('fields.admin_notes') }}</Label>
                                <textarea
                                    id="reason"
                                    v-model="form.reason"
                                    rows="3"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500 sm:text-sm dark:border-gray-600 dark:bg-gray-700"
                                    :placeholder="t('forms.fields.admin_notes.placeholder')"
                                ></textarea>
                                <div v-if="form.errors.reason" class="text-sm text-red-600">
                                    {{ form.errors.reason }}
                                </div>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                    {{ t('helpers.admin_notes_help') }}
                                </p>
                            </div>

                            <div class="flex items-center gap-4">
                                <Button type="submit" :disabled="form.processing">
                                    {{ form.processing ? t('actions.creating') : t('actions.create_user') }}
                                </Button>
                                <Link 
                                    :href="route('admin.users.index')" 
                                    class="text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-200"
                                >
                                    {{ t('actions.cancel') }}
                                </Link>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/components/Shared/PageHeader.vue';
import Label from '@/components/ui/label/Label.vue';
import Input from '@/components/ui/input/Input.vue';
import Button from '@/components/ui/button/Button.vue';
import type { MembershipLevel } from '@/types';

interface Props {
    membershipLevels: MembershipLevel[];
}

const props = defineProps<Props>();
const { t, locale } = useI18n();

const form = useForm({
    name: '',
    email: '',
    mobile_number: '',
    password: '',
    password_confirmation: '',
    membership_level_id: null as number | null,
    membership_duration_months: null as number | null,
    reason: '',
});

const submit = () => {
    form.post(route('admin.users.store'), {
        onSuccess: () => form.reset(),
        preserveScroll: true,
    });
};
</script>