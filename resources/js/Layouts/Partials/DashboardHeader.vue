<script setup lang="ts">
import { ref } from 'vue'
import { Search, Bell, User, Calendar, StickyNote, BarChart3 } from 'lucide-vue-next'
import { Input } from '@/components/ui/input'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from '@/components/ui/popover'
import { ScrollArea } from '@/components/ui/scroll-area'
import EventDetailDialog from '@/components/EventDetailDialog.vue'
import NoteDetailDialog from '@/components/NoteDetailDialog.vue'
import ProfileSettingsDialog from '@/components/ProfileSettingsDialog.vue'
import NotificationSettingsDialog from '@/components/NotificationSettingsDialog.vue'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'

interface Event {
  id: string
  title: string
  color: string
  assignee: string
  time?: string
  department?: string
  location?: string
  description?: string
  date?: string
}

interface Note {
  id: number
  title: string
  content: string
  author: string
  date: string
  deadline?: string
  pinned: boolean
  color: string
  priority: 'high' | 'medium' | 'low'
}

interface Survey {
  id: number
  title: string
  deadline: string
  description?: string
  questions?: string[]
}

const searchQuery = ref('')
const isSearchFocused = ref(false)
const isNotificationOpen = ref(false)

const selectedEvent = ref<Event | null>(null)
const selectedNote = ref<Note | null>(null)
const selectedSurvey = ref<Survey | null>(null)

const isProfileSettingsOpen = ref(false)
const isNotificationSettingsOpen = ref(false)

const insertSearchOption = (option: string) => {
  searchQuery.value += option
}

const importantEvents: Event[] = [
  {
    id: 'multi-14-17',
    title: '経営戦略会議（4日間）',
    date: '2025-10-14 〜 2025-10-17',
    assignee: '田中',
    color: '#3b82f6',
    department: '総務部',
    time: '10:00-12:00',
    location: '会議室A',
    description: '今期の経営戦略について協議します。',
  },
  {
    id: '16-2',
    title: '勤怠確認',
    date: '2025-10-16',
    assignee: '鈴木',
    color: '#10b981',
    department: '総務部',
    time: '14:00-15:00',
    description: '月次の勤怠データを確認します。',
  },
  {
    id: '20-1',
    title: '給与計算',
    date: '2025-10-20',
    assignee: '鈴木',
    color: '#f59e0b',
    department: '総務部',
    time: '13:00-17:00',
    description: '月次の給与計算を実施します。',
  },
  {
    id: '24-1',
    title: '月末処理',
    date: '2025-10-24',
    assignee: '佐藤',
    color: '#ef4444',
    department: '総務部',
    time: '09:00-18:00',
    description: '月末の各種処理を行います。',
  },
]

const importantNotes: Note[] = [
  {
    id: 1,
    title: '備品発注リスト',
    content: '・コピー用紙 A4 10箱\n・ボールペン 黒 50本\n・クリアファイル 100枚',
    deadline: '2025-10-20',
    author: '佐藤',
    date: '2025-10-13',
    pinned: true,
    color: 'bg-yellow-100 border-yellow-300',
    priority: 'high',
  },
  {
    id: 2,
    title: '来客対応メモ',
    content: '10/15 14:00 A社 山本様\n会議室Bを予約済み',
    deadline: '2025-10-15',
    author: '田中',
    date: '2025-10-12',
    pinned: true,
    color: 'bg-blue-100 border-blue-300',
    priority: 'high',
  },
]

const pendingSurveys: Survey[] = [
  {
    id: 1,
    title: '社員満足度調査',
    deadline: '2025-10-25',
    description: '職場環境や業務満足度についてのアンケートです。',
    questions: ['職場環境について', '業務内容について', '福利厚生について'],
  },
  {
    id: 2,
    title: '福利厚生改善アンケート',
    deadline: '2025-10-30',
    description: '福利厚生の改善点についてのアンケートです。',
    questions: ['現在の福利厚生の評価', '改善してほしい点', '追加してほしい制度'],
  },
]

const totalNotifications = importantEvents.length + importantNotes.length + pendingSurveys.length
</script>

<template>
  <header class="bg-white border-b border-gray-200 px-6 py-4">
    <div class="flex items-center justify-between gap-4">
      <!-- 強力な検索バー -->
      <div class="flex-1 max-w-2xl">
        <Popover v-model:open="isSearchFocused">
          <PopoverTrigger as-child>
            <div
              class="relative"
              @mouseenter="isSearchFocused = true"
              @mouseleave="isSearchFocused = false"
            >
              <Search class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 h-5 w-5" />
              <Input
                type="text"
                placeholder="日付、名前、キーワードで検索... (例: 2025-10-20, 田中, 会議)"
                class="pl-10 pr-4 py-2 w-full border-gray-300 focus:border-blue-500 focus:ring-blue-500"
                v-model="searchQuery"
              />
            </div>
          </PopoverTrigger>
          <PopoverContent
            class="w-80 p-2"
            align="start"
            side="bottom"
            @mouseenter="isSearchFocused = true"
            @mouseleave="isSearchFocused = false"
          >
            <div class="space-y-1">
              <p class="text-xs text-gray-500 px-2 py-1">
                検索オプション
              </p>
              <button
                @click="() => { insertSearchOption('タイトル:'); isSearchFocused = false; }"
                class="w-full flex items-center gap-2 px-2 py-1.5 hover:bg-gray-100 rounded text-sm"
              >
                <span class="text-blue-600">T</span>
                <span>タイトル</span>
              </button>
              <button
                @click="() => { insertSearchOption('重要度:'); isSearchFocused = false; }"
                class="w-full flex items-center gap-2 px-2 py-1.5 hover:bg-gray-100 rounded text-sm"
              >
                <span class="text-red-600">!!</span>
                <span>重要度</span>
              </button>
              <button
                @click="() => { insertSearchOption('日付:'); isSearchFocused = false; }"
                class="w-full flex items-center gap-2 px-2 py-1.5 hover:bg-gray-100 rounded text-sm"
              >
                <span>🗓️</span>
                <span>日付</span>
              </button>
              <button
                @click="() => { insertSearchOption('終了日:'); isSearchFocused = false; }"
                class="w-full flex items-center gap-2 px-2 py-1.5 hover:bg-gray-100 rounded text-sm"
              >
                <span class="text-orange-600">End</span>
                <span>ある日付までの予定</span>
              </button>
              <button
                @click="() => { insertSearchOption('開始日:'); isSearchFocused = false; }"
                class="w-full flex items-center gap-2 px-2 py-1.5 hover:bg-gray-100 rounded text-sm"
              >
                <span class="text-green-600">Start</span>
                <span>ある日付からの予定</span>
              </button>
              <button
                @click="() => { insertSearchOption('ジャンル:'); isSearchFocused = false; }"
                class="w-full flex items-center gap-2 px-2 py-1.5 hover:bg-gray-100 rounded text-sm"
              >
                <span class="text-purple-600">#</span>
                <span>ジャンル</span>
              </button>
              <button
                @click="() => { insertSearchOption('メンバー:'); isSearchFocused = false; }"
                class="w-full flex items-center gap-2 px-2 py-1.5 hover:bg-gray-100 rounded text-sm"
              >
                <span>👤</span>
                <span>メンバー</span>
              </button>
              <button
                @click="() => { insertSearchOption('会議室:'); isSearchFocused = false; }"
                class="w-full flex items-center gap-2 px-2 py-1.5 hover:bg-gray-100 rounded text-sm"
              >
                <span>🚪</span>
                <span>会議室</span>
              </button>
              <button
                @click="() => { insertSearchOption('メモ:'); isSearchFocused = false; }"
                class="w-full flex items-center gap-2 px-2 py-1.5 hover:bg-gray-100 rounded text-sm"
              >
                <span>📝</span>
                <span>メモ</span>
              </button>
            </div>
          </PopoverContent>
        </Popover>
        <p class="text-xs text-gray-500 mt-1 ml-1">
          すべての予定、メモ、リマインダーを横断検索
        </p>
      </div>

      <!-- 右側のアクション -->
      <div class="flex items-center gap-3">
        <!-- 通知 -->
        <Popover v-model:open="isNotificationOpen">
          <PopoverTrigger as-child>
            <Button variant="outline" size="icon" class="relative">
              <Bell class="h-5 w-5" />
              <Badge class="absolute -top-1 -right-1 h-5 w-5 flex items-center justify-center p-0 bg-red-500">
                {{ totalNotifications }}
              </Badge>
            </Button>
          </PopoverTrigger>
          <PopoverContent class="w-[420px] p-0" align="end">
            <div class="p-4 border-b border-gray-200">
              <h3 class="flex items-center gap-2">
                <Bell class="h-5 w-5 text-blue-600" />
                通知センター
              </h3>
              <p class="text-xs text-gray-500 mt-1">
                重要な予定、メモ、アンケートをまとめて確認
              </p>
            </div>

            <ScrollArea class="max-h-[600px]">
              <!-- 重要な予定 -->
              <div v-if="importantEvents.length > 0" class="p-3 border-b border-gray-200">
                <div class="flex items-center gap-2 mb-2">
                  <Calendar class="h-4 w-4 text-red-500" />
                  <h4 class="text-sm">重要な予定</h4>
                  <Badge variant="destructive" class="ml-auto text-xs">
                    {{ importantEvents.length }}件
                  </Badge>
                </div>
                <div class="space-y-2">
                  <div
                    v-for="event in importantEvents"
                    :key="event.id"
                    class="p-2 bg-red-50 border border-red-200 rounded-lg hover:bg-red-100 cursor-pointer transition-colors"
                    @click="selectedEvent = event"
                  >
                    <div class="text-sm mb-1">{{ event.title }}</div>
                    <div class="text-xs text-gray-600 flex items-center justify-between">
                      <span>{{ event.date }}</span>
                      <Badge variant="outline" class="text-xs">
                        {{ event.assignee }}
                      </Badge>
                    </div>
                  </div>
                </div>
              </div>

              <!-- 重要なメモ -->
              <div v-if="importantNotes.length > 0" class="p-3 border-b border-gray-200">
                <div class="flex items-center gap-2 mb-2">
                  <StickyNote class="h-4 w-4 text-yellow-600" />
                  <h4 class="text-sm">重要なメモ</h4>
                  <Badge class="ml-auto text-xs bg-yellow-500">
                    {{ importantNotes.length }}件
                  </Badge>
                </div>
                <div class="space-y-2">
                  <div
                    v-for="note in importantNotes"
                    :key="note.id"
                    class="p-2 bg-yellow-50 border border-yellow-200 rounded-lg hover:bg-yellow-100 cursor-pointer transition-colors"
                    @click="selectedNote = note"
                  >
                    <div class="text-sm mb-1">{{ note.title }}</div>
                    <div class="text-xs text-gray-600 flex items-center justify-between">
                      <span>期限: {{ note.deadline }}</span>
                      <Badge variant="outline" class="text-xs">
                        {{ note.author }}
                      </Badge>
                    </div>
                  </div>
                </div>
              </div>

              <!-- 未完了アンケート -->
              <div v-if="pendingSurveys.length > 0" class="p-3">
                <div class="flex items-center gap-2 mb-2">
                  <BarChart3 class="h-4 w-4 text-blue-600" />
                  <h4 class="text-sm">未回答アンケート</h4>
                  <Badge class="ml-auto text-xs bg-blue-500">
                    {{ pendingSurveys.length }}件
                  </Badge>
                </div>
                <div class="space-y-2">
                  <div
                    v-for="survey in pendingSurveys"
                    :key="survey.id"
                    class="p-2 bg-blue-50 border border-blue-200 rounded-lg hover:bg-blue-100 cursor-pointer transition-colors"
                    @click="selectedSurvey = survey"
                  >
                    <div class="text-sm mb-1">{{ survey.title }}</div>
                    <div class="text-xs text-gray-600">
                      回答期限: {{ survey.deadline }}
                    </div>
                  </div>
                </div>
              </div>
            </ScrollArea>
          </PopoverContent>
        </Popover>

        <!-- ユーザーメニュー -->
        <DropdownMenu>
          <DropdownMenuTrigger as-child>
            <Button variant="outline" size="icon">
              <User class="h-5 w-5" />
            </Button>
          </DropdownMenuTrigger>
          <DropdownMenuContent align="end">
            <DropdownMenuLabel>総務部 アカウント</DropdownMenuLabel>
            <DropdownMenuSeparator />
            <DropdownMenuItem @click="isProfileSettingsOpen = true">
              プロフィール設定
            </DropdownMenuItem>
            <DropdownMenuItem @click="isNotificationSettingsOpen = true">
              通知設定
            </DropdownMenuItem>
            <DropdownMenuSeparator />
            <DropdownMenuItem>ログアウト</DropdownMenuItem>
          </DropdownMenuContent>
        </DropdownMenu>
      </div>
    </div>

    <!-- イベント詳細ダイアログ -->
    <EventDetailDialog
      :event="selectedEvent"
      :open="selectedEvent !== null"
      @update:open="(isOpen) => !isOpen && (selectedEvent = null)"
    />

    <!-- メモ詳細ダイアログ -->
    <NoteDetailDialog
      :note="selectedNote"
      :open="selectedNote !== null"
      @update:open="(isOpen) => !isOpen && (selectedNote = null)"
    />

    <!-- アンケート回答ダイアログ -->
    <Dialog :open="selectedSurvey !== null" @update:open="(isOpen) => !isOpen && (selectedSurvey = null)">
      <DialogContent class="max-w-2xl max-h-[90vh]">
        <template v-if="selectedSurvey">
          <DialogHeader>
            <DialogTitle>{{ selectedSurvey.title }}</DialogTitle>
            <DialogDescription>
              回答期限: {{ selectedSurvey.deadline }}
            </DialogDescription>
          </DialogHeader>
          
          <ScrollArea class="max-h-[60vh]">
            <div class="space-y-6 py-4">
              <div v-if="selectedSurvey.description" class="p-4 bg-blue-50 border border-blue-200 rounded-lg">
                <p class="text-sm text-gray-700">{{ selectedSurvey.description }}</p>
              </div>
              
              <div v-for="(question, index) in selectedSurvey.questions" :key="index" class="space-y-2">
                <label class="block text-sm">
                  質問 {{ index + 1 }}: {{ question }}
                </label>
                <textarea
                  class="w-full p-3 border border-gray-300 rounded-lg min-h-[100px] focus:outline-none focus:ring-2 focus:ring-blue-500"
                  placeholder="回答を入力してください..."
                />
              </div>
            </div>
          </ScrollArea>

          <DialogFooter>
            <Button variant="outline" @click="selectedSurvey = null">
              後で回答
            </Button>
            <Button @click="() => { alert('アンケートを送信しました'); selectedSurvey = null; }">
              送信
            </Button>
          </DialogFooter>
        </template>
      </DialogContent>
    </Dialog>

    <!-- プロフィール設定ダイアログ -->
    <ProfileSettingsDialog
      :open="isProfileSettingsOpen"
      @update:open="isProfileSettingsOpen = $event"
    />

    <!-- 通知設定ダイアログ -->
    <NotificationSettingsDialog
      :open="isNotificationSettingsOpen"
      @update:open="isNotificationSettingsOpen = $event"
    />
  </header>
</template>
