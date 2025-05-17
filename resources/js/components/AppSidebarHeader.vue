<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItemType } from '@/types';

withDefaults(defineProps<{
    breadcrumbs?: BreadcrumbItemType[];
    pageTitle?: string;
}>(),{
    breadcrumbs:()=>[],
    pageTitle: ''
});
</script>

<template>
    <header
        class="flex h-auto min-h-16 shrink-0 items-start gap-2 border-b border-sidebar-border/70 px-6 py-3 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4 flex-col md:flex-row md:items-center"
    >
        <div class="flex items-center gap-2">
            <SidebarTrigger class="-ml-1" />
            <h1 v-if="pageTitle" class="text-xl font-semibold text-gray-900 dark:text-white">
                {{ pageTitle }}
            </h1>
        </div>
        <template v-if="breadcrumbs && breadcrumbs.length > 0">
            <div class="hidden md:block h-6 w-px bg-gray-300 dark:bg-gray-600 mx-2"></div>
            <Breadcrumbs :breadcrumbs="breadcrumbs" class="mt-1 md:mt-0" />
        </template>
    </header>
</template>
