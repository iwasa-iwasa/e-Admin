<script setup lang="ts">
import { computed } from 'vue'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
// @ts-ignore
import SharedCalendar from '@/Components/SharedCalendar.vue'
// @ts-ignore
import SharedNotes from '@/Components/SharedNotes.vue'
// @ts-ignore
import PersonalReminders from '@/Components/PersonalReminders.vue'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { X } from 'lucide-vue-next'

defineOptions({
  layout: AuthenticatedLayout,
})

const props = defineProps<{
  events: Object[]
  sharedNotes: Object[]
  personalReminders: Object[]
}>()

</script>

<template>
    <div class="flex gap-6 max-w-[1800px] mx-auto h-[calc(100vh-140px)]">
        <div class="flex-[2.9] h-full">
            <SharedCalendar :events="events" />
        </div>
        <div class="flex-[2.1] space-y-6 h-full flex flex-col">
            <div class="flex-[1.1] min-h-0">
            <SharedNotes :notes="sharedNotes" />
            </div>
            <div class="flex-[1.1] min-h-0">
            <PersonalReminders :reminders="personalReminders" />
            </div>
        </div>
    </div>
</template>
