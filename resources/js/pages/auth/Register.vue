<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthBase from '@/layouts/AuthLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';
// @ts-expect-error - vue-i18n has no type definitions
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const form = useForm({
    name: '',
    email: '',
    mobile_number: '',
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <AuthBase :title="t('auth.register.title')" :description="t('auth.register.description')">
        <Head :title="t('auth.register.page_title')" />

        <form @submit.prevent="submit" class="flex flex-col gap-6">
            <div class="grid gap-6">
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

                <Button type="submit" class="mt-2 w-full" tabindex="6" :disabled="form.processing">
                    <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
                    {{ t('auth.register.submit') }}
                </Button>
            </div>

            <div class="text-muted-foreground text-center text-sm">
                {{ t('auth.have_account') }}
                <TextLink :href="route('login')" class="underline underline-offset-4" :tabindex="7">
                    {{ t('auth.login.submit') }}
                </TextLink>
            </div>
        </form>
    </AuthBase>
</template>
