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
  selectedMember: string | null
}>()

const emit = defineEmits<{
  (e: 'update:selectedMember', memberId: string | null): void
}>()

const members = [
  { id: '1', name: '田中', initial: '田', color: '#3b82f6' },
  { id: '2', name: '佐藤', initial: '佐', color: '#10b981' },
  { id: '3', name: '鈴木', initial: '鈴', color: '#f59e0b' },
  { id: '4', name: '山田', initial: '山', color: '#ef4444' },
]

const selectedMemberData = computed(() => members.find((m) => m.id === props.selectedMember))

const clearSelectedMember = () => {
    emit('update:selectedMember', null)
}

</script>

<template>
    <div v-if="selectedMember" class="max-w-[1800px] mx-auto mb-4">
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 flex items-center justify-between">
            <div class="flex items-center gap-2">
            <Badge
                class="text-white"
                :style="{ backgroundColor: selectedMemberData?.color }"
            >
                {{ selectedMemberData?.name }}
            </Badge>
            <span class="text-sm text-gray-700">
                の予定のみを表示中
            </span>
            </div>
            <Button
            variant="ghost"
            size="sm"
            @click="clearSelectedMember"
            class="h-7"
            >
            <X class="h-4 w-4" />
            フィルター解除
            </Button>
        </div>
    </div>
    <div class="flex gap-6 max-w-[1800px] mx-auto h-[calc(100vh-140px)]">
        <div class="flex-[2.9] h-full">
            <SharedCalendar :selected-member="selectedMember" />
        </div>
        <div class="flex-[2.1] space-y-6 h-full flex flex-col">
            <div class="flex-[1.1] min-h-0">
            <SharedNotes />
            </div>
            <div class="flex-[1.1] min-h-0">
            <PersonalReminders />
            </div>
        </div>
    </div>
</template>
