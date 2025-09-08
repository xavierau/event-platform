<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { XCircle, ArrowLeft, RefreshCw } from 'lucide-vue-next';
// @ts-expect-error - vue-i18n has no type definitions
import { useI18n } from 'vue-i18n';

const { t } = useI18n();
</script>

<template>
    <AuthLayout>
        <Head :title="t('registration.cancelled.title')" />
        
        <div class="max-w-md mx-auto text-center space-y-8">
            <!-- Cancelled Icon -->
            <div class="flex justify-center">
                <div class="h-20 w-20 rounded-full bg-orange-100 dark:bg-orange-900/20 flex items-center justify-center">
                    <XCircle class="h-12 w-12 text-orange-600 dark:text-orange-400" />
                </div>
            </div>
            
            <!-- Cancelled Message -->
            <div class="space-y-4">
                <h1 class="text-3xl font-bold text-foreground">
                    {{ t('registration.cancelled.title') }}
                </h1>
                <p class="text-muted-foreground">
                    {{ t('registration.cancelled.message') }}
                </p>
            </div>
            
            <!-- What Happened -->
            <div class="bg-muted/50 rounded-lg p-6 border text-left">
                <h3 class="font-medium text-foreground mb-2">
                    {{ t('registration.cancelled.what_happened') }}
                </h3>
                <ul class="text-sm text-muted-foreground space-y-1">
                    <li>• {{ t('registration.cancelled.reasons.payment_cancelled') }}</li>
                    <li>• {{ t('registration.cancelled.reasons.closed_window') }}</li>
                    <li>• {{ t('registration.cancelled.reasons.changed_mind') }}</li>
                </ul>
            </div>
            
            <!-- Actions -->
            <div class="space-y-4">
                <div class="grid gap-3">
                    <Button 
                        :href="route('register.subscription.create')" 
                        class="w-full"
                        size="lg"
                    >
                        <RefreshCw class="mr-2 h-4 w-4" />
                        {{ t('registration.cancelled.try_again') }}
                    </Button>
                    
                    <Button 
                        :href="route('home')" 
                        variant="outline"
                        class="w-full"
                    >
                        <ArrowLeft class="mr-2 h-4 w-4" />
                        {{ t('navigation.back_to_home') }}
                    </Button>
                </div>
            </div>
            
            <!-- Help -->
            <div class="pt-4 border-t">
                <p class="text-xs text-muted-foreground">
                    {{ t('registration.cancelled.need_help') }}
                    <a href="mailto:support@example.com" class="text-primary hover:underline">
                        {{ t('common.contact_support') }}
                    </a>
                </p>
            </div>
        </div>
    </AuthLayout>
</template>