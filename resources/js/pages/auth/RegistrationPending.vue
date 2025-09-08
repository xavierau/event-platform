<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { Clock, Mail, ArrowRight } from 'lucide-vue-next';
// @ts-expect-error - vue-i18n has no type definitions
import { useI18n } from 'vue-i18n';

interface User {
    id: number;
    name: string;
    email: string;
}

const props = defineProps<{
    user: User;
}>();

const { t } = useI18n();
</script>

<template>
    <AuthLayout>
        <Head :title="t('registration.pending.title')" />
        
        <div class="max-w-md mx-auto text-center space-y-8">
            <!-- Pending Icon -->
            <div class="flex justify-center">
                <div class="h-20 w-20 rounded-full bg-blue-100 dark:bg-blue-900/20 flex items-center justify-center">
                    <Clock class="h-12 w-12 text-blue-600 dark:text-blue-400" />
                </div>
            </div>
            
            <!-- Pending Message -->
            <div class="space-y-4">
                <h1 class="text-3xl font-bold text-foreground">
                    {{ t('registration.pending.title') }}
                </h1>
                <p class="text-muted-foreground">
                    {{ t('registration.pending.message', { name: user.name }) }}
                </p>
            </div>
            
            <!-- What's Happening -->
            <div class="bg-muted/50 rounded-lg p-6 border text-left">
                <h3 class="font-medium text-foreground mb-3 flex items-center gap-2">
                    <Mail class="h-4 w-4" />
                    {{ t('registration.pending.whats_happening') }}
                </h3>
                <ul class="text-sm text-muted-foreground space-y-2">
                    <li class="flex items-start gap-2">
                        <span class="text-primary">•</span>
                        <span>{{ t('registration.pending.processing_payment') }}</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <span class="text-primary">•</span>
                        <span>{{ t('registration.pending.confirming_subscription') }}</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <span class="text-primary">•</span>
                        <span>{{ t('registration.pending.activating_membership') }}</span>
                    </li>
                </ul>
            </div>
            
            <!-- Timeline -->
            <div class="bg-blue-50 dark:bg-blue-950/50 rounded-lg p-4 border border-blue-200 dark:border-blue-800">
                <div class="text-sm">
                    <p class="font-medium text-blue-900 dark:text-blue-100 mb-1">
                        {{ t('registration.pending.timeline_title') }}
                    </p>
                    <p class="text-blue-700 dark:text-blue-300">
                        {{ t('registration.pending.timeline_description') }}
                    </p>
                </div>
            </div>
            
            <!-- Actions -->
            <div class="space-y-4">
                <div class="grid gap-3">
                    <Button 
                        @click="window.location.reload()" 
                        class="w-full"
                        size="lg"
                    >
                        {{ t('registration.pending.refresh_page') }}
                    </Button>
                    
                    <Button 
                        :href="route('dashboard')" 
                        variant="outline"
                        class="w-full"
                    >
                        {{ t('navigation.go_to_dashboard') }}
                        <ArrowRight class="ml-2 h-4 w-4" />
                    </Button>
                </div>
            </div>
            
            <!-- Help -->
            <div class="pt-4 border-t">
                <p class="text-xs text-muted-foreground">
                    {{ t('registration.pending.taking_too_long') }}
                    <a href="mailto:support@example.com" class="text-primary hover:underline">
                        {{ t('common.contact_support') }}
                    </a>
                </p>
            </div>
        </div>
    </AuthLayout>
</template>