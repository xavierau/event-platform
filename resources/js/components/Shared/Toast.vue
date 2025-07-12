<script setup lang="ts">
import { ref, watch, onMounted } from 'vue';

const props = defineProps({
  message: { type: String, required: true },
  type: { type: String, default: 'success' }, // 'success' | 'error'
  duration: { type: Number, default: 2500 },
});

const visible = ref(true);

watch(() => props.message, () => {
  visible.value = true;
  setTimeout(() => (visible.value = false), props.duration);
});

onMounted(() => {
  setTimeout(() => (visible.value = false), props.duration);
});
</script>

<template>
  <transition name="fade">
    <div v-if="visible" :class="[
      'fixed top-6 left-1/2 z-50 px-6 py-3 rounded shadow-lg text-white text-center',
      type === 'success' ? 'bg-green-600' : 'bg-red-600',
      'transform -translate-x-1/2'
    ]">
      {{ message }}
    </div>
  </transition>
</template>

<style scoped>
.fade-enter-active, .fade-leave-active {
  transition: opacity 0.3s;
}
.fade-enter-from, .fade-leave-to {
  opacity: 0;
}
</style>
