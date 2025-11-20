<script setup lang="ts">
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';
import { Head } from '@inertiajs/vue3';

defineProps<{
    mustVerifyEmail?: boolean;
    status?: string;
}>();
</script>

<template>
    <Head title="プロフィール設定" />

    <div class="flex gap-6 max-w-[1000px] mx-auto h-full p-6">
        <div class="flex-1 h-full flex flex-col">
            
            <div class="flex-1 overflow-y-auto space-y-6 scrollbar-hide">
                <div class="bg-white p-6 shadow rounded-lg">
                    <UpdateProfileInformationForm
                        :must-verify-email="mustVerifyEmail"
                        :status="status"
                    />
                </div>

                <div class="bg-white p-6 shadow rounded-lg">
                    <UpdatePasswordForm />
                </div>
            </div>
        </div>
    </div>
</template>
