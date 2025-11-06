<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps<{
    href: string;
    active?: boolean;
}>();

const classes = computed(() =>
    props.active
        ? 'inline-flex items-center px-3 py-2 rounded-md text-sm font-medium text-primary-foreground bg-primary focus:outline-none transition ease-in-out duration-150'
        : 'inline-flex items-center px-3 py-2 rounded-md text-sm font-medium text-gray-500 hover:text-gray-700 hover:bg-gray-100 focus:outline-none focus:bg-gray-100 focus:text-gray-700 transition ease-in-out duration-150',
);
</script>

<template>
    <Link :href="href" :class="classes">
        <slot />
    </Link>
</template>
