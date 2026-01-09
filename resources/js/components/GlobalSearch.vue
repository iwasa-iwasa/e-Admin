<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import { Search, Calendar, StickyNote, Clock, BarChart3, Trash2, Filter, X } from 'lucide-vue-next'
import { Input } from '@/components/ui/input'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover'
import { Checkbox } from '@/components/ui/checkbox'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import CreateEventDialog from '@/components/CreateEventDialog.vue'
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
const dateFromInput = ref<HTMLInputElement | null>(null)
const dateToInput = ref<HTMLInputElement | null>(null)

const selectedEvent = ref<App.Models.Event | null>(null)
const isEventDialogOpen = ref(false)
const selectedNote = ref<App.Models.SharedNote | null>(null)
const isNoteDialogOpen = ref(false)
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
    if (recentItemsLoaded.value) return
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
        const params = new URLSearchParams({
            q: searchQuery.value,
            search_field: searchField.value,
            creator_name: creatorName.value === '_all_' ? '' : creatorName.value,
            participant_name: participantName.value === '_all_' ? '' : participantName.value,
            date_from: dateFrom.value,
            date_to: dateTo.value,
            date_type: dateType.value,
            ...(types.length > 0 && { types: types.join(',') })
        })
        
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
        isEventDialogOpen.value = true
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
    const filters = []
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
        if (isEventDialogOpen.value || isNoteDialogOpen.value || isReminderDialogOpen.value) {
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
    
    const isCreator = note.created_by === currentUserId
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
                    class="pl-10 pr-10 py-2 w-full flex h-10 rounded-md border border-input bg-background px-3 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                    v-model="searchQuery"
                    @focus="handleFocus"
                    @blur="handleBlur"
                />
                <button
                    v-if="searchQuery"
                    @click="clearSearch"
                    class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-600"
                >
                    <X class="h-4 w-4" />
                </button>
                <div v-if="isResultsOpen" class="search-results-container absolute top-full left-0 mt-2 w-[600px] p-0 max-h-[500px] overflow-hidden flex flex-col z-50 rounded-md border border-gray-300 bg-white shadow-md">
                    <div v-if="isSearching" class="p-8 text-center text-gray-500">
                        <div class="animate-pulse">検索中...</div>
                    </div>
                    <div v-else-if="searchResults.length === 0 && searchQuery.length >= 2" class="p-8 text-center text-gray-500">
                        <div class="mb-2">🔍</div>
                        <div>「{{ searchQuery }}」に一致する結果が見つかりませんでした</div>
                    </div>
                    <div v-else-if="searchQuery.length < 2 && recentItems.length === 0" class="p-8 text-center text-gray-500">
                        <div class="mb-2">📝</div>
                        <div>最近作成・編集したアイテムはありません</div>
                        <div class="text-xs mt-2">アイテムを作成するとここに表示されます</div>
                    </div>
                    <div v-else-if="searchQuery.length < 2 && recentItems.length > 0" class="overflow-y-auto">
                        <div class="p-3 border-b bg-gray-50">
                            <div class="text-xs font-medium text-gray-600">最近作成・編集したアイテム ({{ recentItems.length }}件)</div>
                        </div>
                        <div
                            v-for="result in recentItems"
                            :key="`${result.type}-${result.id}`"
                            class="p-3 hover:bg-gray-50 cursor-pointer border-b last:border-b-0"
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
                                    <div v-if="result.description" class="text-xs text-gray-600 line-clamp-2 mb-1">
                                        {{ result.description }}
                                    </div>
                                    <div class="flex items-center gap-2 text-xs text-gray-500">
                                        <span>{{ result.creator }}</span>
                                        <span v-if="result.date">• {{ new Date(result.date).toLocaleDateString('ja-JP') }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div v-else-if="searchResults.length > 0" class="overflow-y-auto">
                        <div
                            v-for="result in searchResults"
                            :key="`${result.type}-${result.id}`"
                            class="p-3 hover:bg-gray-50 cursor-pointer border-b last:border-b-0"
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
                                    <div v-if="result.description" class="text-xs text-gray-600 line-clamp-2 mb-1">
                                        {{ result.description }}
                                    </div>
                                    <div class="flex items-center gap-2 text-xs text-gray-500">
                                        <span>{{ result.creator }}</span>
                                        <span v-if="result.date">• {{ new Date(result.date).toLocaleDateString('ja-JP') }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <Popover v-model:open="isFilterOpen">
                <PopoverTrigger as-child>
                    <Button variant="outline" size="icon" class="relative">
                        <Filter class="h-5 w-5" />
                        <Badge v-if="activeFilterCount > 0" class="absolute -top-1 -right-1 h-5 w-5 flex items-center justify-center p-0 bg-blue-500 text-white text-xs">
                            {{ activeFilterCount }}
                        </Badge>
                    </Button>
                </PopoverTrigger>
                <PopoverContent class="w-64" align="end">
                    <div class="space-y-4">
                        <div class="flex items-center justify-between">
                            <h4 class="font-medium text-sm">検索フィルター</h4>
                            <div class="flex gap-1">
                                <Button
                                    v-if="activeFilterCount > 0"
                                    variant="ghost"
                                    size="sm"
                                    class="h-auto p-1 text-xs"
                                    @click="clearFilters"
                                >
                                    <X class="h-3 w-3 mr-1" />
                                    クリア
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    class="h-auto p-1 text-xs"
                                    @click="isFilterOpen = false"
                                >
                                    閉じる
                                </Button>
                            </div>
                        </div>
                        <div class="space-y-3">
                            <div class="space-y-2">
                                <Label class="text-xs font-medium text-gray-700">種類</Label>
                                <Select v-model="selectedTypes[0]" @update:model-value="(val) => selectedTypes = val ? [val] : []">
                                    <SelectTrigger class="h-8">
                                        <SelectValue placeholder="種類を選択" />
                                    </SelectTrigger>
                                    <SelectContent>
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
                                <Label class="text-xs font-medium text-gray-700">検索範囲</Label>
                                <div class="space-y-2">
                                    <div class="flex items-center space-x-2">
                                        <input type="radio" id="field-all" value="all" v-model="searchField" class="cursor-pointer" />
                                        <Label for="field-all" class="text-sm cursor-pointer">すべて</Label>
                                    </div>
                                    <div class="flex items-center space-x-2">
                                        <input type="radio" id="field-title" value="title" v-model="searchField" class="cursor-pointer" />
                                        <Label for="field-title" class="text-sm cursor-pointer">タイトルのみ</Label>
                                    </div>
                                    <div class="flex items-center space-x-2">
                                        <input type="radio" id="field-description" value="description" v-model="searchField" class="cursor-pointer" />
                                        <Label for="field-description" class="text-sm cursor-pointer">詳細のみ</Label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="space-y-2">
                                <Label class="text-xs font-medium text-gray-700">作成者</Label>
                                <Select v-model="creatorName">
                                    <SelectTrigger class="h-8">
                                        <SelectValue placeholder="作成者を選択" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="_all_">すべて</SelectItem>
                                        <SelectItem v-for="user in allUsers" :key="user.id" :value="user.name">
                                            {{ user.name }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            
                            <div class="space-y-2">
                                <Label class="text-xs font-medium text-gray-700">参加者</Label>
                                <Select v-model="participantName">
                                    <SelectTrigger class="h-8">
                                        <SelectValue placeholder="参加者を選択" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="_all_">すべて</SelectItem>
                                        <SelectItem v-for="user in allUsers" :key="user.id" :value="user.name">
                                            {{ user.name }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            
                            <div class="space-y-2">
                                <Label class="text-xs font-medium text-gray-700">日付種類</Label>
                                <div class="space-y-2">
                                    <div class="flex items-center space-x-2">
                                        <input type="radio" id="date-updated" value="updated" v-model="dateType" class="cursor-pointer" />
                                        <Label for="date-updated" class="text-sm cursor-pointer">編集日</Label>
                                    </div>
                                    <div class="flex items-center space-x-2">
                                        <input type="radio" id="date-created" value="created" v-model="dateType" class="cursor-pointer" />
                                        <Label for="date-created" class="text-sm cursor-pointer">作成日</Label>
                                    </div>
                                    <div class="flex items-center space-x-2">
                                        <input type="radio" id="date-deleted" value="deleted" v-model="dateType" class="cursor-pointer" />
                                        <Label for="date-deleted" class="text-sm cursor-pointer">削除日</Label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="space-y-2">
                                <Label class="text-xs font-medium text-gray-700">日付範囲</Label>
                                <div class="space-y-2">
                                    <div @click="dateFromInput?.showPicker?.()" class="cursor-pointer">
                                        <Label for="date-from" class="text-xs text-gray-600 cursor-pointer">開始日（この日以降）</Label>
                                        <Input
                                            ref="dateFromInput"
                                            id="date-from"
                                            type="date"
                                            v-model="dateFrom"
                                            placeholder="開始日"
                                            class="h-8 text-sm cursor-pointer"
                                        />
                                    </div>
                                    <div @click="dateToInput?.showPicker?.()" class="cursor-pointer">
                                        <Label for="date-to" class="text-xs text-gray-600 cursor-pointer">終了日（この日まで）</Label>
                                        <Input
                                            ref="dateToInput"
                                            id="date-to"
                                            type="date"
                                            v-model="dateTo"
                                            placeholder="終了日"
                                            class="h-8 text-sm cursor-pointer"
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </PopoverContent>
            </Popover>
        </div>
        <div class="flex items-center justify-between mt-1 ml-1">
            <p class="text-xs text-gray-500">
                すべての予定、メモ、リマインダー、アンケート、ゴミ箱を横断検索
            </p>
            <p v-if="activeFilterCount > 0" class="text-xs text-blue-600 font-medium">
                {{ activeFiltersText }}
            </p>
        </div>
    </div>
    
    <CreateEventDialog
        v-if="selectedEvent"
        :key="selectedEvent.event_id"
        :open="isEventDialogOpen"
        @update:open="isEventDialogOpen = $event"
        :event="selectedEvent"
        :readonly="!canEditEvent(selectedEvent)"
    />
    
    <NoteDetailDialog
        v-if="selectedNote"
        :note="selectedNote"
        :open="isNoteDialogOpen"
        @update:open="isNoteDialogOpen = $event"
        :teamMembers="(usePage().props as any).teamMembers"
        :totalUsers="(usePage().props as any).totalUsers"
    />
    
    <ReminderDetailDialog
        v-if="selectedReminder"
        :reminder="selectedReminder"
        :open="isReminderDialogOpen"
        @update:open="isReminderDialogOpen = $event"
    />
</template>
