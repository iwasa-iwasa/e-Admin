<script setup lang="ts">
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import { Search, Calendar, StickyNote, Clock, BarChart3, Trash2, Filter, X } from 'lucide-vue-next'
import { Input } from '@/components/ui/input'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover'
import { Checkbox } from '@/components/ui/checkbox'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { VueDatePicker } from '@vuepic/vue-datepicker'
import { ja } from 'date-fns/locale'
import '@vuepic/vue-datepicker/dist/main.css'
import CreateEventDialog from '@/components/CreateEventDialog.vue'
import CreateNoteDialog from '@/components/CreateNoteDialog.vue'
import EventDetailDialog from '@/components/EventDetailDialog.vue'
import NoteDetailDialog from '@/components/NoteDetailDialog.vue'
import ReminderDetailDialog from '@/components/ReminderDetailDialog.vue'

interface SearchResult {
    id: number
    type: 'event' | 'note' | 'reminder' | 'survey' | 'trash'
    title: string
    description?: string
    creator: string
    date?: string
    updated_at: string
}

const searchQuery = ref('')
const searchResults = ref<SearchResult[]>([])
const recentItems = ref<SearchResult[]>([])
const isSearching = ref(false)
const isResultsOpen = ref(false)
const isFilterOpen = ref(false)
const recentItemsLoaded = ref(false)
const searchInputRef = ref<HTMLInputElement | null>(null)

const selectedTypes = ref<string[]>(['_all_'])
const searchTimeout = ref<number | null>(null)
const searchField = ref('all')
const creatorName = ref('_all_')
const participantName = ref('_all_')
const dateFrom = ref<string>('')
const dateTo = ref<string>('')
const dateType = ref('updated')
const allUsers = ref<Array<{id: number, name: string}>>([])

const selectedEvent = ref<App.Models.Event | null>(null)
const isEventDetailDialogOpen = ref(false)
const isEventDialogOpen = ref(false)
const selectedNote = ref<App.Models.SharedNote | null>(null)
const isNoteDialogOpen = ref(false)
const isCreateNoteDialogOpen = ref(false)
const selectedReminder = ref<any>(null)
const isReminderDialogOpen = ref(false)

const typeOptions = [
    { value: 'event', label: 'カレンダー', icon: Calendar, color: 'text-blue-600' },
    { value: 'note', label: '共有メモ', icon: StickyNote, color: 'text-orange-600' },
    { value: 'reminder', label: 'リマインダー', icon: Clock, color: 'text-green-600' },
    { value: 'survey', label: 'アンケート', icon: BarChart3, color: 'text-purple-600' },
    { value: 'trash', label: 'ゴミ箱', icon: Trash2, color: 'text-gray-600' },
]

const loadRecentItems = async () => {
    try {
        const response = await fetch('/api/search?q=')
        const data = await response.json()
        recentItems.value = data.recent
        recentItemsLoaded.value = true
    } catch (error) {
        console.error('Recent items error:', error)
    }
}

const performSearch = async () => {
    if (searchQuery.value.length < 2) {
        searchResults.value = []
        return
    }
    
    isSearching.value = true
    try {
        const types = selectedTypes.value.filter(t => t !== '_all_')
        const params = new URLSearchParams()
        params.append('q', searchQuery.value)
        params.append('search_field', searchField.value)
        params.append('creator_name', creatorName.value === '_all_' ? '' : creatorName.value)
        params.append('participant_name', participantName.value === '_all_' ? '' : participantName.value)
        params.append('date_from', dateFrom.value)
        params.append('date_to', dateTo.value)
        params.append('date_type', dateType.value)
        if (types.length > 0) {
            params.append('types', types.join(','))
        }
        
        const response = await fetch(`/api/search?${params}`)
        const data = await response.json()
        
        searchResults.value = data.results
        recentItems.value = data.recent
    } catch (error) {
        console.error('Search error:', error)
    } finally {
        isSearching.value = false
    }
}


const handleNotificationUpdate = () => {
    if (searchQuery.value.length >= 2) {
        performSearch()
    } else {
        recentItemsLoaded.value = false
        loadRecentItems()
    }
}

const handleClickOutside = (event: MouseEvent) => {
    if (!isResultsOpen.value) return
    if (isEventDetailDialogOpen.value || isEventDialogOpen.value || isNoteDialogOpen.value || isReminderDialogOpen.value || isCreateNoteDialogOpen.value) {
        return
    }
    const target = event.target as HTMLElement
    const isDialogClick = target.closest('[role="dialog"]')
    if (isDialogClick) return
    
    if (!target.closest('.search-results-container') && target !== searchInputRef.value && !searchInputRef.value?.contains(target)) {
        isResultsOpen.value = false
    }
}

onMounted(() => {
    window.addEventListener('notification-updated', handleNotificationUpdate)
    document.addEventListener('click', handleClickOutside)
})

onUnmounted(() => {
    window.removeEventListener('notification-updated', handleNotificationUpdate)
    document.removeEventListener('click', handleClickOutside)
})

const loadUsersOnce = async () => {
    if (allUsers.value.length > 0) return
    try {
        const response = await fetch('/api/users')
        allUsers.value = await response.json()
    } catch (error) {
        console.error('Failed to load users:', error)
    }
}

const handleFocus = () => {
    isResultsOpen.value = true
    loadRecentItems()
    loadUsersOnce()
}

watch(searchQuery, () => {
    if (searchTimeout.value) {
        clearTimeout(searchTimeout.value)
    }
    
    if (searchQuery.value.length >= 2) {
        isResultsOpen.value = true
        searchTimeout.value = setTimeout(performSearch, 300)
    } else {
        searchResults.value = []
        if (isResultsOpen.value) {
            loadRecentItems()
        }
    }
})

watch([selectedTypes, searchField, creatorName, participantName, dateFrom, dateTo, dateType], () => {
    if (searchQuery.value.length >= 2) {
        if (searchTimeout.value) {
            clearTimeout(searchTimeout.value)
        }
        searchTimeout.value = setTimeout(performSearch, 300)
    }
})

watch(isFilterOpen, (newValue, oldValue) => {
    if (oldValue === true && newValue === false) {
        setTimeout(() => {
            searchInputRef.value?.focus()
        }, 100)
    }
})

const getTypeInfo = (type: string) => {
    return typeOptions.find(t => t.value === type) || typeOptions[0]
}

const handleItemClick = async (item: SearchResult) => {
    if (item.type === 'event') {
        const response = await fetch(`/api/events/${item.id}`)
        const event = await response.json()
        selectedEvent.value = event
        isEventDetailDialogOpen.value = true
    } else if (item.type === 'note') {
        const response = await fetch(`/api/notes/${item.id}`)
        const note = await response.json()
        selectedNote.value = note
        isNoteDialogOpen.value = true
    } else if (item.type === 'reminder') {
        const response = await fetch(`/api/reminders/${item.id}`)
        const reminder = await response.json()
        selectedReminder.value = reminder
        isReminderDialogOpen.value = true
    } else if (item.type === 'survey') {
        isResultsOpen.value = false
        router.get(route('surveys'), { highlight: item.id })
    } else if (item.type === 'trash') {
        isResultsOpen.value = false
        router.get(route('trash'), { highlight: item.id })
    }
}

const handleCopyEvent = () => {
  console.log('[GlobalSearch] handleCopyEvent called')
  const copyData = sessionStorage.getItem('event_copy_data')
  console.log('[GlobalSearch] event_copy_data from sessionStorage:', copyData)
  if (copyData) {
    const data = JSON.parse(copyData)
    const currentUserId = (usePage().props as any).auth?.user?.id ?? null
    const teamMembers = (usePage().props as any).teamMembers || []
    const me = teamMembers.find((m: any) => m.id === currentUserId)
    
    isEventDetailDialogOpen.value = false
    isResultsOpen.value = true
    
    selectedEvent.value = {
      event_id: 0,
      title: data.title,
      category: data.category,
      importance: data.importance,
      location: data.location,
      description: data.description,
      url: data.url,
      progress: data.progress,
      participants: me ? [me] : [],
      start_date: data.date_range[0],
      end_date: data.date_range[1],
      is_all_day: data.is_all_day,
      start_time: data.start_time,
      end_time: data.end_time,
    } as any
    console.log('[GlobalSearch] selectedEvent set to:', selectedEvent.value)
    isEventDialogOpen.value = true
    console.log('[GlobalSearch] isEventDialogOpen set to true')
  }
}

const handleCopyNote = () => {
  isCreateNoteDialogOpen.value = true
  setTimeout(() => {
    isNoteDialogOpen.value = false
  }, 50)
}

const handleCopyReminder = () => {
  console.log('[GlobalSearch] handleCopyReminder called')
  const copyData = sessionStorage.getItem('reminder_copy_data')
  console.log('[GlobalSearch] reminder_copy_data from sessionStorage:', copyData)
  if (copyData) {
    const data = JSON.parse(copyData)
    isResultsOpen.value = true
    selectedReminder.value = {
      reminder_id: 0,
      title: data.title,
      description: data.description,
      deadline_date: data.deadline ? data.deadline.split('T')[0] : null,
      deadline_time: data.deadline ? data.deadline.split('T')[1] : null,
      tags: data.tags.map((name: string) => ({ tag_name: name })),
    } as any
    console.log('[GlobalSearch] selectedReminder set to:', selectedReminder.value)
    isReminderDialogOpen.value = true
    console.log('[GlobalSearch] isReminderDialogOpen set to true')
  }
}

const clearSearch = () => {
    searchQuery.value = ''
}

const clearFilters = () => {
    selectedTypes.value = ['_all_']
    searchField.value = 'all'
    creatorName.value = '_all_'
    participantName.value = '_all_'
    dateFrom.value = ''
    dateTo.value = ''
    dateType.value = 'updated'
}

const activeFilterCount = computed(() => {
    let count = 0
    if (selectedTypes.value.length > 0 && selectedTypes.value[0] !== '_all_') count++
    if (searchField.value !== 'all') count++
    if (creatorName.value !== '_all_') count++
    if (participantName.value !== '_all_') count++
    if (dateFrom.value || dateTo.value) count++
    return count
})

const activeFiltersText = computed(() => {
    const filters: string[] = []
    if (selectedTypes.value.length > 0 && selectedTypes.value[0] !== '_all_') {
        const labels = selectedTypes.value.map(t => typeOptions.find(o => o.value === t)?.label).join(', ')
        filters.push(`種類: ${labels}`)
    }
    if (searchField.value !== 'all') {
        const fieldMap: Record<string, string> = { title: 'タイトルのみ', description: '詳細のみ' }
        filters.push(`検索範囲: ${fieldMap[searchField.value]}`)
    }
    if (creatorName.value !== '_all_') filters.push(`作成者: ${creatorName.value}`)
    if (participantName.value !== '_all_') filters.push(`参加者: ${participantName.value}`)
    if (dateFrom.value || dateTo.value) {
        const dateTypeMap: Record<string, string> = { created: '作成日', updated: '編集日', deleted: '削除日' }
        if (dateFrom.value && dateTo.value) {
            filters.push(`${dateTypeMap[dateType.value]}: ${dateFrom.value} 〜 ${dateTo.value}`)
        } else if (dateFrom.value) {
            filters.push(`${dateTypeMap[dateType.value]}: ${dateFrom.value}以降`)
        } else {
            filters.push(`${dateTypeMap[dateType.value]}: ${dateTo.value}まで`)
        }
    }
    return filters.join(' / ')
})

const handleBlur = (event: FocusEvent) => {
    setTimeout(() => {
        if (isEventDetailDialogOpen.value || isEventDialogOpen.value || isNoteDialogOpen.value || isReminderDialogOpen.value || isCreateNoteDialogOpen.value) {
            return
        }
        const relatedTarget = event.relatedTarget as HTMLElement
        if (!relatedTarget || !relatedTarget.closest('.search-results-container')) {
            isResultsOpen.value = false
        }
    }, 200)
}

const canEditEvent = (event: App.Models.Event) => {
    const currentUserId = (usePage().props as any).auth?.user?.id ?? null
    const teamMembers = (usePage().props as any).teamMembers || []
    
    const isCreator = event.created_by === currentUserId
    if (isCreator) return true
    
    if (Array.isArray(teamMembers) && teamMembers.length > 0 && event.participants && event.participants.length === teamMembers.length) {
        return true
    }
    
    const isParticipant = event.participants?.some(p => p.id === currentUserId)
    return isParticipant || false
}

const canEditNote = (note: App.Models.SharedNote) => {
    const currentUserId = (usePage().props as any).auth?.user?.id ?? null
    const teamMembers = (usePage().props as any).teamMembers || []
    
    const isCreator = note.author?.id === currentUserId
    if (isCreator) return true
    
    if (Array.isArray(teamMembers) && teamMembers.length > 0 && note.participants && note.participants.length === teamMembers.length) {
        return true
    }
    
    const isParticipant = note.participants?.some(p => p.id === currentUserId)
    return isParticipant || false
}
</script>

<template>
    <div class="flex-1 max-w-2xl relative">
        <div class="flex gap-2">
            <div class="relative flex-1">
                <Search class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 h-5 w-5 pointer-events-none" />
                <input
                    ref="searchInputRef"
                    type="text"
                    placeholder="タイトルまたは詳細で横断検索"
                    class="pl-10 pr-10 py-2 w-full flex h-9 rounded-md border border-gray-300 dark:border-input bg-background px-3 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                    v-model="searchQuery"
                    @focus="handleFocus"
                    @blur="handleBlur"
                />
                <button
                    v-if="searchQuery"
                    @click="clearSearch"
                    class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                >
                    <X class="h-4 w-4" />
                </button>
                <div v-if="isResultsOpen" class="search-results-container absolute top-full left-0 mt-2 w-[600px] p-0 max-h-[500px] overflow-hidden flex flex-col z-[40] rounded-md border-4 border-border dark:border-gray-600 bg-background shadow-md">
                    <div v-if="isSearching" class="p-8 text-center text-muted-foreground">
                        <div class="animate-pulse">検索中...</div>
                    </div>
                    <div v-else-if="searchResults.length === 0 && searchQuery.length >= 2" class="p-8 text-center text-muted-foreground">
                        <div class="mb-2">🔍</div>
                        <div>「{{ searchQuery }}」に一致する結果が見つかりませんでした</div>
                    </div>
                    <div v-else-if="searchQuery.length < 2 && recentItems.length === 0" class="p-8 text-center text-muted-foreground">
                        <div class="mb-2">📝</div>
                        <div>最近作成・編集したアイテムはありません</div>
                        <div class="text-xs mt-2">アイテムを作成するとここに表示されます</div>
                    </div>
                    <div v-else-if="searchQuery.length < 2 && recentItems.length > 0" class="overflow-y-auto">
                        <div class="p-3 border-b-2 border-border dark:border-gray-600 bg-muted/50">
                            <div class="text-xs font-medium text-muted-foreground">最近作成・編集したアイテム ({{ recentItems.length }}件)</div>
                        </div>
                        <div
                            v-for="result in recentItems"
                            :key="`${result.type}-${result.id}`"
                            class="p-3 hover:bg-muted/50 cursor-pointer border-b-2 last:border-b-0 border-border dark:border-gray-600"
                            @mousedown.prevent="handleItemClick(result)"
                        >
                            <div class="flex items-start gap-3">
                                <component
                                    :is="getTypeInfo(result.type).icon"
                                    :class="['h-5 w-5 mt-0.5', getTypeInfo(result.type).color]"
                                />
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2 mb-1">
                                        <div class="font-medium text-sm truncate">{{ result.title }}</div>
                                        <Badge variant="outline" class="text-xs shrink-0">
                                            {{ getTypeInfo(result.type).label }}
                                        </Badge>
                                    </div>
                                    <div v-if="result.description" class="text-xs text-muted-foreground line-clamp-2 mb-1">
                                        {{ result.description }}
                                    </div>
                                    <div class="flex items-center gap-2 text-xs text-muted-foreground">
                                        <span>作成者: {{ result.creator }}</span>
                                        <span v-if="result.date">• 作成日: {{ new Date(result.date).toLocaleDateString('ja-JP', { year: 'numeric', month: '2-digit', day: '2-digit' }).replace(/\//g, '/') }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div v-else-if="searchResults.length > 0" class="overflow-y-auto">
                        <div
                            v-for="result in searchResults"
                            :key="`${result.type}-${result.id}`"
                            class="p-3 hover:bg-muted/50 cursor-pointer border-b-2 last:border-b-0 border-border dark:border-gray-600"
                            @mousedown.prevent="handleItemClick(result)"
                        >
                            <div class="flex items-start gap-3">
                                <component
                                    :is="getTypeInfo(result.type).icon"
                                    :class="['h-5 w-5 mt-0.5', getTypeInfo(result.type).color]"
                                />
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2 mb-1">
                                        <div class="font-medium text-sm truncate">{{ result.title }}</div>
                                        <Badge variant="outline" class="text-xs shrink-0">
                                            {{ getTypeInfo(result.type).label }}
                                        </Badge>
                                    </div>
                                    <div v-if="result.description" class="text-xs text-muted-foreground line-clamp-2 mb-1">
                                        {{ result.description }}
                                    </div>
                                    <div class="flex items-center gap-2 text-xs text-muted-foreground">
                                        <span>作成者: {{ result.creator }}</span>
                                        <span v-if="result.date">• 作成日: {{ new Date(result.date).toLocaleDateString('ja-JP', { year: 'numeric', month: '2-digit', day: '2-digit' }).replace(/\//g, '/') }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
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
                <PopoverContent class="w-64 z-[45]" align="end" @interact-outside="(e) => { if (e.target?.closest?.('.dp__menu')) e.preventDefault() }">
                    <div class="space-y-4">
                        <div class="flex items-center justify-between">
                            <h4 class="font-medium text-sm">検索フィルター</h4>
                            <div class="flex gap-1">
                                <Button
                                    v-if="activeFilterCount > 0"
                                    variant="ghost"
                                    size="sm"
                                    class="h-auto p-1 text-xs hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-gray-100"
                                    @click="clearFilters"
                                >
                                    <X class="h-3 w-3" />
                                    クリア
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    class="h-auto p-1 text-xs hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-gray-100"
                                    @click="isFilterOpen = false"
                                >
                                    閉じる
                                </Button>
                            </div>
                        </div>
                        <div class="space-y-3">
                            <div class="space-y-2">
                                <Label class="text-xs font-medium text-foreground">種類</Label>
                                <Select v-model="selectedTypes[0]" @update:model-value="(val: any) => selectedTypes = val ? [val] : []">
                                    <SelectTrigger class="h-8">
                                        <SelectValue placeholder="種類を選択" />
                                    </SelectTrigger>
                                    <SelectContent class="z-[46]">
                                        <SelectItem value="_all_">すべて</SelectItem>
                                        <SelectItem v-for="option in typeOptions" :key="option.value" :value="option.value">
                                            <div class="flex items-center gap-2">
                                                <component :is="option.icon" :class="['h-4 w-4', option.color]" />
                                                {{ option.label }}
                                            </div>
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            
                            <div class="space-y-2">
                                <Label class="text-xs font-medium text-foreground">検索範囲</Label>
                                <div class="space-y-2">
                                    <div class="flex items-center space-x-2">
                                        <input type="radio" id="field-all" value="all" v-model="searchField" class="w-4 h-4 cursor-pointer text-blue-600 bg-gray-100 border-gray-300 dark:bg-gray-700 dark:border-gray-600" />
                                        <Label for="field-all" class="text-sm cursor-pointer text-foreground">すべて</Label>
                                    </div>
                                    <div class="flex items-center space-x-2">
                                        <input type="radio" id="field-title" value="title" v-model="searchField" class="w-4 h-4 cursor-pointer text-blue-600 bg-gray-100 border-gray-300 dark:bg-gray-700 dark:border-gray-600" />
                                        <Label for="field-title" class="text-sm cursor-pointer text-foreground">タイトルのみ</Label>
                                    </div>
                                    <div class="flex items-center space-x-2">
                                        <input type="radio" id="field-description" value="description" v-model="searchField" class="w-4 h-4 cursor-pointer text-blue-600 bg-gray-100 border-gray-300 dark:bg-gray-700 dark:border-gray-600" />
                                        <Label for="field-description" class="text-sm cursor-pointer text-foreground">詳細のみ</Label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="space-y-2">
                                <Label class="text-xs font-medium text-foreground">作成者</Label>
                                <Select v-model="creatorName">
                                    <SelectTrigger class="h-8">
                                        <SelectValue placeholder="作成者を選択" />
                                    </SelectTrigger>
                                    <SelectContent class="z-[46]">
                                        <SelectItem value="_all_">すべて</SelectItem>
                                        <SelectItem v-for="user in allUsers" :key="user.id" :value="String(user.name)">
                                            {{ user.name }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            
                            <div class="space-y-2">
                                <Label class="text-xs font-medium text-foreground">参加者</Label>
                                <Select v-model="participantName">
                                    <SelectTrigger class="h-8">
                                        <SelectValue placeholder="参加者を選択" />
                                    </SelectTrigger>
                                    <SelectContent class="z-[46]">
                                        <SelectItem value="_all_">すべて</SelectItem>
                                        <SelectItem v-for="user in allUsers" :key="user.id" :value="String(user.name)">
                                            {{ user.name }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            
                            <div class="space-y-2">
                                <Label class="text-xs font-medium text-foreground">日付種類</Label>
                                <div class="space-y-2">
                                    <div class="flex items-center space-x-2">
                                        <input type="radio" id="date-updated" value="updated" v-model="dateType" class="w-4 h-4 cursor-pointer text-blue-600 bg-gray-100 border-gray-300 dark:bg-gray-700 dark:border-gray-600" />
                                        <Label for="date-updated" class="text-sm cursor-pointer text-foreground">編集日</Label>
                                    </div>
                                    <div class="flex items-center space-x-2">
                                        <input type="radio" id="date-created" value="created" v-model="dateType" class="w-4 h-4 cursor-pointer text-blue-600 bg-gray-100 border-gray-300 dark:bg-gray-700 dark:border-gray-600" />
                                        <Label for="date-created" class="text-sm cursor-pointer text-foreground">作成日</Label>
                                    </div>
                                    <div class="flex items-center space-x-2">
                                        <input type="radio" id="date-deleted" value="deleted" v-model="dateType" class="w-4 h-4 cursor-pointer text-blue-600 bg-gray-100 border-gray-300 dark:bg-gray-700 dark:border-gray-600" />
                                        <Label for="date-deleted" class="text-sm cursor-pointer text-foreground">削除日</Label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="space-y-2">
                                <Label class="text-xs font-medium text-foreground">日付範囲</Label>
                                <div class="space-y-2">
                                    <div>
                                        <Label for="date-from" class="text-xs text-muted-foreground">開始日（この日以降）</Label>
                                        <VueDatePicker
                                            v-model="dateFrom"
                                            format="yyyy-MM-dd"
                                            :enable-time-picker="false"
                                            placeholder="開始日を選択"
                                            :locale="ja"
                                            :week-start="0"
                                            auto-apply
                                            :clearable="true"
                                            model-type="yyyy-MM-dd"
                                            :teleport="true"
                                        />
                                    </div>
                                    <div>
                                        <Label for="date-to" class="text-xs text-muted-foreground">終了日（この日まで）</Label>
                                        <VueDatePicker
                                            v-model="dateTo"
                                            format="yyyy-MM-dd"
                                            :enable-time-picker="false"
                                            placeholder="終了日を選択"
                                            :locale="ja"
                                            :week-start="0"
                                            auto-apply
                                            :clearable="true"
                                            model-type="yyyy-MM-dd"
                                            :teleport="true"
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </PopoverContent>
            </Popover>
        </div>
    </div>
    
    <EventDetailDialog
        v-if="selectedEvent"
        :key="selectedEvent.event_id"
        :open="isEventDetailDialogOpen"
        @update:open="isEventDetailDialogOpen = $event"
        :event="selectedEvent"
        @edit="() => { isEventDetailDialogOpen = false; isEventDialogOpen = true }"
        @copy="handleCopyEvent"
    />
    
    <CreateEventDialog
        v-if="selectedEvent"
        :key="`edit-${selectedEvent.event_id}`"
        :open="isEventDialogOpen"
        @update:open="isEventDialogOpen = $event"
        :event="selectedEvent"
        :readonly="selectedEvent.event_id !== 0 && !canEditEvent(selectedEvent)"
    />
    
    <!-- Debug: {{ selectedEvent?.event_id }}, readonly: {{ selectedEvent && selectedEvent.event_id !== 0 && !canEditEvent(selectedEvent) }} -->
    
    <NoteDetailDialog
        v-if="selectedNote"
        :note="selectedNote"
        :open="isNoteDialogOpen"
        @update:open="(val) => { isNoteDialogOpen = val; if (!val && !isCreateNoteDialogOpen) selectedNote = null }"
        :teamMembers="(usePage().props as any).teamMembers"
        :totalUsers="(usePage().props as any).totalUsers"
        @copy="handleCopyNote"
    />
    
    <CreateNoteDialog
        v-if="selectedNote"
        :open="isCreateNoteDialogOpen"
        @update:open="(val) => { isCreateNoteDialogOpen = val; if (!val && !isNoteDialogOpen) selectedNote = null }"
        :team-members="(usePage().props as any).teamMembers"
    />

    <ReminderDetailDialog
        v-if="selectedReminder"
        :reminder="selectedReminder"
        :open="isReminderDialogOpen"
        @update:open="isReminderDialogOpen = $event"
        @copy="handleCopyReminder"
    />
</template>