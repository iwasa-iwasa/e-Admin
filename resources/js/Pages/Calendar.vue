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
    <div class="flex gap-6 max-w-[1800px] mx-auto h-[calc(100vh-140px)]">
        <div class="flex-[2.9] h-full flex flex-col">
            <div v-if="filteredMember" class="mb-4">
              <Badge variant="secondary" class="flex items-center gap-2 text-sm py-1 px-3">
                <span>フィルター中: {{ filteredMember.name }}</span>
                <button @click="clearFilter" class="rounded-full hover:bg-muted">
                  <X class="h-4 w-4" />
                </button>
              </Badge>
            </div>
            <div class="flex-1 min-h-0">
              <SharedCalendar :events="props.events" />
            </div>
        </div>
    </div>
</template>