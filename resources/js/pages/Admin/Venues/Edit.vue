<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AppLayout.vue';
import { VenueData, Country, State } from '@/types';
import RichTextEditor from '@/Components/Form/RichTextEditor.vue';
import MediaUpload from '@/Components/Form/MediaUpload.vue';

interface Props {
    venue: VenueData;
    countries: Country[];
    states: State[];
}

const props = defineProps<Props>();

const form = useForm<VenueData & {
    uploaded_main_image?: File | null;
    removed_main_image_id?: number | null;
    uploaded_gallery_images?: File[];
    removed_gallery_image_ids?: number[];
}> ({
    id: props.venue.id,
    name: props.venue.name || { en: '', 'zh-TW': '', 'zh-CN': '' },
    slug: props.venue.slug || '',
    description: props.venue.description || { en: '', 'zh-TW': '', 'zh-CN': '' },
    address_line_1: props.venue.address_line_1 || { en: '', 'zh-TW': '', 'zh-CN': '' },
    address_line_2: props.venue.address_line_2 || { en: '', 'zh-TW': '', 'zh-CN': '' },
    city: props.venue.city || { en: '', 'zh-TW': '', 'zh-CN': '' },
    postal_code: props.venue.postal_code || '',
    country_id: props.venue.country_id || null,
    state_id: props.venue.state_id || null,
    latitude: props.venue.latitude || null,
    longitude: props.venue.longitude || null,
    contact_email: props.venue.contact_email || '',
    contact_phone: props.venue.contact_phone || '',
    website_url: props.venue.website_url || '',
    seating_capacity: props.venue.seating_capacity || null,
    is_active: props.venue.is_active === undefined ? true : props.venue.is_active,
    organizer_id: props.venue.organizer_id || null,

    uploaded_main_image: null,
    existing_main_image: props.venue.existing_main_image || null,
    removed_main_image_id: null,

    uploaded_gallery_images: [],
    existing_gallery_images: props.venue.existing_gallery_images || [],
    removed_gallery_image_ids: [],
});

const submit = () => {
    if (props.venue.id) {
        console.log('Original form data before transform:', JSON.parse(JSON.stringify(form, (k, v) => {
            if (v instanceof File) return `File(${v.name})`;
            if (Array.isArray(v) && v.length > 0 && v.every(item => item instanceof File)) return v.map(f => `File(${f.name})`);
            return v;
        })));

        form.transform(data => {
            const dataForSubmission: Record<string, any> = {
                id: data.id,
                slug: data.slug,
                postal_code: data.postal_code,
                country_id: data.country_id,
                state_id: data.state_id,
                latitude: data.latitude,
                longitude: data.longitude,
                contact_email: data.contact_email,
                contact_phone: data.contact_phone,
                website_url: data.website_url,
                seating_capacity: data.seating_capacity,
                is_active: data.is_active,
                organizer_id: data.organizer_id,

                // Translatable fields - now send as nested objects
                name: data.name,
                description: data.description,
                address_line_1: data.address_line_1,
                address_line_2: data.address_line_2,
                city: data.city,

                // File and removal IDs
                removed_main_image_id: data.removed_main_image_id,
                removed_gallery_image_ids: data.removed_gallery_image_ids && data.removed_gallery_image_ids.length > 0
                    ? data.removed_gallery_image_ids
                    : [],
            };

            // Handle file uploads
            if (data.uploaded_main_image instanceof File) {
                dataForSubmission.uploaded_main_image = data.uploaded_main_image;
            } else {
                dataForSubmission.uploaded_main_image = null;
            }
            if (data.uploaded_gallery_images && data.uploaded_gallery_images.length > 0 && data.uploaded_gallery_images.every(f => f instanceof File)) {
                dataForSubmission.uploaded_gallery_images = data.uploaded_gallery_images;
            } else {
                dataForSubmission.uploaded_gallery_images = [];
            }

            console.log('Data after transform (to be submitted):', JSON.parse(JSON.stringify(dataForSubmission, (k, v) => {
                if (v instanceof File) return `File(${v.name})`;
                if (Array.isArray(v) && v.length > 0 && v.every(item => item instanceof File)) return v.map(f => `File(${f.name})`);
                return v;
            })));

            dataForSubmission["_method"] = "PUT";

            return dataForSubmission;
        });

        console.log('Submitting PUT request to:', route('admin.venues.update', props.venue.id));

        form.post(route('admin.venues.update', props.venue.id), {
            preserveScroll: true,
            preserveState: true,
            onSuccess: (page) => {
                console.log('Venue update successful:', page.props);
            },
            onError: (errors) => {
                console.error('Venue update failed with errors:', errors);
            },
            onFinish: () => {
                console.log('Venue update submission process finished.');
            }
        });
    }
};

const handleRemoveExistingMainImage = (event: { collection: string; id: number }) => {
    if (event.collection === 'main_image') {
        form.removed_main_image_id = event.id;
        form.existing_main_image = null;
    }
};

const handleRemoveExistingGalleryImage = (event: { collection: string; id: number }) => {
    if (event.collection === 'gallery_images') {
        if (!form.removed_gallery_image_ids.includes(event.id)) {
            form.removed_gallery_image_ids.push(event.id);
        }
        form.existing_gallery_images = form.existing_gallery_images.filter(image => image.id !== event.id);
    }
};

const getTranslation = (translations: any, locale: string = 'en', fallbackLocale: string = 'en') => {
    if (!translations) return '';
    if (typeof translations === 'string') return translations;
    return translations[locale] || translations[fallbackLocale] || Object.values(translations)[0] || '';
};

const dummyCountries = [
    { id: 1, name: { en: 'Hong Kong SAR China' } },
    { id: 2, name: { en: 'Macau SAR China' } },
];
const dummyStates = [
    { id: 1, country_id: 1, name: { en: 'Hong Kong Island' } },
    { id: 2, country_id: 1, name: { en: 'Kowloon' } },
];

</script>

<template>
    <Head :title="`Edit Venue: ${getTranslation(form.name)}`" />

    <AuthenticatedLayout>
        <div class="py-12">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 md:p-8 text-gray-900 dark:text-gray-100">
                        <form @submit.prevent="submit">
                            <div class="space-y-6">

                                <fieldset class="border dark:border-gray-700 p-4 rounded-md">
                                    <legend class="text-sm font-medium text-gray-700 dark:text-gray-300 px-1">Venue Name</legend>
                                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mt-2">
                                        <div>
                                            <label for="name_en" class="block text-xs font-medium text-gray-700 dark:text-gray-400">English</label>
                                            <input type="text" v-model="form.name.en" id="name_en" class="mt-1 block w-full input-sm" />
                                        </div>
                                        <div>
                                            <label for="name_zh_TW" class="block text-xs font-medium text-gray-700 dark:text-gray-400">Traditional Chinese</label>
                                            <input type="text" v-model="form.name['zh-TW']" id="name_zh_TW" class="mt-1 block w-full input-sm" />
                                        </div>
                                        <div>
                                            <label for="name_zh_CN" class="block text-xs font-medium text-gray-700 dark:text-gray-400">Simplified Chinese</label>
                                            <input type="text" v-model="form.name['zh-CN']" id="name_zh_CN" class="mt-1 block w-full input-sm" />
                                        </div>
                                    </div>
                                    <div v-if="form.errors.name" class="input-error">{{ form.errors.name }}</div>
                                </fieldset>

                                <div>
                                    <label for="slug" class="label">Slug</label>
                                    <input type="text" v-model="form.slug" id="slug" class="mt-1 block w-full input" />
                                    <div v-if="form.errors.slug" class="input-error">{{ form.errors.slug }}</div>
                                </div>

                                <fieldset class="border dark:border-gray-700 p-4 rounded-md">
                                    <legend class="text-sm font-medium text-gray-700 dark:text-gray-300 px-1">Description</legend>
                                    <div class="space-y-4 mt-2">
                                        <div>
                                            <label for="description_en" class="block text-xs font-medium text-gray-700 dark:text-gray-400 mb-1">English</label>
                                            <RichTextEditor v-model="form.description.en" id="description_en" />
                                        </div>
                                        <div>
                                            <label for="description_zh_TW" class="block text-xs font-medium text-gray-700 dark:text-gray-400 mb-1">Traditional Chinese</label>
                                            <RichTextEditor v-model="form.description['zh-TW']" id="description_zh_TW" />
                                        </div>
                                        <div>
                                            <label for="description_zh_CN" class="block text-xs font-medium text-gray-700 dark:text-gray-400 mb-1">Simplified Chinese</label>
                                            <RichTextEditor v-model="form.description['zh-CN']" id="description_zh_CN" />
                                        </div>
                                    </div>
                                    <div v-if="form.errors.description" class="input-error">{{ form.errors.description }}</div>
                                </fieldset>

                                <MediaUpload
                                    v-model="form.uploaded_main_image"
                                    :existingMedia="form.existing_main_image"
                                    collectionName="featured_image"
                                    label="Main Image"
                                    :multiple="false"
                                    @remove-existing="handleRemoveExistingMainImage"
                                />
                                <div v-if="form.errors.uploaded_main_image" class="input-error mt-1">{{ form.errors.uploaded_main_image }}</div>
                                <div v-if="form.errors.removed_main_image_id" class="input-error mt-1">{{ form.errors.removed_main_image_id }}</div>

                                <MediaUpload
                                    v-model="form.uploaded_gallery_images"
                                    :existingMedia="form.existing_gallery_images"
                                    collectionName="gallery"
                                    label="Gallery Images"
                                    :multiple="true"
                                    :maxFiles="10"
                                    @remove-existing="handleRemoveExistingGalleryImage"
                                />
                                <div v-if="form.errors.uploaded_gallery_images" class="input-error mt-1">{{ form.errors.uploaded_gallery_images }}</div>
                                <div v-if="form.errors.removed_gallery_image_ids" class="input-error mt-1">{{ form.errors.removed_gallery_image_ids }}</div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label for="country_id" class="label">Country</label>
                                        <select v-model="form.country_id" id="country_id" class="mt-1 block w-full input">
                                            <option :value="null">-- Select Country --</option>
                                            <option v-for="country in (props.countries || dummyCountries)" :key="country.id" :value="country.id">
                                                {{ getTranslation(country.name) }}
                                            </option>
                                        </select>
                                        <div v-if="form.errors.country_id" class="input-error">{{ form.errors.country_id }}</div>
                                    </div>

                                    <div>
                                        <label for="state_id" class="label">State/Province</label>
                                        <select v-model="form.state_id" id="state_id" class="mt-1 block w-full input" :disabled="!form.country_id">
                                            <option :value="null">-- Select State/Province --</option>
                                            <option v-for="state in (props.states || dummyStates).filter(s => s.country_id === form.country_id)" :key="state.id" :value="state.id">
                                                 {{ getTranslation(state.name) }}
                                            </option>
                                        </select>
                                        <div v-if="form.errors.state_id" class="input-error">{{ form.errors.state_id }}</div>
                                    </div>
                                </div>

                                <fieldset class="border dark:border-gray-700 p-4 rounded-md">
                                    <legend class="text-sm font-medium text-gray-700 dark:text-gray-300 px-1">Address Line 1</legend>
                                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mt-2">
                                        <div>
                                            <label for="address_line_1_en" class="block text-xs font-medium text-gray-700 dark:text-gray-400">English</label>
                                            <input type="text" v-model="form.address_line_1.en" id="address_line_1_en" class="mt-1 block w-full input-sm" />
                                        </div>
                                        <div>
                                            <label for="address_line_1_zh_TW" class="block text-xs font-medium text-gray-700 dark:text-gray-400">Traditional Chinese</label>
                                            <input type="text" v-model="form.address_line_1['zh-TW']" id="address_line_1_zh_TW" class="mt-1 block w-full input-sm" />
                                        </div>
                                        <div>
                                            <label for="address_line_1_zh_CN" class="block text-xs font-medium text-gray-700 dark:text-gray-400">Simplified Chinese</label>
                                            <input type="text" v-model="form.address_line_1['zh-CN']" id="address_line_1_zh_CN" class="mt-1 block w-full input-sm" />
                                        </div>
                                    </div>
                                    <div v-if="form.errors.address_line_1" class="input-error">{{ form.errors.address_line_1 }}</div>
                                </fieldset>

                                <fieldset class="border dark:border-gray-700 p-4 rounded-md">
                                    <legend class="text-sm font-medium text-gray-700 dark:text-gray-300 px-1">Address Line 2 (Optional)</legend>
                                     <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mt-2">
                                        <div>
                                            <label for="address_line_2_en" class="block text-xs font-medium text-gray-700 dark:text-gray-400">English</label>
                                            <input type="text" v-model="form.address_line_2.en" id="address_line_2_en" class="mt-1 block w-full input-sm" />
                                        </div>
                                        <div>
                                            <label for="address_line_2_zh_TW" class="block text-xs font-medium text-gray-700 dark:text-gray-400">Traditional Chinese</label>
                                            <input type="text" v-model="form.address_line_2['zh-TW']" id="address_line_2_zh_TW" class="mt-1 block w-full input-sm" />
                                        </div>
                                        <div>
                                            <label for="address_line_2_zh_CN" class="block text-xs font-medium text-gray-700 dark:text-gray-400">Simplified Chinese</label>
                                            <input type="text" v-model="form.address_line_2['zh-CN']" id="address_line_2_zh_CN" class="mt-1 block w-full input-sm" />
                                        </div>
                                    </div>
                                    <div v-if="form.errors.address_line_2" class="input-error">{{ form.errors.address_line_2 }}</div>
                                </fieldset>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                     <fieldset class="border dark:border-gray-700 p-4 rounded-md md:col-span-2">
                                        <legend class="text-sm font-medium text-gray-700 dark:text-gray-300 px-1">City</legend>
                                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mt-2">
                                            <div>
                                                <label for="city_en" class="block text-xs font-medium text-gray-700 dark:text-gray-400">English</label>
                                                <input type="text" v-model="form.city.en" id="city_en" class="mt-1 block w-full input-sm" />
                                            </div>
                                            <div>
                                                <label for="city_zh_TW" class="block text-xs font-medium text-gray-700 dark:text-gray-400">Traditional Chinese</label>
                                                <input type="text" v-model="form.city['zh-TW']" id="city_zh_TW" class="mt-1 block w-full input-sm" />
                                            </div>
                                            <div>
                                                <label for="city_zh_CN" class="block text-xs font-medium text-gray-700 dark:text-gray-400">Simplified Chinese</label>
                                                <input type="text" v-model="form.city['zh-CN']" id="city_zh_CN" class="mt-1 block w-full input-sm" />
                                            </div>
                                        </div>
                                        <div v-if="form.errors.city" class="input-error">{{ form.errors.city }}</div>
                                    </fieldset>

                                    <div>
                                        <label for="postal_code" class="label">Postal Code</label>
                                        <input type="text" v-model="form.postal_code" id="postal_code" class="mt-1 block w-full input" />
                                        <div v-if="form.errors.postal_code" class="input-error">{{ form.errors.postal_code }}</div>
                                    </div>

                                     <div>
                                        <label for="seating_capacity" class="label">Seating Capacity</label>
                                        <input type="number" v-model.number="form.seating_capacity" id="seating_capacity" class="mt-1 block w-full input" />
                                        <div v-if="form.errors.seating_capacity" class="input-error">{{ form.errors.seating_capacity }}</div>
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label for="latitude" class="label">Latitude</label>
                                        <input type="number" step="any" v-model.number="form.latitude" id="latitude" class="mt-1 block w-full input" />
                                        <div v-if="form.errors.latitude" class="input-error">{{ form.errors.latitude }}</div>
                                    </div>

                                    <div>
                                        <label for="longitude" class="label">Longitude</label>
                                        <input type="number" step="any" v-model.number="form.longitude" id="longitude" class="mt-1 block w-full input" />
                                        <div v-if="form.errors.longitude" class="input-error">{{ form.errors.longitude }}</div>
                                    </div>
                                </div>

                                <div class="flex items-center">
                                    <input type="checkbox" v-model="form.is_active" id="is_active" class="h-4 w-4 text-indigo-600 border-gray-300 dark:border-gray-700 dark:bg-gray-900 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded" />
                                    <label for="is_active" class="ml-2 block text-sm text-gray-900 dark:text-gray-100">Active</label>
                                </div>
                                <div v-if="form.errors.is_active" class="input-error">{{ form.errors.is_active }}</div>

                            </div>

                            <div class="mt-8 flex justify-end space-x-3">
                                <Link :href="route('admin.venues.index')" class="btn btn-secondary">Cancel</Link>
                                <button type="submit" :disabled="form.processing"
                                        class="btn btn-primary"
                                        :class="{ 'opacity-25': form.processing }">
                                    Update Venue
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>


