<template>
  <div class="flex justify-between items-center mb-6">
    <h1 class="text-2xl font-medium text-gray-900 dark:text-white">
      {{ title }}
    </h1>
    <div class="flex space-x-2">
      <slot name="actions" />
    </div>
  </div>
</template>

<script setup lang="ts">
const { title } = defineProps<{ title: string }>();
</script>
