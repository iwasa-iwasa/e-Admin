<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import ApplicationLogo from '@/components/ApplicationLogo.vue';

defineOptions({ layout: null });
</script>

<template>
    <Head title="Welcome" />

    <div class="relative flex justify-center items-center min-h-screen bg-gray-100 dark:bg-gray-900">
        <div class="max-w-md w-full p-6 lg:p-8 bg-white dark:bg-gray-800/50 rounded-lg shadow-2xl">
            <div class="flex justify-center mb-8">
                <ApplicationLogo class="w-24 h-24 fill-current text-gray-500" />
            </div>

            <div class="text-center">
                <h1 class="text-3xl font-bold text-gray-900 dark:text-white mb-4">
                    e-Adminへようこそ
                </h1>
                <p class="text-gray-600 dark:text-gray-400 mb-8">
                    ログインまたは新規登録して、サービスをご利用ください。
                </p>
            </div>

            <div class="flex flex-col space-y-4">
                <Link
                    :href="route('login')"
                    class="w-full text-center px-4 py-3 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                >
                    ログイン
                </Link>

                <Link
                    :href="route('register')"
                    class="w-full text-center px-4 py-3 bg-gray-600 text-white font-semibold rounded-lg hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500"
                >
                    新規登録
                </Link>
            </div>
        </div>
    </div>
</template>