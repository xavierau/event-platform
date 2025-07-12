import { ref } from 'vue';

export const toastMessage = ref('');
export const toastType = ref<'success' | 'error'>('success');
export const toastVisible = ref(false);

export function showToast(message: string, type: 'success' | 'error' = 'success', duration = 2500) {
    toastMessage.value = message;
    toastType.value = type;
    toastVisible.value = true;
    setTimeout(() => {
        toastVisible.value = false;
    }, duration);
}
