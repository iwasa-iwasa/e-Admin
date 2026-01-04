<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import { ref, computed, onMounted, nextTick, watch } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import { Trash2, ArrowLeft, RotateCcw, X, Calendar as CalendarIcon, StickyNote, BarChart3, ArrowUp, ArrowDown, Bell, CheckCircle, Undo2, Filter, Search } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardTitle, CardHeader } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle } from '@/components/ui/alert-dialog'
import { Input } from '@/components/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Checkbox } from '@/components/ui/checkbox'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

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

// リアクティブ変数
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
const showRestoreSelectedDialog = ref(false)
const deleteMode = ref<'selected' | 'unselected'>('selected')
const restoreMode = ref<'selected' | 'unselected'>('selected')

// フィルター
const filterType = ref<ItemType | 'all'>('all')
const filterTitle = ref('')
const filterCreator = ref<string | 'all'>('all')
const filterDateFrom = ref('')
const filterDateTo = ref('')
const showFilterDialog = ref(false)
const searchInputRef = ref<HTMLInputElement | null>(null)

// 計算プロパティ
const uniqueCreators = computed(() => {
  const creators = new Set<string>()
  trashItems.value.forEach(item => {
    if (item.creatorName) creators.add(item.creatorName)
  })
  return Array.from(creators).sort((a, b) => a.localeCompare(b, 'ja'))
})

const filteredItems = computed(() => {
  return trashItems.value.filter(item => {
    if (filterType.value !== 'all' && item.type !== filterType.value) return false
    if (filterTitle.value && !item.title.toLowerCase().includes(filterTitle.value.toLowerCase())) return false
    if (filterCreator.value !== 'all' && item.creatorName !== filterCreator.value) return false
    if (filterDateFrom.value || filterDateTo.value) {
      const itemDate = new Date(item.deletedAt)
      if (filterDateFrom.value) {
        const startDate = new Date(filterDateFrom.value)
        if (itemDate < startDate) return false
      }
      if (filterDateTo.value) {
        const endDate = new Date(filterDateTo.value)
        endDate.setHours(23, 59, 59, 999)
        if (itemDate > endDate) return false
      }
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

// ユーティリティ関数
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

const toggleAll = (checked: boolean | string) => {
  if (checked === true || checked === 'true') {
    sortedItems.value.forEach(item => {
      selectedItems.value.add(item.id)
    })
  } else {
    selectedItems.value.clear()
  }
  selectedItems.value = new Set(selectedItems.value)
}

const toggleItem = (id: string, checked: boolean | string) => {
  if (checked === true || checked === 'true') {
    selectedItems.value.add(id)
  } else {
    selectedItems.value.delete(id)
  }
  selectedItems.value = new Set(selectedItems.value)
}

// ハンドラ関数
const handleRowClick = (e: Event, item: TrashItem) => {
  if (!(e.target as HTMLElement).closest('input[type="checkbox"]') && !(e.target as HTMLElement).closest('button')) {
    if (selectedItems.value.size > 0) {
      const checked = selectedItems.value.has(item.id)
      if (checked) {
        selectedItems.value.delete(item.id)
      } else {
        selectedItems.value.add(item.id)
      }
      selectedItems.value = new Set(selectedItems.value)
    }
  }
}

const handleRestore = (id: string) => {
  const item = trashItems.value.find((i) => i.id === id)
  
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
  if (!itemToDelete.value) return
  
  const deleteId = itemToDelete.value
  const item = trashItems.value.find((i) => i.id === deleteId)
  
  router.delete(route('trash.destroy', deleteId), {
    onSuccess: () => {
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

const handleRestoreSelected = (mode: 'selected' | 'unselected') => {
  restoreMode.value = mode
  showRestoreSelectedDialog.value = true
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

const confirmRestoreSelected = () => {
  const idsToRestore = restoreMode.value === 'selected' 
    ? Array.from(selectedItems.value)
    : sortedItems.value.filter(item => !selectedItems.value.has(item.id)).map(item => item.id)
  
  if (idsToRestore.length === 0) {
    showMessage('復元するアイテムがありません', 'success')
    showRestoreSelectedDialog.value = false
    return
  }
  
  router.post(route('trash.restoreMultiple'), { ids: idsToRestore }, {
    preserveScroll: true,
    onSuccess: () => {
      trashItems.value = trashItems.value.filter(item => !idsToRestore.includes(item.id))
      selectedItems.value.clear()
      showMessage(`${idsToRestore.length}件のアイテムを復元しました`, 'success')
    },
    onError: () => {
      showMessage('復元に失敗しました', 'success')
    }
  })
  showRestoreSelectedDialog.value = false
}

watch(showFilterDialog, (isOpen) => {
  if (!isOpen) {
    requestAnimationFrame(() => {
      if (searchInputRef.value) {
        const inputElement = searchInputRef.value
        if (inputElement && typeof inputElement.focus === 'function') {
          inputElement.focus()
        }
      }
    })
  }
})

onMounted(() => {
  const page = usePage()
  const highlightId = (page.props as any).highlight
  if (highlightId) {
    nextTick(() => {
      setTimeout(() => {
        const elementId = `item-${highlightId}`
        const element = document.getElementById(elementId)
        if (element) {
          element.scrollIntoView({ behavior: 'smooth', block: 'center' })
          setTimeout(() => {
            element.classList.add('highlight-flash')
            setTimeout(() => element.classList.remove('highlight-flash'), 3000)
          }, 500)
        }
      }, 500)
    })
  }
})
</script>

<template>
  <Head title="ゴミ箱" />
  
  <div class="max-w-[1800px] mx-auto h-full p-6">
    <Card class="h-full overflow-hidden flex flex-col">
      <!-- ヘッダー部分 -->
      <div class="p-4 border-b border-gray-300 shrink-0">
        <div class="flex items-center justify-between ">
          <!-- タイトル部分 -->
          <div class="flex items-center gap-2">
            <Button
              variant="ghost"
              size="icon"
              @click="router.get(route('dashboard'))"
              class="mr-1"
            >
              <ArrowLeft class="h-5 w-5" />
            </Button>
            <Trash2 class="h-6 w-6 text-gray-600" />
            <CardTitle>ゴミ箱</CardTitle>
          </div>
          
          <!-- 検索・フィルター部分 -->
          <div class="flex items-center gap-3 flex-1 justify-center px-8">
            <div class="flex gap-2">
              <div class="relative flex-1">
                <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
                <input
                  ref="searchInputRef"
                  placeholder="タイトル検索"
                  v-model="filterTitle"
                  class="pl-9 pr-9 flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 w-[300px]"
                />
                <button v-if="filterTitle" @click="filterTitle = ''" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                  <X class="h-4 w-4" />
                </button>
              </div>
              <Button variant="outline" size="icon" @click="showFilterDialog = !showFilterDialog" :class="showFilterDialog ? 'bg-gray-100' : ''">
                <Filter class="h-4 w-4" />
              </Button>
            </div>
          </div>
          
          <!-- アクションボタン -->
          <div class="flex items-center gap-2">
            <Button 
              v-if="selectedItems.size > 0" 
              variant="outline" 
              @click="handleRestoreSelected('selected')" 
              class="gap-2 bg-green-600 text-white border-green-600 hover:bg-green-700"
            >
              <RotateCcw class="h-4 w-4" />
              選択を復元
            </Button>
            <Button 
              v-if="selectedItems.size > 0" 
              variant="outline" 
              @click="handleDeleteSelected('selected')" 
              class="gap-2 bg-red-600 text-white border-red-600 hover:bg-red-700"
            >
              <Trash2 class="h-4 w-4" />
              選択を削除
            </Button>
            <Button 
              v-if="selectedItems.size > 0" 
              variant="outline" 
              @click="handleDeleteSelected('unselected')" 
              class="gap-2"
            >
              <Trash2 class="h-4 w-4" />
              他を削除
            </Button>
            <Button 
              v-if="trashItems.length > 0 && selectedItems.size === 0" 
              variant="outline" 
              @click="showEmptyTrashDialog = true" 
              class="gap-2"
            >
              <Trash2 class="h-4 w-4" />
              全て削除
            </Button>
          </div>
        </div>
        
        <div v-if="showFilterDialog" class="space-y-2 mb-2 p-3 bg-gray-50 rounded-lg border mx-4">
          <div>
            <label class="text-xs font-medium text-gray-700 mb-1 block">種類</label>
            <Select v-model="filterType">
              <SelectTrigger class="h-8 border-gray-300">
                <SelectValue placeholder="すべての種類" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">すべての種類</SelectItem>
                <SelectItem value="event">共有カレンダー</SelectItem>
                <SelectItem value="shared_note">共有メモ</SelectItem>
                <SelectItem value="survey">アンケート</SelectItem>
                <SelectItem value="reminder">個人リマインダー</SelectItem>
              </SelectContent>
            </Select>
          </div>
          <div>
            <label class="text-xs font-medium text-gray-700 mb-1 block">作成者</label>
            <Select v-model="filterCreator">
              <SelectTrigger class="h-8 border-gray-300">
                <SelectValue placeholder="すべての作成者" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">すべての作成者</SelectItem>
                <SelectItem v-for="creator in uniqueCreators" :key="creator" :value="creator">
                  {{ creator }}
                </SelectItem>
              </SelectContent>
            </Select>
          </div>
          <div>
            <label class="text-xs font-medium text-gray-700 mb-1 block">削除日</label>
            <div class="flex gap-2">
              <Input
                type="date"
                v-model="filterDateFrom"
                placeholder="開始日"
                class="h-8 text-xs flex-1"
              />
              <Input
                type="date"
                v-model="filterDateTo"
                placeholder="終了日"
                class="h-8 text-xs flex-1"
              />
            </div>
          </div>
        </div>
        
        <p class="text-sm text-gray-500 px-4 py-2">削除されたアイテム ({{ sortedItems.length }}件)</p>
        <!-- テーブルヘッダー -->
        <div v-if="trashItems.length > 0" class="grid grid-cols-12 gap-4 py-2 px-4 bg-gray-50 border-t border-b text-sm font-medium text-gray-700">
          <div class="col-span-1 flex items-center">
            <input 
              type="checkbox" 
              :checked="isAllSelected" 
              @change="(e) => toggleAll((e.target as HTMLInputElement).checked)"
              class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
            />
          </div>
          <div class="col-span-1">種類</div>
          <div class="col-span-2">タイトル</div>
          <div class="col-span-1">作成者</div>
          <div class="col-span-2">削除した日時</div>
          <div class="col-span-3">詳細</div>
          <div class="col-span-2 text-right">アクション</div>
        </div>
      </div>
      
      <!-- メインコンテンツ -->
      <div class="flex-1 overflow-y-auto">
        <!-- 空の状態 -->
        <div v-if="trashItems.length === 0" class="flex items-center justify-center h-full">
          <div class="text-center py-16">
            <Trash2 class="h-16 w-16 mx-auto mb-4 text-gray-300" />
            <h2 class="mb-2 text-gray-900">ゴミ箱は空です</h2>
            <p class="text-gray-500">削除されたアイテムはここに表示されます</p>
          </div>
        </div>
        
        <!-- テーブル -->
        <div v-else class="p-6">
          <div class="space-y-2">
            <div 
              v-for="item in sortedItems" 
              :key="item.id || 'unknown'" 
              :id="`item-${item.id}`" 
              :class="['grid grid-cols-12 gap-4 py-3 px-4 border-b hover:bg-gray-50 cursor-pointer', selectedItems.has(item.id) ? 'bg-blue-50' : '']"
              @click="handleRowClick($event, item)"
            >
              <div class="col-span-1 flex items-center">
                <input 
                  type="checkbox" 
                  :checked="selectedItems.has(item.id)" 
                  @change="(e) => toggleItem(item.id, (e.target as HTMLInputElement).checked)"
                  class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                />
              </div>
              <div class="col-span-1 flex items-center">
                <Badge v-if="item" variant="outline" :class="['gap-1', getItemTypeInfo(item.type || 'shared_note').color]">
                  <component :is="getItemTypeInfo(item.type || 'shared_note').icon" class="h-4 w-4" />
                  {{ getItemTypeInfo(item.type || 'shared_note').label }}
                </Badge>
                <Badge v-else variant="outline" class="gap-1 bg-gray-100 text-gray-700 border-gray-300">
                  <StickyNote class="h-4 w-4" />
                  不明
                </Badge>
              </div>
              <div class="col-span-2 flex items-center text-sm">{{ item?.title || '不明' }}</div>
              <div class="col-span-1 flex items-center text-sm">{{ item?.creatorName || '不明' }}</div>
              <div class="col-span-2 flex items-center text-sm text-gray-600">{{ item?.deletedAt || '不明' }}</div>
              <div class="col-span-3 flex items-center text-sm text-gray-500">
                <span v-if="item?.description" class="line-clamp-2">
                  {{ item.description.length > 50 ? item.description.substring(0, 50) + '...' : item.description }}
                </span>
                <span v-else class="italic">詳細なし</span>
              </div>
              <div class="col-span-2 flex items-center justify-end gap-2">
                <Button variant="outline" size="sm" @click="handleRestore(item.id)" class="gap-2" :disabled="!item">
                  <RotateCcw class="h-4 w-4" />
                  元に戻す
                </Button>
                <Button variant="outline" size="sm" @click="itemToDelete = item?.id" class="gap-2 bg-red-600 text-white border-red-600 hover:bg-red-700 hover:border-red-700" :disabled="!item">
                  <X class="h-4 w-4" />
                  完全に削除
                </Button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </Card>

    <!-- ダイアログ -->
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

    <AlertDialog :open="showRestoreSelectedDialog" @update:open="(open) => showRestoreSelectedDialog = open">
      <AlertDialogContent class="bg-white">
        <AlertDialogHeader>
          <AlertDialogTitle>
            {{ restoreMode === 'selected' ? `選択した${selectedItems.size}件を復元しますか？` : `選択以外の${sortedItems.length - selectedItems.size}件を復元しますか？` }}
          </AlertDialogTitle>
          <AlertDialogDescription>復元されたアイテムは元の場所に戻ります。</AlertDialogDescription>
        </AlertDialogHeader>
        <AlertDialogFooter>
          <AlertDialogCancel>キャンセル</AlertDialogCancel>
          <AlertDialogAction @click="confirmRestoreSelected" class="bg-green-600 text-white border-green-600 hover:bg-green-700 hover:border-green-700">
            復元
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