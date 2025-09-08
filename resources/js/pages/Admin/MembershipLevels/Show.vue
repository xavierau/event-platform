<template>
    <AppLayout>
        <Head :title="getLevelName(membershipLevel)" />
        <div class="space-y-6">
            <div class="flex items-center gap-4">
                <Button variant="ghost" :href="route('admin.membership-levels.index')" class="gap-2">
                    <ArrowLeft class="h-4 w-4" />
                    Back
                </Button>
                <div>
                    <h1 class="text-3xl font-bold">{{ getLevelName(membershipLevel) }}</h1>
                    <p class="text-gray-500">Membership Level Details</p>
                </div>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="bg-white p-6 rounded-lg shadow">
                    <h3 class="text-lg font-semibold">Basic Information</h3>
                    <p class="text-gray-600">{{ getDescription(membershipLevel) }}</p>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup lang="ts">
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';

interface MembershipLevel {
    id: number;
    name: Record<string, string>;
    description?: Record<string, string>;
    is_active: boolean;
}

const props = defineProps<{
    membershipLevel: MembershipLevel;
}>();

const getLevelName = (level: MembershipLevel): string => {
    return level.name?.en || level.name?.['zh-TW'] || level.name?.['zh-CN'] || 'Unknown Level';
};

const getDescription = (level: MembershipLevel): string => {
    return level.description?.en || level.description?.['zh-TW'] || level.description?.['zh-CN'] || 'No description available';
};
</script>