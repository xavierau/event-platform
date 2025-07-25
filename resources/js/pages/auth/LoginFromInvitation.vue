<template>
    <GuestLayout>
        <Head title="Login to Accept Invitation" />

        <div class="mb-4 text-sm text-gray-600">
            <p class="mb-2">
                You've been invited to join <strong>{{ invitation.organizer_name }}</strong> as a <strong>{{ invitation.role }}</strong
                >.
            </p>
            <p class="mb-2">
                Please log in with your existing account (<strong>{{ invitation.user_name }}</strong
                >) to accept this invitation.
            </p>
        </div>

        <div v-if="status" class="mb-4 text-sm font-medium text-green-600">
            {{ status }}
        </div>

        <form @submit.prevent="submit">
            <div>
                <InputLabel for="email" value="Email" />
                <TextInput id="email" type="email" class="mt-1 block w-full" v-model="form.email" readonly disabled />
                <InputError class="mt-2" :message="form.errors.email" />
            </div>

            <div class="mt-4">
                <InputLabel for="password" value="Password" />
                <TextInput
                    id="password"
                    type="password"
                    class="mt-1 block w-full"
                    v-model="form.password"
                    required
                    autofocus
                    autocomplete="current-password"
                />
                <InputError class="mt-2" :message="form.errors.password" />
            </div>

            <div class="mt-4 block">
                <label class="flex items-center">
                    <Checkbox name="remember" v-model:checked="form.remember" />
                    <span class="ml-2 text-sm text-gray-600">Remember me</span>
                </label>
            </div>

            <div class="mt-6 flex items-center justify-between">
                <div class="flex flex-col space-y-2">
                    <Link
                        v-if="canResetPassword"
                        :href="route('password.request')"
                        class="rounded-md text-sm text-gray-600 underline hover:text-gray-900 focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 focus:outline-none"
                    >
                        Forgot your password?
                    </Link>
                    <Link
                        :href="route('home')"
                        class="rounded-md text-sm text-gray-600 underline hover:text-gray-900 focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 focus:outline-none"
                    >
                        Cancel
                    </Link>
                </div>

                <PrimaryButton class="ml-4" :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                    Log in & Accept Invitation
                </PrimaryButton>
            </div>
        </form>
    </GuestLayout>
</template>

<script setup lang="ts">
import Checkbox from '@/Components/Checkbox.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    canResetPassword: {
        type: Boolean,
    },
    status: {
        type: String,
    },
    invitation: {
        type: Object,
        required: true,
    },
    return_url: {
        type: String,
        required: true,
    },
});
const form = useForm({
    email: props.invitation.email,
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
        onSuccess: () => {
            // Redirect to the invitation acceptance URL
            window.location.href = props.return_url;
        },
    });
};
</script>
