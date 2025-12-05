<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import { Trash2, ArrowLeft, RotateCcw, X, Calendar as CalendarIcon, StickyNote, BarChart3, ArrowUp, ArrowDown, Bell, CheckCircle, Undo2, Filter, Search } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { ScrollArea } from '@/components/ui/scroll-area'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle } from '@/components/ui/alert-dialog'
import { Input } from '@/components/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Checkbox } from '@/components/ui/checkbox'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { VueDatePicker } from '@vuepic/vue-datepicker'
import '@vuepic/vue-datepicker/dist/main.css'

defineOptions({
  layout: AuthenticatedLayout,
})

type ItemType = 'shared_note' | 'event' | 'survey' | 'reminder'

interface TrashItem {
  id: string
  type: ItemType
  title: string
  description: string
  deletedAt: string
  item_id: string
  permanent_delete_at: string
  creatorName?: string
}

type SortField = 'title' | 'deletedAt' | 'type' | 'creatorName'
type SortOrder = 'asc' | 'desc'

const props = defineProps<{
  trashItems: TrashItem[]
}>()

console.log('Trash props:', props.trashItems)
console.log('Array length:', props.trashItems.length)
console.log('First item:', props.trashItems[0])
console.log('First item type:', props.trashItems[0]?.type)

const trashItems = ref<TrashItem[]>(props.trashItems || [])
const sortField = ref<SortField>('deletedAt')
const sortOrder = ref<SortOrder>('desc')
const itemToDelete = ref<string | null>(null)
const showEmptyTrashDialog = ref(false)
const saveMessage = ref('')
const messageType = ref<'success' | 'delete'>('success')
const messageTimer = ref<number | null>(null)
const selectedItems = ref<Set<string>>(new Set())
const showDeleteSelectedDialog = ref(false)
const deleteMode = ref<'selected' | 'unselected'>('selected')

// フィルター
const filterType = ref<ItemType | 'all'>('all')
const filterTitle = ref('')
const filterCreator = ref<string | 'all'>('all')
const filterDateRange = ref<Date[] | null>(null)

const uniqueCreators = computed(() => {
  const creators = new Set<string>()
  trashItems.value.forEach(item => {
    if (item.creatorName) creators.add(item.creatorName)
  })
  return Array.from(creators).sort((a, b) => a.localeCompare(b, 'ja'))
})

const showMessage = (message: string, type: 'success' | 'delete' = 'success') => {
  if (messageTimer.value) {
    clearTimeout(messageTimer.value)
  }
  
  saveMessage.value = message
  messageType.value = type
  
  messageTimer.value = setTimeout(() => {
    saveMessage.value = ''
  }, 4000)
}

const getItemTypeInfo = (type: ItemType) => {
  switch (type) {
    case 'event': return { icon: CalendarIcon, label: '共有カレンダー', color: 'bg-blue-100 text-blue-700 border-blue-200' }
    case 'shared_note': return { icon: StickyNote, label: '共有メモ', color: 'bg-yellow-100 text-orange-600 border-yellow-200' }
    case 'survey': return { icon: BarChart3, label: 'アンケート', color: 'bg-purple-100 text-purple-700 border-purple-200' }
    case 'reminder': return { icon: Bell, label: '個人リマインダー', color: 'bg-green-100 text-green-700 border-green-200' }
    default: return { icon: StickyNote, label: '不明', color: 'bg-gray-100 text-gray-700 border-gray-300' }
  }
}

const handleSort = (field: SortField) => {
  if (sortField.value === field) {
    sortOrder.value = sortOrder.value === 'asc' ? 'desc' : 'asc'
  } else {
    sortField.value = field
    sortOrder.value = 'asc'
  }
}

const filteredItems = computed(() => {
  return trashItems.value.filter(item => {
    if (filterType.value !== 'all' && item.type !== filterType.value) return false
    if (filterTitle.value && !item.title.toLowerCase().includes(filterTitle.value.toLowerCase())) return false
    if (filterCreator.value !== 'all' && item.creatorName !== filterCreator.value) return false
    if (filterDateRange.value && filterDateRange.value.length === 2) {
      const itemDate = new Date(item.deletedAt)
      const startDate = new Date(filterDateRange.value[0])
      const endDate = new Date(filterDateRange.value[1])
      endDate.setHours(23, 59, 59, 999)
      if (itemDate < startDate || itemDate > endDate) return false
    }
    return true
  })
})

const sortedItems = computed(() => {
  return [...filteredItems.value].sort((a, b) => {
    let comparison = 0
    if (sortField.value === 'title') {
      comparison = a.title.localeCompare(b.title, 'ja')
    } else if (sortField.value === 'deletedAt') {
      comparison = a.deletedAt.localeCompare(b.deletedAt)
    } else if (sortField.value === 'type') {
      comparison = a.type.localeCompare(b.type)
    } else if (sortField.value === 'creatorName') {
      const aName = a.creatorName || ''
      const bName = b.creatorName || ''
      comparison = aName.localeCompare(bName, 'ja')
    }
    return sortOrder.value === 'asc' ? comparison : -comparison
  })
})

const isAllSelected = computed(() => {
  return sortedItems.value.length > 0 && sortedItems.value.every(item => selectedItems.value.has(item.id))
})

const toggleAll = (checked: boolean | string) => {
  console.log('toggleAll called with:', checked, 'type:', typeof checked)
  console.log('sortedItems count:', sortedItems.value.length)
  
  if (checked === true || checked === 'true') {
    sortedItems.value.forEach(item => {
      console.log('Adding item:', item.id)
      selectedItems.value.add(item.id)
    })
  } else {
    selectedItems.value.clear()
  }
  
  console.log('selectedItems after toggle:', selectedItems.value.size)
  // Force reactivity update
  selectedItems.value = new Set(selectedItems.value)
}

const toggleItem = (id: string, checked: boolean | string) => {
  console.log('toggleItem called with id:', id, 'checked:', checked)
  
  if (checked === true || checked === 'true') {
    selectedItems.value.add(id)
  } else {
    selectedItems.value.delete(id)
  }
  
  console.log('selectedItems after toggle:', selectedItems.value.size)
  // Force reactivity update
  selectedItems.value = new Set(selectedItems.value)
}

const handleRestore = (id: string) => {
  const item = trashItems.value.find((i) => i.id === id)
  console.log('handleRestore called with ID:', id)
  console.log('Generated route URL:', route('trash.restore', id))
  
  router.post(route('trash.restore', { id: id }), {}, {
    preserveScroll: true,
    onSuccess: () => {
      if (item) {
        trashItems.value = trashItems.value.filter((i) => i.id !== id)
        const itemTypeLabel = getItemTypeInfo(item.type).label
        showMessage(`${itemTypeLabel}が元に戻されました。`, 'success')
      }
    },
    onError: (errors) => {
      console.error('Restore error:', errors)
      showMessage('復元に失敗しました。', 'success')
    }
  })
}



const handlePermanentDelete = () => {
  console.log('handlePermanentDelete called')
  console.log('itemToDelete.value:', itemToDelete.value)
  
  if (!itemToDelete.value) {
    console.log('No item to delete')
    return
  }
  
  const deleteId = itemToDelete.value
  const item = trashItems.value.find((i) => i.id === deleteId)
  console.log('Found item:', item)
  console.log('Route URL:', route('trash.destroy', deleteId))
  
  router.delete(route('trash.destroy', deleteId), {
    onSuccess: () => {
      console.log('Delete success')
      trashItems.value = trashItems.value.filter((i) => i.id !== deleteId)
      showMessage(`「${item?.title || 'アイテム'}」を完全に削除しました`, 'success')
    },
    onError: (errors) => {
      console.log('Delete error:', errors)
      showMessage('削除に失敗しました', 'success')
    }
  })
  
  itemToDelete.value = null
}

const handleEmptyTrash = () => {
  router.delete(route('trash.empty'), {
    preserveScroll: true,
    onSuccess: () => {
      trashItems.value = []
      showMessage('ゴミ箱を空にしました', 'success')
    },
    onError: (errors) => {
      console.error('Empty trash error:', errors)
      showMessage('ゴミ箱を空にできませんでした。', 'success')
    }
  })
  showEmptyTrashDialog.value = false
}

const handleDeleteSelected = (mode: 'selected' | 'unselected') => {
  deleteMode.value = mode
  showDeleteSelectedDialog.value = true
}

const confirmDeleteSelected = () => {
  const idsToDelete = deleteMode.value === 'selected' 
    ? Array.from(selectedItems.value)
    : sortedItems.value.filter(item => !selectedItems.value.has(item.id)).map(item => item.id)
  
  if (idsToDelete.length === 0) {
    showMessage('削除するアイテムがありません', 'success')
    showDeleteSelectedDialog.value = false
    return
  }
  
  router.post(route('trash.destroyMultiple'), { ids: idsToDelete }, {
    preserveScroll: true,
    onSuccess: () => {
      trashItems.value = trashItems.value.filter(item => !idsToDelete.includes(item.id))
      selectedItems.value.clear()
      showMessage(`${idsToDelete.length}件のアイテムを削除しました`, 'success')
    },
    onError: () => {
      showMessage('削除に失敗しました', 'success')
    }
  })
  showDeleteSelectedDialog.value = false
}

</script>

<template>
  <div>
    <Head title="ゴミ箱" />
    <div class="max-w-[1800px] mx-auto h-full p-6">
      <Card class="h-full overflow-hidden">
        <div class="p-4 border-b border-gray-300">
          <div class="flex items-center justify-between mb-4">
            <div class="flex items-center gap-2">
              <Button variant="ghost" size="icon" @click="router.get(route('dashboard'))" class="mr-1">
                <ArrowLeft class="h-5 w-5" />
              </Button>
              <Trash2 class="h-6 w-6 text-gray-600" />
              <CardTitle>ゴミ箱</CardTitle>
            </div>
            <div class="flex items-center gap-3 flex-1 justify-center px-8">
              <Select v-model="filterType">
                <SelectTrigger class="w-[180px]">
                  <SelectValue placeholder="種類" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">すべての種類</SelectItem>
                  <SelectItem value="event">共有カレンダー</SelectItem>
                  <SelectItem value="shared_note">共有メモ</SelectItem>
                  <SelectItem value="survey">アンケート</SelectItem>
                  <SelectItem value="reminder">個人リマインダー</SelectItem>
                </SelectContent>
              </Select>
              <div class="relative">
                <Search class="absolute left-2 top-2.5 h-4 w-4 text-gray-500" />
                <Input v-model="filterTitle" placeholder="タイトル検索" class="pl-8 w-[200px]" />
              </div>
              <Select v-model="filterCreator">
                <SelectTrigger class="w-[180px]">
                  <SelectValue placeholder="作成者" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">すべての作成者</SelectItem>
                  <SelectItem v-for="creator in uniqueCreators" :key="creator" :value="creator">
                    {{ creator }}
                  </SelectItem>
                </SelectContent>
              </Select>
              <VueDatePicker v-model="filterDateRange" range placeholder="削除日時" auto-apply class="w-[280px]" />
            </div>
            <div class="flex items-center gap-2">
              <Button 
                v-if="trashItems.length > 0 && selectedItems.size > 0" 
                variant="outline" 
                @click="handleDeleteSelected('unselected')" 
                class="gap-2"
              >
                <Trash2 class="h-4 w-4" />
                選択したもの以外を完全削除
              </Button>
              <Button 
                v-if="trashItems.length > 0" 
                variant="outline" 
                @click="selectedItems.size > 0 ? handleDeleteSelected('selected') : showEmptyTrashDialog = true" 
                :class="[
                  'gap-2 transition-all duration-200',
                  selectedItems.size > 0 ? 'bg-red-600 text-white border-red-600 hover:bg-red-700 hover:border-red-700 shadow-md' : ''
                ]"
              >
                <Trash2 class="h-4 w-4" />
                {{ selectedItems.size > 0 ? `選択した${selectedItems.size}件を完全削除` : 'ゴミ箱を空にする' }}
              </Button>
            </div>
          </div>
          <p class="text-sm text-gray-500">削除されたアイテム ({{ sortedItems.length }}件)</p>
        </div>
        <div v-if="trashItems.length === 0" class="flex-1 flex items-center justify-center">
          <div class="text-center py-16">
            <Trash2 class="h-16 w-16 mx-auto mb-4 text-gray-300" />
            <h2 class="mb-2 text-gray-900">ゴミ箱は空です</h2>
            <p class="text-gray-500">削除されたアイテムはここに表示されます</p>
          </div>
        </div>
        <ScrollArea v-else class="h-[calc(100vh-240px)]">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead class="w-12">
                  <input 
                    type="checkbox" 
                    :checked="isAllSelected" 
                    @change="(e) => toggleAll((e.target as HTMLInputElement).checked)"
                    class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                  />
                </TableHead>
                <TableHead class="cursor-pointer hover:bg-gray-50 select-none" @click="handleSort('type')">
                  種類
                  <ArrowUp v-if="sortField === 'type' && sortOrder === 'asc'" class="h-4 w-4 inline ml-1" />
                  <ArrowDown v-if="sortField === 'type' && sortOrder === 'desc'" class="h-4 w-4 inline ml-1" />
                </TableHead>
                <TableHead class="cursor-pointer hover:bg-gray-50 select-none" @click="handleSort('title')">
                  タイトル
                  <ArrowUp v-if="sortField === 'title' && sortOrder === 'asc'" class="h-4 w-4 inline ml-1" />
                  <ArrowDown v-if="sortField === 'title' && sortOrder === 'desc'" class="h-4 w-4 inline ml-1" />
                </TableHead>
                <TableHead class="cursor-pointer hover:bg-gray-50 select-none" @click="handleSort('creatorName')">
                  作成者
                  <ArrowUp v-if="sortField === 'creatorName' && sortOrder === 'asc'" class="h-4 w-4 inline ml-1" />
                  <ArrowDown v-if="sortField === 'creatorName' && sortOrder === 'desc'" class="h-4 w-4 inline ml-1" />
                </TableHead>
                <TableHead class="cursor-pointer hover:bg-gray-50 select-none" @click="handleSort('deletedAt')">
                  削除した日時
                  <ArrowUp v-if="sortField === 'deletedAt' && sortOrder === 'asc'" class="h-4 w-4 inline ml-1" />
                  <ArrowDown v-if="sortField === 'deletedAt' && sortOrder === 'desc'" class="h-4 w-4 inline ml-1" />
                </TableHead>
                <TableHead>詳細</TableHead>
                <TableHead class="text-right">アクション</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              <TableRow v-for="item in sortedItems" :key="item.id || 'unknown'">
                <TableCell>
                  <input 
                    type="checkbox" 
                    :checked="selectedItems.has(item.id)" 
                    @change="(e) => toggleItem(item.id, (e.target as HTMLInputElement).checked)"
                    class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                  />
                </TableCell>
                <TableCell v-if="item">
                  <Badge variant="outline" :class="['gap-1', getItemTypeInfo(item.type || 'shared_note').color]">
                    <component :is="getItemTypeInfo(item.type || 'shared_note').icon" class="h-4 w-4" />
                    {{ getItemTypeInfo(item.type || 'shared_note').label }}
                  </Badge>
                </TableCell>
                <TableCell v-else>
                  <Badge variant="outline" class="gap-1 bg-gray-100 text-gray-700 border-gray-300">
                    <StickyNote class="h-4 w-4" />
                    不明
                  </Badge>
                </TableCell>
                <TableCell>{{ item?.title || '不明' }}</TableCell>
                <TableCell>{{ item?.creatorName || '不明' }}</TableCell>
                <TableCell class="text-gray-600">{{ item?.deletedAt || '不明' }}</TableCell>
                <TableCell class="text-gray-500 text-sm max-w-xs">
                  <span v-if="item?.description" class="line-clamp-2">
                    {{ item.description.length > 50 ? item.description.substring(0, 50) + '...' : item.description }}
                  </span>
                  <span v-else class="italic">詳細なし</span>
                </TableCell>
                <TableCell class="text-right">
                  <div class="flex items-center justify-end gap-2">
                    <Button variant="outline" size="sm" @click="handleRestore(item.id)" class="gap-2" :disabled="!item">
                      <RotateCcw class="h-4 w-4" />
                      元に戻す
                    </Button>
                    <Button variant="outline" size="sm" @click="itemToDelete = item?.id" class="gap-2 bg-red-600 text-white border-red-600 hover:bg-red-700 hover:border-red-700" :disabled="!item">
                      <X class="h-4 w-4" />
                      完全に削除
                    </Button>
                  </div>
                </TableCell>
              </TableRow>
            </TableBody>
          </Table>
        </ScrollArea>
      </Card>
    </div>

    <AlertDialog :open="itemToDelete !== null">
      <AlertDialogContent class="bg-white">
        <AlertDialogHeader>
          <AlertDialogTitle>完全に削除しますか？</AlertDialogTitle>
          <AlertDialogDescription>このアイテムを完全に削除します。この操作は取り消せません。</AlertDialogDescription>
        </AlertDialogHeader>
        <AlertDialogFooter>
          <AlertDialogCancel @click="itemToDelete = null" class="hover:bg-gray-100">キャンセル</AlertDialogCancel>
          <AlertDialogAction @click="handlePermanentDelete" class="bg-red-600 text-white border-red-600 hover:bg-red-700 hover:border-red-700">
            完全に削除
          </AlertDialogAction>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>

    <AlertDialog :open="showDeleteSelectedDialog" @update:open="(open) => showDeleteSelectedDialog = open">
      <AlertDialogContent class="bg-white">
        <AlertDialogHeader>
          <AlertDialogTitle>
            {{ deleteMode === 'selected' ? `選択した${selectedItems.size}件を削除しますか？` : `選択以外の${sortedItems.length - selectedItems.size}件を削除しますか？` }}
          </AlertDialogTitle>
          <AlertDialogDescription>この操作は取り消せません。</AlertDialogDescription>
        </AlertDialogHeader>
        <AlertDialogFooter>
          <AlertDialogCancel>キャンセル</AlertDialogCancel>
          <AlertDialogAction @click="confirmDeleteSelected" class="bg-red-600 text-white border-red-600 hover:bg-red-700 hover:border-red-700">
            削除
          </AlertDialogAction>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>

    <AlertDialog :open="showEmptyTrashDialog" @update:open="(open) => showEmptyTrashDialog = open">
      <AlertDialogContent class="bg-white">
        <AlertDialogHeader>
          <AlertDialogTitle>ゴミ箱を空にしますか？</AlertDialogTitle>
          <AlertDialogDescription>すべてのアイテムを完全に削除します。この操作は取り消せません。</AlertDialogDescription>
        </AlertDialogHeader>
        <AlertDialogFooter>
          <AlertDialogCancel>キャンセル</AlertDialogCancel>
          <AlertDialogAction @click="handleEmptyTrash" class="bg-red-600 text-white border-red-600 hover:bg-red-700 hover:border-red-700">
            ゴミ箱を空にする
          </AlertDialogAction>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>

    <!-- メッセージ表示 -->
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
        :class="['fixed bottom-4 left-1/2 transform -translate-x-1/2 z-50 p-3 text-white rounded-lg shadow-lg',
          messageType === 'success' ? 'bg-green-500' : 'bg-red-500']"
      >
        <div class="flex items-center gap-2">
          <CheckCircle class="h-5 w-5" />
          <span class="font-medium">{{ saveMessage }}</span>
        </div>
      </div>
    </Transition>
  </div>
</template>
