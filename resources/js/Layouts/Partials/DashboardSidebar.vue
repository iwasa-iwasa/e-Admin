<script setup lang="ts">
  import { Link, usePage, router } from '@inertiajs/vue3'
  import { ref, computed, onMounted, onUnmounted } from 'vue'
  import {
    Calendar, StickyNote, BarChart3, Mail, Home,
    Trash2, Users, Bell, X
  } from 'lucide-vue-next'
  import { Button } from '@/components/ui/button'
  import { Separator } from '@/components/ui/separator'
  import { Badge } from '@/components/ui/badge'
  import { ScrollArea } from '@/components/ui/scroll-area'
  import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar'
  import LogoTitle from '@/components/logoTitle.vue'
  
  const props = defineProps<{ 
    isTablet?: boolean
  }>()
  const emit = defineEmits(['close'])
  
  const page = usePage()
  
  const teamMembers = computed(() => page.props.teamMembers as App.Models.User[])
  const selectedMember = computed(() => page.props.filteredMemberId as number | null)
  const unansweredSurveysCount = computed(
    () => page.props.unansweredSurveysCount as number
  )
  
  const isActive = (path: string) => {
    if (path === '/dashboard') return page.url === '/dashboard'
    if (path === '/calendar') return page.url === '/calendar'
    return page.url.startsWith(path)
  }
  
  const handleMemberClick = (memberId: number, path: string) => {
    let routeName = ''
    if (path.startsWith('/dashboard')) routeName = 'dashboard'
    else if (path.startsWith('/calendar')) routeName = 'calendar'
    else if (path.startsWith('/notes')) routeName = 'notes'
  
    if (!routeName) return
  
    router.get(
      route(routeName),
      selectedMember.value === memberId ? {} : { member_id: memberId },
      { preserveState: true, replace: true }
    )
  }
  
  const currentURL = computed(() => page.url)
  
  // リサイズ機能
  const sidebarRef = ref<HTMLElement | null>(null)
  const isResizing = ref(false)
  const sidebarWidth = ref(260)
  
  const startResize = (e: MouseEvent) => {
    isResizing.value = true
    document.addEventListener('mousemove', handleResize)
    document.addEventListener('mouseup', stopResize)
    e.preventDefault()
  }
  
  const handleResize = (e: MouseEvent) => {
    if (!isResizing.value || !sidebarRef.value) return
    const rect = sidebarRef.value.getBoundingClientRect()
    const newWidth = Math.max(220, Math.min(270, e.clientX - rect.left))
    sidebarWidth.value = newWidth
  }
  
  const stopResize = () => {
    isResizing.value = false
    document.removeEventListener('mousemove', handleResize)
    document.removeEventListener('mouseup', stopResize)
    
    // 幅を保存
    localStorage.setItem('dashboard_sidebar_width', sidebarWidth.value.toString())
    
    // リサイズ終了時にカレンダーに通知
    setTimeout(() => {
      window.dispatchEvent(new Event('resize'))
    }, 0)
  }
  
  onMounted(() => {
    const savedWidth = localStorage.getItem('dashboard_sidebar_width')
    if (savedWidth) {
      const parsed = parseInt(savedWidth, 10)
      if (!isNaN(parsed)) {
        sidebarWidth.value = Math.max(220, Math.min(270, parsed))
      }
    }
  })
  
  onUnmounted(() => {
    document.removeEventListener('mousemove', handleResize)
    document.removeEventListener('mouseup', stopResize)
  })
  </script>
  
  <template>
    <aside 
      ref="sidebarRef" 
      class="bg-gray-100 dark:bg-gray-950 text-sidebar-foreground border-r border-sidebar-border flex flex-col h-screen relative select-none"
      :style="{
        width: sidebarWidth + 'px',
        minWidth: '220px',
        maxWidth: '270px'
      }"
    >
      <ScrollArea>
        <nav class="flex-1 p-4 flex flex-col gap-2">
  
          <!-- タブレット用 -->
          <div v-if="props.isTablet" class="flex items-center justify-between mb-4">
            <div class="text-lg font-semibold">メニュー</div>
            <Button variant="ghost" size="icon" @click="emit('close')">
              <X class="h-5 w-5" />
            </Button>
          </div>
  
          <!-- ロゴ -->
          <div class="px-4 py-3">
            <LogoTitle logo-src="/images/logo.png" />
          </div>
  
          <Separator />
  
          <!-- 共通ナビボタン用クラス -->
          <!-- max-w + w-fit がキモ -->
            <Button
            :class="[
              'w-full justify-start gap-3 px-3',
              isActive('/dashboard')
                ? 'bg-gray-900 text-white shadow-sm hover:bg-black dark:bg-gray-100 dark:text-gray-900 dark:hover:bg-gray-200'
                : 'bg-white text-gray-700 border border-gray-200 hover:bg-gray-100 dark:bg-black dark:text-gray-400 dark:border-gray-800 dark:hover:bg-gray-900'
            ]"
            as-child
          >
            <Link href="/dashboard">
              <Home class="h-5 w-5" />
              ホーム
            </Link>
          </Button>
  
          <Button
            :class="[
              'w-full justify-start gap-3 px-3',
              isActive('/calendar')
                ? 'bg-gray-900 text-white shadow-sm hover:bg-black dark:bg-gray-100 dark:text-gray-900 dark:hover:bg-gray-200'
                : 'bg-white text-gray-700 border border-gray-200 hover:bg-gray-100 dark:bg-black dark:text-gray-400 dark:border-gray-800 dark:hover:bg-gray-900'
            ]"
            as-child
          >
            <Link href="/calendar">
              <Calendar class="h-5 w-5 text-blue-700 dark:text-blue-400" />
              共有カレンダー
            </Link>
          </Button>
  
          <Button
            :class="[
              'w-full justify-start gap-3 px-3',
              isActive('/notes')
                ? 'bg-gray-900 text-white shadow-sm hover:bg-black dark:bg-gray-100 dark:text-gray-900 dark:hover:bg-gray-200'
                : 'bg-white text-gray-700 border border-gray-200 hover:bg-gray-100 dark:bg-black dark:text-gray-400 dark:border-gray-800 dark:hover:bg-gray-900'
            ]"
            as-child
          >
            <Link href="/notes">
              <StickyNote class="h-5 w-5 text-orange-600 dark:text-orange-400" />
              共有メモ
            </Link>
          </Button>
  
          <Button
            :class="[
              'w-full justify-start gap-3 px-3',
              isActive('/reminders')
                ? 'bg-gray-900 text-white shadow-sm hover:bg-black dark:bg-gray-100 dark:text-gray-900 dark:hover:bg-gray-200'
                : 'bg-white text-gray-700 border border-gray-200 hover:bg-gray-100 dark:bg-black dark:text-gray-400 dark:border-gray-800 dark:hover:bg-gray-900'
            ]"
            as-child
          >
            <Link href="/reminders">
              <Bell class="h-5 w-5 text-green-700 dark:text-green-400" />
              個人リマインダー
            </Link>
          </Button>
  
          <Button
            :class="[
              'w-full justify-start gap-3 px-3 overflow-hidden',
              isActive('/surveys')
                ? 'bg-gray-900 text-white shadow-sm hover:bg-black dark:bg-gray-100 dark:text-gray-900 dark:hover:bg-gray-200'
                : 'bg-white text-gray-700 border border-gray-200 hover:bg-gray-100 dark:bg-black dark:text-gray-400 dark:border-gray-800 dark:hover:bg-gray-900'
            ]"
            as-child
          >
            <Link href="/surveys" class="flex items-center gap-3 w-full">
              <BarChart3 class="h-5 w-5 text-purple-700 dark:text-purple-400 flex-shrink-0" />
              <span class="flex-shrink-0">アンケート管理</span>
              <Badge v-if="unansweredSurveysCount > 0" variant="secondary" :class="[
              'w-full justify-start gap-3 px-3 overflow-hidden',
              isActive('/surveys')
                ? 'bg-gray-900 text-white shadow-sm hover:bg-black dark:bg-gray-100 dark:text-gray-900 dark:hover:bg-gray-200'
                : 'bg-white text-gray-700 border border-gray-200 hover:bg-gray-100 dark:bg-black dark:text-gray-400 dark:border-gray-800 dark:hover:bg-gray-900'
            ]">
                {{ unansweredSurveysCount }}件
              </Badge>
            </Link>
          </Button>
  
          <Button
            v-if="$page.props.auth.user.role === 'admin'"
            :class="[
              'w-full justify-start gap-3 px-3',
              isActive('/admin/users')
                ? 'bg-gray-900 text-white shadow-sm hover:bg-black dark:bg-gray-100 dark:text-gray-900 dark:hover:bg-gray-200'
                : 'bg-white text-gray-700 border border-gray-200 hover:bg-gray-100 dark:bg-black dark:text-gray-400 dark:border-gray-800 dark:hover:bg-gray-900'
            ]"
            as-child
          >
            <Link href="/admin/users">
              <Users class="h-5 w-5 text-indigo-700 dark:text-indigo-400" />
              ユーザー管理
            </Link>
          </Button>
  
          <Button
            :class="[
              'w-full justify-start gap-3 px-3',
              isActive('/trash')
                ? 'bg-gray-900 text-white shadow-sm hover:bg-black dark:bg-gray-100 dark:text-gray-900 dark:hover:bg-gray-200'
                : 'bg-white text-gray-700 border border-gray-200 hover:bg-gray-100 dark:bg-black dark:text-gray-400 dark:border-gray-800 dark:hover:bg-gray-900'
            ]"
            as-child
          >
            <Link href="/trash">
              <Trash2 class="h-5 w-5" />
              ゴミ箱
            </Link>
          </Button>
  
          <Separator class="my-4 dark:bg-gray-700" />
  
          <div class="px-3 py-2 text-xs text-gray-500 dark:text-gray-400">連携機能</div>
  
          <a href="https://outlook.office.com" target="_blank" class="block">
            <Button variant="ghost" class="w-full justify-start gap-3 px-3">
              <Mail class="h-5 w-5" />
              Outlookを開く
            </Button>
          </a>
  
          <Separator class="my-4 dark:bg-gray-700" />
  
          <div class="px-3 py-2 text-xs text-gray-500 dark:text-gray-400">部署メンバー</div>
  
          <ScrollArea>
            <div class="flex flex-col gap-1">
              <template v-for="member in teamMembers" :key="member.id">
                <div 
                  v-if="selectedMember === member.id" 
                  class="px-2 py-2 my-1 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded cursor-pointer hover:bg-blue-100 dark:hover:bg-blue-900/40"
                  @click="handleMemberClick(member.id, currentURL)"
                >
                  <div class="text-xs text-blue-700 dark:text-blue-300 font-medium">{{ member.name }} フィルター中</div>
                </div>
                <Button
                  v-else
                  variant="ghost"
                  class="w-full justify-start gap-3 px-3"
                  @click="handleMemberClick(member.id, currentURL)"
                >
                  <Avatar class="h-6 w-6">
                    <AvatarImage :src="member.avatar || ''" />
                    <AvatarFallback>{{ member.name.charAt(0) }}</AvatarFallback>
                  </Avatar>
                  {{ member.name }}
                </Button>
              </template>
            </div>
          </ScrollArea>
  
        </nav>
      </ScrollArea>
      
      <!-- リサイズバー -->
      <div 
        class="absolute top-0 right-0 w-1 h-full cursor-col-resize bg-gray-200 hover:bg-gray-300 dark:bg-gray-800 dark:hover:bg-gray-700 transition-colors"
        @mousedown="startResize"
      ></div>
    </aside>
  </template>
  