<script setup lang="ts">
import FrontendFooter from '@/components/FrontendFooter.vue';
import type { User } from '@/types';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import QRCode from 'qrcode';
// @ts-expect-error - vue-i18n has no type definitions
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

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
    <Head :title="t('profile.my_profile')" />

    <div class="min-h-screen bg-gray-100 dark:bg-gray-900">
        <!-- Header Section -->
        <header class="sticky top-0 z-50 border-b bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <div class="relative container mx-auto flex items-center p-4">
                <Link href="/" class="absolute left-4 text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300">
                    {{ t('profile.back') }}
                </Link>
                <h1 class="flex-1 text-center text-xl font-semibold text-gray-900 dark:text-gray-100">{{ t('profile.my_profile') }}</h1>
            </div>
        </header>

        <main class="container mx-auto px-4 py-6 pb-24">
            <!-- Profile Information Section -->
            <section class="mb-8 rounded-lg bg-white p-6 shadow dark:bg-gray-800">
                <div class="mb-6 flex items-center justify-between">
                    <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">{{ t('profile.profile_information') }}</h2>
                    <button
                        @click="toggleEdit"
                        :class="[
                            'rounded-lg px-4 py-2 text-sm font-medium transition-colors',
                            isEditing
                                ? 'bg-gray-200 text-gray-700 hover:bg-gray-300 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600'
                                : 'bg-indigo-600 text-white hover:bg-indigo-700 dark:bg-indigo-500 dark:hover:bg-indigo-600',
                        ]"
                    >
                        {{ isEditing ? t('profile.cancel') : t('profile.edit') }}
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
                            <p class="text-sm text-gray-500 dark:text-gray-400">{{ t('profile.member_since') }} {{ joinedDate }}</p>
                        </div>
                    </div>
                </div>

                <!-- Edit Mode -->
                <form v-else @submit.prevent="updateProfile" class="space-y-4">
                    <div>
                        <label for="name" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('profile.name') }}</label>
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
                        <label for="email" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('profile.email') }}</label>
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
                            {{ form.processing ? t('profile.saving') : t('profile.save_changes') }}
                        </button>
                        <button
                            type="button"
                            @click="toggleEdit"
                            class="rounded-lg bg-gray-200 px-4 py-2 font-medium text-gray-700 hover:bg-gray-300 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600"
                        >
                            {{ t('profile.cancel') }}
                        </button>
                    </div>
                </form>

                <!-- Email Verification Notice -->
                <div
                    v-if="mustVerifyEmail && !user.email_verified_at"
                    class="mt-4 rounded-lg border border-yellow-200 bg-yellow-50 p-4 dark:border-yellow-700 dark:bg-yellow-900/20"
                >
                    <div class="flex items-start space-x-3">
                        <svg class="h-5 w-5 text-yellow-600 dark:text-yellow-400 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                        </svg>
                        <div class="flex-1">
                            <h3 class="text-sm font-medium text-yellow-800 dark:text-yellow-200">
                                {{ t('profile.email_verification_required') }}
                            </h3>
                            <div class="mt-1 text-sm text-yellow-700 dark:text-yellow-300">
                                <p>{{ t('profile.email_unverified_message') }}</p>
                                <p class="mt-1">{{ t('profile.verification_expire_info') }}</p>
                            </div>
                            <div class="mt-3">
                                <Link
                                    :href="route('verification.send')"
                                    method="post"
                                    as="button"
                                    class="inline-flex items-center rounded-md bg-yellow-100 px-3 py-2 text-sm font-medium text-yellow-800 hover:bg-yellow-200 focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:ring-offset-2 dark:bg-yellow-900/50 dark:text-yellow-200 dark:hover:bg-yellow-900"
                                >
                                    {{ t('profile.resend_verification_email') }}
                                </Link>
                            </div>
                        </div>
                    </div>

                    <div v-if="status === 'verification-link-sent'" class="mt-3 text-sm font-medium text-green-600 dark:text-green-400">
                        {{ t('profile.verification_link_sent') }}
                    </div>
                </div>

                <!-- Success Message -->
                <div v-if="status" class="mt-4 rounded-lg border border-green-200 bg-green-50 p-4 dark:border-green-700 dark:bg-green-900/20">
                    <p class="text-sm text-green-800 dark:text-green-200">{{ status }}</p>
                </div>
            </section>

            <!-- Section for Membership -->
            <section class="mb-8 rounded-lg bg-white p-6 shadow dark:bg-gray-800">
                <div class="mb-6 flex items-center justify-between">
                    <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">{{ t('profile.membership') }}</h2>
                    <div class="flex space-x-2">
                        <button
                            v-if="membershipInfo.isActive"
                            @click="showMembershipQrCode"
                            class="rounded-lg bg-gray-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-gray-700 dark:bg-gray-500 dark:hover:bg-gray-600"
                        >
                            {{ t('profile.show_qr') }}
                        </button>
                        <Link
                            :href="route('my-membership')"
                            class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-indigo-700 dark:bg-indigo-500 dark:hover:bg-indigo-600"
                        >
                            {{ t('profile.manage') }}
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
                            <span v-if="membershipInfo.isActive">{{ t('profile.expires_on') }} {{ membershipInfo.expiresAt }}</span>
                            <span v-else>{{ t('profile.upgrade_to_unlock') }}</span>
                        </p>
                    </div>
                    <div v-if="!membershipInfo.isActive" class="text-right">
                        <Link
                            :href="route('my-membership')"
                            class="text-sm font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300"
                        >
                            {{ t('profile.upgrade') }}
                        </Link>
                    </div>
                </div>
            </section>

            <!-- Account Settings Section -->
            <section class="mb-8 rounded-lg bg-white p-6 shadow dark:bg-gray-800" style="display: none;">
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
                    <h3 class="text-lg font-semibold">{{ t('profile.membership_qr_title') }}</h3>
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
                            {{ t('profile.expires_on') }}: {{ membershipInfo.expiresAt }}
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
                            <div class="text-sm text-gray-600">{{ t('profile.generating_qr') }}</div>
                        </div>
                    </div>

                    <p class="text-sm text-gray-600">{{ t('profile.qr_verification_instruction') }}</p>
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
