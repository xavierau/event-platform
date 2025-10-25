<script setup lang="ts">
import { computed, ref, onMounted } from 'vue';
import axios from 'axios';
import { marked } from 'marked';
import DOMPurify from 'dompurify';
import { usePage } from '@inertiajs/vue3';
import type { Message, ChatbotResponse } from '@/types/chatbot';
import { MessageCircle, Send, X, Loader2 } from 'lucide-vue-next';
import { cn } from '@/lib/utils';
import { usePageContent } from '@/composables/usePageContent';

const isOpen = ref(false);
const messages = ref<Message[]>([]);
const userInput = ref('');
const isLoading = ref(false);
const chatContainer = ref<HTMLElement | null>(null);
const sessionId = ref<string>('');

// Get auth data from Inertia
const page = usePage();
const auth = computed(() => page.props.auth);

// Page content extraction
const { extractPageContent } = usePageContent();

// Get or create session ID
const getSessionId = (): string => {
    let id = localStorage.getItem('chatbot_session_id');
    if (!id) {
        id = `session-${Date.now()}-${Math.random().toString(36).substring(7)}`;
        localStorage.setItem('chatbot_session_id', id);
    }
    return id;
};

// Load previous messages
const loadMessages = async () => {
    try {
        const response = await axios.post<{ messages: Message[] }>('/api/chatbot/messages', {
            session_id: sessionId.value,
        });

        if (response.data.messages && Array.isArray(response.data.messages)) {
            messages.value = response.data.messages.map((msg: any) => ({
                id: msg.id || `msg-${Date.now()}-${Math.random()}`,
                content: msg.content || msg.message,
                role: msg.role,
                timestamp: new Date(msg.timestamp || msg.created_at),
            }));
        }
    } catch (error) {
        console.error('Failed to load previous messages:', error);
    }
};

const toggleChat = async () => {
    isOpen.value = !isOpen.value;
    if (isOpen.value) {
        scrollToBottom();
    }
};

onMounted(() => {
    sessionId.value = getSessionId();
    loadMessages();
});

const scrollToBottom = () => {
    setTimeout(() => {
        if (chatContainer.value) {
            chatContainer.value.scrollTop = chatContainer.value.scrollHeight;
        }
    }, 100);
};

const convertMarkdownToHtml = (markdown: string): string => {
    const rawHtml = marked(markdown, { breaks: true }) as string;
    return DOMPurify.sanitize(rawHtml);
};

const sendMessage = async () => {
    const message = userInput.value.trim();
    if (!message || isLoading.value) return;

    const userMessage: Message = {
        id: `user-${Date.now()}`,
        content: message,
        role: 'user',
        timestamp: new Date(),
    };

    messages.value.push(userMessage);
    userInput.value = '';
    isLoading.value = true;
    scrollToBottom();

    try {
        const response = await axios.post<ChatbotResponse>('/api/chatbot', {
            message: message,
            user_id: auth.value?.user?.id ?? null,
            session_id: sessionId.value,
            current_url: window.location.href,
            page_content: extractPageContent(),
        });

        const botMessage: Message = {
            id: `bot-${Date.now()}`,
            content: response.data.message,
            role: 'assistant',
            timestamp: new Date(response.data.timestamp),
        };

        messages.value.push(botMessage);
        scrollToBottom();
    } catch (error) {
        const errorMessage: Message = {
            id: `error-${Date.now()}`,
            content: 'Sorry, I encountered an error. Please try again later.',
            role: 'assistant',
            timestamp: new Date(),
        };
        messages.value.push(errorMessage);
    } finally {
        isLoading.value = false;
    }
};

const handleKeyPress = (event: KeyboardEvent) => {
    if (event.key === 'Enter' && !event.shiftKey) {
        event.preventDefault();
        sendMessage();
    }
};
</script>

<template>
    <!-- Chat Window -->
    <Transition
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="transform scale-95 opacity-0"
        enter-to-class="transform scale-100 opacity-100"
        leave-active-class="transition duration-150 ease-in"
        leave-from-class="transform scale-100 opacity-100"
        leave-to-class="transform scale-95 opacity-0"
    >
        <div
            v-if="isOpen"
            class="chatbot-widget fixed bottom-32 left-4 right-4 z-[1000] flex flex-col overflow-hidden rounded-lg border border-gray-200 bg-white shadow-2xl md:bottom-20 md:left-auto md:w-[400px] dark:border-gray-700 dark:bg-gray-800"
            style="max-height: 600px"
        >
                <!-- Header -->
                <div
                    class="flex items-center justify-between border-b border-gray-200 bg-gradient-to-r from-blue-500 to-blue-600 px-4 py-3 dark:border-gray-700"
                >
                    <div class="flex items-center gap-2">
                        <MessageCircle class="h-5 w-5 text-white" />
                        <h3 class="font-semibold text-white">Chat Assistant</h3>
                    </div>
                    <button
                        type="button"
                        @click="toggleChat"
                        class="rounded-full p-1 text-white transition-colors hover:bg-white/20"
                        aria-label="Close chat"
                    >
                        <X class="h-5 w-5" />
                    </button>
                </div>

                <!-- Messages Container -->
                <div
                    ref="chatContainer"
                    class="flex-1 space-y-4 overflow-y-auto p-4"
                    style="min-height: 300px; max-height: 450px"
                >
                    <div
                        v-if="messages.length === 0"
                        class="flex h-full items-center justify-center text-gray-500 dark:text-gray-400"
                    >
                        <p class="text-center text-sm">
                            Hi! How can I help you today?
                        </p>
                    </div>

                    <div
                        v-for="message in messages"
                        :key="message.id"
                        :class="
                            cn('flex', {
                                'justify-end': message.role === 'user',
                                'justify-start': message.role === 'assistant',
                            })
                        "
                    >
                        <div
                            :class="
                                cn(
                                    'max-w-[80%] rounded-lg px-4 py-2',
                                    {
                                        'bg-blue-500 text-white':
                                            message.role === 'user',
                                        'bg-gray-100 text-gray-900 dark:bg-gray-700 dark:text-gray-100':
                                            message.role === 'assistant',
                                    },
                                )
                            "
                        >
                            <!-- User messages are plain text -->
                            <div v-if="message.role === 'user'" class="text-sm">
                                {{ message.content }}
                            </div>

                            <!-- Assistant messages with markdown -->
                            <div
                                v-else
                                class="prose prose-sm dark:prose-invert max-w-none"
                                v-html="convertMarkdownToHtml(message.content)"
                            />
                        </div>
                    </div>

                    <!-- Loading indicator -->
                    <div v-if="isLoading" class="flex justify-start">
                        <div
                            class="flex items-center gap-2 rounded-lg bg-gray-100 px-4 py-2 dark:bg-gray-700"
                        >
                            <Loader2 class="h-4 w-4 animate-spin text-gray-600 dark:text-gray-400" />
                            <span class="text-sm text-gray-600 dark:text-gray-400">Thinking...</span>
                        </div>
                    </div>
                </div>

                <!-- Input Area -->
                <div class="border-t border-gray-200 p-4 dark:border-gray-700">
                    <div class="flex gap-2">
                        <input
                            v-model="userInput"
                            type="text"
                            placeholder="Type your message..."
                            @keypress="handleKeyPress"
                            :disabled="isLoading"
                            class="flex-1 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:placeholder-gray-400"
                            aria-label="Chat message input"
                        />
                        <button
                            type="button"
                            @click="sendMessage"
                            :disabled="!userInput.trim() || isLoading"
                            class="rounded-lg bg-blue-500 px-4 py-2 text-white transition-colors hover:bg-blue-600 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-blue-600 dark:hover:bg-blue-700"
                            aria-label="Send message"
                        >
                            <Send class="h-4 w-4" />
                        </button>
                    </div>
                </div>
            </div>
    </Transition>

    <!-- Floating Button -->
    <button
        type="button"
        @click="toggleChat"
        class="fixed bottom-20 right-4 z-[1000] flex h-14 w-14 items-center justify-center rounded-full bg-transparent transition-all hover:scale-110 focus:outline-none md:bottom-4"
        :aria-expanded="isOpen"
        aria-label="Toggle chat"
    >
        <img
            src="/images/showeasy-avatar.png"
            alt="Chat Assistant"
            class="h-12 w-12 object-contain drop-shadow-md"
        />
    </button>
</template>

<style scoped>
/* Custom scrollbar for chat container */
.overflow-y-auto::-webkit-scrollbar {
    width: 6px;
}

.overflow-y-auto::-webkit-scrollbar-track {
    background: transparent;
}

.overflow-y-auto::-webkit-scrollbar-thumb {
    background: rgb(156 163 175 / 0.5);
    border-radius: 3px;
}

.overflow-y-auto::-webkit-scrollbar-thumb:hover {
    background: rgb(156 163 175 / 0.7);
}

/* Dark mode scrollbar */
.dark .overflow-y-auto::-webkit-scrollbar-thumb {
    background: rgb(75 85 99 / 0.5);
}

.dark .overflow-y-auto::-webkit-scrollbar-thumb:hover {
    background: rgb(75 85 99 / 0.7);
}

/* Prose styles for markdown content */
.prose :deep(p) {
    margin-bottom: 0.5em;
}

.prose :deep(p:last-child) {
    margin-bottom: 0;
}

.prose :deep(strong) {
    font-weight: 600;
}

.prose :deep(em) {
    font-style: italic;
}

.prose :deep(code) {
    background: rgb(0 0 0 / 0.05);
    padding: 0.125rem 0.25rem;
    border-radius: 0.25rem;
    font-size: 0.875em;
}

.dark .prose :deep(code) {
    background: rgb(255 255 255 / 0.1);
}

.prose :deep(pre) {
    background: rgb(0 0 0 / 0.05);
    padding: 0.75rem;
    border-radius: 0.375rem;
    overflow-x: auto;
    margin: 0.5em 0;
}

.dark .prose :deep(pre) {
    background: rgb(255 255 255 / 0.1);
}

.prose :deep(ul),
.prose :deep(ol) {
    margin: 0.5em 0;
    padding-left: 1.5em;
}

.prose :deep(li) {
    margin: 0.25em 0;
}

.prose :deep(a) {
    color: #3b82f6;
    text-decoration: underline;
}

.dark .prose :deep(a) {
    color: #60a5fa;
}
</style>
