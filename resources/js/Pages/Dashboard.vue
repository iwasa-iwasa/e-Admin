<script setup lang="ts">
import { Head, usePage, router } from '@inertiajs/vue3'
import { computed, ref, onMounted, onUnmounted } from 'vue'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import SharedCalendar from '@/components/SharedCalendar.vue'
import SharedNotes from '@/components/SharedNotes.vue'
import PersonalReminders from '@/components/PersonalReminders.vue'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
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

// iPad横画面判定とカード高さ計算
const isIPadLayout = computed(() => {
  const width = window.innerWidth
  return (width >= 1180 && width <= 1366)
})

const isIPadPro = computed(() => window.innerWidth >= 1300)
const cardHeight = computed(() => isIPadPro.value ? 780 : 600)

// PC用リサイズ機能
const calendarWidth = ref(parseFloat(localStorage.getItem('dashboard_calendar_width') || '58'))
const notesHeight = ref(parseFloat(localStorage.getItem('dashboard_notes_height') || '50'))
const isDraggingH = ref(false)
const isDraggingV = ref(false)
const dashboardRef = ref<HTMLElement | null>(null)
const rightPanelRef = ref<HTMLElement | null>(null)

const startDragH = (e: MouseEvent) => {
  if (isIPadLayout.value || !dashboardRef.value) return
  e.preventDefault()
  isDraggingH.value = true
  document.body.style.cursor = 'col-resize'
  document.body.style.userSelect = 'none'
}

const startDragV = (e: MouseEvent) => {
  if (isIPadLayout.value || !rightPanelRef.value) return
  e.preventDefault()
  isDraggingV.value = true
  document.body.style.cursor = 'row-resize'
  document.body.style.userSelect = 'none'
}

const onDrag = (e: MouseEvent) => {
  if (isDraggingH.value && dashboardRef.value) {
    e.preventDefault()
    const rect = dashboardRef.value.getBoundingClientRect()
    const newWidth = ((e.clientX - rect.left) / rect.width) * 100
    const clampedWidth = Math.max(35, Math.min(65, newWidth))
    calendarWidth.value = clampedWidth
    localStorage.setItem('dashboard_calendar_width', clampedWidth.toString())
  }
  if (isDraggingV.value && rightPanelRef.value) {
    e.preventDefault()
    const rect = rightPanelRef.value.getBoundingClientRect()
    const newHeight = ((e.clientY - rect.top) / rect.height) * 100
    const clampedHeight = Math.max(30, Math.min(70, newHeight))
    notesHeight.value = clampedHeight
    localStorage.setItem('dashboard_notes_height', clampedHeight.toString())
  }
}

const stopDrag = () => {
  if (isDraggingH.value || isDraggingV.value) {
    isDraggingH.value = false
    isDraggingV.value = false
    document.body.style.cursor = ''
    document.body.style.userSelect = ''
    setTimeout(() => window.dispatchEvent(new Event('resize')), 0)
  }
}

// iPadレイアウト変更時のカレンダー調整
const handleResize = () => {
  if (isIPadLayout.value) {
    setTimeout(() => window.dispatchEvent(new Event('resize')), 0)
  }
}

onMounted(() => {
  window.addEventListener('mousemove', onDrag)
  window.addEventListener('mouseup', stopDrag)
  window.addEventListener('resize', handleResize)
  
  // iPad用初期調整
  if (isIPadLayout.value) {
    setTimeout(() => window.dispatchEvent(new Event('resize')), 100)
  }
})

onUnmounted(() => {
  window.removeEventListener('mousemove', onDrag)
  window.removeEventListener('mouseup', stopDrag)
  window.removeEventListener('resize', handleResize)
})


</script>

<template>
    <Head title="ホーム" />
    
    <div class="max-w-[1800px] mx-auto h-full p-6">
        <!-- iPad横画面：縦並びレイアウト -->
        <Card v-if="isIPadLayout" class="h-full overflow-hidden flex flex-col">
            <div class="flex-1 overflow-y-auto p-6 space-y-6">
                <Card class="hover:shadow-md transition-shadow">
                    <CardContent class="p-0">
                        <div :style="{ height: cardHeight + 'px' }" class="overflow-hidden">
                            <SharedCalendar :events="events" />
                        </div>
                    </CardContent>
                </Card>
                
                <Card class="hover:shadow-md transition-shadow">
                    <CardContent class="p-0">
                        <div :style="{ height: cardHeight + 'px' }" class="overflow-hidden">
                            <SharedNotes :notes="sharedNotes" :totalUsers="totalUsers" :teamMembers="teamMembers" />
                        </div>
                    </CardContent>
                </Card>
                
                <Card class="hover:shadow-md transition-shadow">
                    <CardContent class="p-0">
                        <div :style="{ height: cardHeight + 'px' }" class="overflow-hidden">
                            <PersonalReminders :reminders="personalReminders" />
                        </div>
                    </CardContent>
                </Card>
            </div>
        </Card>
        
        <!-- PC：横並びレイアウト -->
        <div v-else ref="dashboardRef" class="flex h-full">
            <div :style="{ width: calendarWidth + '%' }" class="h-full pr-3">
                <SharedCalendar :events="events" />
            </div>
            
            <!-- 横リサイズバー -->
            <div class="relative flex items-center justify-center" style="width: 16px; margin: 0 -8px;">
                <div 
                    class="absolute inset-0 cursor-col-resize z-10 hover:bg-blue-100 hover:bg-opacity-50 rounded transition-colors"
                    @mousedown="startDragH"
                ></div>
                <div class="w-1 h-full bg-gray-300 hover:bg-blue-500 transition-colors pointer-events-none relative z-0">
                    <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2">
                        <GripVertical class="h-4 w-4 text-gray-400" />
                    </div>
                </div>
            </div>
            
            <div ref="rightPanelRef" :style="{ width: (100 - calendarWidth) + '%' }" class="h-full flex flex-col pl-3">
                <div :style="{ height: notesHeight + '%' }" class="pb-3">
                    <SharedNotes :notes="sharedNotes" :totalUsers="totalUsers" :teamMembers="teamMembers" />
                </div>
                
                <!-- 縦リサイズバー -->
                <div class="relative flex items-center justify-center" style="height: 16px; margin: -8px 0;">
                    <div 
                        class="absolute inset-0 cursor-row-resize z-10 hover:bg-blue-100 hover:bg-opacity-50 rounded transition-colors"
                        @mousedown="startDragV"
                    ></div>
                    <div class="h-1 w-full bg-gray-300 hover:bg-blue-500 transition-colors pointer-events-none relative z-0">
                        <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2">
                            <GripVertical class="h-4 w-4 text-gray-400 rotate-90" />
                        </div>
                    </div>
                </div>
                
                <div :style="{ height: (100 - notesHeight) + '%' }" class="pt-3">
                    <PersonalReminders :reminders="personalReminders" />
                </div>
            </div>
        </div>
    </div>
</template>
