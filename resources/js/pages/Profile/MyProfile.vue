<script setup lang="ts">
import FrontendFooter from '@/components/FrontendFooter.vue';
import type { User } from '@/types';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import QRCode from 'qrcode';

const props = defineProps({
    user: {
        type: Object as () => User,
        required: true,
    },
    membership: {
        type: Object,
        default: null,
    },
    mustVerifyEmail: {
        type: Boolean,
        default: false,
    },
    status: {
        type: String,
        default: '',
    },
});

// Form for updating profile
const form = useForm({
    name: props.user.name,
    email: props.user.email,
});

const isEditing = ref(false);
const showMembershipQrModal = ref(false);
const membershipQrCodeUrl = ref<string>('');

function toggleEdit() {
    if (isEditing.value) {
        // Cancel editing, reset form
        form.reset();
    }
    isEditing.value = !isEditing.value;
}

async function showMembershipQrCode() {
    // Generate QR code data for membership
    const membershipData = {
        userId: props.user.id,
        userName: props.user.name,
        email: props.user.email,
        membershipLevel: membershipInfo.value.level,
        membershipStatus: membershipInfo.value.status,
        expiresAt: membershipInfo.value.expiresAt,
        timestamp: new Date().toISOString(),
    };
    
    try {
        // Generate QR code from the membership data
        const qrCodeUrl = await QRCode.toDataURL(JSON.stringify(membershipData), {
            width: 256,
            margin: 2,
            color: {
                dark: '#000000',
                light: '#FFFFFF'
            }
        });
        
        membershipQrCodeUrl.value = qrCodeUrl;
    } catch (error) {
        console.error('Error generating membership QR code:', error);
        membershipQrCodeUrl.value = '';
    }
    
    showMembershipQrModal.value = true;
}

function updateProfile() {
    form.patch(route('profile.update'), {
        preserveScroll: true,
        onSuccess: () => {
            isEditing.value = false;
        },
    });
}

const joinedDate = computed(() => {
    if (!props.user.created_at) return 'N/A';
    return new Date(props.user.created_at).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });
});

const membershipInfo = computed(() => {
    if (props.membership && props.membership.status === 'active') {
        return {
            level: props.membership.level?.name?.en || 'Premium',
            status: 'Active',
            expiresAt: props.membership.expires_at
                ? new Date(props.membership.expires_at).toLocaleDateString('en-US', {
                      year: 'numeric',
                      month: 'long',
                      day: 'numeric',
                  })
                : 'N/A',
            isActive: true,
        };
    }

    // Default/Standard level for users without membership
    return {
        level: 'Standard',
        status: 'Free',
        expiresAt: 'Never',
        isActive: false,
    };
});
</script>

<template>
    <Head title="My Profile" />

    <div class="min-h-screen bg-gray-100 dark:bg-gray-900">
        <!-- Header Section -->
        <header class="sticky top-0 z-50 border-b bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <div class="relative container mx-auto flex items-center p-4">
                <Link href="/" class="absolute left-4 text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300">
                    &larr; Back
                </Link>
                <h1 class="flex-1 text-center text-xl font-semibold text-gray-900 dark:text-gray-100">My Profile</h1>
            </div>
        </header>

        <main class="container mx-auto px-4 py-6 pb-24">
            <!-- Profile Information Section -->
            <section class="mb-8 rounded-lg bg-white p-6 shadow dark:bg-gray-800">
                <div class="mb-6 flex items-center justify-between">
                    <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">Profile Information</h2>
                    <button
                        @click="toggleEdit"
                        :class="[
                            'rounded-lg px-4 py-2 text-sm font-medium transition-colors',
                            isEditing
                                ? 'bg-gray-200 text-gray-700 hover:bg-gray-300 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600'
                                : 'bg-indigo-600 text-white hover:bg-indigo-700 dark:bg-indigo-500 dark:hover:bg-indigo-600',
                        ]"
                    >
                        {{ isEditing ? 'Cancel' : 'Edit' }}
                    </button>
                </div>

                <!-- Display Mode -->
                <div v-if="!isEditing" class="space-y-4">
                    <div class="flex items-center space-x-4">
                        <div class="flex h-16 w-16 items-center justify-center rounded-full bg-indigo-100 dark:bg-indigo-900">
                            <span class="text-2xl font-bold text-indigo-600 dark:text-indigo-400">
                                {{ user.name.charAt(0).toUpperCase() }}
                            </span>
                        </div>
                        <div>
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">{{ user.name }}</h3>
                            <p class="text-gray-600 dark:text-gray-300">{{ user.email }}</p>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Member since {{ joinedDate }}</p>
                        </div>
                    </div>
                </div>

                <!-- Edit Mode -->
                <form v-else @submit.prevent="updateProfile" class="space-y-4">
                    <div>
                        <label for="name" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300"> Name </label>
                        <input
                            id="name"
                            v-model="form.name"
                            type="text"
                            required
                            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-gray-900 focus:border-indigo-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                        />
                        <div v-if="form.errors.name" class="mt-1 text-sm text-red-600 dark:text-red-400">
                            {{ form.errors.name }}
                        </div>
                    </div>

                    <div>
                        <label for="email" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300"> Email </label>
                        <input
                            id="email"
                            v-model="form.email"
                            type="email"
                            required
                            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-gray-900 focus:border-indigo-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                        />
                        <div v-if="form.errors.email" class="mt-1 text-sm text-red-600 dark:text-red-400">
                            {{ form.errors.email }}
                        </div>
                    </div>

                    <div class="flex space-x-3 pt-4">
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="rounded-lg bg-indigo-600 px-4 py-2 font-medium text-white hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            {{ form.processing ? 'Saving...' : 'Save Changes' }}
                        </button>
                        <button
                            type="button"
                            @click="toggleEdit"
                            class="rounded-lg bg-gray-200 px-4 py-2 font-medium text-gray-700 hover:bg-gray-300 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600"
                        >
                            Cancel
                        </button>
                    </div>
                </form>

                <!-- Email Verification Notice -->
                <div
                    v-if="mustVerifyEmail && !user.email_verified_at"
                    class="mt-4 rounded-lg border border-yellow-200 bg-yellow-50 p-4 dark:border-yellow-700 dark:bg-yellow-900/20"
                >
                    <p class="text-sm text-yellow-800 dark:text-yellow-200">
                        Your email address is unverified. Please check your email for a verification link.
                    </p>
                </div>

                <!-- Success Message -->
                <div v-if="status" class="mt-4 rounded-lg border border-green-200 bg-green-50 p-4 dark:border-green-700 dark:bg-green-900/20">
                    <p class="text-sm text-green-800 dark:text-green-200">{{ status }}</p>
                </div>
            </section>

            <!-- Section for Membership -->
            <section class="mb-8 rounded-lg bg-white p-6 shadow dark:bg-gray-800">
                <div class="mb-6 flex items-center justify-between">
                    <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">Membership</h2>
                    <div class="flex space-x-2">
                        <button
                            v-if="membershipInfo.isActive"
                            @click="showMembershipQrCode"
                            class="rounded-lg bg-gray-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-gray-700 dark:bg-gray-500 dark:hover:bg-gray-600"
                        >
                            Show QR
                        </button>
                        <Link
                            :href="route('my-membership')"
                            class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-indigo-700 dark:bg-indigo-500 dark:hover:bg-indigo-600"
                        >
                            Manage
                        </Link>
                    </div>
                </div>

                <div class="flex items-center space-x-4">
                    <div
                        :class="[
                            'flex h-16 w-16 items-center justify-center rounded-full',
                            membershipInfo.isActive ? 'bg-gradient-to-br from-yellow-400 to-yellow-600' : 'bg-gray-100 dark:bg-gray-700',
                        ]"
                    >
                        <span :class="['text-2xl font-bold', membershipInfo.isActive ? 'text-white' : 'text-gray-600 dark:text-gray-300']">
                            {{ membershipInfo.isActive ? '👑' : '📋' }}
                        </span>
                    </div>
                    <div class="flex-1">
                        <div class="flex items-center space-x-2">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                                {{ membershipInfo.level }}
                            </h3>
                            <span
                                :class="[
                                    'rounded-full px-2 py-1 text-xs font-medium',
                                    membershipInfo.isActive
                                        ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
                                        : 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200',
                                ]"
                            >
                                {{ membershipInfo.status }}
                            </span>
                        </div>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                            <span v-if="membershipInfo.isActive"> Expires on {{ membershipInfo.expiresAt }} </span>
                            <span v-else> Upgrade to unlock premium features </span>
                        </p>
                    </div>
                    <div v-if="!membershipInfo.isActive" class="text-right">
                        <Link
                            :href="route('my-membership')"
                            class="text-sm font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300"
                        >
                            Upgrade →
                        </Link>
                    </div>
                </div>
            </section>

            <!-- Account Settings Section -->
            <section class="mb-8 rounded-lg bg-white p-6 shadow dark:bg-gray-800">
                <h2 class="mb-4 text-xl font-semibold text-gray-800 dark:text-gray-200">Account Settings</h2>
                <div class="space-y-4">
                    <div class="flex items-center justify-between border-b border-gray-200 py-3 dark:border-gray-700">
                        <div>
                            <h3 class="text-sm font-medium text-gray-900 dark:text-gray-100">Email Notifications</h3>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Receive updates about your bookings and events</p>
                        </div>
                        <button class="text-sm font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300">
                            Manage
                        </button>
                    </div>
                    <div class="flex items-center justify-between border-b border-gray-200 py-3 dark:border-gray-700">
                        <div>
                            <h3 class="text-sm font-medium text-gray-900 dark:text-gray-100">Privacy Settings</h3>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Control who can see your activity</p>
                        </div>
                        <button class="text-sm font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300">
                            Manage
                        </button>
                    </div>
                    <div class="flex items-center justify-between py-3">
                        <div>
                            <h3 class="text-sm font-medium text-gray-900 dark:text-gray-100">Password</h3>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Change your account password</p>
                        </div>
                        <button class="text-sm font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300">
                            Change
                        </button>
                    </div>
                </div>
            </section>
        </main>

        <FrontendFooter />

        <!-- Membership QR Code Modal -->
        <div v-if="showMembershipQrModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
            <div class="mx-4 w-full max-w-md rounded-lg bg-white p-6">
                <div class="mb-4 flex items-start justify-between">
                    <h3 class="text-lg font-semibold">Membership QR Code</h3>
                    <button @click="showMembershipQrModal = false" class="text-gray-400 hover:text-gray-600">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>

                <div class="text-center">
                    <!-- User Info -->
                    <div class="mb-4">
                        <h4 class="font-medium">{{ user.name }}</h4>
                        <p class="text-sm text-gray-600">{{ user.email }}</p>
                        <div class="mt-2">
                            <span
                                :class="[
                                    'inline-block rounded-full px-3 py-1 text-sm font-medium',
                                    membershipInfo.isActive
                                        ? 'bg-gradient-to-r from-yellow-400 to-yellow-600 text-white'
                                        : 'bg-gray-100 text-gray-700',
                                ]"
                            >
                                {{ membershipInfo.level }} {{ membershipInfo.status }}
                            </span>
                        </div>
                        <p v-if="membershipInfo.isActive" class="mt-1 text-xs text-gray-500">
                            Expires: {{ membershipInfo.expiresAt }}
                        </p>
                    </div>

                    <!-- QR Code -->
                    <div class="mx-auto mb-4 flex h-64 w-64 items-center justify-center rounded-lg border border-gray-200 bg-white">
                        <img
                            v-if="membershipQrCodeUrl"
                            :src="membershipQrCodeUrl"
                            alt="Membership QR Code"
                            class="h-full w-full object-contain p-2"
                        />
                        <div v-else class="text-center">
                            <div class="mb-2 text-2xl text-gray-400">📱</div>
                            <div class="text-sm text-gray-600">Generating QR Code...</div>
                        </div>
                    </div>

                    <p class="text-sm text-gray-600">Present this QR code for membership verification</p>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.shadow-top-lg {
    box-shadow:
        0 -4px 6px -1px rgb(0 0 0 / 0.05),
        0 -2px 4px -2px rgb(0 0 0 / 0.05);
}
</style>
