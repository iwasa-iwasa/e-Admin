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
  if (!item) return
  
  const restoreData = {
    type: item.type,
    item_id: item.item_id
  }
  
  router.post(route('trash.restore', { id: id }), restoreData, {
    preserveScroll: true,
    onSuccess: () => {
      trashItems.value = trashItems.value.filter((i) => i.id !== id)
      const itemTypeLabel = getItemTypeInfo(item.type).label
      showMessage(`${itemTypeLabel}が元に戻されました。`, 'success')
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
  const itemsToRestore = restoreMode.value === 'selected' 
    ? sortedItems.value.filter(item => selectedItems.value.has(item.id))
    : sortedItems.value.filter(item => !selectedItems.value.has(item.id))
  
  if (itemsToRestore.length === 0) {
    showMessage('復元するアイテムがありません', 'success')
    showRestoreSelectedDialog.value = false
    return
  }
  
  const restoreData = {
    items: itemsToRestore.map(item => ({
      id: item.id,
      type: item.type,
      item_id: item.item_id
    }))
  }
  
  router.post(route('trash.restoreMultiple'), restoreData, {
    preserveScroll: true,
    onSuccess: () => {
      const restoredIds = itemsToRestore.map(item => item.id)
      trashItems.value = trashItems.value.filter(item => !restoredIds.includes(item.id))
      selectedItems.value.clear()
      showMessage(`${itemsToRestore.length}件のアイテムを復元しました`, 'success')
    },
    onError: (errors) => {
      console.error('Restore error:', errors)
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
      <div class="p-6 border-b border-gray-200 shrink-0 bg-white">
        <div class="flex items-center justify-between mb-4">
          <!-- タイトル部分 -->
          <div class="flex items-center gap-3">
            <Button
              variant="ghost"
              size="icon"
              @click="router.get(route('dashboard'))"
            >
              <ArrowLeft class="h-5 w-5" />
            </Button>
            <Trash2 class="h-6 w-6 text-gray-600" />
            <CardTitle class="text-2xl">ゴミ箱</CardTitle>
          </div>
          
          <!-- 検索・フィルター・アクションボタン -->
          <div class="flex items-center gap-2">
            <Button 
              variant="outline" 
              size="icon" 
              @click="showFilterDialog = !showFilterDialog" 
              :class="showFilterDialog ? 'bg-gray-100' : ''"
            >
              <Filter class="h-4 w-4" />
            </Button>
            <div class="relative">
              <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
              <input
                ref="searchInputRef"
                placeholder="タイトルで検索"
                v-model="filterTitle"
                class="pl-9 pr-9 flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 w-[300px]"
              />
              <button v-if="filterTitle" @click="filterTitle = ''" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                <X class="h-4 w-4" />
              </button>
            </div>
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
              選択を完全削除
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
        
        <!-- フィルターパネル -->
        <div v-if="showFilterDialog" class="space-y-3 p-4 bg-gray-50 rounded-lg border border-gray-200 mb-4">
          <div class="grid grid-cols-3 gap-4">
            <div>
              <label class="text-xs font-medium text-gray-700 mb-1.5 block">種類</label>
              <Select v-model="filterType">
                <SelectTrigger class="h-9 border-gray-300">
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
              <label class="text-xs font-medium text-gray-700 mb-1.5 block">作成者</label>
              <Select v-model="filterCreator">
                <SelectTrigger class="h-9 border-gray-300">
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
              <label class="text-xs font-medium text-gray-700 mb-1.5 block">削除日</label>
              <div class="flex gap-2">
                <Input
                  type="date"
                  v-model="filterDateFrom"
                  placeholder="開始日"
                  class="h-9 text-xs flex-1"
                />
                <Input
                  type="date"
                  v-model="filterDateTo"
                  placeholder="終了日"
                  class="h-9 text-xs flex-1"
                />
              </div>
            </div>
          </div>
        </div>
        
        <div class="flex items-center justify-between">
          <p class="text-sm text-gray-600">
            削除されたアイテム <span class="font-semibold text-gray-900">{{ sortedItems.length }}件</span>
          </p>
          <div v-if="selectedItems.size > 0" class="flex items-center gap-2">
            <input 
              type="checkbox" 
              :checked="isAllSelected" 
              @change="(e) => toggleAll((e.target as HTMLInputElement).checked)"
              class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
            />
            <span class="text-sm text-gray-600">{{ selectedItems.size }}件選択中</span>
          </div>
        </div>
      </div>
      
      <!-- テーブルヘッダー (固定) -->
      <div v-if="trashItems.length > 0" class="sticky top-0 z-10 bg-white border-b border-gray-200 shrink-0">
        <div class="grid grid-cols-12 gap-3 py-3 px-4 text-sm font-medium text-gray-700">
          <div class="col-span-1 flex items-center justify-center">
            <div class="translate-x-2">
              <input 
                type="checkbox" 
                :checked="isAllSelected" 
                @change="(e) => toggleAll((e.target as HTMLInputElement).checked)"
                class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
              />
            </div>
          </div>
          <div class="col-span-2 flex items-center justify-center">種類</div>
          <div class="col-span-6 flex items-center">タイトルと説明</div>
          <div class="col-span-3 flex items-center justify-center">アクション</div>
        </div>
      </div>
      
      <!-- メインコンテンツ -->
      <div class="flex-1 overflow-y-auto bg-gray-50">
        <!-- 空の状態 -->
        <div v-if="trashItems.length === 0" class="flex items-center justify-center h-full">
          <div class="text-center py-16">
            <Trash2 class="h-16 w-16 mx-auto mb-4 text-gray-300" />
            <h2 class="text-xl font-semibold mb-2 text-gray-900">ゴミ箱は空です</h2>
            <p class="text-gray-500">削除されたアイテムはここに表示されます</p>
          </div>
        </div>
        
        <!-- アイテムリスト -->
        <div v-else class="p-2 space-y-3">
          <div 
            v-for="item in sortedItems" 
            :key="item.id" 
            :id="`item-${item.id}`" 
            :class="[
              'bg-white rounded-lg border transition-all duration-200 hover:shadow-md',
              selectedItems.has(item.id) ? 'border-blue-500 ring-2 ring-blue-100' : 'border-gray-200'
            ]"
            @click="handleRowClick($event, item)"
          >
            <div class="grid grid-cols-12 gap-3 px-4 py-3">
                <!-- チェックボックス -->
                <div class="col-span-1 flex items-center justify-center">
                  <div class="w-6 flex justify-center">
                    <input 
                      type="checkbox" 
                      :checked="selectedItems.has(item.id)" 
                      @change="(e) => toggleItem(item.id, (e.target as HTMLInputElement).checked)"
                      class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 cursor-pointer"
                    />
                  </div>
                </div>
                
                <!-- バッジ（種類） -->
                <div class="col-span-2 flex items-center justify-center">
                  <Badge 
                    v-if="item" 
                    variant="outline" 
                    :class="getItemTypeInfo(item.type).color"
                  >
                    <component :is="getItemTypeInfo(item.type).icon" class="h-4 w-4" />
                    <span class="text-xs font-medium ml-1">{{ getItemTypeInfo(item.type).label }}</span>
                  </Badge>
                </div>
                
                <!-- タイトルと詳細 -->
                <div class="col-span-6 min-w-0">
                  <!-- メイン行（列の基準） -->
                  <div class="flex items-center gap-2 min-w-0">
                    <h3 class="text-sm truncate font-semibold text-gray-900">
                      {{ item?.title || '不明' }}
                    </h3>

                    <!-- モバイル用バッジ（横に出す） -->
                    <Badge
                      v-if="item"
                      variant="outline"
                      :class="['gap-1 px-2 py-0.5 text-xs inline-flex shrink-0 sm:hidden', getItemTypeInfo(item.type).color]"
                    >
                      <component :is="getItemTypeInfo(item.type).icon" class="h-3 w-3" />
                      <span class="font-medium">{{ getItemTypeInfo(item.type).label }}</span>
                    </Badge>
                  </div>

                  <!-- サブ情報 -->
                  <div class="mt-1.5 space-y-1">
                    <p v-if="item?.description" class="text-xs text-gray-600 line-clamp-2">
                      {{ item.description }}
                    </p>

                    <div class="flex flex-wrap gap-4 text-xs text-gray-500">
                      <div class="flex items-center gap-1.5">
                        <span class="font-medium">作成者:</span>
                        <span>{{ item?.creatorName || '不明' }}</span>
                      </div>
                      <div class="flex items-center gap-1.5">
                        <span class="font-medium">削除日時:</span>
                        <span class="whitespace-nowrap">{{ item?.deletedAt || '不明' }}</span>
                      </div>
                    </div>
                  </div>
                </div>

                
                <!-- アクションボタン -->
                <div class="col-span-3 flex flex-col items-center justify-center gap-2">
                  <Button 
                    variant="outline" 
                    size="sm" 
                    @click.stop="handleRestore(item.id)" 
                    class="w-[150px] flex items-center justify-center gap-2 hover:bg-green-50 hover:border-green-300 hover:text-green-700"
                    :disabled="!item"
                  >
                    <RotateCcw class="h-3.5 w-3.5 sm:h-4 sm:w-4" />
                    <span class="hidden sm:inline">元に戻す</span>
                    <span class="sm:hidden">復元</span>
                  </Button>
                  <Button 
                    variant="outline" 
                    size="sm" 
                    @click.stop="itemToDelete = item?.id" 
                    class="w-[150px] flex items-center justify-center gap-2 bg-red-50 text-red-700 border-red-200 hover:bg-red-600 hover:text-white hover:border-red-600"
                    :disabled="!item"
                  >
                    <X class="h-3.5 w-3.5 sm:h-4 sm:w-4" />
                    <span class="hidden sm:inline">完全に削除</span>
                    <span class="sm:hidden">削除</span>
                  </Button>
                </div>
            </div>
          </div>
        </div>
      </div>
    </Card>

    <!-- ダイアログ（元のまま） -->
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