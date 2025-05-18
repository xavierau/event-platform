<script setup lang="ts">
import { ref, watch, computed } from 'vue';
import { MediaItem } from '@/types'; // Assuming MediaItem type is defined globally

interface Props {
    modelValue: File | File[] | null; // For new uploads (v-model)
    existingMedia: MediaItem[] | MediaItem | null; // Existing media to display
    collectionName: string; // Identifier for this upload instance
    label?: string;
    multiple?: boolean; // Allow multiple file selection
    accept?: string; // File input accept attribute (e.g., 'image/*')
    maxFiles?: number; // Optional: Max number of files for multiple uploads
    maxFileSizeMb?: number; // Optional: Max file size in MB for client-side check
}

const props = withDefaults(defineProps<Props>(), {
    label: 'Upload Media',
    multiple: false,
    accept: 'image/*',
    maxFiles: 5, // Default if multiple is true
    maxFileSizeMb: 2, // Default max file size
});

const emit = defineEmits(['update:modelValue', 'remove-existing']);

const newFiles = ref<File[]>([]); // Store newly selected files
const displayedExistingMedia = ref<MediaItem[]>([]); // For UI manipulation of existing media

const inputId = computed(() => `media-upload-${props.collectionName}-${Math.random().toString(36).substring(7)}`);

// Initialize displayedExistingMedia from props
watch(() => props.existingMedia, (newValue) => {
    if (newValue) {
        displayedExistingMedia.value = Array.isArray(newValue) ? [...newValue] : [newValue];
    } else {
        displayedExistingMedia.value = [];
    }
}, { immediate: true, deep: true });

// When newFiles changes, emit update:modelValue
watch(newFiles, (newValue) => {
    if (props.multiple) {
        emit('update:modelValue', newValue);
    } else {
        emit('update:modelValue', newValue.length > 0 ? newValue[0] : null);
    }
});

const handleFileChange = (event: Event) => {
    const target = event.target as HTMLInputElement;
    if (target.files) {
        let selectedFiles = Array.from(target.files);

        selectedFiles = selectedFiles.filter(file => {
            if (file.size > props.maxFileSizeMb * 1024 * 1024) {
                console.warn(`File ${file.name} is too large (max ${props.maxFileSizeMb}MB).`);
                return false;
            }
            return true;
        });

        if (props.multiple) {
            if (newFiles.value.length + selectedFiles.length > props.maxFiles) {
                console.warn(`Cannot upload more than ${props.maxFiles} files.`);
                newFiles.value = [...newFiles.value, ...selectedFiles.slice(0, props.maxFiles - newFiles.value.length)];
            } else {
                newFiles.value = [...newFiles.value, ...selectedFiles];
            }
        } else {
            newFiles.value = selectedFiles.length > 0 ? [selectedFiles[0]] : [];
        }
    }
    target.value = '';
};

const removeNewFile = (index: number) => {
    newFiles.value.splice(index, 1);
};

const removeExistingFile = (mediaItem: MediaItem) => {
    displayedExistingMedia.value = displayedExistingMedia.value.filter(m => m.id !== mediaItem.id);
    emit('remove-existing', { collection: props.collectionName, id: mediaItem.id });
};

const getPreviewUrl = (file: File | MediaItem): string => {
    if (file instanceof File) {
        return URL.createObjectURL(file);
    }
    return file.thumbnail_url || file.preview_url || file.url;
};

const getFileName = (file: File | MediaItem): string => {
    if (file instanceof File) {
        return file.name;
    }
    return file.file_name || file.name;
};

</script>

<template>
    <div class="media-upload-container border dark:border-gray-700 p-4 rounded-md">
        <label :for="inputId" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">{{ label }} <span v-if="multiple" class="text-xs">(Max {{ props.maxFiles }})</span></label>

        <div v-if="displayedExistingMedia.length > 0" class="mb-4 grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-3">
            <div v-for="media in displayedExistingMedia" :key="media.id" class="relative group border dark:border-gray-600 rounded-md p-1.5">
                <img :src="getPreviewUrl(media)" :alt="getFileName(media)" class="h-24 w-full object-cover rounded-md bg-gray-100 dark:bg-gray-700" />
                <p class="text-xs text-gray-500 dark:text-gray-400 truncate mt-1" :title="getFileName(media)">{{ getFileName(media) }}</p>
                <button
                    type="button"
                    @click="removeExistingFile(media)"
                    class="absolute top-1 right-1 bg-red-500 hover:bg-red-700 text-white text-xxs p-1 rounded-full opacity-75 group-hover:opacity-100 transition-opacity focus:outline-none focus:ring-2 focus:ring-red-500/50">
                    X
                </button>
            </div>
        </div>

        <div>
            <input
                :id="inputId"
                type="file"
                :multiple="props.multiple"
                :accept="props.accept"
                @change="handleFileChange"
                class="block w-full text-sm text-gray-500 dark:text-gray-400 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 dark:file:bg-indigo-800 file:text-indigo-700 dark:file:text-indigo-300 hover:file:bg-indigo-100 dark:hover:file:bg-indigo-700 cursor-pointer"
            />
            <p v-if="!props.multiple && newFiles.length > 0 && displayedExistingMedia.length > 0 && props.existingMedia && !Array.isArray(props.existingMedia)" class="text-xs text-amber-600 dark:text-amber-500 mt-1">Uploading a new file will replace the existing one.</p>
        </div>

        <div v-if="newFiles.length > 0" class="mt-4 grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-3">
             <div v-for="(file, index) in newFiles" :key="index" class="relative group border dark:border-gray-600 rounded-md p-1.5">
                <img :src="getPreviewUrl(file)" :alt="file.name" class="h-24 w-full object-cover rounded-md bg-gray-100 dark:bg-gray-700" />
                <p class="text-xs text-gray-500 dark:text-gray-400 truncate mt-1" :title="file.name">{{ file.name }}</p>
                <button
                    type="button"
                    @click="removeNewFile(index)"
                     class="absolute top-1 right-1 bg-red-500 hover:bg-red-700 text-white text-xxs p-1 rounded-full opacity-75 group-hover:opacity-100 transition-opacity focus:outline-none focus:ring-2 focus:ring-red-500/50">
                    X
                </button>
            </div>
        </div>
    </div>
</template>

<style scoped>
.text-xxs {
    font-size: 0.65rem;
    line-height: 0.85rem;
}
</style>
