<script setup lang="ts">
import DashboardSidebar from '@/Layouts/Partials/DashboardSidebar.vue'
import DashboardHeader from '@/Layouts/Partials/DashboardHeader.vue'
import { Toaster } from '@/components/ui/toast'
import { ref, onMounted, computed } from 'vue'

const isSidebarOpen = ref(false)
const isTablet = ref(false)
const sidebarWidth = ref(256)
const isResizing = ref(false)

const checkDevice = () => {
  const width = window.innerWidth
  const height = window.innerHeight
  isTablet.value = (
    (width >= 768 && width <= 1400 && height >= 768 && height <= 1400)
  )
}

const startResize = () => {
  isResizing.value = true
  document.body.style.cursor = 'col-resize'
  document.body.style.userSelect = 'none'
}

const stopResize = () => {
  isResizing.value = false
  document.body.style.cursor = ''
  document.body.style.userSelect = ''
  localStorage.setItem('sidebarWidth', sidebarWidth.value.toString())
}

const resize = (e: MouseEvent) => {
  if (!isResizing.value) return
  const newWidth = e.clientX
  if (newWidth >= 200 && newWidth <= 500) {
    sidebarWidth.value = newWidth
  }
}

onMounted(() => {
  checkDevice()
  window.addEventListener('resize', checkDevice)
  window.addEventListener('mousemove', resize)
  window.addEventListener('mouseup', stopResize)
  
  const savedWidth = localStorage.getItem('sidebarWidth')
  if (savedWidth) {
    sidebarWidth.value = parseInt(savedWidth)
  }
  
  // タブレット以外では常にサイドバーを表示
  isSidebarOpen.value = true
})
</script>

<template>
  <div class="flex h-screen bg-gray-50">
    <!-- Overlay (タブレットのみ) -->
    <div 
      v-if="isSidebarOpen && isTablet" 
      class="fixed inset-0 bg-black/50 z-40"
      @click="isSidebarOpen = false"
    ></div>
    
    <!-- Sidebar -->
    <template v-if="isTablet">
      <Transition
        enter-active-class="transition-transform duration-300"
        leave-active-class="transition-transform duration-300"
        enter-from-class="-translate-x-full"
        enter-to-class="translate-x-0"
        leave-from-class="translate-x-0"
        leave-to-class="-translate-x-full"
      >
        <div v-if="isSidebarOpen" class="fixed inset-y-0 left-0 z-50">
          <DashboardSidebar :is-tablet="true" @close="isSidebarOpen = false" />
        </div>
      </Transition>
    </template>
    <template v-else>
      <div class="relative" :style="{ width: sidebarWidth + 'px' }">
        <DashboardSidebar :is-tablet="false" />
        <div class="absolute top-0 right-0 h-full flex items-center" style="width: 12px; margin-right: -6px;">
          <div 
            class="absolute inset-0 cursor-col-resize z-10"
            @mousedown="startResize"
          ></div>
          <div class="w-1 h-full bg-gray-300 hover:bg-blue-500 transition-colors pointer-events-none relative">
            <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-1 h-12 bg-gray-300 group-hover:bg-blue-500"></div>
          </div>
        </div>
      </div>
    </template>
    
    <div class="flex-1 flex flex-col overflow-hidden">
      <DashboardHeader 
        @toggle-sidebar="isSidebarOpen = !isSidebarOpen" 
        :is-sidebar-open="isSidebarOpen"
        :is-tablet="isTablet"
      />
      <main class="flex-1 overflow-hidden">
        <slot />
      </main>
    </div>
    <Toaster />
  </div>
</template>
