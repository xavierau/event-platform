<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';

import DeleteUser from '@/components/DeleteUser.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { type BreadcrumbItem, type SharedData, type User } from '@/types';

interface Props {
    mustVerifyEmail: boolean;
    status?: string;
}

defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Profile settings',
        href: '/settings/profile',
    },
];

const page = usePage<SharedData>();
const user = page.props.auth.user as User;

const form = useForm({
    name: user.name,
    email: user.email,
    mobile_number: user.mobile_number || '',
});

const submit = () => {
    form.patch(route('profile.update'), {
        preserveScroll: true,
    });
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Profile settings" />

        <SettingsLayout>
            <div class="flex flex-col space-y-6">
                <HeadingSmall title="Profile information" description="Update your name and email address" />

                <form @submit.prevent="submit" class="space-y-6">
                    <div class="grid gap-2">
                        <Label for="name">Name</Label>
                        <Input id="name" class="mt-1 block w-full" v-model="form.name" required autocomplete="name" placeholder="Full name" />
                        <InputError class="mt-2" :message="form.errors.name" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="email">Email address</Label>
                        <Input
                            id="email"
                            type="email"
                            class="mt-1 block w-full"
                            v-model="form.email"
                            required
                            autocomplete="username"
                            placeholder="Email address"
                            :disabled="!user.email_verified_at"
                        />
                        <p v-if="!user.email_verified_at" class="text-sm text-muted-foreground">
                            Email editing is disabled until your email address is verified.
                        </p>
                        <InputError class="mt-2" :message="form.errors.email" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="mobile_number">Mobile Number</Label>
                        <Input
                            id="mobile_number"
                            type="tel"
                            class="mt-1 block w-full"
                            v-model="form.mobile_number"
                            required
                            autocomplete="tel"
                            placeholder="+1234567890"
                        />
                        <InputError class="mt-2" :message="form.errors.mobile_number" />
                    </div>

                    <div v-if="mustVerifyEmail && !user.email_verified_at" class="rounded-lg border border-yellow-200 bg-yellow-50 p-4 dark:border-yellow-800 dark:bg-yellow-900/20">
                        <div class="flex items-start space-x-3">
                            <svg class="h-5 w-5 text-yellow-600 dark:text-yellow-400 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                            </svg>
                            <div class="flex-1">
                                <h3 class="text-sm font-medium text-yellow-800 dark:text-yellow-200">
                                    Email Verification Required
                                </h3>
                                <div class="mt-1 text-sm text-yellow-700 dark:text-yellow-300">
                                    <p>Your email address is unverified. Please check your email and click the verification link.</p>
                                    <p class="mt-1">Verification links expire after 60 minutes.</p>
                                </div>
                                <div class="mt-3">
                                    <Link
                                        :href="route('verification.send')"
                                        method="post"
                                        as="button"
                                        class="inline-flex items-center rounded-md bg-yellow-100 px-3 py-2 text-sm font-medium text-yellow-800 hover:bg-yellow-200 focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:ring-offset-2 dark:bg-yellow-900/50 dark:text-yellow-200 dark:hover:bg-yellow-900"
                                    >
                                        Resend Verification Email
                                    </Link>
                                </div>
                            </div>
                        </div>

                        <div v-if="status === 'verification-link-sent'" class="mt-3 text-sm font-medium text-green-600 dark:text-green-400">
                            ✓ A new verification link has been sent to your email address.
                        </div>
                    </div>

                    <div class="flex items-center gap-4">
                        <Button :disabled="form.processing">Save</Button>

                        <Transition
                            enter-active-class="transition ease-in-out"
                            enter-from-class="opacity-0"
                            leave-active-class="transition ease-in-out"
                            leave-to-class="opacity-0"
                        >
                            <p v-show="form.recentlySuccessful" class="text-sm text-neutral-600">Saved.</p>
                        </Transition>
                    </div>
                </form>
            </div>

            <DeleteUser />
        </SettingsLayout>
    </AppLayout>
</template>
