<script setup>
import { Head } from '@inertiajs/vue3'
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    canLogin: {
        type: Boolean,
    },
    canRegister: {
        type: Boolean,
    },
});
</script>

<template>
    <Head title="ようこそ" />

    <div class="flex items-center justify-center min-h-screen bg-gray-100">
        <div class="text-center">
            <h1 class="text-4xl font-bold mb-8">Welcome to e-Admin</h1>
            <div class="space-x-4">
                <Link
                    v-if="canLogin"
                    :href="route('login')"
                    class="px-6 py-3 text-white bg-indigo-600 rounded-md hover:bg-indigo-700"
                    >Log in</Link
                >

                <Link
                    v-if="canRegister"
                    :href="route('register')"
                    class="px-6 py-3 text-white bg-green-600 rounded-md hover:bg-green-700"
                    >Register</Link
                >
            </div>
        </div>
    </div>
</template>
