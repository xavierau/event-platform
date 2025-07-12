<template>
    <Head :title="pageTitle" />
    <AppLayout>
        <div v-if="locale" class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
                    <div class="p-6 lg:p-8 bg-white dark:bg-gray-800 dark:bg-gradient-to-bl dark:from-gray-700/50 dark:via-transparent border-b border-gray-200 dark:border-gray-700">
                        <PageHeader :title="pageTitle" :subtitle="t('users.edit_subtitle')" />

                        <form v-if="props.user" @submit.prevent="submit" class="mt-6 space-y-6">
                            <div>
                                <Label for="name">{{ t('fields.name') }}</Label>
                                <Input id="name" type="text" :defaultValue="props.user.name" class="mt-1 block w-full" disabled />
                            </div>

                            <div>
                                <Label for="email">{{ t('fields.email') }}</Label>
                                <Input id="email" type="email" :defaultValue="props.user.email" class="mt-1 block w-full" disabled />
                            </div>

                            <div>
                                <Label for="membership_level">{{ t('fields.membership_level') }}</Label>
                                <Input id="membership_level" type="text" :defaultValue="props.user.membership_level" class="mt-1 block w-full" disabled />
                            </div>

                            <div>
                                <Label for="organization">{{ t('fields.organization') }}</Label>
                                <Input id="organization" type="text" :defaultValue="props.user.organizer_info" class="mt-1 block w-full" disabled />
                            </div>

                            <div class="flex items-center space-x-2">
                                 <Switch id="is_commenting_blocked" v-model:checked="form.is_commenting_blocked" />
                                 <Label for="is_commenting_blocked">{{ t('fields.commenting_blocked') }}</Label>
                             </div>
                             <div v-if="form.errors.is_commenting_blocked" class="text-sm text-red-600">
                                 {{ form.errors.is_commenting_blocked }}
                             </div>

                            <div class="flex items-center gap-4">
                                <Button :disabled="form.processing">{{ t('actions.save') }}</Button>
                                 <Transition enter-active-class="transition ease-in-out" enter-from-class="opacity-0" leave-active-class="transition ease-in-out" leave-to-class="opacity-0">
                                     <p v-if="form.recentlySuccessful" class="text-sm text-gray-600 dark:text-gray-300">{{ t('common.saved') }}</p>
                                 </Transition>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/components/Shared/PageHeader.vue';
import { Switch } from '@/components/ui/switch';
import Label from '@/components/ui/label/Label.vue';
import Input from '@/components/ui/input/Input.vue';
import Button from '@/components/ui/button/Button.vue';
import type { User } from '@/types';

const props = defineProps<{
    user: User;
}>();

const { t, locale } = useI18n();

const form = useForm({
    is_commenting_blocked: props.user ? props.user.is_commenting_blocked : false,
});

const pageTitle = computed(() => t('users.edit_title', { name: props.user.name }));

const submit = () => {
    if (props.user) {
        form.patch(route('admin.users.update', props.user.id), {
            preserveScroll: true,
        });
    }
};
</script>
