<script setup lang="ts">
import { Head, usePage, router } from '@inertiajs/vue3'
import { computed, ref, onMounted, onUnmounted } from 'vue'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import SharedCalendar from '@/components/SharedCalendar.vue'
import SharedNotes from '@/components/SharedNotes.vue'
import PersonalReminders from '@/components/PersonalReminders.vue'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { X, GripVertical } from 'lucide-vue-next'

defineOptions({
  layout: AuthenticatedLayout,
})

const props = defineProps<{
  events: App.Models.Event[];
  sharedNotes: App.Models.SharedNote[];
  personalReminders: App.Models.Reminder[];
  filteredMemberId?: number | null
  teamMembers: App.Models.User[]
  totalUsers: number
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

const calendarWidth = ref(parseFloat(localStorage.getItem('dashboard_calendar_width') || '58'))
const notesHeight = ref(parseFloat(localStorage.getItem('dashboard_notes_height') || '50'))
const isDraggingH = ref(false)
const isDraggingV = ref(false)

const startDragH = (e: MouseEvent) => {
  e.preventDefault()
  isDraggingH.value = true
  document.body.style.cursor = 'col-resize'
  document.body.style.userSelect = 'none'
}

const startDragV = (e: MouseEvent) => {
  e.preventDefault()
  isDraggingV.value = true
  document.body.style.cursor = 'row-resize'
  document.body.style.userSelect = 'none'
}

const onDrag = (e: MouseEvent) => {
  if (isDraggingH.value) {
    e.preventDefault()
    const container = document.querySelector('.dashboard-container')
    if (container) {
      const rect = container.getBoundingClientRect()
      const newWidth = ((e.clientX - rect.left) / rect.width) * 100
      const minWidthPx = 710
      const minWidthPercent = (minWidthPx / rect.width) * 100
      if (newWidth > minWidthPercent && newWidth < 70) {
        calendarWidth.value = newWidth
        localStorage.setItem('dashboard_calendar_width', newWidth.toString())
      }
    }
  }
  if (isDraggingV.value) {
    e.preventDefault()
    const container = document.querySelector('.right-panel')
    if (container) {
      const rect = container.getBoundingClientRect()
      const newHeight = ((e.clientY - rect.top) / rect.height) * 100
      if (newHeight > 20 && newHeight < 80) {
        notesHeight.value = newHeight
        localStorage.setItem('dashboard_notes_height', newHeight.toString())
      }
    }
  }
}

const stopDrag = () => {
  if (isDraggingH.value || isDraggingV.value) {
    isDraggingH.value = false
    isDraggingV.value = false
    document.body.style.cursor = ''
    document.body.style.userSelect = ''
    window.dispatchEvent(new Event('resize'))
  }
}

onMounted(() => {
  window.addEventListener('mousemove', onDrag)
  window.addEventListener('mouseup', stopDrag)
})

onUnmounted(() => {
  window.removeEventListener('mousemove', onDrag)
  window.removeEventListener('mouseup', stopDrag)
})
</script>

<template>
    <Head title="ホーム" />
    <div class="flex max-w-[1800px] mx-auto h-full p-6 dashboard-container">
        <div :style="{ width: calendarWidth + '%' }" class="h-full flex flex-col pr-3">
            <SharedCalendar :events="events" />
        </div>
        <div class="relative flex items-center justify-center" style="width: 12px; margin: 0 -6px;">
          <div 
            class="absolute inset-0 cursor-col-resize z-10"
            @mousedown="startDragH"
          ></div>
          <div class="w-1 h-full bg-gray-300 group-hover:bg-blue-500 transition-colors pointer-events-none relative z-0">
            <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2">
              <GripVertical class="h-4 w-4 text-gray-400 group-hover:text-white" />
            </div>
          </div>
        </div>
        <div :style="{ width: (100 - calendarWidth) + '%' }" class="h-full flex flex-col pl-3 right-panel">
            <div :style="{ height: notesHeight + '%' }" class="min-h-0 pb-3">
              <SharedNotes :notes="sharedNotes" :totalUsers="totalUsers" :teamMembers="teamMembers" />
            </div>
            <div class="relative flex items-center justify-center" style="height: 12px; margin: -6px 0;">
              <div 
                class="absolute inset-0 cursor-row-resize z-10"
                @mousedown="startDragV"
              ></div>
              <div class="h-1 w-full bg-gray-300 group-hover:bg-blue-500 transition-colors pointer-events-none relative z-0">
                <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2">
                  <GripVertical class="h-4 w-4 text-gray-400 group-hover:text-white rotate-90" />
                </div>
              </div>
            </div>
            <div :style="{ height: (100 - notesHeight) + '%' }" class="min-h-0 pt-3">
              <PersonalReminders :reminders="personalReminders" />
            </div>
        </div>
    </div>
</template>
