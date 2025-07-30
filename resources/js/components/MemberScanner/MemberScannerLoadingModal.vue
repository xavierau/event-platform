<template>
  <div v-if="show" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50 flex items-center justify-center">
    <div class="bg-white dark:bg-gray-800 p-8 rounded-lg shadow-xl max-w-md w-full mx-4">
      <div class="text-center">
        <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-indigo-600 mx-auto mb-4"></div>
        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">Processing Member QR Code...</h3>
        <p class="text-sm text-gray-500 dark:text-gray-400">Validating member information and checking membership status</p>
        
        <!-- Additional visual feedback -->
        <div class="mt-4 space-y-2">
          <div class="flex items-center justify-center space-x-2 text-sm text-gray-600 dark:text-gray-400">
            <svg class="w-4 h-4 text-green-500" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
            </svg>
            <span>QR code detected</span>
          </div>
          <div class="flex items-center justify-center space-x-2 text-sm text-gray-600 dark:text-gray-400">
            <div class="animate-spin rounded-full h-3 w-3 border-b border-indigo-600"></div>
            <span>Validating member data...</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
interface Props {
  show: boolean;
}

defineProps<Props>();
</script>