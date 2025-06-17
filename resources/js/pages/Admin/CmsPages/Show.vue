<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Globe, Pencil, Languages } from 'lucide-vue-next';

interface CmsPage {
    id: number;
    title: Record<string, string>;
    content: Record<string, string>;
    slug: string;
    is_published: boolean;
    published_at: string | null;
    author: {
        name: string;
    } | null;
    created_at: string;
    updated_at: string;
    sort_order: number;
}

defineProps<{
    page: CmsPage;
}>();

const availableLocales = {
    en: 'English',
    'zh-CN': 'Simplified Chinese',
    'zh-TW': 'Traditional Chinese',
};

const getTranslation = (translations: any, locale: string, fallbackLocale: string = 'en') => {
    if (!translations) return 'N/A';
    if (typeof translations === 'string') return translations;
    return translations[locale] || translations[fallbackLocale] || Object.values(translations)[0] || 'N/A';
};

</script>

<template>
    <Head :title="`CMS Page: ${page.title.en}`" />

    <AppLayout>
        <div class="container mx-auto py-10 px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between mb-6">
                <h1 class="text-2xl font-bold">
                    View CMS Page
                </h1>
                <Link :href="route('admin.cms-pages.index')">
                    <Button variant="outline">
                        Back to List
                    </Button>
                </Link>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>{{ page.title.en || 'No English Title' }}</CardTitle>
                </CardHeader>
                <CardContent class="space-y-6">
                    <div class="space-y-2">
                        <h3 class="font-semibold text-lg">
                            Page Details
                        </h3>
                        <p><strong>ID:</strong> {{ page.id }}</p>
                        <p><strong>Slug:</strong> {{ page.slug }}</p>
                        <p><strong>Author:</strong> {{ page.author?.name || 'N/A' }}</p>
                        <p><strong>Published:</strong> <span :class="page.is_published ? 'text-green-600' : 'text-red-600'">{{ page.is_published ? 'Yes' : 'No' }}</span></p>
                        <p><strong>Published At:</strong> {{ page.published_at ? new Date(page.published_at).toLocaleString() : 'Not published' }}</p>
                        <p><strong>Sort Order:</strong> {{ page.sort_order }}</p>
                    </div>

                    <div v-for="(name, locale) in availableLocales" :key="locale" class="space-y-2 border-t pt-4">
                        <h3 class="font-semibold text-lg">
                            Content ({{ name }})
                        </h3>
                        <div v-if="page.content[locale]" class="prose dark:prose-invert max-w-none p-4 border rounded-md bg-gray-50 dark:bg-gray-800" v-html="page.content[locale]" />
                        <p v-else class="text-gray-500">
                            No content provided for {{ name }}.
                        </p>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
