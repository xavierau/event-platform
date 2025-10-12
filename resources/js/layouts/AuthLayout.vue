<script setup lang="ts">
import AuthLayout from '@/layouts/auth/AuthSimpleLayout.vue';
import Toast from '@/components/Shared/Toast.vue';
import { toastMessage, toastType, toastVisible } from '@/composables/useToast';


defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <AuthLayout :title="title" :description="description">
        <Toast v-if="toastVisible" :message="toastMessage" :type="toastType" />
        <slot />
    </AuthLayout>
</template>
