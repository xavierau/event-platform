<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

interface SeoData {
    meta_title?: string;
    meta_description?: string;
    keywords?: string;
    og_title?: string;
    og_description?: string;
    og_image_url?: string;
    canonical_url?: string;
}

const props = defineProps<{
    seo: SeoData;
    fallbackTitle?: string;
    fallbackDescription?: string;
}>();

// Generate meta tags with fallbacks
const metaTitle = props.seo.meta_title || props.fallbackTitle || 'Event Platform';
const metaDescription = props.seo.meta_description || props.fallbackDescription || '';
const ogTitle = props.seo.og_title || metaTitle;
const ogDescription = props.seo.og_description || metaDescription;
const ogImageUrl = props.seo.og_image_url || '';
const canonicalUrl = props.seo.canonical_url || '';
const keywords = props.seo.keywords || '';
</script>

<template>
    <Head :title="metaTitle">
        <!-- Basic meta tags -->
        <meta name="description" :content="metaDescription" v-if="metaDescription" />
        <meta name="keywords" :content="keywords" v-if="keywords" />

        <!-- Canonical URL -->
        <link rel="canonical" :href="canonicalUrl" v-if="canonicalUrl" />

        <!-- Open Graph meta tags for social sharing -->
        <meta property="og:title" :content="ogTitle" />
        <meta property="og:description" :content="ogDescription" v-if="ogDescription" />
        <meta property="og:image" :content="ogImageUrl" v-if="ogImageUrl" />
        <meta property="og:url" :content="canonicalUrl" v-if="canonicalUrl" />
        <meta property="og:type" content="event" />
        <meta property="og:site_name" content="Event Platform" />

        <!-- Twitter Card meta tags -->
        <meta name="twitter:card" content="summary_large_image" />
        <meta name="twitter:title" :content="ogTitle" />
        <meta name="twitter:description" :content="ogDescription" v-if="ogDescription" />
        <meta name="twitter:image" :content="ogImageUrl" v-if="ogImageUrl" />

        <!-- Additional structured data indicators -->
        <meta property="article:content_tier" content="free" />
        <meta name="robots" content="index, follow" />
    </Head>
</template>