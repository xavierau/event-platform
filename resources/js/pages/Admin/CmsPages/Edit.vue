<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError.vue';
import { Switch } from '@/components/ui/switch';
import RichTextEditor from '@/components/Form/RichTextEditor.vue';
import { CmsPage } from '@/types';
import { computed } from 'vue';

const props = defineProps<{
    page: CmsPage;
}>();

const availableLocales = computed(() => usePage().props.available_locales as Record<string, string>);

// Dynamically create the structure for title and content based on available locales
const initialTitle = Object.keys(availableLocales.value).reduce((acc, locale) => {
    acc[locale] = props.page.title[locale] || '';
    return acc;
}, {} as Record<string, string>);

const initialContent = Object.keys(availableLocales.value).reduce((acc, locale) => {
    acc[locale] = props.page.content[locale] || '';
    return acc;
}, {} as Record<string, string>);


const form = useForm({
    title: initialTitle,
    content: initialContent,
    is_published: props.page.is_published,
});

const submit = () => {
    form.put(route('admin.cms-pages.update', props.page.id));
};
</script>

<template>
    <Head :title="`Edit CMS Page: ${page.title.en}`" />

    <AppLayout>
        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <Card>
                    <CardHeader>
                        <CardTitle>Edit CMS Page</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form @submit.prevent="submit">
                            <div class="grid grid-cols-1 gap-6">
                                <!-- Dynamically generate fields for each locale -->
                                <div v-for="(name, code) in availableLocales" :key="code" class="space-y-2 p-4 border rounded-md">
                                    <h3 class="font-semibold text-lg">{{ name }} Content</h3>
                                    <div>
                                        <Label :for="`title_${code}`">Title ({{ name }})</Label>
                                        <Input :id="`title_${code}`" v-model="form.title[code]" type="text" />
                                        <InputError class="mt-2" :message="form.errors[`title.${code}`]" />
                                    </div>
                                    <div>
                                        <Label :for="`content_${code}`">Content ({{ name }})</Label>
                                        <RichTextEditor :id="`content_${code}`" v-model="form.content[code]" />
                                        <InputError class="mt-2" :message="form.errors[`content.${code}`]" />
                                    </div>
                                </div>

                                <div class="flex items-center space-x-2">
                                    <Switch id="is_published" v-model="form.is_published" />
                                    <Label for="is_published">Published</Label>
                                    <InputError class="mt-2" :message="form.errors.is_published" />
                                </div>
                            </div>

                            <div class="flex items-center justify-end mt-6 gap-4">
                                <Link :href="route('admin.cms-pages.index')" class="text-sm text-gray-600 hover:text-gray-900">
                                    Cancel
                                </Link>
                                <Button :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                                    Update Page
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
