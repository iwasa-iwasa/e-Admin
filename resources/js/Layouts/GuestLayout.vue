<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';

defineProps<{
    title?: string;
    showBackButton?: boolean;
}>();

const goBack = () => {
    router.visit(route('welcome'));
};
</script>

<template>
    <div
        class="relative flex justify-center items-center min-h-screen bg-gray-100 dark:bg-gray-900"
    >
        <div class="max-w-md w-full p-6 lg:p-8 bg-white dark:bg-gray-800/50 rounded-lg shadow-2xl">
            <Link :href="route('welcome')" class="block">
                <div class="flex justify-center mb-8 cursor-pointer">
                    <svg id="_レイヤー_1" xmlns="http://www.w3.org/2000/svg" version="1.1" viewBox="0 0 129.17 13.81" class="w-[90%]">
                        <g>
                            <rect class="fill-[#22ac38]" y=".98" width="5.73" height="12.85"/>
                            <rect class="fill-[#22ac38]" x="13.6" y=".98" width="5.73" height="12.85"/>
                            <rect class="fill-[#22ac38]" x="6.8" y=".98" width="5.73" height="12.85"/>
                        </g>
                        <g class="fill-[#3e3a39] dark:fill-white">
                            <path d="M31.86,9.03c0-2.72,2.29-4.74,5.38-4.74s5.33,2,5.33,4.76v.54h-8.49c.28,1.7,1.72,2.79,3.72,2.79,1.28,0,2.2-.45,2.42-1.18h2.2c-.33,1.59-2.15,2.61-4.68,2.61-3.48,0-5.88-1.95-5.88-4.77h0ZM40.26,8.15c-.28-1.45-1.46-2.43-3.05-2.43s-2.76.98-3.09,2.43h6.14Z"/>
                            <path d="M46.99,7.89h7.4v1.61h-7.4v-1.61Z"/>
                            <path d="M68.23,10.21h-6.01l-1.37,3.31h-2.37l5.22-12.52h3.05l5.22,12.52h-2.37s-1.37-3.31-1.37-3.31ZM67.56,8.6l-2.33-5.56-2.33,5.56h4.66Z"/>
                            <path d="M83.01,12.3c-.81.93-2.15,1.5-3.76,1.5-2.92,0-4.94-1.93-4.94-4.76s2.02-4.76,4.94-4.76c1.61,0,2.96.57,3.76,1.5V1h2.18v12.52h-2.18v-1.22ZM79.66,12.37c1.94,0,3.35-1.39,3.35-3.33s-1.42-3.33-3.35-3.33-3.18,1.34-3.18,3.33,1.28,3.33,3.18,3.33Z"/>
                            <path d="M97.74,5.9c-1.13-.02-1.63.93-1.63,3.08v4.54h-2.09v-5.7c0-1.23-.52-1.9-1.52-1.91-1.02-.02-1.39.7-1.42,2.81l-.02,4.81h-2.11V4.58h2.15v1.02c.48-.82,1.28-1.31,2.28-1.31,1.13,0,2,.59,2.42,1.59.52-.98,1.46-1.59,2.63-1.59,1.68,0,2.74,1.23,2.74,3.17v6.06h-2.13v-5.7c0-1.29-.41-1.9-1.31-1.91h0Z"/>
                            <path d="M104.77,11.91h4.05v-5.72h-3.39v-1.61h5.57v7.33h4.05v1.61h-10.27v-1.61h-.01ZM108.49.02h2.83v2.5h-2.83V.02Z"/>
                            <path d="M124.47,5.9c-1.81-.02-3.18,1.11-3.18,2.61v5.01h-2.18V4.58h2.18v1.11c.72-.86,2.04-1.39,3.66-1.39,2.48,0,4.22,1.31,4.22,3.17v6.06h-2.18v-5.7c0-1.11-1.04-1.9-2.52-1.91v-.02Z"/>
                        </g>
                    </svg>
                </div>

                <div v-if="title" class="text-center mb-6">
                    <h2 class="text-2xl font-bold text-gray-900 dark:text-white">{{ title }}</h2>
                </div>
            </Link>

            <slot :show-back-button="showBackButton" :go-back="goBack" />
        </div>
    </div>
</template>
