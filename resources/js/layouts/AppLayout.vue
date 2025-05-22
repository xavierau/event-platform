<script setup lang="ts">
import InnerAppSidebarLayout from '@/layouts/app/AppSidebarLayout.vue';
import type { BreadcrumbItemType } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

// Stagewise Toolbar Integration
import { StagewiseToolbar } from '@stagewise/toolbar-vue';

const stagewiseConfig = ref({ // Use ref for reactivity if config needs to change, though likely static here
  plugins: []
});

// Vite specific check for development mode
const isDevelopment = import.meta.env.DEV;
// End Stagewise Toolbar Integration

// Props that this AppLayout component itself might receive if nested or used with direct props.
// These are less likely to be used for pageTitle/breadcrumbs if this is always the root layout for a page.
interface Props {
    breadcrumbs?: BreadcrumbItemType[];
    pageTitle?: string;
}
const explicitProps = defineProps<Props>();

const page = usePage();

// Prioritize page props from controller (via usePage()), fallback to explicitly passed props (if any).
const currentBreadcrumbs = computed(() => {
    // Explicitly cast page.props.breadcrumbs, as usePage().props are initially unknown
    const pageSharedBreadcrumbs = page.props.breadcrumbs as BreadcrumbItemType[] | undefined;
    if (pageSharedBreadcrumbs && pageSharedBreadcrumbs.length > 0) {
        return pageSharedBreadcrumbs;
    }
    return explicitProps.breadcrumbs || []; // Fallback to props passed directly to AppLayout
});

const currentPageTitle = computed(() => {
    // Explicitly cast page.props.pageTitle
    const pageSharedTitle = page.props.pageTitle as string | undefined;
    return pageSharedTitle || explicitProps.pageTitle || ''; // Fallback to props passed directly to AppLayout
});

</script>

<template>
    <InnerAppSidebarLayout :breadcrumbs="currentBreadcrumbs" :page-title="currentPageTitle">
        <slot />
    </InnerAppSidebarLayout>
    <!-- Stagewise Toolbar Integration: Only render in development -->
    <StagewiseToolbar v-if="isDevelopment" :config="stagewiseConfig" />
</template>
