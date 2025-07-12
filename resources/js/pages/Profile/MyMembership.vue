<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps({
  membership: {
    type: Object,
    default: null,
  },
});

const hasMembership = computed(() => props.membership && Object.keys(props.membership).length > 0);
</script>

<template>
  <div>
    <h1>My Membership</h1>

    <div v-if="hasMembership">
      <h2>Current Plan: {{ membership.level.name.en }}</h2>
      <p>Status: {{ membership.status }}</p>
      <p>Expires on: {{ new Date(membership.expires_at).toLocaleDateString() }}</p>
    </div>
    <div v-else>
      <p>You do not have an active membership.</p>
    </div>
  </div>
</template>

<style scoped>
/* Add component-specific styles here */
</style>
