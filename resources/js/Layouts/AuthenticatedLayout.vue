<script setup lang="ts">
  import DashboardSidebar from '@/Layouts/Partials/DashboardSidebar.vue'
  import DashboardHeader from '@/Layouts/Partials/DashboardHeader.vue'
  import { Toaster } from '@/components/ui/toast'
  import { ref, onMounted, onUnmounted } from 'vue'
  
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
  
  onMounted(() => {
    // viewport固定のためにhtml/bodyのoverflowを制御
    document.documentElement.style.overflow = 'hidden'
    document.documentElement.style.height = '100%'
    document.body.style.overflow = 'hidden'
    document.body.style.height = '100%'
    
    checkDevice()
    window.addEventListener('resize', checkDevice)
    
    const savedWidth = localStorage.getItem('sidebarWidth')
    if (savedWidth) {
      sidebarWidth.value = parseInt(savedWidth)
    }
    
    // タブレット以外では常にサイドバーを表示
    isSidebarOpen.value = true
  })
  
  onUnmounted(() => {
    // クリーンアップ: 他のページに影響しないようリセット
    document.documentElement.style.overflow = ''
    document.documentElement.style.height = ''
    document.body.style.overflow = ''
    document.body.style.height = ''
    
    window.removeEventListener('resize', checkDevice)
  })
  </script>
  
  <template>
    <div class="flex h-screen overflow-hidden bg-gray-50 dark:bg-background dark:text-foreground">
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
        <DashboardSidebar :is-tablet="false" />
      </template>
      
      <div class="flex-1 flex flex-col overflow-hidden">
        <DashboardHeader
          @toggle-sidebar="isSidebarOpen = !isSidebarOpen"
          :is-sidebar-open="isSidebarOpen"
          :is-tablet="isTablet"
        />
        <main class="flex-1 min-h-0 overflow-hidden">
          <slot />
        </main>
      </div>
      
      <Toaster />
    </div>
  </template>