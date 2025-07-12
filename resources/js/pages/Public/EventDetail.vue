<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import CommentList from '@/components/Comments/CommentList.vue';
import CommentForm from '@/components/Comments/CommentForm.vue';
import type { Comment } from '@/types/comment';

interface EventDetails {
  id: string | number;
  name: string;
  category_tag: string;
  thumbnail_url?: string;
  description_html?: string;
  comments: Comment[];
}

const props = defineProps<{
    event: EventDetails;
}>();

const localComments = ref<Comment[]>(props.event.comments || []);
const showCommentForm = ref(false);

const handleCommentPosted = (newComment: Comment) => {
    localComments.value.unshift(newComment);
    showCommentForm.value = false;
};
</script>

<template>
    <Head :title="event.name" />
        <div class="min-h-screen bg-gray-100 dark:bg-gray-900 pb-20">

            <!-- Hero/Header Section -->
            <section class="bg-white dark:bg-gray-800 p-4 shadow-sm">
                <div class="container mx-auto flex">
                    <div class="w-1/4 md:w-1/5 flex-shrink-0">
                        <img :src="event.thumbnail_url || 'https://via.placeholder.com/150x200.png?text=Event'" :alt="event.name" class="w-full h-auto object-cover rounded" />
                    </div>
                    <div class="w-3/4 md:w-4/5 pl-4">
                        <span class="inline-block bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 text-xs font-semibold px-2 py-0.5 rounded mb-1">{{ event.category_tag }}</span>
                        <h1 class="text-lg md:text-xl font-bold text-gray-900 dark:text-gray-100 leading-tight mb-1">{{ event.name }}</h1>
                    </div>
                </div>
            </section>

            <!-- Event Description Section  -->
            <section class="bg-white dark:bg-gray-800 p-4 mt-1 shadow-sm">
                <div class="container mx-auto max-w-full">
                    <h2 class="text-md font-semibold mb-3 text-gray-900 dark:text-gray-100">Event Description</h2>
                    <div v-html="event.description_html"></div>
                </div>
            </section>

            <!-- Comments Section -->
            <section class="bg-white dark:bg-gray-800 p-4 mt-3 shadow-sm">
                <div class="container mx-auto">
                    <div class="flex justify-between items-center mb-4">
                        <h2 class="text-xl font-bold text-gray-900 dark:text-white">Comments</h2>
                        <button @click="showCommentForm = !showCommentForm" class="text-indigo-600 hover:text-indigo-800">
                            {{ showCommentForm ? 'Cancel' : 'Leave a Comment' }}
                        </button>
                    </div>
                    <CommentForm v-if="showCommentForm" :event-id="event.id" @comment-posted="handleCommentPosted" />
                    <CommentList :comments="localComments" />
                </div>
            </section>
        </div>
</template>
