<script setup lang="ts">
  import { Link, usePage, router } from '@inertiajs/vue3'
  import { ref, computed, onMounted, onUnmounted } from 'vue'
  import {
    Calendar, StickyNote, BarChart3, Mail, Home,
    Trash2, Users, Bell, X, ChevronDown, ChevronRight, Building2
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
  const departments = computed(() => page.props.departments as App.Models.Department[])
  const selectedMember = computed(() => page.props.filteredMemberId as number | null)
  const unansweredSurveysCount = computed(
    () => page.props.unansweredSurveysCount as number
  )
  
  // 現在のページを取得
  const currentURL = computed(() => page.url)
  const isCalendarPage = computed(() => {
    const path = currentURL.value.split('?')[0] // パラメータを除いたパス
    return path.startsWith('/calendar')
  })
  const isDashboardPage = computed(() => {
    const path = currentURL.value.split('?')[0] // パラメータを除いたパス
    return path === '/dashboard'
  })
  const isCalendarOrDashboard = computed(() => isCalendarPage.value || isDashboardPage.value)
  const userRoleType = computed(() => page.props.auth?.user?.role_type as string)
  
  // 部署の展開状態を管理
  const expandedDepartments = ref<Set<number>>(new Set())
  
  // ユーザーの所属部署を取得
  const userDepartmentId = computed(() => page.props.auth?.user?.department_id as number | null)
  
  // 初期化時に所属部署を展開（全社管理者の場合は全部署を展開）
  let expandDepartmentHandler: ((event: CustomEvent) => void) | null = null
  
  onMounted(() => {
    console.log('DashboardSidebar mounted')
    console.log('currentURL:', currentURL.value)
    console.log('isCalendarPage:', isCalendarPage.value)
    console.log('isDashboardPage:', isDashboardPage.value)
    console.log('isCalendarOrDashboard:', isCalendarOrDashboard.value)
    console.log('departments:', departments.value)
    console.log('departments raw:', JSON.stringify(departments.value, null, 2))
    console.log('userRoleType:', userRoleType.value)
    console.log('userDepartmentId:', userDepartmentId.value)
    
    // 各部署のusersプロパティをチェック
    departments.value.forEach(dept => {
      console.log(`Department ${dept.name}:`, {
        id: dept.id,
        users: dept.users,
        usersType: typeof dept.users,
        usersLength: dept.users?.length
      })
    })
    
    // 現在のURLパラメータをチェックして適切な初期展開を設定
    const url = new URL(window.location.href)
    const currentDeptId = url.searchParams.get('department_id')
    const showCompany = url.searchParams.get('show_company')
    
    // ページに応じて初期展開を設定
    if (isCalendarOrDashboard.value) {
      // ホーム・カレンダーページでは現在の表示状態に応じて展開
      if (currentDeptId) {
        // 特定部署を表示中の場合、その部署を展開
        expandedDepartments.value.add(parseInt(currentDeptId))
      } else if (showCompany) {
        // 全社表示の場合、権限に応じて展開
        if (userRoleType.value === 'company_admin') {
          // 全社管理者は全部署を展開
          departments.value.forEach(dept => {
            expandedDepartments.value.add(dept.id)
          })
        }
      } else {
        // パラメータがない場合はデフォルト設定
        if (userRoleType.value === 'company_admin') {
          // 全社管理者は全部署を展開
          departments.value.forEach(dept => {
            expandedDepartments.value.add(dept.id)
          })
        } else if (userDepartmentId.value) {
          // 一般ユーザーは所属部署のみ展開
          expandedDepartments.value.add(userDepartmentId.value)
        }
      }
    } else {
      // その他のページでは自部署のみ展開
      if (userDepartmentId.value) {
        expandedDepartments.value.add(userDepartmentId.value)
      }
    }
    
    console.log('expandedDepartments:', expandedDepartments.value)
    
    // 部署展開イベントリスナーを追加
    expandDepartmentHandler = (event: CustomEvent) => {
      const { departmentId } = event.detail
      if (departmentId) {
        expandedDepartments.value.add(departmentId)
      }
    }
    window.addEventListener('expandDepartment', expandDepartmentHandler as EventListener)
    
    // リサイズ機能の初期化
    const savedWidth = localStorage.getItem('dashboard_sidebar_width')
    if (savedWidth) {
      const parsed = parseInt(savedWidth, 10)
      if (!isNaN(parsed)) {
        sidebarWidth.value = Math.max(220, Math.min(270, parsed))
      }
    }
  })
  
  const toggleDepartment = (departmentId: number) => {
    if (expandedDepartments.value.has(departmentId)) {
      expandedDepartments.value.delete(departmentId)
    } else {
      expandedDepartments.value.add(departmentId)
    }
  }
  
  const isActive = (path: string) => {
    if (path === '/dashboard') return page.url === '/dashboard'
    if (path === '/calendar') return page.url === '/calendar'
    return page.url.startsWith(path)
  }
  
  // 現在選択されている部署を追跡
  const selectedDepartmentId = computed(() => {
    const url = new URL(window.location.href)
    const deptId = url.searchParams.get('department_id')
    const showCompany = url.searchParams.get('show_company')
    
    if (showCompany) return null // 全社表示
    if (deptId) return parseInt(deptId)
    return null
  })
  
  const handleMemberClick = (memberId: number, path: string) => {
    let routeName = ''
    if (path.startsWith('/dashboard')) routeName = 'dashboard'
    else if (path.startsWith('/calendar')) routeName = 'calendar'
    else if (path.startsWith('/notes')) routeName = 'notes'
  
    if (!routeName) return
    
    // 現在のパラメータを取得
    const currentParams: Record<string, any> = {}
    
    // ホーム・カレンダーページの場合のみ処理
    if (isCalendarOrDashboard.value) {
      if (selectedMember.value === memberId) {
        // 既に選択されている場合はフィルターを解除
        // 現在の表示状態を維持（部署表示または全社表示）
        const url = new URL(window.location.href)
        const currentDeptId = url.searchParams.get('department_id')
        const showCompany = url.searchParams.get('show_company')
        
        if (showCompany) {
          // 全社表示を維持
          currentParams.show_company = true
        } else if (currentDeptId) {
          // 部署表示を維持
          currentParams.department_id = parseInt(currentDeptId)
        } else {
          // フォールバック: ユーザーの所属部署
          if (userDepartmentId.value) {
            currentParams.department_id = userDepartmentId.value
          } else {
            currentParams.show_company = true
          }
        }
        // member_idパラメータは追加しない（フィルター解除）
        // サイドバーの展開状態は変更しない
      } else {
        // 新しいメンバーを選択
        currentParams.member_id = memberId
        
        const selectedMemberData = teamMembers.value.find(m => m.id === memberId)
        const url = new URL(window.location.href)
        const currentDeptId = url.searchParams.get('department_id')
        const showCompany = url.searchParams.get('show_company')
        
        if (selectedMemberData) {
          if (selectedMemberData.department_id) {
            // メンバーの所属部署のカレンダーに切り替え
            currentParams.department_id = selectedMemberData.department_id
            
            // 現在表示中の部署と異なる場合のみサイドバーの展開状態を更新
            const memberDeptId = selectedMemberData.department_id
            if (currentDeptId !== memberDeptId.toString() && !showCompany) {
              // 異なる部署の場合、該当部署を展開
              expandedDepartments.value.clear()
              expandedDepartments.value.add(memberDeptId)
              
              // 全社管理者の場合は全社タブも維持
              if (userRoleType.value === 'company_admin') {
                const hasCompanyMembers = teamMembers.value.some(m => !m.department_id)
                if (hasCompanyMembers) {
                  // 全社メンバーがいる場合は全社タブの表示も維持
                }
              }
            }
          } else {
            // 部署に所属していない場合（全社管理者など）は全社表示
            currentParams.show_company = true
            
            // 全社表示でない場合のみサイドバーの展開状態を更新
            if (!showCompany) {
              expandedDepartments.value.clear()
              if (userRoleType.value === 'company_admin') {
                departments.value.forEach(dept => {
                  expandedDepartments.value.add(dept.id)
                })
              }
            }
          }
        } else {
          // メンバーデータが見つからない場合は現在の表示状態を維持
          if (showCompany) {
            currentParams.show_company = true
          } else if (currentDeptId) {
            currentParams.department_id = parseInt(currentDeptId)
          } else {
            // フォールバック
            if (userDepartmentId.value) {
              currentParams.department_id = userDepartmentId.value
            } else {
              currentParams.show_company = true
            }
          }
        }
      }
    }
    
    console.log('Member click params:', currentParams)
  
    router.get(
      route(routeName),
      currentParams,
      { preserveState: true, replace: true }
    )
  }
  
  const handleDepartmentClick = (departmentId: number | null) => {
    // ホーム・カレンダーページで部署カレンダーへの切り替え機能を実装
    if (isCalendarOrDashboard.value) {
      // 部署切り替え時は展開状態を調整（全社タブは維持）
      if (departmentId) {
        // 特定部署を選択した場合、その部署のみ展開
        const currentExpanded = new Set(expandedDepartments.value)
        expandedDepartments.value.clear()
        expandedDepartments.value.add(departmentId)
        
        // 全社管理者の場合は全社タブも維持
        if (userRoleType.value === 'company_admin') {
          // 全社管理者以外のメンバーがいる場合は全社タブを維持
          const hasCompanyMembers = teamMembers.value.some(m => !m.department_id)
          if (hasCompanyMembers) {
            // 全社タブの展開状態は変更しない（全社メンバーの表示を維持）
          }
        }
      } else {
        // 全社を選択した場合
        if (userRoleType.value === 'company_admin') {
          // 全社管理者は全部署を展開
          expandedDepartments.value.clear()
          departments.value.forEach(dept => {
            expandedDepartments.value.add(dept.id)
          })
        }
      }
      
      // 現在のページに応じて遷移
      const targetRoute = isDashboardPage.value ? 'dashboard' : 'calendar'
      const params = departmentId ? { department_id: departmentId } : { show_company: true }
      
      console.log('Navigating to:', targetRoute, 'with params:', params)
      
      router.get(
        route(targetRoute),
        params,
        { preserveState: true, replace: true }
      )
    }
  }
  
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
    
    // リサイズ終了時にカレンダーに通知（遅延して実行）
    setTimeout(() => {
      if (!isResizing.value) {
        window.dispatchEvent(new Event('resize'))
      }
    }, 100)
  }

  
  onUnmounted(() => {
    document.removeEventListener('mousemove', handleResize)
    document.removeEventListener('mouseup', stopResize)
    if (expandDepartmentHandler) {
      window.removeEventListener('expandDepartment', expandDepartmentHandler as EventListener)
    }
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
              <!-- ホーム・カレンダーページの場合は部署選択機能を表示 -->
              <template v-if="isCalendarOrDashboard">
                <!-- 全社 -->
                <div class="mb-2">
                  <Button
                    variant="ghost"
                    class="w-full justify-start gap-2 px-3 text-sm font-medium"
                    @click="handleDepartmentClick(null)"
                  >
                    <Building2 class="h-4 w-4 text-blue-600 dark:text-blue-400" />
                    全社
                  </Button>
                  
                  <!-- 全社管理者を全社タブの下に表示 -->
                  <div class="ml-6 mt-1 flex flex-col gap-1">
                    <template v-for="member in teamMembers.filter(m => !m.department_id)" :key="member.id">
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
                        size="sm"
                        class="w-full justify-start gap-3 px-3 text-sm"
                        @click="handleMemberClick(member.id, currentURL)"
                      >
                        <Avatar class="h-5 w-5">
                          <AvatarImage :src="member.avatar || ''" />
                          <AvatarFallback class="text-xs">{{ member.name.charAt(0) }}</AvatarFallback>
                        </Avatar>
                        {{ member.name }}
                      </Button>
                    </template>
                  </div>
                </div>
                
                <!-- 部署一覧 -->
                <template v-for="department in departments" :key="department.id">
                  <div class="mb-1">
                    <!-- 部署ヘッダー -->
                    <div class="flex items-center">
                      <Button
                        variant="ghost"
                        size="sm"
                        class="flex-1 justify-start gap-2 px-3 text-sm font-medium"
                        @click="handleDepartmentClick(department.id)"
                      >
                        <Building2 class="h-4 w-4 text-green-600 dark:text-green-400" />
                        {{ department.name }}
                      </Button>
                      <Button
                        variant="ghost"
                        size="sm"
                        class="px-2"
                        @click="toggleDepartment(department.id)"
                      >
                        <ChevronDown 
                          v-if="expandedDepartments.has(department.id)" 
                          class="h-4 w-4" 
                        />
                        <ChevronRight 
                          v-else 
                          class="h-4 w-4" 
                        />
                      </Button>
                    </div>
                    
                    <!-- 部署メンバー（部署に所属するメンバーのみ） -->
                    <div 
                      v-if="expandedDepartments.has(department.id)" 
                      class="ml-6 mt-1 flex flex-col gap-1"
                    >
                      <template v-for="member in department.users.filter(m => m.department_id === department.id)" :key="member.id">
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
                          size="sm"
                          class="w-full justify-start gap-3 px-3 text-sm"
                          @click="handleMemberClick(member.id, currentURL)"
                        >
                          <Avatar class="h-5 w-5">
                            <AvatarImage :src="member.avatar || ''" />
                            <AvatarFallback class="text-xs">{{ member.name.charAt(0) }}</AvatarFallback>
                          </Avatar>
                          {{ member.name }}
                        </Button>
                      </template>
                    </div>
                  </div>
                </template>
              </template>
              
              <!-- ホーム・カレンダー以外のページの場合 -->
              <template v-else>
                <template v-for="department in departments" :key="department.id">
                  <div class="mb-1">
                    <!-- 部署ヘッダー -->
                    <div class="flex items-center">
                      <div class="flex-1 justify-start gap-2 px-3 text-sm font-medium flex items-center">
                        <Building2 class="h-4 w-4 text-green-600 dark:text-green-400" />
                        {{ department.name }}
                      </div>
                      <Button
                        variant="ghost"
                        size="sm"
                        class="px-2"
                        @click="toggleDepartment(department.id)"
                      >
                        <ChevronDown 
                          v-if="expandedDepartments.has(department.id)" 
                          class="h-4 w-4" 
                        />
                        <ChevronRight 
                          v-else 
                          class="h-4 w-4" 
                        />
                      </Button>
                    </div>
                    
                    <!-- 部署メンバー -->
                    <div 
                      v-if="expandedDepartments.has(department.id)" 
                      class="ml-6 mt-1 flex flex-col gap-1"
                    >
                      <template v-for="member in department.users" :key="member.id">
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
                          size="sm"
                          class="w-full justify-start gap-3 px-3 text-sm"
                          @click="handleMemberClick(member.id, currentURL)"
                        >
                          <Avatar class="h-5 w-5">
                            <AvatarImage :src="member.avatar || ''" />
                            <AvatarFallback class="text-xs">{{ member.name.charAt(0) }}</AvatarFallback>
                          </Avatar>
                          {{ member.name }}
                        </Button>
                      </template>
                    </div>
                  </div>
                </template>
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
  