<script setup lang="ts">
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import UserMenuContent from '@/components/UserMenuContent.vue';
import LocaleSwitcher from '@/components/Shared/LocaleSwitcher.vue';
import { getInitials } from '@/composables/useInitials';
import type { User } from '@/types';

interface Props {
  showSearch?: boolean;
  showLocationSelector?: boolean;
}

withDefaults(defineProps<Props>(), {
  showSearch: true,
  showLocationSelector: true,
});

const page = usePage();
const auth = computed(() => page.props.auth as { user?: User });
const isAuthenticated = computed(() => !!auth.value?.user);
</script>

<template>
  <header class="bg-white dark:bg-gray-800 shadow-sm sticky top-0 z-50 border-b dark:border-gray-700">
    <div class="container mx-auto flex justify-between items-center p-4">
      <!-- Left side: Logo, Location, Search -->
      <div class="flex items-center space-x-4 flex-1 min-w-0">

        <!-- Location Selector -->
        <!-- <div v-if="showLocationSelector" class="text-sm text-gray-600 dark:text-gray-300 cursor-pointer hover:text-indigo-600 dark:hover:text-indigo-400">
          <span class="font-semibold">Nationwide</span> ▼
        </div> -->

        <!-- Search Bar -->
        <input
          v-if="showSearch"
          type="search"
          placeholder="Search events, artists, venues..."
          class="p-3 border border-gray-300 dark:border-gray-600 rounded-full text-sm focus:ring-indigo-500 focus:border-indigo-500 w-full max-w-xs md:max-w-md lg:max-w-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-200 placeholder-gray-500 dark:placeholder-gray-400"
        />
      </div>

      <!-- Right side: Language Switcher & Authentication -->
      <div class="flex items-center space-x-3 flex-shrink-0">
        <!-- Language Switcher -->
        <LocaleSwitcher variant="dropdown" />

        <!-- Unauthenticated: Login/Register buttons -->
        <template v-if="!isAuthenticated">
          <Button variant="ghost" as-child>
            <Link :href="route('login')" class="text-sm font-medium">
              Log in
            </Link>
          </Button>
        </template>

        <!-- Authenticated: User menu -->
        <template v-else>
          <DropdownMenu>
            <DropdownMenuTrigger as-child>
              <Button
                variant="ghost"
                size="icon"
                class="relative size-10 w-auto rounded-full p-1 focus-within:ring-2 focus-within:ring-primary"
              >
                <Avatar class="size-8 overflow-hidden rounded-full">
                  <AvatarImage v-if="auth.user?.avatar" :src="auth.user.avatar" :alt="auth.user.name" />
                  <AvatarFallback class="rounded-lg bg-neutral-200 font-semibold text-black dark:bg-neutral-700 dark:text-white">
                    {{ getInitials(auth.user?.name || '') }}
                  </AvatarFallback>
                </Avatar>
              </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" class="w-56">
              <UserMenuContent :user="auth.user!" />
            </DropdownMenuContent>
          </DropdownMenu>
        </template>
      </div>
    </div>
  </header>
</template>
