<script setup lang="ts">
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import { ChevronLeft, ChevronRight, Calendar, StickyNote, ArrowLeft } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'

type ViewMode = 'schedule' | 'notes'

interface ScheduleItem {
  id: string
  time: string
  title: string
  assignedTo: string
  department: string
}

interface Note {
  id: string
  title: string
  content: string
  author: string
  date: string
}

const mockSchedule: ScheduleItem[] = [
  { id: '1', time: '09:00', title: '月次報告書作成', assignedTo: '田中', department: '総務部' },
  { id: '2', time: '10:30', title: '部署ミーティング', assignedTo: '全員', department: '総務部' },
  { id: '3', time: '13:00', title: '備品発注確認', assignedTo: '佐藤', department: '総務部' },
  { id: '4', time: '15:00', title: '新入社員研修準備', assignedTo: '鈴木', department: '総務部' },
  { id: '5', time: '16:30', title: '施設点検立会い', assignedTo: '山田', department: '総務部' },
]

const mockNotes: Note[] = [
  { id: '1', title: '重要：来週の全社イベント準備', content: '11月15日の全社イベントに向けて、会場設営と受付準備を進めてください。', author: '田中', date: '2025/10/14' },
  { id: '2', title: '備品在庫チェック完了', content: '10月分の備品在庫チェックが完了しました。不足品リストを共有フォルダに保存済み。', author: '佐藤', date: '2025/10/13' },
  { id: '3', title: '勤怠システム更新のお知らせ', content: '来月から新しい勤怠システムに移行します。詳細は後日共有します。', author: '鈴木', date: '2025/10/12' },
]

const viewMode = ref<ViewMode>('schedule')
const currentDate = ref(new Date())

const formatDate = (date: Date) => {
  const year = date.getFullYear()
  const month = date.getMonth() + 1
  const day = date.getDate()
  const weekdays = ['日', '月', '火', '水', '木', '金', '土']
  const weekday = weekdays[date.getDay()]
  return { year, month, day, weekday }
}

const formattedDate = computed(() => formatDate(currentDate.value))

</script>

<template>
  <div class="h-screen bg-white flex flex-col">
    <header class="bg-primary text-primary-foreground px-12 py-8 shadow-lg">
      <div class="flex items-center justify-between max-w-7xl mx-auto">
        <div class="flex items-center gap-6">
          <Button variant="ghost" size="icon" @click="router.get('/')" class="text-primary-foreground hover:bg-primary-foreground/10 w-16 h-16">
            <ArrowLeft class="w-10 h-10" />
          </Button>
          <Calendar class="w-16 h-16" />
          <div>
            <div class="text-6xl tracking-tight">
              {{ formattedDate.month }}月{{ formattedDate.day }}日 ({{ formattedDate.weekday }})
            </div>
            <div class="text-3xl opacity-90 mt-2">{{ formattedDate.year }}年</div>
          </div>
        </div>
        <div class="text-4xl opacity-90">総務部 共有カレンダー</div>
      </div>
    </header>

    <main class="flex-1 overflow-auto px-12 py-10">
      <div class="max-w-7xl mx-auto">
        <div v-if="viewMode === 'schedule'" class="space-y-6">
          <h2 class="text-5xl text-gray-900 mb-8 pb-4 border-b-4 border-gray-300">本日の予定</h2>
          <div v-if="mockSchedule.length === 0" class="text-center py-20">
            <p class="text-4xl text-gray-500">本日の予定はありません</p>
          </div>
          <div v-else class="space-y-4">
            <div v-for="item in mockSchedule" :key="item.id" class="bg-gray-50 border-l-8 border-primary px-10 py-8 rounded-lg shadow-md hover:shadow-lg transition-shadow">
              <div class="flex items-center gap-8">
                <div class="text-5xl text-primary min-w-[180px]">{{ item.time }}</div>
                <div class="flex-1">
                  <div class="text-4xl text-gray-900 mb-3">{{ item.title }}</div>
                  <div class="flex items-center gap-4">
                    <Badge class="text-2xl px-4 py-2 bg-blue-100 text-blue-900 hover:bg-blue-100">{{ item.department }}</Badge>
                    <span class="text-3xl text-gray-600">担当: {{ item.assignedTo }}</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div v-else class="space-y-6">
          <h2 class="text-5xl text-gray-900 mb-8 pb-4 border-b-4 border-gray-300">共有メモ</h2>
          <div v-if="mockNotes.length === 0" class="text-center py-20">
            <p class="text-4xl text-gray-500">共有メモはありません</p>
          </div>
          <div v-else class="space-y-6">
            <div v-for="note in mockNotes" :key="note.id" class="bg-yellow-50 border-l-8 border-yellow-500 px-10 py-8 rounded-lg shadow-md">
              <div class="flex items-start justify-between mb-4">
                <h3 class="text-4xl text-gray-900">{{ note.title }}</h3>
                <div class="text-2xl text-gray-600 whitespace-nowrap ml-6">{{ note.date }}</div>
              </div>
              <p class="text-3xl text-gray-700 mb-4 leading-relaxed">{{ note.content }}</p>
              <div class="text-2xl text-gray-600">投稿者: {{ note.author }}</div>
            </div>
          </div>
        </div>
      </div>
    </main>

    <footer class="bg-gray-100 border-t-4 border-gray-300 px-12 py-8">
      <div class="max-w-7xl mx-auto">
        <div class="flex items-center justify-center gap-8">
          <Button variant="outline" size="lg" @click="viewMode = viewMode === 'schedule' ? 'notes' : 'schedule'" class="h-24 px-12 text-3xl border-4 hover:bg-gray-200">
            <ChevronLeft class="w-12 h-12 mr-4" />
            {{ viewMode === 'schedule' ? '共有メモ' : '予定' }}
          </Button>
          <div class="flex items-center gap-8 px-12">
            <div :class="['flex flex-col items-center gap-2 px-8 py-4 rounded-lg transition-colors', viewMode === 'schedule' ? 'bg-primary text-primary-foreground' : 'bg-gray-200 text-gray-600']">
              <Calendar class="w-10 h-10" />
              <span class="text-2xl">予定</span>
            </div>
            <div :class="['flex flex-col items-center gap-2 px-8 py-4 rounded-lg transition-colors', viewMode === 'notes' ? 'bg-primary text-primary-foreground' : 'bg-gray-200 text-gray-600']">
              <StickyNote class="w-10 h-10" />
              <span class="text-2xl">メモ</span>
            </div>
          </div>
          <Button variant="outline" size="lg" @click="viewMode = viewMode === 'schedule' ? 'notes' : 'schedule'" class="h-24 px-12 text-3xl border-4 hover:bg-gray-200">
            {{ viewMode === 'schedule' ? '共有メモ' : '予定' }}
            <ChevronRight class="w-12 h-12 ml-4" />
          </Button>
        </div>
        <div class="text-center mt-6">
          <p class="text-2xl text-gray-500">← スワイプして切り替え →</p>
        </div>
      </div>
    </footer>
  </div>
</template>
