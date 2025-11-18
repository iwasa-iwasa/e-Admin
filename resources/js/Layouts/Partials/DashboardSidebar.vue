<script setup lang="ts">
import { Link, usePage, router } from '@inertiajs/vue3'
import { ref, provide, computed} from 'vue'
import { Calendar, StickyNote, BarChart3, Mail, Home, Settings, Monitor, Trash2, Users, Bell } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import { Separator } from '@/components/ui/separator'
import { Badge } from '@/components/ui/badge'
import { ScrollArea } from '@/components/ui/scroll-area'
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar'
import LogoTitle from '@/components/logoTitle.vue'

const page = usePage()

const teamMembers = computed(() => page.props.teamMembers as App.Models.User[])
const selectedMember = computed(() => page.props.filteredMemberId as number | null)
const unansweredSurveysCount = computed(() => page.props.unansweredSurveysCount as number)

const isActive = (path: string) => {
    if (path === '/dashboard') {
        return page.url === '/dashboard'
    }
    if (path === '/calendar') {
        return page.url === '/calendar'
    }
    return page.url.startsWith(path)
}

const handleMemberClick = (memberId: number, path: string) => {
  if (path.slice(0, 10) === '/dashboard') {
    const routeName = 'dashboard'

    // If the clicked member is already selected, clear the filter.
    if (selectedMember.value === memberId) {
      router.get(route(routeName), {}, {
        preserveState: true,
        replace: true,
      })
    } else {
      // Otherwise, filter by the new member.
      router.get(route(routeName), { member_id: memberId }, {
        preserveState: true,
        replace: true,
      })
    }
  }
  if (path.slice(0, 9)=== '/calendar') {
    const routeName = 'calendar'

    // If the clicked member is already selected, clear the filter.
    if (selectedMember.value === memberId) {
      router.get(route(routeName), {}, {
        preserveState: true,
        replace: true,
      })
    console.log('通ったわよー');
    } else {
      // Otherwise, filter by the new member.
      router.get(route(routeName), { member_id: memberId }, {
        preserveState: true,
        replace: true,
      })
    }
  }
}

// 💡 現在のURL全体を取得 (例: '/calendar?member_id=5')
const currentURL = computed(() => page.url )
</script>

<template>
  <aside class="w-64 bg-white border-r border-gray-200 flex flex-col overflow-y-auto h-screen">
    
    <!-- ナビゲーション -->
    
    <nav class="flex-1 p-4 space-y-2">
      <!-- ロゴ・タイトル -->
      <!-- サイドバー用ロゴコンポーネント -->
      <div class="p-6">
        <LogoTitle logo-src="/images/logo.png" />
      </div>
   
      <Separator />
      <Button
        :class="[
        'w-full justify-start gap-3',
        isActive('/dashboard') ? 'bg-primary text-primary-foreground hover:bg-primary/90' : 'hover:bg-accent hover:text-accent-foreground'
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
        'w-full justify-start gap-3',
        isActive('/calendar') ? 'bg-primary text-primary-foreground hover:bg-primary/90' : 'hover:bg-accent hover:text-accent-foreground'
      ]"
        as-child
      >
        <Link href="/calendar">
          <Calendar class="h-5 w-5" />
          共有カレンダー
        </Link>
      </Button>

      <Button
        :class="[
        'w-full justify-start gap-3',
        isActive('/notes') ? 'bg-primary text-primary-foreground hover:bg-primary/90' : 'hover:bg-accent hover:text-accent-foreground'
      ]"
        as-child
      >
        <Link href="/notes">
          <StickyNote class="h-5 w-5" />
          共有メモ
        </Link>
      </Button>

      <Button
        :class="[
        'w-full justify-start gap-3',
        isActive('/reminders') ? 'bg-primary text-primary-foreground hover:bg-primary/90' : 'hover:bg-accent hover:text-accent-foreground'
      ]"
        as-child
      >
        <Link href="/reminders">
          <Bell class="h-5 w-5" />
          個人リマインダー
        </Link>
      </Button>

      <Button
        :class="[
        'w-full justify-start gap-3 relative',
        isActive('/surveys') ? 'bg-primary text-primary-foreground hover:bg-primary/90' : 'hover:bg-accent hover:text-accent-foreground'
      ]"
        as-child
      >
        <Link href="/surveys">
          <BarChart3 class="h-5 w-5" />
          アンケート管理
          <Badge v-if="unansweredSurveysCount > 0" variant="secondary" class="ml-auto">
            {{ unansweredSurveysCount }}件
          </Badge>
        </Link>
      </Button>

      <Button
        :class="[
        'w-full justify-start gap-3',
        isActive('/trash') ? 'bg-primary text-primary-foreground hover:bg-primary/90' : 'hover:bg-accent hover:text-accent-foreground'
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

      <div class="space-y-1">
        <Button variant="ghost" class="w-full justify-start gap-3">
          <Mail class="h-5 w-5" />
          Outlook連携
          <Badge variant="outline" class="ml-auto text-xs">
            同期済
          </Badge>
        </Button>
        <a
          href="https://outlook.office.com"
          target="_blank"
          rel="noopener noreferrer"
          class="block"
        >
          <Button variant="ghost" class="w-full justify-start gap-3 pl-11 text-sm">
            Outlookを開く
          </Button>
        </a>
      </div>

      <Separator class="my-4" />

      <div class="px-3 py-2 text-xs text-gray-500">部署メンバー</div>

      <ScrollArea>
        <div class="space-y-1">
          <Button
            v-for="member in teamMembers"
            :key="member.id"
            :variant="selectedMember === member.id ? 'default' : 'ghost'"
            class="w-full justify-start gap-3"
            @click="handleMemberClick(member.id, currentURL)"
          >
            <Avatar class="h-6 w-6">
              <AvatarImage :src="member.avatar || ''" :alt="member.name" />
              <AvatarFallback>{{ member.name.charAt(0) }}</AvatarFallback>
            </Avatar>
            {{ member.name }}
            <Badge v-if="selectedMember === member.id" variant="secondary" class="ml-auto text-xs">
              フィルター中
            </Badge>
          </Button>
        </div>
      </ScrollArea>
    </nav>
  </aside>
</template>