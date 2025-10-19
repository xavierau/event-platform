<template>
    <footer class="border-t bg-gray-800 p-6 text-center text-white dark:border-gray-700 dark:bg-gray-950 dark:text-gray-300">
        <div>
            <ul class="flex justify-center space-x-4">
                <li><a href="/pages/about-us" class="hover:underline">About Us</a></li>
                <li><a href="/pages/privacy-policies" class="hover:underline">Privacy Policy</a></li>
                <li><a href="/pages/contact-us" class="hover:underline">Contact Us</a></li>
                <li><a href="/pages/terms-and-conditions" class="hover:underline">Terms and Conditions</a></li>
            </ul>
        </div>
        <div>
            <p>&copy; {{ new Date().getFullYear() }} Showeasy. All rights reserved. Made with ❤️</p>
        </div>
    </footer>
</template>

<script setup lang="ts">

</script>
