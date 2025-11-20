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
  events: App.Models.Event[]
  filteredMemberId?: number | null
  teamMembers: App.Models.User[]
}>()

const filteredMember = computed(() => {
  if (!props.filteredMemberId) return null
  return props.teamMembers.find(member => member.id === props.filteredMemberId)
})

const clearFilter = () => {
  router.get(route('dashboard'), {}, {
    preserveState: true,
    replace: true,
  })
}
</script>

<template>
    <Head title="カレンダー" />
    <div class="flex gap-6 max-w-[1800px] mx-auto h-full p-6">
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
              <SharedCalendar :events="props.events" :show-back-button="true" />
            </div>
        </div>
    </div>
</template>