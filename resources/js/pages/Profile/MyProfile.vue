<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import type { User } from '@/types';

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

function toggleEdit() {
  if (isEditing.value) {
    // Cancel editing, reset form
    form.reset();
  }
  isEditing.value = !isEditing.value;
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
      expiresAt: props.membership.expires_at ? new Date(props.membership.expires_at).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
      }) : 'N/A',
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
    <header class="bg-white dark:bg-gray-800 shadow-sm sticky top-0 z-50 border-b dark:border-gray-700">
      <div class="container mx-auto flex items-center p-4 relative">
        <Link href="/" class="text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300 absolute left-4">
          &larr; Back
        </Link>
        <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100 flex-1 text-center">My Profile</h1>
      </div>
    </header>

    <main class="container mx-auto py-6 px-4 pb-24">
      <!-- Profile Information Section -->
      <section class="mb-8 bg-white dark:bg-gray-800 rounded-lg shadow p-6">
        <div class="flex items-center justify-between mb-6">
          <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">Profile Information</h2>
          <button
            @click="toggleEdit"
            :class="[
              'px-4 py-2 rounded-lg text-sm font-medium transition-colors',
              isEditing
                ? 'bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-300 dark:hover:bg-gray-600'
                : 'bg-indigo-600 text-white hover:bg-indigo-700 dark:bg-indigo-500 dark:hover:bg-indigo-600'
            ]"
          >
            {{ isEditing ? 'Cancel' : 'Edit' }}
          </button>
        </div>

        <!-- Display Mode -->
        <div v-if="!isEditing" class="space-y-4">
          <div class="flex items-center space-x-4">
            <div class="w-16 h-16 bg-indigo-100 dark:bg-indigo-900 rounded-full flex items-center justify-center">
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
            <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              Name
            </label>
            <input
              id="name"
              v-model="form.name"
              type="text"
              required
              class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-indigo-500 focus:border-indigo-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
            />
            <div v-if="form.errors.name" class="mt-1 text-sm text-red-600 dark:text-red-400">
              {{ form.errors.name }}
            </div>
          </div>

          <div>
            <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              Email
            </label>
            <input
              id="email"
              v-model="form.email"
              type="email"
              required
              class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-indigo-500 focus:border-indigo-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
            />
            <div v-if="form.errors.email" class="mt-1 text-sm text-red-600 dark:text-red-400">
              {{ form.errors.email }}
            </div>
          </div>

          <div class="flex space-x-3 pt-4">
            <button
              type="submit"
              :disabled="form.processing"
              class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed font-medium"
            >
              {{ form.processing ? 'Saving...' : 'Save Changes' }}
            </button>
            <button
              type="button"
              @click="toggleEdit"
              class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-300 dark:hover:bg-gray-600 font-medium"
            >
              Cancel
            </button>
          </div>
        </form>

        <!-- Email Verification Notice -->
        <div v-if="mustVerifyEmail && !user.email_verified_at" class="mt-4 p-4 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-700 rounded-lg">
          <p class="text-sm text-yellow-800 dark:text-yellow-200">
            Your email address is unverified. Please check your email for a verification link.
          </p>
        </div>

        <!-- Success Message -->
        <div v-if="status" class="mt-4 p-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-700 rounded-lg">
          <p class="text-sm text-green-800 dark:text-green-200">{{ status }}</p>
        </div>
      </section>

      <!-- Section for Membership -->
      <section class="mb-8 bg-white dark:bg-gray-800 rounded-lg shadow p-6">
        <div class="flex items-center justify-between mb-6">
          <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">Membership</h2>
          <Link
            :href="route('my-membership')"
            class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 dark:bg-indigo-500 dark:hover:bg-indigo-600 text-sm font-medium transition-colors"
          >
            Manage
          </Link>
        </div>

        <div class="flex items-center space-x-4">
          <div
            :class="[
              'w-16 h-16 rounded-full flex items-center justify-center',
              membershipInfo.isActive
                ? 'bg-gradient-to-br from-yellow-400 to-yellow-600'
                : 'bg-gray-100 dark:bg-gray-700'
            ]"
          >
            <span
              :class="[
                'text-2xl font-bold',
                membershipInfo.isActive
                  ? 'text-white'
                  : 'text-gray-600 dark:text-gray-300'
              ]"
            >
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
                  'px-2 py-1 rounded-full text-xs font-medium',
                  membershipInfo.isActive
                    ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
                    : 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200'
                ]"
              >
                {{ membershipInfo.status }}
              </span>
            </div>
            <p class="text-sm text-gray-600 dark:text-gray-300 mt-1">
              <span v-if="membershipInfo.isActive">
                Expires on {{ membershipInfo.expiresAt }}
              </span>
              <span v-else>
                Upgrade to unlock premium features
              </span>
            </p>
          </div>
          <div v-if="!membershipInfo.isActive" class="text-right">
            <Link
              :href="route('my-membership')"
              class="text-indigo-600 dark:text-indigo-400 text-sm font-medium hover:text-indigo-800 dark:hover:text-indigo-300"
            >
              Upgrade →
            </Link>
          </div>
        </div>
      </section>

      <!-- Account Settings Section -->
      <section class="mb-8 bg-white dark:bg-gray-800 rounded-lg shadow p-6">
        <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200 mb-4">Account Settings</h2>
        <div class="space-y-4">
          <div class="flex items-center justify-between py-3 border-b border-gray-200 dark:border-gray-700">
            <div>
              <h3 class="text-sm font-medium text-gray-900 dark:text-gray-100">Email Notifications</h3>
              <p class="text-xs text-gray-500 dark:text-gray-400">Receive updates about your bookings and events</p>
            </div>
            <button class="text-indigo-600 dark:text-indigo-400 text-sm font-medium hover:text-indigo-800 dark:hover:text-indigo-300">
              Manage
            </button>
          </div>
          <div class="flex items-center justify-between py-3 border-b border-gray-200 dark:border-gray-700">
            <div>
              <h3 class="text-sm font-medium text-gray-900 dark:text-gray-100">Privacy Settings</h3>
              <p class="text-xs text-gray-500 dark:text-gray-400">Control who can see your activity</p>
            </div>
            <button class="text-indigo-600 dark:text-indigo-400 text-sm font-medium hover:text-indigo-800 dark:hover:text-indigo-300">
              Manage
            </button>
          </div>
          <div class="flex items-center justify-between py-3">
            <div>
              <h3 class="text-sm font-medium text-gray-900 dark:text-gray-100">Password</h3>
              <p class="text-xs text-gray-500 dark:text-gray-400">Change your account password</p>
            </div>
            <button class="text-indigo-600 dark:text-indigo-400 text-sm font-medium hover:text-indigo-800 dark:hover:text-indigo-300">
              Change
            </button>
          </div>
        </div>
      </section>

    </main>

    <!-- Fixed Footer/Bottom Bar -->
    <footer class="fixed bottom-0 left-0 right-0 bg-white dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700 p-3 shadow-top-lg z-50">
      <div class="container mx-auto flex justify-around items-center">
        <Link href="/" class="text-center text-xs text-gray-600 dark:text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-300">
          <span class="block text-xl">🏠</span>
          <span>Home</span>
        </Link>
        <Link :href="route('my-bookings')" class="text-center text-xs text-gray-600 dark:text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-300">
          <span class="block text-xl">🎟️</span>
          <span>My Bookings</span>
        </Link>
        <Link :href="route('my-wishlist')" class="text-center text-xs text-gray-600 dark:text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-300">
          <span class="block text-xl">❤️</span>
          <span>My Wishlist</span>
        </Link>
        <Link :href="route('my-wallet')" class="text-center text-xs text-gray-600 dark:text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-300">
          <span class="block text-xl">💳</span>
          <span>My Wallet</span>
        </Link>
      </div>
    </footer>
  </div>
</template>

<style scoped>
.shadow-top-lg {
  box-shadow: 0 -4px 6px -1px rgb(0 0 0 / 0.05), 0 -2px 4px -2px rgb(0 0 0 / 0.05);
}
</style>
