<script setup lang="ts">
import { Head, usePage, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import SharedCalendar from '@/components/SharedCalendar.vue'
import { computed } from 'vue'
import { ArrowLeft, Calendar as CalendarIcon, X } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { CardTitle } from '@/components/ui/card'

defineOptions({
  layout: AuthenticatedLayout,
})

const props = defineProps<{
  events: App.Models.ExpandedEvent[]
  filteredMemberId?: number | null
  teamMembers: App.Models.User[]
}>()

const filteredMember = computed(() => {
  if (!props.filteredMemberId) return null
  return props.teamMembers.find(member => member.id === props.filteredMemberId)
})

const clearFilter = () => {
  router.get(route('calendar'), {}, {
    preserveState: true,
    replace: true,
  })
}
</script>

<template>
    <Head title="カレンダー" />
    <div class="flex gap-6 mx-auto h-full p-4 md:p-6">
        <div class="flex-1 h-full flex flex-col">
            <SharedCalendar :events="props.events" :show-back-button="true" :filtered-member-id="props.filteredMemberId" />
        </div>
    </div>
</template>