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

      <stripe-pricing-table pricing-table-id="prctbl_1RlPYoGkGJbeDaIk9zSYLgBl"
                            publishable-key="pk_live_51REfHHGkGJbeDaIkqgQEUJfKfY0GdGTWyoISqmf3cMksLLnN1G8PNr5nGuhdRmq2njIf0zPYsZtTmpjKT7Pb9z5d00vibNZZxN"></stripe-pricing-table>
  </div>
</template>

<style scoped>
/* Add component-specific styles here */
</style>
