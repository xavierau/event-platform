<template>
    <span :class="badgeClasses">
        <slot />
    </span>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
    variant: {
        type: String,
        default: 'default',
        validator: (value) => ['default', 'secondary', 'success', 'destructive', 'outline'].includes(value)
    }
})

const badgeClasses = computed(() => {
    const baseClasses = 'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium'

    const variantClasses = {
        default: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300',
        secondary: 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
        success: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
        destructive: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300',
        outline: 'border border-gray-200 text-gray-600 dark:border-gray-700 dark:text-gray-400'
    }

    return `${baseClasses} ${variantClasses[props.variant] || variantClasses.default}`
})
</script>
