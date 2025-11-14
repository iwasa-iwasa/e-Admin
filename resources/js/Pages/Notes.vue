<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import { ref, computed, onMounted, watch } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import { StickyNote, Plus, Search, Pin, User, Calendar, Save, Trash2, Share2, Filter, X, Clock, ArrowLeft, AlertCircle, ArrowUp, ArrowDown } from 'lucide-vue-next'
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

const props = defineProps<{
  notes: (App.Models.SharedNote & { is_pinned: boolean })[]
}>()

const selectedNote = ref<(App.Models.SharedNote & { is_pinned: boolean }) | null>(props.notes.length > 0 ? props.notes[0] : null)
const searchQuery = ref('')
const filterAuthor = ref('all')
const filterPinned = ref('all')
const sortKey = ref<'priority' | 'deadline' | 'updated_at'>('updated_at')
const sortOrder = ref<'asc' | 'desc'>('desc')
const editedTitle = ref(selectedNote.value?.title || '')
const editedContent = ref(selectedNote.value?.content || '')
const editedDeadline = ref(selectedNote.value?.deadline || '')
const editedPriority = ref<Priority>(selectedNote.value?.priority as Priority || 'medium')
const editedColor = ref(selectedNote.value?.color || 'yellow')
const isCreateDialogOpen = ref(false)
const isSaving = ref(false)
const saveMessage = ref('')

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
    editedDeadline.value = newNote.deadline || ''
    editedPriority.value = newNote.priority as Priority
    editedColor.value = newNote.color
  }
})

watch(() => props.notes, (newNotes) => {
  if (selectedNote.value) {
    const updatedSelectedNote = newNotes.find(note => note.note_id === selectedNote.value.note_id);
    if (updatedSelectedNote) {
      selectedNote.value = updatedSelectedNote;
    }
  }
}, { deep: true });

const getPriorityValue = (priority: Priority) => {
  switch (priority) {
    case 'high': return 3
    case 'medium': return 2
    case 'low': return 1
    default: return 0
  }
}

const filteredNotes = computed(() => {
  const filtered = props.notes.filter((note) => {
    const matchesSearch =
      note.title.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
      (note.content && note.content.toLowerCase().includes(searchQuery.value.toLowerCase())) ||
      (note.author && note.author.name.toLowerCase().includes(searchQuery.value.toLowerCase())) ||
      note.created_at.includes(searchQuery.value) ||
      note.updated_at.includes(searchQuery.value)

    const matchesAuthor = filterAuthor.value === 'all' || (note.author && note.author.name === filterAuthor.value)

    const matchesPinned =
      filterPinned.value === 'all' ||
      (filterPinned.value === 'pinned' && note.is_pinned) ||
      (filterPinned.value === 'unpinned' && !note.is_pinned)

    return matchesSearch && matchesAuthor && matchesPinned
  })

  return filtered.sort((a, b) => {
    // 1. ピン留め優先
    if (a.is_pinned !== b.is_pinned) {
      return a.is_pinned ? -1 : 1
    }

    // 2. 選択されたソートキー
    let sortResult = 0
    if (sortKey.value === 'priority') {
      sortResult = getPriorityValue(b.priority as Priority) - getPriorityValue(a.priority as Priority)
    } else if (sortKey.value === 'deadline') {
      const aDeadline = a.deadline || '9999-12-31'
      const bDeadline = b.deadline || '9999-12-31'
      sortResult = aDeadline.localeCompare(bDeadline)
    } else {
      sortResult = new Date(b.updated_at).getTime() - new Date(a.updated_at).getTime()
    }
    
    if (sortResult !== 0) {
      return sortOrder.value === 'asc' ? -sortResult : sortResult
    }

    // 3. デフォルト: 更新日時（新しい順）
    return new Date(b.updated_at).getTime() - new Date(a.updated_at).getTime()
  })
})

const authors = computed(() => Array.from(new Set(props.notes.map((note) => note.author?.name).filter(Boolean))))

const handleSelectNote = (note: App.Models.SharedNote & { is_pinned: boolean }) => {
  selectedNote.value = note
}

const handleCreateNote = () => {
  isCreateDialogOpen.value = true
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

const togglePin = (note: App.Models.SharedNote & { is_pinned: boolean }) => {
    if (note.is_pinned) {
        router.delete(route('notes.unpin', note.note_id), {
            preserveScroll: true, // スクロール位置のみ維持する
        });
    } else {
        router.post(route('notes.pin', note.note_id), {}, {
            preserveScroll: true, // スクロール位置のみ維持する
        });
    }
};

const handleSaveNote = () => {
  if (!selectedNote.value) return
  
  isSaving.value = true
  saveMessage.value = ''
  
  const updateData = {
    title: editedTitle.value,
    content: editedContent.value,
    deadline: editedDeadline.value || null,
    priority: editedPriority.value,
    color: editedColor.value
  }
  
  router.put(route('shared-notes.update', selectedNote.value.note_id), updateData, {
    preserveScroll: true,
    onSuccess: () => {
      saveMessage.value = 'メモを保存しました'
      setTimeout(() => saveMessage.value = '', 3000)
    },
    onError: () => {
      saveMessage.value = '保存に失敗しました'
      setTimeout(() => saveMessage.value = '', 3000)
    },
    onFinish: () => {
      isSaving.value = false
    }
  })
}

const getPriorityInfo = (priority: Priority) => {
  switch (priority) {
    case 'high':
      return { className: 'bg-red-600 text-white border-red-600', label: '重要' }
    case 'medium':
      return { className: 'bg-yellow-500 text-white border-yellow-500', label: '中' }
    case 'low':
      return { className: 'bg-gray-400 text-white border-gray-400', label: '低' }
  }
}

const getColorInfo = (color: string) => {
  const colorMap: Record<string, { bg: string; label: string }> = {
    yellow: { bg: 'bg-yellow-100', label: 'イエロー' },
    blue: { bg: 'bg-blue-100', label: 'ブルー' },
    green: { bg: 'bg-green-100', label: 'グリーン' },
    pink: { bg: 'bg-pink-100', label: 'ピンク' },
    purple: { bg: 'bg-purple-100', label: 'パープル' },
  }
  return colorMap[color] || colorMap.yellow
}

</script>

<template>
  <Head title="共有メモ" />
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
          <Button variant="outline" @click="handleCreateNote" class="gap-2">
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

        <div class="flex gap-2 mb-2">
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

        <div class="space-y-2">
          <div class="text-xs font-medium text-gray-700 mb-1">並び順</div>
          <div class="flex gap-2">
            <Button
              size="sm"
              :variant="sortKey === 'updated_at' ? 'default' : 'outline'"
              @click="sortKey === 'updated_at' ? (sortOrder = sortOrder === 'desc' ? 'asc' : 'desc') : (sortKey = 'updated_at', sortOrder = 'desc')"
              class="flex-1 h-9 text-xs font-medium transition-all"
              :class="sortKey === 'updated_at' ? 'shadow-md' : 'hover:bg-gray-50'"
            >
              <Clock class="h-3.5 w-3.5 mr-1.5" />
              <span>更新日時順</span>
              <component 
                :is="sortOrder === 'desc' ? ArrowDown : ArrowUp" 
                v-if="sortKey === 'updated_at'" 
                class="h-3 w-3 ml-1" 
              />
            </Button>
            <Button
              size="sm"
              :variant="sortKey === 'priority' ? 'default' : 'outline'"
              @click="sortKey === 'priority' ? (sortOrder = sortOrder === 'desc' ? 'asc' : 'desc') : (sortKey = 'priority', sortOrder = 'desc')"
              class="flex-1 h-9 text-xs font-medium transition-all"
              :class="sortKey === 'priority' ? 'shadow-md' : 'hover:bg-gray-50'"
            >
              <AlertCircle class="h-3.5 w-3.5 mr-1.5" />
              <span>重要度順</span>
              <component 
                :is="sortOrder === 'desc' ? ArrowDown : ArrowUp" 
                v-if="sortKey === 'priority'" 
                class="h-3 w-3 ml-1" 
              />
            </Button>
            <Button
              size="sm"
              :variant="sortKey === 'deadline' ? 'default' : 'outline'"
              @click="sortKey === 'deadline' ? (sortOrder = sortOrder === 'desc' ? 'asc' : 'desc') : (sortKey = 'deadline', sortOrder = 'desc')"
              class="flex-1 h-9 text-xs font-medium transition-all"
              :class="sortKey === 'deadline' ? 'shadow-md' : 'hover:bg-gray-50'"
            >
              <Calendar class="h-3.5 w-3.5 mr-1.5" />
              <span>期限順</span>
              <component 
                :is="sortOrder === 'desc' ? ArrowDown : ArrowUp" 
                v-if="sortKey === 'deadline'" 
                class="h-3 w-3 ml-1" 
              />
            </Button>
          </div>
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
            :key="note.note_id"
            @click="handleSelectNote(note)"
            :class="['cursor-pointer transition-all border-l-4', selectedNote?.note_id === note.note_id ? 'ring-2 ring-primary shadow-md' : 'hover:shadow-md', getColorClass(note.color)]"
          >
            <div class="p-4">
              <div class="flex items-start justify-between mb-2">
                <h3 class="flex-1 flex items-center gap-2 pr-2">
                  <Pin v-if="note.is_pinned" class="h-4 w-4 text-yellow-500 fill-current flex-shrink-0" />
                  <span class="line-clamp-1">{{ note.title }}</span>
                </h3>
                <Badge :class="[getPriorityInfo(note.priority as Priority).className, 'text-xs px-2 py-0.5 flex-shrink-0']">
                  {{ getPriorityInfo(note.priority as Priority).label }}
                </Badge>
              </div>
              <p class="text-sm text-gray-600 mb-3 line-clamp-2 whitespace-pre-line">{{ note.content }}</p>
              <div v-if="note.tags.length > 0" class="flex flex-wrap gap-1 mb-3">
                <Badge v-for="(tag, index) in note.tags" :key="index" variant="secondary" class="text-xs">
                  {{ tag.tag_name }}
                </Badge>
              </div>
              <div class="flex items-center justify-between text-xs text-gray-500">
                <div class="flex items-center gap-1">
                  <User class="h-3 w-3" />
                  <span>{{ note.author?.name }}</span>
                </div>
                <div class="flex items-center gap-1">
                  <Clock class="h-3 w-3" />
                  <span>{{ new Date(note.updated_at).toLocaleString('ja-JP') }}</span>
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
                class="mb-3 border-none shadow-none p-0 focus-visible:ring-0 text-2xl font-bold"
              />
              <div class="flex items-center gap-4 text-sm text-gray-500">
                <div class="flex items-center gap-1">
                  <User class="h-4 w-4" />
                  <span>{{ selectedNote.author?.name }}</span>
                </div>
                <div class="flex items-center gap-1">
                  <Calendar class="h-4 w-4" />
                  <span>作成: {{ new Date(selectedNote.created_at).toLocaleString('ja-JP') }}</span>
                </div>
                <div class="flex items-center gap-1">
                  <Clock class="h-4 w-4" />
                  <span>更新: {{ new Date(selectedNote.updated_at).toLocaleString('ja-JP') }}</span>
                </div>
              </div>
            </div>
            <div class="flex items-center gap-2 ml-4">
              <Button variant="outline" size="sm" class="gap-2" @click="togglePin(selectedNote)">
                <Pin class="h-4 w-4" :class="{'fill-current text-yellow-500': selectedNote.is_pinned}" />
                {{ selectedNote.is_pinned ? 'ピン解除' : 'ピン留め' }}
              </Button>
            </div>
          </div>
          
          <!-- 編集UI -->
          <div class="space-y-3 mb-4">
            <div class="grid grid-cols-3 gap-3">
              <div>
                <label class="text-xs font-medium text-gray-700 mb-1 block">期限</label>
                <Input
                  type="date"
                  v-model="editedDeadline"
                  class="h-8 text-xs"
                />
              </div>
              <div>
                <label class="text-xs font-medium text-gray-700 mb-1 block">重要度</label>
                <Select v-model="editedPriority">
                  <SelectTrigger class="h-8 text-xs">
                    <div class="flex items-center gap-2">
                      <Badge :class="getPriorityInfo(editedPriority).className" class="text-xs px-1 py-0">
                        {{ getPriorityInfo(editedPriority).label }}
                      </Badge>
                    </div>
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="high">
                      <Badge class="bg-red-600 text-white text-xs">重要</Badge>
                    </SelectItem>
                    <SelectItem value="medium">
                      <Badge class="bg-yellow-500 text-white text-xs">中</Badge>
                    </SelectItem>
                    <SelectItem value="low">
                      <Badge class="bg-gray-400 text-white text-xs">低</Badge>
                    </SelectItem>
                  </SelectContent>
                </Select>
              </div>
              <div>
                <label class="text-xs font-medium text-gray-700 mb-1 block">色</label>
                <Select v-model="editedColor">
                  <SelectTrigger class="h-8 text-xs">
                    <div class="flex items-center gap-2">
                      <div :class="['w-3 h-3 rounded', getColorInfo(editedColor).bg]"></div>
                      <span>{{ getColorInfo(editedColor).label }}</span>
                    </div>
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem v-for="c in ['yellow', 'blue', 'green', 'pink', 'purple']" :key="c" :value="c">
                      <div class="flex items-center gap-2">
                        <div :class="['w-3 h-3 rounded', getColorInfo(c).bg]"></div>
                        <span>{{ getColorInfo(c).label }}</span>
                      </div>
                    </SelectItem>
                  </SelectContent>
                </Select>
              </div>
            </div>
          </div>
          
          <div v-if="selectedNote.tags.length > 0" class="flex flex-wrap gap-2">
            <Badge v-for="(tag, index) in selectedNote.tags" :key="index" variant="secondary">
              {{ tag.tag_name }}
            </Badge>
          </div>
          

        </div>
        <div class="flex-1 p-6 overflow-auto">
          <Textarea
            v-model="editedContent"
            placeholder="メモの内容を入力..."
            class="min-h-[400px] resize-none border-none shadow-none focus-visible:ring-0 p-0 leading-relaxed"
          />
        </div>
        <div class="p-6 border-t border-gray-200">
          <!-- 保存メッセージ -->
          <div v-if="saveMessage" class="mb-3 p-2 rounded text-sm" :class="saveMessage.includes('失敗') ? 'bg-red-50 text-red-700' : 'bg-green-50 text-green-700'">
            {{ saveMessage }}
          </div>
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
              <Button variant="destructive" class="gap-2" disabled>
                <Trash2 class="h-4 w-4" />
                削除
              </Button>
              <Button variant="outline" class="gap-2" disabled>
                <Share2 class="h-4 w-4" />
                共有
              </Button>
            </div>
            <Button class="gap-2" @click="handleSaveNote" :disabled="isSaving">
              <Save class="h-4 w-4" />
              {{ isSaving ? '保存中...' : '保存' }}
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