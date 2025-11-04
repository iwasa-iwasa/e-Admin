<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import { StickyNote, Plus, Search, Pin, User, Calendar, Save, Trash2, Share2, Filter, X, Clock, ArrowLeft } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { ScrollArea } from '@/components/ui/scroll-area'
import { Badge } from '@/components/ui/badge'
import { Card } from '@/components/ui/card'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import CreateNoteDialog from '@/components/CreateNoteDialog.vue'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

defineOptions({
  layout: AuthenticatedLayout,
})

type Priority = 'high' | 'medium' | 'low'

interface Note {
  id: string
  title: string
  content: string
  author: string
  createdAt: string
  updatedAt: string
  deadline?: string
  pinned: boolean
  tags: string[]
  color: string
  priority: Priority
}

const mockNotes: Note[] = [
  {
    id: '1',
    title: '備品発注リスト',
    content:
      '・コピー用紙 A4 10箱\n・ボールペン 黒 50本\n・クリアファイル 100枚\n・付箋 大小各10個\n・ホッチキス針 5箱\n\n発注期限：10月20日まで\n業者：オフィスサプライ株式会社',
    author: '佐藤',
    createdAt: '2025-10-13 09:30',
    updatedAt: '2025-10-14 14:20',
    pinned: true,
    tags: ['備品', '発注'],
    color: 'yellow',
    priority: 'high',
  },
  {
    id: '2',
    title: '来客対応メモ',
    content:
      '10/15 14:00 A社 山本様\n会議室Bを予約済み\n\n準備事項：\n・プロジェクター設定\n・お茶の準備\n・資料10部印刷',
    author: '田中',
    createdAt: '2025-10-12 16:45',
    updatedAt: '2025-10-13 10:15',
    pinned: true,
    tags: ['来客', '会議'],
    color: 'blue',
    priority: 'high',
  },
  {
    id: '3',
    title: '月次報告の進捗',
    content:
      '経理：完了（10/10）\n人事：作業中（進捗80%）\n総務：未着手\n\n総務部の報告期限：10/18\n担当：鈴木',
    author: '鈴木',
    createdAt: '2025-10-11 11:20',
    updatedAt: '2025-10-14 09:30',
    pinned: false,
    tags: ['報告', '進捗'],
    color: 'green',
    priority: 'medium',
  },
  {
    id: '4',
    title: '社内イベント企画',
    content:
      '忘年会の候補日：12/20（水）、12/22（金）\n参加人数：約50名\n予算：一人5,000円\n\n候補会場：\n1. 駅前ホテル宴会場\n2. 和食レストラン「花月」\n3. イタリアンレストラン「ベルソーレ」',
    author: '山田',
    createdAt: '2025-10-10 15:00',
    updatedAt: '2025-10-11 13:45',
    pinned: false,
    tags: ['イベント', '社内'],
    color: 'pink',
    priority: 'low',
  },
  {
    id: '5',
    title: '勤怠管理システム更新',
    content:
      '新システム導入予定：11月1日\n\n変更点：\n・スマホアプリ対応\n・有給申請のワークフロー変更\n・打刻方法の簡素化\n\n説明会：10/25 13:00-14:00',
    author: '佐藤',
    createdAt: '2025-10-09 10:00',
    updatedAt: '2025-10-09 10:00',
    pinned: false,
    tags: ['システム', '勤怠'],
    color: 'purple',
    priority: 'medium',
  },
  {
    id: '6',
    title: 'オフィス清掃スケジュール',
    content:
      '10月の清掃スケジュール：\n毎週月曜日：会議室\n毎週水曜日：給湯室\n毎週金曜日：エントランス\n\n清掃業者：クリーンサービス\n連絡先：03-1234-5678',
    author: '田中',
    createdAt: '2025-10-08 14:30',
    updatedAt: '2025-10-08 14:30',
    pinned: false,
    tags: ['清掃', 'スケジュール'],
    color: 'yellow',
    priority: 'low',
  },
]

const notes = ref<Note[]>(mockNotes)
const selectedNote = ref<Note | null>(notes.value[0])
const searchQuery = ref('')
const filterAuthor = ref('all')
const filterPinned = ref('all')
const editedTitle = ref(selectedNote.value?.title || '')
const editedContent = ref(selectedNote.value?.content || '')
const isCreateDialogOpen = ref(false)

const page = usePage()

onMounted(() => {
  const url = new URL(window.location.href)
  if (url.searchParams.get('create') === 'true') {
    isCreateDialogOpen.value = true
    url.searchParams.delete('create')
    window.history.replaceState({}, '', url.toString())
  }
})

watch(selectedNote, (newNote) => {
  if (newNote) {
    editedTitle.value = newNote.title
    editedContent.value = newNote.content
  }
})

const filteredNotes = computed(() => {
  return notes.value.filter((note) => {
    const matchesSearch =
      note.title.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
      note.content.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
      note.author.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
      note.createdAt.includes(searchQuery.value) ||
      note.updatedAt.includes(searchQuery.value)

    const matchesAuthor = filterAuthor.value === 'all' || note.author === filterAuthor.value

    const matchesPinned =
      filterPinned.value === 'all' ||
      (filterPinned.value === 'pinned' && note.pinned) ||
      (filterPinned.value === 'unpinned' && !note.pinned)

    return matchesSearch && matchesAuthor && matchesPinned
  })
})

const authors = computed(() => Array.from(new Set(notes.value.map((note) => note.author))))

const handleSelectNote = (note: Note) => {
  selectedNote.value = note
}

const handleCreateNote = () => {
  const newNote: Note = {
    id: String(Date.now()),
    title: '新しいメモ',
    content: '',
    author: '田中', // Replace with actual logged in user
    createdAt: new Date().toLocaleString('ja-JP'),
    updatedAt: new Date().toLocaleString('ja-JP'),
    pinned: false,
    tags: [],
    color: 'yellow',
    priority: 'medium',
  }
  notes.value.unshift(newNote)
  handleSelectNote(newNote)
}

const getColorClass = (color: string) => {
  const colorMap: Record<string, string> = {
    yellow: 'bg-yellow-50 border-yellow-300 hover:bg-yellow-100',
    blue: 'bg-blue-50 border-blue-300 hover:bg-blue-100',
    green: 'bg-green-50 border-green-300 hover:bg-green-100',
    pink: 'bg-pink-50 border-pink-300 hover:bg-pink-100',
    purple: 'bg-purple-50 border-purple-300 hover:bg-purple-100',
  }
  return colorMap[color] || 'bg-gray-50 border-gray-300 hover:bg-gray-100'
}

</script>

<template>
  <div class="h-screen bg-gray-50 flex">
    <div class="w-full md:w-96 lg:w-[420px] bg-white border-r border-gray-200 flex flex-col">
      <div class="p-4 border-b border-gray-200">
        <div class="flex items-center justify-between mb-4">
          <div class="flex items-center gap-2">
            <Button variant="ghost" size="icon" @click="router.get('/')" class="mr-1">
              <ArrowLeft class="h-5 w-5" />
            </Button>
            <StickyNote class="h-6 w-6 text-yellow-600" />
            <h1>共有メモ</h1>
          </div>
          <Button @click="handleCreateNote" class="gap-2">
            <Plus class="h-4 w-4" />
            新規作成
          </Button>
        </div>

        <div class="relative mb-3">
          <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
          <Input
            placeholder="日付、名前、内容で検索..."
            v-model="searchQuery"
            class="pl-9 pr-9"
          />
          <button v-if="searchQuery" @click="searchQuery = ''" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
            <X class="h-4 w-4" />
          </button>
        </div>

        <div class="flex gap-2">
          <Select v-model="filterAuthor">
            <SelectTrigger class="flex-1">
              <div class="flex items-center gap-2">
                <User class="h-4 w-4" />
                <SelectValue placeholder="作成者" />
              </div>
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="all">すべての作成者</SelectItem>
              <SelectItem v-for="author in authors" :key="author" :value="author">
                {{ author }}
              </SelectItem>
            </SelectContent>
          </Select>

          <Select v-model="filterPinned">
            <SelectTrigger class="flex-1">
              <div class="flex items-center gap-2">
                <Filter class="h-4 w-4" />
                <SelectValue placeholder="フィルター" />
              </div>
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="all">すべてのメモ</SelectItem>
              <SelectItem value="pinned">ピン留めのみ</SelectItem>
              <SelectItem value="unpinned">通常メモのみ</SelectItem>
            </SelectContent>
          </Select>
        </div>
      </div>

      <ScrollArea class="flex-1">
        <div class="p-4 space-y-3">
          <div v-if="filteredNotes.length === 0" class="text-center py-12 text-gray-500">
            <StickyNote class="h-12 w-12 mx-auto mb-3 opacity-30" />
            <p>メモが見つかりません</p>
          </div>
          <Card
            v-for="note in filteredNotes"
            :key="note.id"
            @click="handleSelectNote(note)"
            :class="['cursor-pointer transition-all border-l-4', selectedNote?.id === note.id ? 'ring-2 ring-primary shadow-md' : 'hover:shadow-md', getColorClass(note.color)]"
          >
            <div class="p-4">
              <div class="flex items-start justify-between mb-2">
                <h3 class="flex-1 flex items-center gap-2 pr-2">
                  <Pin v-if="note.pinned" class="h-4 w-4 text-gray-600 flex-shrink-0" />
                  <span class="line-clamp-1">{{ note.title }}</span>
                </h3>
              </div>
              <p class="text-sm text-gray-600 mb-3 line-clamp-2 whitespace-pre-line">{{ note.content }}</p>
              <div v-if="note.tags.length > 0" class="flex flex-wrap gap-1 mb-3">
                <Badge v-for="(tag, index) in note.tags" :key="index" variant="secondary" class="text-xs">
                  {{ tag }}
                </Badge>
              </div>
              <div class="flex items-center justify-between text-xs text-gray-500">
                <div class="flex items-center gap-1">
                  <User class="h-3 w-3" />
                  <span>{{ note.author }}</span>
                </div>
                <div class="flex items-center gap-1">
                  <Clock class="h-3 w-3" />
                  <span>{{ note.updatedAt }}</span>
                </div>
              </div>
            </div>
          </Card>
        </div>
      </ScrollArea>
    </div>

    <div class="flex-1 flex flex-col bg-white">
      <template v-if="selectedNote">
        <div class="p-6 border-b border-gray-200">
          <div class="flex items-start justify-between mb-4">
            <div class="flex-1">
              <Input
                v-model="editedTitle"
                placeholder="メモのタイトル"
                class="mb-3 border-none shadow-none p-0 focus-visible:ring-0"
              />
              <div class="flex items-center gap-4 text-sm text-gray-500">
                <div class="flex items-center gap-1">
                  <User class="h-4 w-4" />
                  <span>{{ selectedNote.author }}</span>
                </div>
                <div class="flex items-center gap-1">
                  <Calendar class="h-4 w-4" />
                  <span>作成: {{ selectedNote.createdAt }}</span>
                </div>
                <div class="flex items-center gap-1">
                  <Clock class="h-4 w-4" />
                  <span>更新: {{ selectedNote.updatedAt }}</span>
                </div>
              </div>
            </div>
            <div class="flex items-center gap-2 ml-4">
              <Button variant="outline" size="sm" class="gap-2">
                <Pin class="h-4 w-4" />
                {{ selectedNote.pinned ? 'ピン解除' : 'ピン留め' }}
              </Button>
            </div>
          </div>
          <div v-if="selectedNote.tags.length > 0" class="flex flex-wrap gap-2">
            <Badge v-for="(tag, index) in selectedNote.tags" :key="index" variant="secondary">
              {{ tag }}
            </Badge>
          </div>
        </div>
        <div class="flex-1 p-6 overflow-auto">
          <Textarea
            v-model="editedContent"
            placeholder="メモの内容を入力..."
            class="min-h-[400px] resize-none border-none shadow-none focus-visible:ring-0 p-0"
          />
        </div>
        <div class="p-6 border-t border-gray-200">
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
              <Button variant="destructive" class="gap-2">
                <Trash2 class="h-4 w-4" />
                削除
              </Button>
              <Button variant="outline" class="gap-2">
                <Share2 class="h-4 w-4" />
                共有
              </Button>
            </div>
            <Button class="gap-2">
              <Save class="h-4 w-4" />
              保存
            </Button>
          </div>
        </div>
      </template>
      <div v-else class="flex-1 flex items-center justify-center text-gray-400">
        <div class="text-center">
          <StickyNote class="h-24 w-24 mx-auto mb-4 opacity-20" />
          <p>メモを選択してください</p>
        </div>
      </div>
    </div>
    <CreateNoteDialog :open="isCreateDialogOpen" @update:open="isCreateDialogOpen = $event" />
  </div>
</template>