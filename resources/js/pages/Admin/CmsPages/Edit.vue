<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import InputError from '@/components/InputError.vue'
import { Switch } from '@/components/ui/switch'
import RichTextEditor from '@/components/Form/RichTextEditor.vue'
interface CmsPage {
    id: number;
    title: Record<string, string>;
    content: Record<string, string>;
    slug: string;
    is_published: boolean;
    published_at: string | null;
    author: { name: string } | null;
    created_at: string;
    updated_at: string;
    sort_order: number;
}

const props = defineProps<{
  page: CmsPage,
  available_locales: Record<string, string>
}>()

const form = useForm({
  title: { ...props.page.title },
  content: { ...props.page.content },
  slug: props.page.slug || '',
  is_published: props.page.is_published,
  sort_order: props.page.sort_order || 0,
})

const submit = () => {
  form.put(route('admin.cms-pages.update', props.page.id))
}
</script>

<template>
  <Head :title="`Edit CMS Page: ${form.title.en}`" />

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
                <div v-for="(name, code) in available_locales" :key="code" class="space-y-2 p-4 border rounded-md">
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

                <!-- Page Settings -->
                <div class="space-y-4 p-4 border rounded-md">
                  <h3 class="font-semibold text-lg">Page Settings</h3>

                  <div>
                    <Label for="slug">URL Slug</Label>
                    <Input id="slug" v-model="form.slug" type="text" placeholder="e.g., about-us" />
                    <p class="text-sm text-muted-foreground mt-1">Leave blank to auto-generate from the English title.</p>
                    <InputError class="mt-2" :message="form.errors.slug" />
                  </div>

                  <div>
                    <Label for="sort_order">Sort Order</Label>
                    <Input id="sort_order" v-model="form.sort_order" type="number" />
                    <InputError class="mt-2" :message="form.errors.sort_order" />
                  </div>

                  <div class="flex items-center space-x-2">
                    <Switch id="is_published" v-model="form.is_published" />
                    <Label for="is_published">Published</Label>
                    <InputError class="mt-2" :message="form.errors.is_published" />
                  </div>
                </div>
              </div>

              <div class="flex items-center justify-end mt-6 gap-4">
                <Link :href="route('admin.cms-pages.index')" class="text-sm text-gray-600 hover:text-gray-900">
                  Cancel
                </Link>
                <Button :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                  Save Changes
                </Button>
              </div>
            </form>
          </CardContent>
        </Card>
      </div>
    </div>
  </AppLayout>
</template>
