<script setup lang="ts">
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { CheckCircle, ArrowRight } from 'lucide-vue-next';
// @ts-expect-error - vue-i18n has no type definitions
import { useI18n } from 'vue-i18n';

interface User {
    id: number;
    name: string;
    email: string;
    memberships: {
        membershipLevel: {
            name: Record<string, string>;
            slug: string;
            benefits: string[];
        };
    }[];
}

const props = defineProps<{
    user: User;
}>();

const { t, locale } = useI18n();

const currentMembership = computed(() => {
    return props.user.memberships?.[0]?.membershipLevel;
});

const membershipName = computed(() => {
    if (!currentMembership.value) return t('membership.levels.free');
    return currentMembership.value.name[locale.value] || currentMembership.value.name.en || '';
});
</script>

<template>
    <AuthLayout>
        <Head :title="t('registration.success.title')" />
        
        <div class="max-w-md mx-auto text-center space-y-8">
            <!-- Success Icon -->
            <div class="flex justify-center">
                <div class="h-20 w-20 rounded-full bg-green-100 dark:bg-green-900/20 flex items-center justify-center">
                    <CheckCircle class="h-12 w-12 text-green-600 dark:text-green-400" />
                </div>
            </div>
            
            <!-- Success Message -->
            <div class="space-y-4">
                <h1 class="text-3xl font-bold text-foreground">
                    {{ t('registration.success.title') }}
                </h1>
                <p class="text-muted-foreground">
                    {{ t('registration.success.welcome', { name: user.name }) }}
                </p>
            </div>
            
            <!-- Membership Info -->
            <div v-if="currentMembership" class="bg-muted/50 rounded-lg p-6 border">
                <div class="space-y-3">
                    <div class="flex items-center justify-center gap-2">
                        <Badge variant="secondary" class="text-sm">
                            {{ membershipName }}
                        </Badge>
                    </div>
                    
                    <div class="text-sm text-muted-foreground">
                        {{ t('registration.success.membership_activated') }}
                    </div>
                    
                    <!-- Benefits Preview -->
                    <div class="pt-2">
                        <p class="text-xs font-medium text-muted-foreground mb-2">
                            {{ t('membership.benefits.included') }}:
                        </p>
                        <div class="flex flex-wrap gap-1">
                            <span 
                                v-for="benefit in currentMembership.benefits?.slice(0, 3)" 
                                :key="benefit"
                                class="text-xs bg-primary/10 text-primary px-2 py-1 rounded"
                            >
                                {{ t(`membership.benefits.${benefit}`) }}
                            </span>
                            <span 
                                v-if="currentMembership.benefits?.length > 3"
                                class="text-xs text-muted-foreground px-2 py-1"
                            >
                                +{{ currentMembership.benefits.length - 3 }} {{ t('common.more') }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Next Steps -->
            <div class="space-y-4">
                <div class="text-sm text-muted-foreground">
                    {{ t('registration.success.next_steps') }}
                </div>
                
                <div class="grid gap-3">
                    <Button 
                        :href="route('dashboard')" 
                        class="w-full"
                        size="lg"
                    >
                        {{ t('navigation.dashboard') }}
                        <ArrowRight class="ml-2 h-4 w-4" />
                    </Button>
                    
                    <Button 
                        :href="route('events.index')" 
                        variant="outline"
                        class="w-full"
                    >
                        {{ t('navigation.browse_events') }}
                    </Button>
                </div>
            </div>
            
            <!-- Help -->
            <div class="pt-4 border-t">
                <p class="text-xs text-muted-foreground">
                    {{ t('registration.success.need_help') }}
                    <a href="mailto:support@example.com" class="text-primary hover:underline">
                        {{ t('common.contact_support') }}
                    </a>
                </p>
            </div>
        </div>
    </AuthLayout>
</template>