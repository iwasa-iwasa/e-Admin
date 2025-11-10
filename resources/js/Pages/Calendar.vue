<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import SharedCalendar from '@/components/SharedCalendar.vue'

defineOptions({
  layout: AuthenticatedLayout,
})

const props = defineProps<{
  events: App.Models.Event[]
}>()
</script>

<template>
    <Head title="カレンダー" />
    <div class="p-6">
        <SharedCalendar :events="events" />
    </div>
</template>