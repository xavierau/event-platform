<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import CustomContainer from '@/components/Shared/CustomContainer.vue';
import CommentList from '@/components/Comments/CommentList.vue';
import CommentForm from '@/components/Comments/CommentForm.vue';
import type { Comment } from '@/types/comment';

interface Event {
  id: number;
  name: string;
  href: string;
  image_url: string;
  price_range?: string;
  next_occurrence_date?: string;
  venue_name?: string;
}

interface Organizer {
  id: number;
  name: string;
  description?: string;
  contact_email?: string;
  contact_phone?: string;
  website_url?: string;
  logo_url?: string;
  banner_url?: string;
  events: Event[];
  comments: Comment[];
  comment_config: string;
  total_events: number;
}

const props = defineProps<{
  organizer: Organizer;
}>();

const localComments = ref<Comment[]>(props.organizer.comments || []);
const showCommentForm = ref(false);

const handleCommentPosted = (newComment: Comment) => {
  localComments.value.unshift(newComment);
  showCommentForm.value = false;
};
</script>

<template>
  <CustomContainer :title="organizer.name" :poster_url="organizer.banner_url">
    <div class="min-h-screen bg-gray-100 dark:bg-gray-900">
      
      <!-- Hero Section -->
      <section class="bg-white dark:bg-gray-800 shadow-sm">
        <div class="container mx-auto p-6">
          <div class="flex flex-col md:flex-row items-start gap-6">
            <!-- Logo -->
            <div class="flex-shrink-0">
              <img 
                :src="organizer.logo_url || 'https://via.placeholder.com/150x150.png?text=Logo'" 
                :alt="organizer.name" 
                class="w-24 h-24 md:w-32 md:h-32 object-cover rounded-lg border"
              />
            </div>
            
            <!-- Organizer Info -->
            <div class="flex-1">
              <h1 class="text-2xl md:text-3xl font-bold text-gray-900 dark:text-gray-100 mb-2">
                {{ organizer.name }}
              </h1>
              
              <div class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                {{ organizer.total_events }} {{ organizer.total_events === 1 ? 'Event' : 'Events' }}
              </div>
              
              <div v-if="organizer.description" class="prose dark:prose-invert max-w-full mb-4">
                <p class="text-gray-700 dark:text-gray-300">{{ organizer.description }}</p>
              </div>
              
              <!-- Contact Info -->
              <div class="flex flex-wrap gap-4 text-sm">
                <a 
                  v-if="organizer.website_url" 
                  :href="organizer.website_url" 
                  target="_blank"
                  class="text-indigo-600 dark:text-indigo-400 hover:underline flex items-center gap-1"
                >
                  🌐 Website
                </a>
                <a 
                  v-if="organizer.contact_email" 
                  :href="`mailto:${organizer.contact_email}`"
                  class="text-indigo-600 dark:text-indigo-400 hover:underline flex items-center gap-1"
                >
                  ✉️ Email
                </a>
                <a 
                  v-if="organizer.contact_phone" 
                  :href="`tel:${organizer.contact_phone}`"
                  class="text-indigo-600 dark:text-indigo-400 hover:underline flex items-center gap-1"
                >
                  📞 {{ organizer.contact_phone }}
                </a>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- Events Section -->
      <section v-if="organizer.events.length > 0" class="bg-white dark:bg-gray-800 p-6 mt-3 shadow-sm">
        <div class="container mx-auto">
          <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-4">Upcoming Events</h2>
          
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <Link 
              v-for="event in organizer.events" 
              :key="event.id"
              :href="event.href"
              class="block bg-gray-50 dark:bg-gray-700 rounded-lg overflow-hidden shadow hover:shadow-md transition-shadow"
            >
              <img 
                :src="event.image_url || 'https://via.placeholder.com/300x200.png?text=Event'" 
                :alt="event.name"
                class="w-full h-40 object-cover"
              />
              <div class="p-4">
                <h3 class="font-semibold text-gray-900 dark:text-white mb-2 line-clamp-2">
                  {{ event.name }}
                </h3>
                <div class="text-sm text-gray-600 dark:text-gray-400 space-y-1">
                  <div v-if="event.next_occurrence_date">📅 {{ event.next_occurrence_date }}</div>
                  <div v-if="event.venue_name">📍 {{ event.venue_name }}</div>
                  <div v-if="event.price_range" class="font-semibold text-red-500 dark:text-red-400">
                    {{ event.price_range }}
                  </div>
                </div>
              </div>
            </Link>
          </div>
          
          <div v-if="organizer.total_events > organizer.events.length" class="text-center mt-6">
            <Link 
              :href="`/events?organizer=${organizer.id}`"
              class="inline-block px-6 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg font-semibold"
            >
              View All Events ({{ organizer.total_events }})
            </Link>
          </div>
        </div>
      </section>

      <!-- No Events Message -->
      <section v-else class="bg-white dark:bg-gray-800 p-6 mt-3 shadow-sm">
        <div class="container mx-auto text-center">
          <div class="text-gray-500 dark:text-gray-400">
            <div class="text-4xl mb-2">🎭</div>
            <p>No upcoming events at the moment.</p>
            <p class="text-sm mt-1">Check back soon for new events from {{ organizer.name }}!</p>
          </div>
        </div>
      </section>

      <!-- Comments Section -->
      <section 
        v-if="organizer.comment_config !== 'disabled'" 
        class="bg-white dark:bg-gray-800 p-6 mt-3 shadow-sm"
      >
        <div class="container mx-auto">
          <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-bold text-gray-900 dark:text-white">Comments</h2>
            <button 
              @click="showCommentForm = !showCommentForm" 
              class="text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300 font-semibold"
            >
              {{ showCommentForm ? 'Cancel' : 'Leave a Comment' }}
            </button>
          </div>
          
          <CommentForm 
            v-if="showCommentForm" 
            :commentable-type="'App\\Models\\Organizer'"
            :commentable-id="organizer.id"
            @comment-posted="handleCommentPosted" 
            class="mb-6"
          />
          
          <CommentList :comments="localComments" />
        </div>
      </section>

      <!-- Footer Spacer -->
      <div class="h-20"></div>
    </div>
  </CustomContainer>
</template>

<style scoped>
.line-clamp-2 {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
</style>