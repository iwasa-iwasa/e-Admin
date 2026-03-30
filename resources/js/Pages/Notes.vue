<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import { ref, computed, onMounted, watch } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import { useHighlight } from '@/composables/useHighlight'
import { StickyNote, Plus, Search, Pin, User, Calendar, Save, Trash2, Copy, Filter, X, Clock, ArrowLeft, AlertCircle, ArrowUp, ArrowDown, CheckCircle, Undo2, HelpCircle, Tag, ExternalLink } from 'lucide-vue-next'
import { ja } from "date-fns/locale";
import { CATEGORY_LABELS, CATEGORY_COLORS, loadCategoryLabels } from '@/constants/calendar'
import '@vuepic/vue-datepicker/dist/main.css';
import { VueDatePicker } from '@vuepic/vue-datepicker';
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { ScrollArea } from '@/components/ui/scroll-area'
import { Badge } from '@/components/ui/badge'
import { Card, CardTitle } from '@/components/ui/card'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover'
import { Label } from '@/components/ui/label'

import CreateNoteDialog from '@/components/CreateNoteDialog.vue'
import CreateEventDialog from '@/components/CreateEventDialog.vue'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

defineOptions({
  layout: AuthenticatedLayout,
})

// App.Modelsの代替定義
type Priority = 'high' | 'medium' | 'low'

interface UserModel {
  id: number
  name: string
  profile_photo_url?: string
}

interface SharedNoteModel {
  note_id: number
  title: string
  content: string | null
  author_id: number
  color: string
  priority: string
  deadline_date: string | null
  deadline_time: string | null
  is_deleted: boolean
  created_at: string | null
  updated_at: string | null
  author?: UserModel
  participants?: UserModel[]
  tags: Array<{ tag_id: number; tag_name: string }>
  is_pinned?: boolean
  progress?: number | null
  linked_event_id?: number | null
  visibility_type?: string
  owner_department_id?: number | null
}

const props = defineProps<{
  notes: SharedNoteModel[]
  totalUsers: number
  teamMembers: UserModel[]
  allTags: string[]
  filteredMemberId?: number | null
  currentDepartmentFilter?: string
  userDepartmentId?: number
  departments?: Array<{ id: number; name: string }>
}>()

const page = usePage()
const currentUserId = computed(() => (page.props as any).auth?.user?.id ?? null)

const filteredMember = computed(() => {
  if (!props.filteredMemberId) return null
  return props.teamMembers.find(member => member.id === props.filteredMemberId)
})

const clearFilter = () => {
  router.get(route('notes'), {}, {
    preserveState: true,
    replace: true,
  })
}

const isAllUsers = (participants: UserModel[] | undefined) => {
  return participants && participants.length === props.totalUsers
}

const canEditParticipants = computed(() => {
  if (!selectedNote.value) return false
  // 作成者は常に編集可能
  if (selectedNote.value.author?.id === currentUserId.value) return true
  // 参加者のみ編集可能
  return selectedNote.value.participants?.some(p => p.id === currentUserId.value) || false
})

const selectedNote = ref<SharedNoteModel | null>(props.notes.length > 0 ? props.notes[0] : null)
const searchQuery = ref('')
const filterAuthor = ref('all')
const filterPinned = ref('all')
const filterTag = ref('all')
const isFilterOpen = ref(false)
const searchInputRef = ref<HTMLInputElement | null>(null)
const sortKey = ref<'priority' | 'deadline' | 'updated_at'>('updated_at')
const sortOrder = ref<'asc' | 'desc'>('desc')
const editedTitle = ref(selectedNote.value?.title || '')
const editedContent = ref(selectedNote.value?.content || '')
const editedDeadline = ref('')
const deadlineDateTime = ref<Date | null>(null)
const editedPriority = ref<Priority>(selectedNote.value?.priority as Priority || 'medium')
const editedColor = ref(selectedNote.value?.color || 'yellow')
const editedTags = ref<string[]>(selectedNote.value?.tags.map(tag => tag.tag_name) || [])
const editedParticipants = ref<UserModel[]>(selectedNote.value?.participants || [])
const participantSelectValue = ref<string | null>(null)
const tagInput = ref('')
const showTagSuggestions = ref(false)
const tagDebounceTimer = ref<ReturnType<typeof setTimeout> | null>(null)
const isCreateDialogOpen = ref(false)
const isSaving = ref(false)
const saveMessage = ref('')
const editedProgress = ref<number>(selectedNote.value?.progress ?? 0)
const editedVisibility = ref<string>(selectedNote.value?.visibility_type || 'public')
const linkedEvent = ref<any>(null)
const isEventDialogOpen = ref(false)

const departmentFilter = ref(props.currentDepartmentFilter || 'all')

watch(departmentFilter, (newVal) => {
    router.get(route('notes'), { department_filter: newVal }, {
        preserveState: true,
        replace: true
    })
})

// メッセージとUndoロジック
const messageType = ref<'success' | 'delete'>('success')
const messageTimer = ref<ReturnType<typeof setTimeout> | null>(null)
const lastDeletedNote = ref<SharedNoteModel | null>(null)

onMounted(() => {
  loadCategoryLabels()
  const handleCategoryUpdate = () => loadCategoryLabels()
  window.addEventListener('category-labels-updated', handleCategoryUpdate)
  
  const url = new URL(window.location.href)
  if (url.searchParams.get('create') === 'true') {
    isCreateDialogOpen.value = true
    url.searchParams.delete('create')
    window.history.replaceState({}, '', url.toString())
  }
  
  // 新規作成されたメモを選択
  const selectNoteId = url.searchParams.get('select')
  if (selectNoteId) {
    const noteToSelect = props.notes.find(note => note.note_id.toString() === selectNoteId)
    if (noteToSelect) {
      selectedNote.value = noteToSelect
      scrollToNote(selectNoteId)
    }
    url.searchParams.delete('select')
    window.history.replaceState({}, '', url.toString())
  }
  
  // ハイライト機能
  const highlightId = (page.props as any).highlight
  if (highlightId) {
    const noteToHighlight = props.notes.find(note => note.note_id === highlightId)
    if (noteToHighlight) {
      selectedNote.value = noteToHighlight
      setTimeout(() => {
        const element = document.querySelector(`[data-note-id="${highlightId}"]`)
        if (element) {
          element.scrollIntoView({ behavior: 'smooth', block: 'center' })
          element.classList.add('highlight-flash')
          setTimeout(() => element.classList.remove('highlight-flash'), 3000)
        }
      }, 100)
    }
  }
})

const showMessage = (message: string, type: 'success' | 'delete' = 'success') => {
    if (messageTimer.value) {
        clearTimeout(messageTimer.value);
    }
    
    saveMessage.value = message
    messageType.value = type
    
    messageTimer.value = window.setTimeout(() => {
        saveMessage.value = ''
        lastDeletedNote.value = null
    }, 4000)
}

watch(selectedNote, (newNote) => {
  if (newNote) {
    editedTitle.value = newNote.title
    editedContent.value = newNote.content || ''
    // deadline_dateとdeadline_timeを結合してdatetime-local形式に変換
    if (newNote.deadline_date) {
      const time = newNote.deadline_time || '23:59'
      editedDeadline.value = `${newNote.deadline_date}T${time}`
      deadlineDateTime.value = new Date(`${newNote.deadline_date}T${time}`)
    } else {
      editedDeadline.value = ''
      deadlineDateTime.value = null
    }
    editedPriority.value = newNote.priority as Priority
    editedColor.value = newNote.color
    editedTags.value = newNote.tags.map(tag => tag.tag_name)
    editedParticipants.value = newNote.participants || []
    participantSelectValue.value = null
    editedProgress.value = newNote.progress ?? 0
    editedVisibility.value = newNote.visibility_type || 'public'
    // タグ入力欄をクリア
    tagInput.value = ''
    showTagSuggestions.value = false
    
    // リンクされたイベントを取得
    if (newNote.linked_event_id) {
      fetchLinkedEvent(newNote.linked_event_id)
    } else {
      linkedEvent.value = null
    }
  }
})

watch(deadlineDateTime, (newDate) => {
  if (newDate) {
    editedDeadline.value = newDate.toISOString().slice(0, 16)
  } else {
    editedDeadline.value = ''
  }
})


watch(() => props.notes, (newNotes, oldNotes) => {
  // 新しいメモが追加された場合の処理
  const url = new URL(window.location.href)
  const selectNoteId = url.searchParams.get('select')
  
  if (selectNoteId && newNotes.length > (oldNotes?.length || 0)) {
    const noteToSelect = newNotes.find(note => note.note_id.toString() === selectNoteId)
    if (noteToSelect) {
      selectedNote.value = noteToSelect
      scrollToNote(selectNoteId)
      url.searchParams.delete('select')
      window.history.replaceState({}, '', url.toString())
    }
  }
  
  // 既存の選択されたメモの更新処理
  if (selectedNote.value) {
    const updatedSelectedNote = newNotes.find(note => note.note_id === selectedNote.value?.note_id);
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
      note.created_at?.includes(searchQuery.value) ||
      note.updated_at?.includes(searchQuery.value) ||
      note.tags.some(tag => tag.tag_name.toLowerCase().includes(searchQuery.value.toLowerCase()))

    const matchesAuthor = filterAuthor.value === 'all' || (note.author && note.author.name === filterAuthor.value)

    const matchesPinned =
      filterPinned.value === 'all' ||
      (filterPinned.value === 'pinned' && note.is_pinned) ||
      (filterPinned.value === 'unpinned' && !note.is_pinned)

    const matchesTag = filterTag.value === 'all' || note.tags.some(tag => tag.tag_name === filterTag.value)

    return matchesSearch && matchesAuthor && matchesPinned && matchesTag
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
      const aDeadline = a.deadline_date || '9999-12-31'
      const bDeadline = b.deadline_date || '9999-12-31'
      sortResult = aDeadline.localeCompare(bDeadline)
    } else {
      sortResult = new Date(b.updated_at || 0).getTime() - new Date(a.updated_at || 0).getTime()
    }
    
    if (sortResult !== 0) {
      return sortOrder.value === 'asc' ? -sortResult : sortResult
    }

    // 3. デフォルト: 更新日時（新しい順）
    return new Date(b.updated_at || 0).getTime() - new Date(a.updated_at || 0).getTime()
  })
})

const activeFilterCount = computed(() => {
  let count = 0
  if (filterAuthor.value !== 'all') count++
  if (filterPinned.value !== 'all') count++
  if (filterTag.value !== 'all') count++
  if (departmentFilter.value !== 'all') count++
  return count
})

const clearFilters = () => {
  filterAuthor.value = 'all'
  filterPinned.value = 'all'
  filterTag.value = 'all'
  departmentFilter.value = 'all'
}

watch(isFilterOpen, (isOpen) => {
  if (!isOpen) {
    requestAnimationFrame(() => {
      const inputElement = searchInputRef.value as HTMLInputElement
      if (inputElement && typeof inputElement.focus === 'function') {
        inputElement.focus()
      }
    })
  }
})

const authors = computed<string[]>(() =>
  Array.from(
    new Set(
      props.notes
        .map(note => note.author?.name)
        .filter((name): name is string => typeof name === 'string')
    )
  )
)

const handleSelectNote = (note: SharedNoteModel) => {
  selectedNote.value = note
}

const handleCreateNote = () => {
  isCreateDialogOpen.value = true
}

const handleSaveNote = () => {
  if (!selectedNote.value) return
  
  isSaving.value = true
  saveMessage.value = ''
  
  // datetime-localの値をdateとtimeに分割
  let deadlineDate: string | null = null
  let deadlineTime: string | null = null
  if (editedDeadline.value) {
    const [date, time] = editedDeadline.value.split('T')
    deadlineDate = date
    deadlineTime = time
  }
  
  const updateData = {
    title: editedTitle.value,
    content: editedContent.value,
    deadline: editedDeadline.value || null,
    priority: editedPriority.value,
    color: editedColor.value,
    tags: editedTags.value,
    participants: editedParticipants.value.map(p => p.id),
    progress: editedProgress.value,
    visibility_type: editedVisibility.value
  }
  
  // note_idが0の場合は新規作成
  if (selectedNote.value.note_id === 0) {
    router.post(route('shared-notes.store'), updateData, {
      preserveScroll: true,
      onSuccess: () => {
        showMessage('メモが作成されました。', 'success')
        window.dispatchEvent(new CustomEvent('notification-updated'))
      },
      onError: () => {
        showMessage('作成に失敗しました。', 'success')
      },
      onFinish: () => {
        isSaving.value = false
      }
    })
  } else {
    // 更新の場合
    router.put(route('shared-notes.update', selectedNote.value.note_id), updateData, {
      preserveScroll: true,
      onSuccess: () => {
        showMessage('メモが保存されました。', 'success')
        window.dispatchEvent(new CustomEvent('notification-updated'))
      },
      onError: () => {
        showMessage('保存に失敗しました。', 'success')
      },
      onFinish: () => {
        isSaving.value = false
      }
    })
  }
}

const fetchLinkedEvent = async (eventId: number) => {
  try {
    const response = await fetch(`/api/events/${eventId}`)
    if (response.ok) {
      linkedEvent.value = await response.json()
    }
  } catch (error) {
    console.error('Failed to fetch linked event:', error)
  }
}

const openEventDialog = () => {
  if (linkedEvent.value) {
    isEventDialogOpen.value = true
  }
}

// 削除処理
const handleDeleteNote = () => {
  if (!selectedNote.value) return
  
  // note_idが0の場合は新規作成モードなので削除できない
  if (selectedNote.value.note_id === 0) {
    selectedNote.value = null
    return
  }
  
  const currentIndex = selectedNote.value ? filteredNotes.value.findIndex(note => note.note_id === selectedNote.value?.note_id) : -1
  const nextNote = filteredNotes.value[currentIndex + 1] || filteredNotes.value[currentIndex - 1] || null
  
  lastDeletedNote.value = selectedNote.value;
  
  router.delete(route('notes.destroy', selectedNote.value.note_id), {
    preserveScroll: true,
    preserveState: true,
    onSuccess: () => {
      showMessage('メモを削除しました。', 'delete')
      selectedNote.value = nextNote
      window.dispatchEvent(new CustomEvent('notification-updated'))
    },
    onError: () => {
      lastDeletedNote.value = null
      showMessage('メモの削除に失敗しました。', 'success')
    }
  })
}



// Undo処理
const handleUndoDelete = () => {
  if (!lastDeletedNote.value) return;

  if (messageTimer.value) {
    clearTimeout(messageTimer.value);
  }
  saveMessage.value = '元に戻しています...'
  
  const noteToRestore = lastDeletedNote.value
  lastDeletedNote.value = null

  router.post(route('notes.restore', noteToRestore.note_id), {}, {
    preserveScroll: true,
    preserveState: true,
    onSuccess: () => {
      showMessage('メモが元に戻されました。', 'success')
      window.dispatchEvent(new CustomEvent('notification-updated'))
      selectedNote.value = noteToRestore
      setTimeout(() => {
        scrollToNote(noteToRestore.note_id.toString())
      }, 100)
    },
    onError: () => {
      showMessage('元に戻す処理に失敗しました。', 'success')
    }
  })
}

const getColorClass = (color: string) => {
  const colorMap: Record<string, string> = {
    yellow: 'bg-yellow-50 border-yellow-300 hover:bg-yellow-100 dark:bg-card dark:border-yellow-600',
    blue: 'bg-blue-50 border-blue-300 hover:bg-blue-100 dark:bg-card dark:border-blue-600',
    green: 'bg-green-50 border-green-300 hover:bg-green-100 dark:bg-card dark:border-green-600',
    pink: 'bg-pink-50 border-pink-300 hover:bg-pink-100 dark:bg-card dark:border-pink-600',
    purple: 'bg-purple-50 border-purple-300 hover:bg-purple-100 dark:bg-card dark:border-purple-600',
    gray: 'bg-gray-50 border-gray-300 hover:bg-gray-100 dark:bg-card dark:border-gray-600',
  }
  return colorMap[color] || 'bg-gray-50 border-gray-300 hover:bg-gray-100 dark:bg-card dark:border-gray-600'
}

const isOverdue = (deadlineDate: string | null, deadlineTime: string | null) => {
  if (!deadlineDate) return false
  const now = new Date()
  const deadline = new Date(deadlineDate)
  if (deadlineTime) {
    const [hours, minutes] = deadlineTime.split(':')
    deadline.setHours(parseInt(hours), parseInt(minutes))
  } else {
    deadline.setHours(23, 59, 59)
  }
  return deadline < now
}

const scrollToNote = (noteId: string) => {
  window.setTimeout(() => {
    const element = document.querySelector(`[data-note-id="${noteId}"]`)
    if (element) {
      element.scrollIntoView({ behavior: 'smooth', block: 'center' })
    }
  }, 100)
}

const togglePin = (note: SharedNoteModel) => {
    const noteId = note.note_id
    if (note.is_pinned) {
        router.delete(route('notes.unpin', noteId), {
            preserveScroll: true,
            onSuccess: () => {
                scrollToNote(String(noteId))
                window.dispatchEvent(new CustomEvent('notification-updated'))
            }
        });
    } else {
        router.post(route('notes.pin', noteId), {}, {
            preserveScroll: true,
            onSuccess: () => {
                scrollToNote(String(noteId))
                window.dispatchEvent(new CustomEvent('notification-updated'))
            }
        });
    }
};

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

const getColorInfo = (c: string) => {
  const colorMap: Record<string, { bg: string; label: string; color: string }> = {
    blue: { bg: 'bg-blue-100', label: CATEGORY_LABELS.value['会議'] || '会議', color: CATEGORY_COLORS['会議'] },
    green: { bg: 'bg-green-100', label: CATEGORY_LABELS.value['業務'] || '業務', color: CATEGORY_COLORS['業務'] },
    yellow: { bg: 'bg-yellow-100', label: CATEGORY_LABELS.value['来客'] || '来客', color: CATEGORY_COLORS['来客'] },
    purple: { bg: 'bg-purple-100', label: CATEGORY_LABELS.value['出張・外出'] || '出張・外出', color: CATEGORY_COLORS['出張・外出'] },
    pink: { bg: 'bg-pink-100', label: CATEGORY_LABELS.value['休暇'] || '休暇', color: CATEGORY_COLORS['休暇'] },
    gray: { bg: 'bg-gray-100', label: CATEGORY_LABELS.value['その他'] || 'その他', color: CATEGORY_COLORS['その他'] },
  }
  return colorMap[c] || colorMap.yellow
}

const handleCopyNote = () => {
  if (!selectedNote.value) return
  
  const copyData = {
    title: `${selectedNote.value.title}（コピー）`,
    content: selectedNote.value.content || '',
    color: selectedNote.value.color || 'yellow',
    priority: selectedNote.value.priority || 'medium',
    deadline_date: selectedNote.value.deadline_date,
    deadline_time: selectedNote.value.deadline_time,
    tags: selectedNote.value.tags.map(tag => tag.tag_name),
    participants: selectedNote.value.participants || [],
    progress: selectedNote.value.progress ?? 0,
    visibility_type: selectedNote.value.visibility_type || 'public',
  }
  
  sessionStorage.setItem('note_copy_data', JSON.stringify(copyData))
  isCreateDialogOpen.value = true
}

const suggestedTags = computed(() => {
  if (!tagInput.value.trim()) {
    return props.allTags.filter(tag => !editedTags.value.includes(tag))
  }
  return props.allTags.filter(tag => 
    tag.toLowerCase().includes(tagInput.value.toLowerCase()) && 
    !editedTags.value.includes(tag)
  )
})

const handleAddTag = (tag?: string) => {
  const tagToAdd = tag || tagInput.value.trim()
  if (tagToAdd && !editedTags.value.includes(tagToAdd)) {
    editedTags.value.push(tagToAdd)
    tagInput.value = ''
    showTagSuggestions.value = false
  }
}

const handleTagInputChange = () => {
  if (tagDebounceTimer.value) {
    clearTimeout(tagDebounceTimer.value)
  }
  tagDebounceTimer.value = window.setTimeout(() => {
    showTagSuggestions.value = true
  }, 300)
}

const handleRemoveTag = (tagToRemove: string) => {
  editedTags.value = editedTags.value.filter(tag => tag !== tagToRemove)
}

const handleAddParticipant = (memberId: unknown) => {
  if (memberId === null || memberId === undefined) return
  const id = Number(memberId as any)
  if (Number.isNaN(id)) return
  const member = props.teamMembers.find((m) => m.id === id)
  if (member && !editedParticipants.value.find((p) => p.id === member.id)) {
    editedParticipants.value.push(member)
  }
  // Selectの値をクリア
  participantSelectValue.value = null
}

const handleTagBlur = () => {
  setTimeout(() => {
    showTagSuggestions.value = false
  }, 200)
}

const handleRemoveParticipant = (participantId: number) => {
  editedParticipants.value = editedParticipants.value.filter((p) => p.id !== participantId)
}

const isHelpOpen = ref(false)

const formatDate = (date: Date) => {
  if (!date) return ''
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  const hours = String(date.getHours()).padStart(2, '0')
  const minutes = String(date.getMinutes()).padStart(2, '0')
  return `${year}/${month}/${day} ${hours}:${minutes}`
}

</script>

<template>
  <Head title="共有メモ" />
  <div class="flex gap-6 mx-auto h-full p-6">
    <Card class="flex-1 flex h-full overflow-hidden">
      <div class="w-full md:w-96 lg:w-[420px] flex flex-col h-full overflow-hidden border-r border-border bg-background">
      <div class="p-4 border-b border-border">
        <div class="flex items-center justify-between mb-4">
          <div class="flex items-center gap-2 min-w-0 flex-1">
            <Button variant="ghost" size="icon" @click="router.get(route('dashboard'))" class="mr-1 flex-shrink-0">
              <ArrowLeft class="h-5 w-5" />
            </Button>
            <StickyNote class="h-6 w-6 text-orange-600 flex-shrink-0" />
            <CardTitle class="flex items-center gap-2 min-w-0">
              <span class="truncate">共有メモ</span>
              <Button
                variant="ghost"
                size="icon"
                class="h-5 w-5 p-0 text-gray-500 hover:text-gray-700 dark:hover:text-gray-300 flex-shrink-0"
                @click="isHelpOpen = true"
                title="共有メモの使い方"
              >
                <HelpCircle class="h-5 w-5" />
              </Button>
            </CardTitle>
          </div>
          <Button variant="outline" @click="handleCreateNote" class="gap-2 flex-shrink-0">
            <Plus class="h-4 w-4" />
            <span class="hidden sm:inline">新規作成</span>
          </Button>
        </div>

        <div class="flex gap-2 mb-3">
          <div class="relative flex-1">
            <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400 pointer-events-none" />
            <input
              ref="searchInputRef"
              placeholder="タイトルなどで検索"
              v-model="searchQuery"
              class="pl-9 pr-9 flex h-10 w-full rounded-md border border-gray-300 dark:border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
            />
            <button v-if="searchQuery" @click="searchQuery = ''" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
              <X class="h-4 w-4" />
            </button>
          </div>
          
          <Popover v-model:open="isFilterOpen">
            <PopoverTrigger as-child>
              <Button variant="outline" size="icon" class="relative border-gray-300 dark:border-input">
                <Filter class="h-5 w-5" />
                <Badge v-if="activeFilterCount > 0" class="absolute -top-1 -right-1 h-5 w-5 flex items-center justify-center p-0 bg-blue-500 text-white text-xs">
                  {{ activeFilterCount }}
                </Badge>
              </Button>
            </PopoverTrigger>
            <PopoverContent class="w-64 z-[45] max-h-[80vh] flex flex-col" align="end">
              <div class="flex items-center justify-between px-4 pb-2 border-b border-border flex-shrink-0">
                <h4 class="font-medium text-sm">フィルター</h4>
                <div class="flex gap-1">
                  <Button
                    v-if="activeFilterCount > 0"
                    variant="ghost"
                    size="sm"
                    class="h-6 px-2 text-xs hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-gray-100"
                    @click="clearFilters"
                  >
                    <X class="h-3 w-3 mr-1" />
                    クリア
                  </Button>
                  <Button
                    variant="ghost"
                    size="sm"
                    class="h-6 px-2 text-xs hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-gray-100"
                    @click="isFilterOpen = false"
                  >
                    閉じる
                  </Button>
                </div>
              </div>
              <div class="flex-1 overflow-y-auto p-2">
                <div class="space-y-3">
                  <div class="space-y-2">
                    <Label class="text-xs font-medium text-foreground">作成者</Label>
                    <Select v-model="filterAuthor">
                      <SelectTrigger class="h-8">
                        <SelectValue placeholder="すべての作成者" />
                      </SelectTrigger>
                      <SelectContent class="z-[46]">
                        <SelectItem value="all">すべて</SelectItem>
                        <SelectItem v-for="author in authors" :key="author" :value="author">
                          {{ author }}
                        </SelectItem>
                      </SelectContent>
                    </Select>
                  </div>
                  
                  <div class="space-y-2">
                    <Label class="text-xs font-medium text-foreground">メモの種類</Label>
                    <Select v-model="filterPinned">
                      <SelectTrigger class="h-8">
                        <SelectValue placeholder="すべてのメモ" />
                      </SelectTrigger>
                      <SelectContent class="z-[46]">
                        <SelectItem value="all">すべて</SelectItem>
                        <SelectItem value="pinned">ピン留めのみ</SelectItem>
                        <SelectItem value="unpinned">通常メモのみ</SelectItem>
                      </SelectContent>
                    </Select>
                  </div>
                  
                  <div class="space-y-2">
                    <Label class="text-xs font-medium text-foreground">タグ</Label>
                    <Select v-model="filterTag">
                      <SelectTrigger class="h-8">
                        <SelectValue placeholder="すべてのタグ" />
                      </SelectTrigger>
                      <SelectContent class="z-[46]">
                        <SelectItem value="all">すべて</SelectItem>
                        <SelectItem v-for="tag in props.allTags" :key="tag" :value="tag">
                          {{ tag }}
                        </SelectItem>
                      </SelectContent>
                    </Select>
                  </div>
                  
                  <div class="space-y-2">
                    <Label class="text-xs font-medium text-foreground">部署</Label>
                    <Select v-model="departmentFilter">
                      <SelectTrigger class="h-8">
                        <SelectValue placeholder="すべて" />
                      </SelectTrigger>
                      <SelectContent class="z-[46] max-h-48 overflow-y-auto">
                        <SelectItem value="all">すべて</SelectItem>
                        <SelectItem value="public">🌐 全社公開のみ</SelectItem>
                        <div class="px-2 py-1.5 text-xs font-semibold text-gray-500 bg-gray-50 dark:bg-gray-800 border-t border-b">部署メモ</div>
                        <SelectItem v-for="dept in props.departments" :key="dept.id" :value="`dept_${dept.id}`">
                          {{ dept.name }} {{ props.userDepartmentId === dept.id ? '(自部署)' : '' }}
                        </SelectItem>
                      </SelectContent>
                    </Select>
                  </div>
                </div>
              </div>
            </PopoverContent>
          </Popover>
        </div>

        <div class="space-y-2">
          <div class="flex gap-1 p-1 bg-gray-100 dark:bg-gray-800 rounded-lg">
            <button
              :class="['flex items-center justify-center gap-1.5 py-1 px-3 rounded text-xs transition-all duration-300 ease-in-out whitespace-nowrap', sortKey === 'updated_at' ? 'bg-white dark:bg-gray-700 shadow-sm text-gray-900 dark:text-gray-100' : 'hover:bg-gray-200 dark:hover:bg-gray-700 text-gray-500 dark:text-gray-400']"
              @click="sortKey === 'updated_at' ? (sortOrder = sortOrder === 'desc' ? 'asc' : 'desc') : (sortKey = 'updated_at', sortOrder = 'desc')"
              class="flex-1 h-8 font-medium"
            >
              <Clock :class="['h-3.5 w-3.5', sortKey === 'updated_at' ? 'text-yellow-500' : 'text-gray-400']" />
              <span v-if="sortKey === 'updated_at' || filteredNotes.length > 0">更新日時順</span>
              <component 
                :is="sortOrder === 'desc' ? ArrowDown : ArrowUp" 
                v-if="sortKey === 'updated_at'" 
                class="h-3 w-3" 
              />
            </button>
            <button
              :class="['flex items-center justify-center gap-1.5 py-1 px-3 rounded text-xs transition-all duration-300 ease-in-out whitespace-nowrap', sortKey === 'priority' ? 'bg-white dark:bg-gray-700 shadow-sm text-gray-900 dark:text-gray-100' : 'hover:bg-gray-200 dark:hover:bg-gray-700 text-gray-500 dark:text-gray-400']"
              @click="sortKey === 'priority' ? (sortOrder = sortOrder === 'desc' ? 'asc' : 'desc') : (sortKey = 'priority', sortOrder = 'desc')"
              class="flex-1 h-8 font-medium"
            >
              <AlertCircle :class="['h-3.5 w-3.5', sortKey === 'priority' ? 'text-red-500' : 'text-gray-400']" />
              <span v-if="sortKey === 'priority' || filteredNotes.length > 0">重要度順</span>
              <component 
                :is="sortOrder === 'desc' ? ArrowDown : ArrowUp" 
                v-if="sortKey === 'priority'" 
                class="h-3 w-3" 
              />
            </button>
            <button
              :class="['flex items-center justify-center gap-1.5 py-1 px-3 rounded text-xs transition-all duration-300 ease-in-out whitespace-nowrap', sortKey === 'deadline' ? 'bg-white dark:bg-gray-700 shadow-sm text-gray-900 dark:text-gray-100' : 'hover:bg-gray-200 dark:hover:bg-gray-700 text-gray-500 dark:text-gray-400']"
              @click="sortKey === 'deadline' ? (sortOrder = sortOrder === 'desc' ? 'asc' : 'desc') : (sortKey = 'deadline', sortOrder = 'desc')"
              class="flex-1 h-8 font-medium"
            >
              <Calendar :class="['h-3.5 w-3.5', sortKey === 'deadline' ? 'text-blue-500' : 'text-gray-400']" />
              <span v-if="sortKey === 'deadline' || filteredNotes.length > 0">期限順</span>
              <component 
                :is="sortOrder === 'desc' ? ArrowDown : ArrowUp" 
                v-if="sortKey === 'deadline'" 
                class="h-3 w-3" 
              />
            </button>
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
            :data-note-id="note.note_id"
            @click="handleSelectNote(note)"
            :class="[
              'cursor-pointer transition-all border-l-4', 
              selectedNote?.note_id === note.note_id ? 'ring-2 ring-primary shadow-md' : 'hover:shadow-md', 
              isOverdue(note.deadline_date, note.deadline_time) 
                ? 'bg-muted border-muted-foreground/50 hover:bg-muted/80 dark:bg-card dark:border-gray-500' 
                : getColorClass(note.color)
            ]"
          >
            <div class="p-4">
              <div class="flex items-start gap-3 mb-2">
                <div class="flex-1 flex items-start justify-between">
                  <h3 class="flex-1 flex items-center gap-2 pr-2">
                    <Pin v-if="note.is_pinned" class="h-4 w-4 text-yellow-500 fill-current flex-shrink-0" />
                    <span class="line-clamp-1">{{ note.title }}</span>
                  </h3>
                  <Badge :class="[getPriorityInfo(note.priority as Priority).className, 'text-xs px-2 py-0.5 flex-shrink-0']">
                    {{ getPriorityInfo(note.priority as Priority).label }}
                  </Badge>
                </div>
              </div>
              <p class="text-sm text-gray-600 mb-3 line-clamp-2 whitespace-pre-line">{{ note.content }}</p>
              <div v-if="note.tags.length > 0" class="flex flex-wrap gap-1 mb-3">
                <Badge v-for="(tag, index) in note.tags" :key="index" variant="secondary" class="text-xs">
                  {{ tag.tag_name }}
                </Badge>
              </div>
              <div class="flex items-center justify-between text-xs text-gray-500">
                <div class="flex items-center gap-2">
                  <div class="flex items-center gap-1">
                    <User class="h-3 w-3" />
                    <span>{{ note.author?.name }}</span>
                  </div>
                  <div v-if="note.participants && note.participants.length > 0" class="flex items-center gap-1">
                    <Badge v-if="isAllUsers(note.participants)" variant="outline" class="text-xs text-blue-600 border-blue-300">
                      全員
                    </Badge>
                    <template v-else>
                      <Badge v-for="participant in (note.participants || []).slice(0, 2)" :key="participant.id" variant="outline" class="text-xs text-blue-600 border-blue-300">
                        {{ participant.name }}
                      </Badge>
                      <Badge v-if="(note.participants?.length || 0) > 2" variant="outline" class="text-xs text-blue-600 border-blue-300">
                        +{{ (note.participants?.length || 0) - 2 }}
                      </Badge>
                    </template>
                  </div>
                  <Badge v-if="isOverdue(note.deadline_date, note.deadline_time)" variant="outline" class="text-xs bg-gray-200 text-gray-700 border-gray-400">
                    期限切れ
                  </Badge>
                </div>
                <div class="flex items-center gap-1">
                  <Clock class="h-3 w-3" />
                  <span>{{ note.updated_at ? new Date(note.updated_at).toLocaleString('ja-JP') : '' }}</span>
                </div>
              </div>
            </div>
          </Card>
        </div>
      </ScrollArea>
      </div>

      <div class="flex-1 flex flex-col relative h-full overflow-hidden">
      <template v-if="selectedNote">
        <div class="flex-1 overflow-y-auto">
          <div class="flex flex-col min-h-full">
            <div class="flex-shrink-0 p-3 border-b border-border bg-background">
          <div class="flex items-start justify-between mb-3">
            <div class="flex-1">
              <Input
                v-model="editedTitle"
                placeholder="メモのタイトル"
                class="mb-2 border-none shadow-none p-0 focus-visible:ring-0 text-xl font-bold"
              />
              <div class="flex items-center gap-3 text-xs text-gray-500">
                <div class="flex items-center gap-1">
                  <User class="h-3 w-3" />
                  <span>{{ selectedNote.author?.name }}</span>
                </div>
                <!-- 作成日 -->
                <div v-if="selectedNote.created_at" class="flex items-center gap-1">
                  <Calendar class="h-3 w-3" />
                  <span>作成日：{{ new Date(selectedNote.created_at).toLocaleDateString('ja-JP', { year: 'numeric', month: '2-digit', day: '2-digit' }).replace(/\//g, '-') }}</span>
                </div>
                <!-- 編集日（作成日と異なる場合のみ） -->
                <div v-if="selectedNote.updated_at && selectedNote.created_at && new Date(selectedNote.updated_at).toDateString() !== new Date(selectedNote.created_at).toDateString()" class="flex items-center gap-1">
                  <Clock class="h-3 w-3" />
                  <span>編集日：{{ new Date(selectedNote.updated_at).toLocaleString('ja-JP', { year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' }).replace(/\//g, '-') }}</span>
                </div>
                <!-- 期限（設定されている場合のみBadgeで表示） -->
                <div>
                <label class="text-xs font-medium text-gray-700 dark:text-gray-300 mb-1 block">期限</label>
                <VueDatePicker
                  v-model="deadlineDateTime"
                  :locale="ja"
                  :format="formatDate"
                  :week-start="0"
                  auto-apply
                  teleport-center
                  enable-time-picker
                  placeholder="期限を選択"
                  class="h-8"
                />
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
          <div class="space-y-2 mb-3">
            <div class="grid grid-cols-4 gap-2">
              <!-- 公開範囲選択 -->
          <div class="space-y-2 mb-3">
            <label class="text-xs font-medium text-gray-700 dark:text-gray-300 block">公開範囲</label>
            <Select v-model="editedVisibility">
              <SelectTrigger class="h-8 text-xs border-gray-300 dark:border-input bg-white dark:bg-gray-800 w-full max-w-sm">
                <SelectValue placeholder="公開範囲を選択" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="public">🌐 全社公開</SelectItem>
                <SelectItem value="department">🏢 自部署のみ</SelectItem>
                <SelectItem value="custom">👥 一部ユーザーのみ（共有メンバー）</SelectItem>
              </SelectContent>
            </Select>
          </div>
              <div>
                <label class="text-xs font-medium text-gray-700 dark:text-gray-300 mb-1 block">重要度</label>
                <Select v-model="editedPriority">
                  <SelectTrigger class="h-8 text-xs border-gray-300 dark:border-input">
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
                <label class="text-xs font-medium text-gray-700 dark:text-gray-300 mb-1 block">ジャンル</label>
                <Select v-model="editedColor">
                  <SelectTrigger class="h-8 text-xs border-gray-300 dark:border-input">
                    <div class="flex items-center gap-2">
                      <div class="w-3 h-3 rounded-full" :style="{ backgroundColor: getColorInfo(editedColor).color }"></div>
                      <span>{{ getColorInfo(editedColor).label }}</span>
                    </div>
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem v-for="c in ['blue', 'green', 'yellow', 'purple', 'pink', 'gray']" :key="c" :value="c">
                      <div class="flex items-center gap-2">
                        <div class="w-3 h-3 rounded-full" :style="{ backgroundColor: getColorInfo(c).color }"></div>
                        <span>{{ getColorInfo(c).label }}</span>
                      </div>
                    </SelectItem>
                  </SelectContent>
                </Select>
              </div>
              <div class="relative">
                <label class="text-xs font-medium text-gray-700 dark:text-gray-300 mb-1 block">タグ</label>
                <div class="flex gap-1">
                  <Input
                    placeholder="タグを追加"
                    v-model="tagInput"
                    @input="handleTagInputChange"
                    @focus="showTagSuggestions = true"
                    @blur="handleTagBlur"
                    @keypress.enter.prevent="handleAddTag()"
                    class="h-8 text-xs flex-1"
                  />
                  <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    @click="handleAddTag()"
                    class="h-8 px-2 text-xs"
                  >
                    追加
                  </Button>
                </div>
                <div v-if="showTagSuggestions && suggestedTags.length > 0" class="absolute z-10 w-full mt-1 bg-white dark:bg-gray-800 border dark:border-gray-700 rounded-md shadow-lg max-h-40 overflow-y-auto">
                  <button
                    v-for="tag in suggestedTags"
                    :key="tag"
                    @click="handleAddTag(tag)"
                    class="w-full text-left px-3 py-2 text-xs hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors text-gray-900 dark:text-gray-100"
                  >
                    {{ tag }}
                  </button>
                </div>
              </div>
            </div>
            
            <!-- 進捗バー（休暇以外） -->
            <div v-if="editedColor !== 'pink'" class="mt-2">
              <label class="text-xs font-medium text-gray-700 dark:text-gray-300 mb-1 flex items-center gap-2">
                <span>進捗</span>
                <span class="text-xs text-gray-600 dark:text-gray-400">{{ editedProgress }}%</span>
              </label>
              <div class="relative w-full h-4 flex items-center">
                <div 
                  class="absolute w-full h-2 rounded-lg overflow-hidden pointer-events-none"
                  :style="{ background: `linear-gradient(to right, #3b82f6 0%, #3b82f6 ${editedProgress}%, #e5e7eb ${editedProgress}%, #e5e7eb 100%)` }"
                ></div>
                <input 
                  type="range" 
                  v-model="editedProgress" 
                  min="0" 
                  max="100" 
                  class="relative w-full h-2 bg-transparent rounded-lg appearance-none cursor-pointer slider m-0 z-10 focus:outline-none"
                />
              </div>
            </div>
            
            <!-- リンクされたカレンダー情報 -->
            <div v-if="selectedNote?.linked_event_id && linkedEvent" class="mt-2 p-2 bg-blue-50 dark:bg-blue-950/30 rounded border border-blue-200 dark:border-blue-800">
              <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                  <Calendar class="h-4 w-4 text-blue-600" />
                  <span class="text-xs font-medium text-blue-800 dark:text-blue-200">共有カレンダーとリンク</span>
                </div>
                <Button 
                  variant="ghost" 
                  size="sm" 
                  class="h-6 px-2 text-xs gap-1"
                  @click="openEventDialog"
                >
                  <ExternalLink class="h-3 w-3" />
                  詳細を表示
                </Button>
              </div>
              <p class="text-xs text-gray-600 dark:text-gray-400 mt-1">{{ linkedEvent.title }}</p>
            </div>
          </div>
          
          <div v-if="editedTags.length > 0" class="flex flex-wrap gap-1 mb-2">
            <Badge v-for="tag in editedTags" :key="tag" variant="secondary" class="text-xs gap-1">
              {{ tag }}
              <button @click="handleRemoveTag(tag)" class="hover:bg-gray-300 rounded-full p-0.5">
                <X class="h-2 w-2" />
              </button>
            </Badge>
          </div>
          
          

          <!-- メンバー追加UI -->
          <div class="space-y-2">
            <label class="text-xs font-medium text-gray-700 dark:text-gray-300 block">共有メンバー</label>
            <template v-if="!canEditParticipants">
              <div class="text-xs text-muted-foreground p-2 bg-muted/50 rounded border border-border">
                共有メンバーの変更は作成者または参加者のみ可能です
              </div>
            </template>
            <template v-else-if="isAllUsers(editedParticipants) && selectedNote?.author?.id !== currentUserId">
              <div class="text-xs text-muted-foreground p-2 bg-muted/50 rounded border border-border">
                全員共有のメモは作成者のみが共有設定を変更できます
              </div>
            </template>
            <template v-else-if="editedVisibility === 'public'">
              <div class="text-xs text-blue-600 p-2 bg-blue-50 dark:bg-blue-900/20 dark:text-blue-200 rounded border border-blue-200 dark:border-blue-800">
                🌐 全社公開の場合、共有メンバーの選択は不要です
              </div>
            </template>
            <template v-else>
              <div v-if="editedParticipants.length === props.totalUsers" class="text-xs text-blue-600 p-2 bg-blue-50 dark:bg-blue-900/20 dark:text-blue-200 rounded border border-blue-200 dark:border-blue-800">
                全員が選択されています。変更するにはメンバーを削除してください。
              </div>
              <div v-else class="max-h-[200px] overflow-y-auto border border-input rounded p-2 space-y-1">
                <template v-if="editedVisibility === 'department'">
                  <label v-for="member in props.teamMembers.filter(m => m.id !== selectedNote?.author?.id && (m as any).department_id === ($page.props as any).auth?.user?.department_id)" :key="member.id" class="flex items-center gap-2 p-1 hover:bg-muted rounded cursor-pointer">
                    <input 
                      type="checkbox" 
                      :checked="editedParticipants.find(p => p.id === member.id) !== undefined"
                      @change="(e) => (e.target as HTMLInputElement).checked ? handleAddParticipant(member.id) : handleRemoveParticipant(member.id)"
                      class="h-4 w-4 text-blue-600 rounded border-gray-300 dark:border-gray-600 dark:bg-gray-800"
                    />
                    <span class="text-xs text-gray-900 dark:text-gray-100">{{ member.name }}</span>
                  </label>
                </template>
                <template v-else>
                  <label v-for="member in props.teamMembers.filter(m => m.id !== selectedNote?.author?.id)" :key="member.id" class="flex items-center gap-2 p-1 hover:bg-muted rounded cursor-pointer">
                    <input 
                      type="checkbox" 
                      :checked="editedParticipants.find(p => p.id === member.id) !== undefined"
                      @change="(e) => (e.target as HTMLInputElement).checked ? handleAddParticipant(member.id) : handleRemoveParticipant(member.id)"
                      class="h-4 w-4 text-blue-600 rounded border-gray-300 dark:border-gray-600 dark:bg-gray-800"
                    />
                    <span class="text-xs text-gray-900 dark:text-gray-100">{{ member.name }}</span>
                  </label>
                </template>
              </div>
            </template>
            <div v-if="editedParticipants.length > 0 && editedVisibility !== 'public'" class="flex flex-wrap gap-1 mt-2">
              <Badge v-for="participant in editedParticipants" :key="participant.id" variant="secondary" class="text-xs gap-1">
                {{ participant.name }}
                <button v-if="canEditParticipants && !(isAllUsers(editedParticipants) && selectedNote?.author?.id !== currentUserId)" @click="handleRemoveParticipant(participant.id)" class="hover:bg-muted-foreground/20 rounded-full p-0.5">
                  <X class="h-2 w-2" />
                </button>
              </Badge>
            </div>
          </div>
            </div>
            <div class="flex-1 p-3 flex flex-col min-h-[300px]">
              <Textarea
                v-model="editedContent"
                placeholder="メモの内容を入力..."
                class="flex-1 w-full resize-none border-none shadow-none focus-visible:ring-0 p-0 leading-relaxed"
              />
            </div>
          </div>
        </div>
        <!-- 固定ボタンエリア -->
        <div class="flex-shrink-0 px-5 py-4 border-t border-border bg-background">
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
              <Button variant="outline" class="gap-2" @click="handleDeleteNote">
                <Trash2 class="h-4 w-4" />
                削除
              </Button>
              <Button variant="outline" class="gap-2" @click="handleCopyNote">
                <Copy class="h-4 w-4" />
                複製
              </Button>
            </div>
            <Button variant="outline" class="gap-2" @click="handleSaveNote" :disabled="isSaving">
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
    </Card>
    <CreateNoteDialog :open="isCreateDialogOpen" @update:open="isCreateDialogOpen = $event" :team-members="(props.teamMembers as any[])" />
    
    <!-- 下部メッセージ -->
    <Transition
      enter-active-class="transition ease-out duration-300"
      enter-from-class="transform opacity-0 translate-y-full"
      enter-to-class="transform opacity-100 translate-y-0"
      leave-active-class="transition ease-in duration-200"
      leave-from-class="transform opacity-100 translate-y-0"
      leave-to-class="transform opacity-0 translate-y-full"
    >
      <div 
        v-if="saveMessage"
        :class="['absolute bottom-4 left-1/2 transform -translate-x-1/2 z-20 p-3 text-white rounded-lg shadow-lg',
          messageType === 'success' ? 'bg-green-500' : 'bg-red-500']"
      >
        <div class="flex items-center gap-2">
          <CheckCircle class="h-5 w-5" />
          <span class="font-medium">{{ saveMessage }}</span>
          <Button 
            v-if="messageType === 'delete' && lastDeletedNote"
            variant="link"
            :class="messageType === 'delete' ? 'text-white hover:bg-red-400 p-1 h-auto ml-2' : 'text-white hover:bg-green-400 p-1 h-auto ml-2'"
            @click.stop="handleUndoDelete"
          >
            <Undo2 class="h-4 w-4 mr-1" />
            <span class="underline">元に戻す</span>
          </Button>
        </div>
      </div>
    </Transition>
  </div>

  <!-- イベント詳細ダイアログ -->
  <CreateEventDialog
    v-if="linkedEvent"
    :event="linkedEvent"
    :open="isEventDialogOpen"
    @update:open="isEventDialogOpen = $event"
  />

  <!-- ヘルプダイアログ -->
  <Dialog :open="isHelpOpen" @update:open="isHelpOpen = $event">
    <DialogContent class="max-w-3xl max-h-[90vh] flex flex-col">
      <DialogHeader>
        <DialogTitle>共有メモの使い方</DialogTitle>
      </DialogHeader>
      <div class="space-y-6 overflow-y-auto flex-1 pr-2">
        <!-- 基本操作 -->
        <div class="relative pl-4 border-l-4 border-blue-500 bg-gradient-to-r from-blue-50 to-transparent dark:from-blue-950/30 p-4 rounded-r-lg">
          <h3 class="font-semibold mb-3 text-lg">📝 基本操作</h3>
          <div class="space-y-4">
            <div class="bg-white dark:bg-gray-800 p-3 rounded-lg shadow-sm">
              <div class="flex items-start gap-4">
                <div class="flex-shrink-0 pt-1 w-32">
                  <Button variant="outline" class="gap-2 pointer-events-none opacity-100 w-full" tabindex="-1">
                    <Plus class="h-4 w-4" />
                    新規作成
                  </Button>
                </div>
                <div class="flex-1">
                  <p class="font-medium text-sm mb-1">メモ作成</p>
                  <p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed">新しい共有メモを作成します。タイトル、内容、期限、優先度、色、タグ、参加者を設定できます。</p>
                </div>
              </div>
            </div>
            <div class="bg-white dark:bg-gray-800 p-3 rounded-lg shadow-sm">
              <div class="flex items-start gap-4">
                <div class="flex-shrink-0 pt-1 w-32">
                  <div class="relative pointer-events-none">
                    <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
                    <Input placeholder="検索" class="pl-9 h-9" readonly tabindex="-1" />
                  </div>
                </div>
                <div class="flex-1">
                  <p class="font-medium text-sm mb-1">検索機能</p>
                  <p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed">タイトル、内容、タグ、作成者名でメモを素早く検索できます。</p>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- フィルター -->
        <div class="relative pl-4 border-l-4 border-green-500 bg-gradient-to-r from-green-50 to-transparent dark:from-green-950/30 p-4 rounded-r-lg">
          <h3 class="font-semibold mb-3 text-lg">🔍 フィルター機能</h3>
          <div class="space-y-4">
            <div class="bg-white dark:bg-gray-800 p-3 rounded-lg shadow-sm">
              <div class="flex items-start gap-4">
                <div class="flex-shrink-0 pt-1 w-32">
                  <Button variant="outline" size="icon" class="pointer-events-none opacity-100" tabindex="-1">
                    <Filter class="h-4 w-4" />
                  </Button>
                </div>
                <div class="flex-1">
                  <p class="font-medium text-sm mb-1">絞り込み</p>
                  <p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed">作成者、優先度、色、タグでメモを絞り込めます。複数の条件を組み合わせて使用できます。</p>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- メモ管理 -->
        <div class="relative pl-4 border-l-4 border-purple-500 bg-gradient-to-r from-purple-50 to-transparent dark:from-purple-950/30 p-4 rounded-r-lg">
          <h3 class="font-semibold mb-3 text-lg">📌 メモ管理</h3>
          <div class="space-y-4">
            <div class="bg-white dark:bg-gray-800 p-3 rounded-lg shadow-sm">
              <div class="flex items-start gap-4">
                <div class="flex-shrink-0 pt-1 w-32">
                  <div class="flex gap-2 pointer-events-none">
                    <Badge variant="outline" class="gap-1 opacity-100 bg-red-600 text-white border-red-600">
                      重要
                    </Badge>
                  </div>
                </div>
                <div class="flex-1">
                  <p class="font-medium text-sm mb-1">優先度表示</p>
                  <p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed">重要度に応じて色分けされたバッジが表示されます（重要・中・低）。</p>
                </div>
              </div>
            </div>
            <div class="bg-white dark:bg-gray-800 p-3 rounded-lg shadow-sm">
              <div class="flex items-start gap-4">
                <div class="flex-shrink-0 pt-1 w-32">
                  <Button variant="ghost" size="icon" class="pointer-events-none opacity-100" tabindex="-1">
                    <Pin class="h-4 w-4" />
                  </Button>
                </div>
                <div class="flex-1">
                  <p class="font-medium text-sm mb-1">ピン留め</p>
                  <p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed">重要なメモをリストの上部に固定できます。ピンアイコンをクリックして切り替えます。</p>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- 期限管理 -->
        <div class="relative pl-4 border-l-4 border-orange-500 bg-gradient-to-r from-orange-50 to-transparent dark:from-orange-950/30 p-4 rounded-r-lg">
          <h3 class="font-semibold mb-3 text-lg">⏰ 期限管理</h3>
          <div class="space-y-4">
            <div class="bg-white dark:bg-gray-800 p-3 rounded-lg shadow-sm">
              <div class="flex items-start gap-4">
                <div class="flex-shrink-0 pt-1 w-32">
                  <div class="flex flex-col gap-1 pointer-events-none">
                    <Badge variant="outline" class="text-xs bg-red-100 text-red-700 border-red-400 opacity-100">期限切れ</Badge>
                    <Badge variant="outline" class="text-xs bg-yellow-100 text-yellow-700 border-yellow-400 opacity-100">期限間近</Badge>
                  </div>
                </div>
                <div class="flex-1">
                  <p class="font-medium text-sm mb-1">期限アラート</p>
                  <p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed">期限切れは赤、期限間近（3日以内）は黄色のバッジで表示されます。</p>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- 削除と復元 -->
        <div class="relative pl-4 border-l-4 border-red-500 bg-gradient-to-r from-red-50 to-transparent dark:from-red-950/30 p-4 rounded-r-lg">
          <h3 class="font-semibold mb-3 text-lg">🗑️ 削除と復元</h3>
          <div class="space-y-4">
            <div class="bg-white dark:bg-gray-800 p-3 rounded-lg shadow-sm">
              <div class="flex items-start gap-4">
                <div class="flex-shrink-0 pt-1 w-32">
                  <Button variant="outline" size="sm" class="gap-2 pointer-events-none opacity-100 bg-red-600 text-white border-red-600" tabindex="-1">
                    <Trash2 class="h-4 w-4" />
                    削除
                  </Button>
                </div>
                <div class="flex-1">
                  <p class="font-medium text-sm mb-1">メモの削除</p>
                  <p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed">削除したメモはゴミ箱に移動します。削除後すぐに「元に戻す」で復元できます。</p>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- 運用ルール -->
        <div class="relative pl-4 border-l-4 border-indigo-500 bg-gradient-to-r from-indigo-50 to-transparent dark:from-indigo-950/30 p-4 rounded-r-lg">
          <h3 class="font-semibold mb-3 text-lg">📝 運用ルール</h3>
          <div class="space-y-4">
            <div class="bg-white dark:bg-gray-800 p-3 rounded-lg shadow-sm">
              <p class="font-medium text-sm mb-2">【共有メモの活用方法】</p>
              <ul class="text-sm text-gray-600 dark:text-gray-400 space-y-1">
                <li>・カレンダー予定にリンクすることで、詳細情報を共有できます</li>
                <li>・<span class="font-semibold">特に他部署メンバーが参加する予定には、共有メモのリンクを推奨</span>します</li>
                <li>・会議の議題、資料、事前準備事項などを記載してください</li>
              </ul>
            </div>
            <div class="bg-white dark:bg-gray-800 p-3 rounded-lg shadow-sm">
              <p class="font-medium text-sm mb-2">【公開範囲の設定】</p>
              <ul class="text-sm text-gray-600 dark:text-gray-400 space-y-1">
                <li>・<span class="font-semibold">全社公開</span>：全社員が閲覧可能</li>
                <li>・<span class="font-semibold">自部署のみ</span>：自分の部署のメンバーのみ閲覧可能</li>
                <li>・<span class="font-semibold">一部ユーザーのみ</span>：選択したメンバーのみ閲覧可能</li>
              </ul>
            </div>
          </div>
        </div>
      </div>
      <div class="mt-4 p-3 bg-blue-50 dark:bg-blue-950/30 rounded-lg border border-blue-200 dark:border-blue-800 flex-shrink-0">
        <p class="text-sm text-blue-800 dark:text-blue-200 flex items-center gap-2">
          <span class="text-lg">💡</span>
          <span>メモをクリックすると詳細を表示・編集できます。チーム全員と情報を共有しましょう！</span>
        </p>
      </div>
    </DialogContent>
  </Dialog>
</template>
