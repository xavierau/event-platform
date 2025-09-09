<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import PricingCard from '@/components/PricingCard.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { LoaderCircle, ArrowLeft } from 'lucide-vue-next';
// @ts-expect-error - vue-i18n has no type definitions
import { useI18n } from 'vue-i18n';
import { logger } from '@/Utils/logger';
import { showToast } from '@/composables/useToast';

interface MembershipLevel {
    id: number;
    name: Record<string, string>;
    description: Record<string, string>;
    price: number;
    price_formatted: string;
    stripe_price_id: string;
    duration_months: number;
    benefits: Record<string, string>;
    is_popular: boolean;
    slug: string;
}

const props = defineProps<{
    membershipLevels: MembershipLevel[];
    flowId?: string;
}>();

const { t, locale } = useI18n();
const currentStep = ref<'pricing' | 'registration'>('pricing');
const selectedPlan = ref<MembershipLevel | null>(null);
const registrationFlowId = ref<string>(props.flowId || '');
const hasFormErrors = ref(false);

const form = useForm({
    name: '',
    email: '',
    mobile_number: '',
    password: '',
    password_confirmation: '',
    selected_price_id: '',
    flow_id: registrationFlowId.value,
});

// Generate flow ID if not provided from backend
onMounted(() => {
    if (!registrationFlowId.value) {
        registrationFlowId.value = crypto.randomUUID();
        form.flow_id = registrationFlowId.value;
    }
    
    // Log initial page visit
    logger.registration.pageVisit(registrationFlowId.value, 'pricing_page_loaded', {
        membership_levels_count: props.membershipLevels.length,
        available_plans: props.membershipLevels.map(level => level.slug),
    });
});

// Watch for form errors
watch(() => form.errors, (newErrors) => {
    hasFormErrors.value = Object.keys(newErrors).length > 0;
    
    if (hasFormErrors.value) {
        logger.registration.validationError(
            registrationFlowId.value, 
            newErrors, 
            {
                email: form.email,
                selected_price_id: form.selected_price_id,
            }
        );
        
        // Show user-friendly error message
        const errorFields = Object.keys(newErrors);
        const errorMessage = errorFields.length === 1 
            ? `Please fix the error in the ${errorFields[0]} field.`
            : `Please fix the errors in the following fields: ${errorFields.join(', ')}.`;
            
        showToast(errorMessage, 'error', 4000);
    }
}, { deep: true });

const selectedPlanName = computed(() => {
    if (!selectedPlan.value) return '';
    return selectedPlan.value.name[locale.value] || selectedPlan.value.name.en || '';
});

const selectPlan = (plan: MembershipLevel) => {
    selectedPlan.value = plan;
    form.selected_price_id = plan.stripe_price_id;
    
    // Log plan selection
    logger.registration.planSelected(registrationFlowId.value, plan);
    
    currentStep.value = 'registration';
    
    // Log step change
    logger.registration.pageVisit(registrationFlowId.value, 'registration_form_displayed', {
        selected_plan: plan.slug,
        plan_price: plan.price,
    });
};

const goBackToPricing = () => {
    logger.registration.pageVisit(registrationFlowId.value, 'back_to_pricing', {
        previous_selection: selectedPlan.value?.slug,
    });
    
    currentStep.value = 'pricing';
};

const submitRegistration = () => {
    // Clear any previous form errors for cleaner UI
    hasFormErrors.value = false;
    
    // Log form submission attempt
    logger.registration.formSubmitted(registrationFlowId.value, {
        name: form.name,
        email: form.email,
        mobile_number: form.mobile_number,
        selected_price_id: form.selected_price_id,
    });
    
    form.post(route('register.subscription.store'), {
        preserveScroll: true,
        onStart: () => {
            logger.registration.pageVisit(registrationFlowId.value, 'form_submission_started', {
                email: form.email,
                selected_plan: form.selected_price_id,
            });
        },
        onSuccess: (response) => {
            logger.registration.registrationSuccess(registrationFlowId.value, {
                email: form.email,
                selected_plan: form.selected_price_id,
                redirect_type: 'success',
            });
            
            showToast(t('registration.success_message'), 'success', 3000);
        },
        onError: (errors) => {
            logger.registration.submitError(registrationFlowId.value, {
                message: 'Form submission failed with validation errors',
                errors: errors,
            }, {
                email: form.email,
                selected_price_id: form.selected_price_id,
            });
            
            // Handle specific error cases
            if (errors.email) {
                showToast(t('registration.errors.email_taken'), 'error', 5000);
            } else if (errors.selected_price_id) {
                showToast(t('registration.errors.invalid_plan'), 'error', 5000);
            } else {
                showToast(t('registration.errors.general'), 'error', 5000);
            }
        },
        onException: (exception) => {
            logger.registration.error(registrationFlowId.value, 'Form submission exception', exception, {
                email: form.email,
                selected_price_id: form.selected_price_id,
                step: 'form_submission',
            });
            
            showToast(t('registration.errors.server_error'), 'error', 5000);
        },
        onFinish: () => {
            logger.registration.pageVisit(registrationFlowId.value, 'form_submission_finished', {
                email: form.email,
                selected_plan: form.selected_price_id,
                has_errors: hasFormErrors.value,
            });
        },
    });
};
</script>

<template>
    <AuthLayout>
        <Head :title="t('auth.register.with_plan')" />
        
        <!-- Step Indicator -->
        <div class="mb-8">
            <div class="flex items-center justify-center">
                <div class="flex items-center">
                    <div :class="[
                        'flex h-10 w-10 items-center justify-center rounded-full border-2 text-sm font-medium',
                        currentStep === 'pricing' 
                            ? 'bg-primary text-primary-foreground border-primary'
                            : 'bg-background text-foreground border-primary'
                    ]">1</div>
                    <span class="ml-2 text-sm font-medium text-foreground">
                        {{ t('registration.steps.choose_plan') }}
                    </span>
                </div>
                <div class="mx-4 h-px w-16 bg-border" />
                <div class="flex items-center">
                    <div :class="[
                        'flex h-10 w-10 items-center justify-center rounded-full border-2 text-sm font-medium',
                        currentStep === 'registration'
                            ? 'bg-primary text-primary-foreground border-primary'
                            : 'bg-background text-muted-foreground border-border'
                    ]">2</div>
                    <span class="ml-2 text-sm font-medium" :class="[
                        currentStep === 'registration' ? 'text-foreground' : 'text-muted-foreground'
                    ]">
                        {{ t('registration.steps.create_account') }}
                    </span>
                </div>
            </div>
        </div>
        
        <!-- Pricing Selection Step -->
        <div v-if="currentStep === 'pricing'" class="space-y-8">
            <div class="text-center">
                <h2 class="text-3xl font-bold text-foreground">{{ t('pricing.choose_your_plan') }}</h2>
                <p class="mt-2 text-muted-foreground max-w-2xl mx-auto">
                    {{ t('pricing.plan_description') }}
                </p>
            </div>
            
            <div class="w-full px-4">
                <div class="flex flex-wrap justify-center gap-6 max-w-7xl mx-auto">
                <PricingCard
                    v-for="plan in membershipLevels"
                    :key="plan.id"
                    :plan="plan"
                    :is-popular="plan.is_popular"
                    @select="selectPlan"
                />
                </div>
            </div>
            
            <div class="text-center text-sm text-muted-foreground">
                {{ t('auth.have_account') }}
                <TextLink :href="route('login')" class="underline underline-offset-4">
                    {{ t('auth.login.submit') }}
                </TextLink>
            </div>
        </div>
        
        <!-- Registration Form Step -->
        <div v-else-if="currentStep === 'registration'" class="space-y-6 max-w-md mx-auto">
            <div class="text-center">
                <h2 class="text-2xl font-bold text-foreground">
                    {{ t('registration.create_account') }}
                </h2>
                <div class="mt-2 text-sm text-muted-foreground">
                    <p>{{ t('registration.selected_plan', { plan: selectedPlanName }) }}</p>
                    <button
                        @click="goBackToPricing"
                        class="inline-flex items-center gap-1 mt-2 text-primary hover:underline"
                    >
                        <ArrowLeft class="h-3 w-3" />
                        {{ t('registration.change_plan') }}
                    </button>
                </div>
            </div>
            
            <!-- Selected Plan Summary -->
            <div v-if="selectedPlan" class="bg-muted/50 rounded-lg p-4 border">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="font-medium text-foreground">{{ selectedPlanName }}</h3>
                        <p class="text-sm text-muted-foreground">
                            {{ selectedPlan.description[locale] || selectedPlan.description.en }}
                        </p>
                    </div>
                    <div class="text-right">
                        <div class="font-bold text-foreground">
                            {{ selectedPlan.price === 0 ? t('pricing.free') : selectedPlan.price_formatted }}
                        </div>
                        <div v-if="selectedPlan.price > 0" class="text-xs text-muted-foreground">
                            / {{ t('pricing.month') }}
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Registration Form -->
            <form @submit.prevent="submitRegistration" class="space-y-4">
                <div class="grid gap-2">
                    <Label for="name">{{ t('forms.fields.name.label') }}</Label>
                    <Input 
                        id="name" 
                        type="text" 
                        required 
                        autofocus 
                        :tabindex="1" 
                        autocomplete="name" 
                        v-model="form.name" 
                        :placeholder="t('forms.fields.name.placeholder')" 
                    />
                    <InputError :message="form.errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label for="email">{{ t('forms.fields.email.label') }}</Label>
                    <Input 
                        id="email" 
                        type="email" 
                        required 
                        :tabindex="2" 
                        autocomplete="email" 
                        v-model="form.email" 
                        :placeholder="t('forms.fields.email.placeholder')" 
                    />
                    <InputError :message="form.errors.email" />
                </div>

                <div class="grid gap-2">
                    <Label for="mobile_number">{{ t('forms.fields.mobile_number.label') }}</Label>
                    <Input 
                        id="mobile_number" 
                        type="tel" 
                        required 
                        :tabindex="3" 
                        autocomplete="tel" 
                        v-model="form.mobile_number" 
                        :placeholder="t('forms.fields.mobile_number.placeholder')" 
                    />
                    <InputError :message="form.errors.mobile_number" />
                </div>

                <div class="grid gap-2">
                    <Label for="password">{{ t('forms.fields.password.label') }}</Label>
                    <Input
                        id="password"
                        type="password"
                        required
                        :tabindex="4"
                        autocomplete="new-password"
                        v-model="form.password"
                        :placeholder="t('forms.fields.password.placeholder')"
                    />
                    <InputError :message="form.errors.password" />
                </div>

                <div class="grid gap-2">
                    <Label for="password_confirmation">{{ t('forms.fields.password_confirmation.label') }}</Label>
                    <Input
                        id="password_confirmation"
                        type="password"
                        required
                        :tabindex="5"
                        autocomplete="new-password"
                        v-model="form.password_confirmation"
                        :placeholder="t('forms.fields.password_confirmation.placeholder')"
                    />
                    <InputError :message="form.errors.password_confirmation" />
                </div>

                <Button type="submit" class="w-full" :tabindex="6" :disabled="form.processing">
                    <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin mr-2" />
                    {{ t('auth.register.submit') }}
                </Button>
            </form>

            <div class="text-center text-sm text-muted-foreground">
                {{ t('auth.have_account') }}
                <TextLink :href="route('login')" class="underline underline-offset-4" :tabindex="7">
                    {{ t('auth.login.submit') }}
                </TextLink>
            </div>
        </div>
    </AuthLayout>
</template>