<script setup lang="ts">
  import { Link, usePage, router } from '@inertiajs/vue3'
  import { ref, computed } from 'vue'
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
  
  const props = defineProps<{ isTablet?: boolean }>()
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
  </script>
  
  <template>
    <aside class="bg-white border-r border-gray-300 flex flex-col h-screen w-[260px]">
      <ScrollArea>
        <nav class="flex-1 p-4 space-y-2">
  
          <!-- タブレット用 -->
          <div v-if="props.isTablet" class="flex items-center justify-between mb-4">
            <div class="text-lg font-semibold">メニュー</div>
            <Button variant="ghost" size="icon" @click="emit('close')">
              <X class="h-5 w-5" />
            </Button>
          </div>
  
          <!-- ロゴ -->
          <div class="px-4 py-6">
            <LogoTitle logo-src="/images/logo.png" />
          </div>
  
          <Separator />
  
          <!-- 共通ナビボタン用クラス -->
          <!-- max-w + w-fit がキモ -->
          <Button
            :class="[
              'max-w-[240px] w-fit justify-start gap-3 px-3',
              isActive('/dashboard')
                ? 'bg-primary text-primary-foreground hover:bg-primary/90'
                : 'hover:bg-accent hover:text-accent-foreground'
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
              'max-w-[240px] w-fit justify-start gap-3 px-3',
              isActive('/calendar')
                ? 'bg-primary text-primary-foreground hover:bg-primary/90'
                : 'hover:bg-accent hover:text-accent-foreground'
            ]"
            as-child
          >
            <Link href="/calendar">
              <Calendar class="h-5 w-5 text-blue-700" />
              共有カレンダー
            </Link>
          </Button>
  
          <Button
            :class="[
              'max-w-[240px] w-fit justify-start gap-3 px-3',
              isActive('/notes')
                ? 'bg-primary text-primary-foreground hover:bg-primary/90'
                : 'hover:bg-accent hover:text-accent-foreground'
            ]"
            as-child
          >
            <Link href="/notes">
              <StickyNote class="h-5 w-5 text-orange-600" />
              共有メモ
            </Link>
          </Button>
  
          <Button
            :class="[
              'max-w-[240px] w-fit justify-start gap-3 px-3',
              isActive('/reminders')
                ? 'bg-primary text-primary-foreground hover:bg-primary/90'
                : 'hover:bg-accent hover:text-accent-foreground'
            ]"
            as-child
          >
            <Link href="/reminders">
              <Bell class="h-5 w-5 text-green-700" />
              個人リマインダー
            </Link>
          </Button>
  
          <Button
            :class="[
              'max-w-[240px] w-fit justify-start gap-3 px-3',
              isActive('/surveys')
                ? 'bg-primary text-primary-foreground hover:bg-primary/90'
                : 'hover:bg-accent hover:text-accent-foreground'
            ]"
            as-child
          >
            <Link href="/surveys">
              <BarChart3 class="h-5 w-5 text-purple-700" />
              アンケート管理
              <Badge v-if="unansweredSurveysCount > 0" variant="secondary" class="ml-2">
                {{ unansweredSurveysCount }}件
              </Badge>
            </Link>
          </Button>
  
          <Button
            v-if="$page.props.auth.user.role === 'admin'"
            :class="[
              'max-w-[240px] w-fit justify-start gap-3 px-3',
              isActive('/admin/users')
                ? 'bg-primary text-primary-foreground hover:bg-primary/90'
                : 'hover:bg-accent hover:text-accent-foreground'
            ]"
            as-child
          >
            <Link href="/admin/users">
              <Users class="h-5 w-5 text-indigo-700" />
              ユーザー管理
            </Link>
          </Button>
  
          <Button
            :class="[
              'max-w-[240px] w-fit justify-start gap-3 px-3',
              isActive('/trash')
                ? 'bg-primary text-primary-foreground hover:bg-primary/90'
                : 'hover:bg-accent hover:text-accent-foreground'
            ]"
            as-child
          >
            <Link href="/trash">
              <Trash2 class="h-5 w-5" />
              ゴミ箱
            </Link>
          </Button>
  
          <Separator class="my-4" />
  
          <div class="px-3 py-2 text-xs text-gray-500">連携機能</div>
  
          <a href="https://outlook.office.com" target="_blank" class="block">
            <Button variant="ghost" class="max-w-[240px] w-fit justify-start gap-3 px-3">
              <Mail class="h-5 w-5" />
              Outlookを開く
            </Button>
          </a>
  
          <Separator class="my-4" />
  
          <div class="px-3 py-2 text-xs text-gray-500">部署メンバー</div>
  
          <ScrollArea>
            <div class="space-y-1">
              <Button
                v-for="member in teamMembers"
                :key="member.id"
                :variant="selectedMember === member.id ? 'default' : 'ghost'"
                class="max-w-[240px] w-fit justify-start gap-3 px-3"
                @click="handleMemberClick(member.id, currentURL)"
              >
                <Avatar class="h-6 w-6">
                  <AvatarImage :src="member.avatar || ''" />
                  <AvatarFallback>{{ member.name.charAt(0) }}</AvatarFallback>
                </Avatar>
                {{ member.name }}
              </Button>
            </div>
          </ScrollArea>
  
        </nav>
      </ScrollArea>
    </aside>
  </template>
  