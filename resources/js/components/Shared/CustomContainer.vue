<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

const props = defineProps<{
  title: string;
  subtitle?: string;
  poster_url?: string;
}>();

const subtitleToShow = typeof props.subtitle !== 'undefined' ? props.subtitle : ''

</script>

<template>

    <Head :title="props.title" />

    <div class="min-h-screen bg-gray-100 dark:bg-gray-900 pb-20"> <!-- padding-bottom for fixed footer -->
        <div class="container mx-auto pt-6 pb-2 px-3">
        <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-100">{{ props.title }}</h1>
        <div v-if="subtitleToShow" class="text-gray-500 dark:text-gray-400 text-base mt-1">{{ subtitleToShow }}</div>
        </div>

        <!-- Landscape Poster Section -->
        <section v-if="poster_url" class="w-full bg-gray-200 dark:bg-gray-700 mb-4">
        <img
            :src="poster_url"
            alt="Event Landscape Poster"
            class="w-full h-auto object-cover rounded-b-lg"
            style="max-height: 220px;"
        />
        </section>

        <slot></slot>
    </div>
</template>
