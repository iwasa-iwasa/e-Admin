<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import { ref, computed, onMounted, nextTick, watch, onUnmounted } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import { Trash2, ArrowLeft, RotateCcw, X, Calendar as CalendarIcon, StickyNote, BarChart3, ArrowUp, ArrowDown, Bell, CheckCircle, Undo2, Filter, Search, HelpCircle, AlertCircle } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardTitle, CardHeader } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle } from '@/components/ui/alert-dialog'
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Checkbox } from '@/components/ui/checkbox'
import { VueDatePicker } from '@vuepic/vue-datepicker'
import { ja } from 'date-fns/locale'
import '@vuepic/vue-datepicker/dist/main.css'
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
  department_id?: number | null
  owner_department_id?: number | null
  is_mine?: boolean
}

type SortField = 'title' | 'deletedAt' | 'type' | 'creatorName'
type SortOrder = 'asc' | 'desc'

const props = defineProps<{
  trashItems: TrashItem[]
  currentDepartmentFilter?: string
  userDepartmentId?: number
  departments?: Array<{ id: number; name: string }>
}>()

// リアクティブ変数
const trashItems = ref<TrashItem[]>(props.trashItems || [])
const departmentFilter = ref(props.currentDepartmentFilter || 'all')
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

watch(departmentFilter, (newVal) => {
    router.get('/trash', { department_filter: newVal }, {
        preserveState: false,
        replace: true,
        onSuccess: (page) => {
            // レスポンスからtrashItemsを更新
            trashItems.value = page.props.trashItems || []
        }
    })
})

// propsの変更を監視してtrashItemsを更新
watch(() => props.trashItems, (newItems) => {
    trashItems.value = newItems || []
}, { immediate: true })

// フィルター
const filterScope = ref<'all' | 'mine' | 'department'>('all')
const filterType = ref<ItemType | 'all'>('all')
const filterTitle = ref('')
const filterCreator = ref<string | 'all'>('all')
const filterDateFrom = ref<Date | null>(null)
const filterDateTo = ref<Date | null>(null)
const showFilterDialog = ref(false)
const searchInputRef = ref<HTMLInputElement | null>(null)
const isHelpOpen = ref(false)

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
    if (filterScope.value === 'mine' && !item.is_mine) return false
    if (filterScope.value === 'department' && item.is_mine) return false // 自部署（他者のアイテム）のみ

    if (filterType.value !== 'all' && item.type !== filterType.value) return false
    if (filterTitle.value && !item.title.toLowerCase().includes(filterTitle.value.toLowerCase())) return false
    if (filterCreator.value !== 'all' && item.creatorName !== filterCreator.value) return false
    if (filterDateFrom.value || filterDateTo.value) {
      const itemDate = new Date(item.deletedAt)
      if (filterDateFrom.value) {
        const startDate = new Date(filterDateFrom.value)
        startDate.setHours(0, 0, 0, 0)
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
    case 'event': return { icon: CalendarIcon, label: '共有カレンダー', color: 'bg-blue-100 text-blue-700 border-blue-200 dark:bg-blue-900/30 dark:text-blue-300 dark:border-blue-800' }
    case 'shared_note': return { icon: StickyNote, label: '共有メモ', color: 'bg-yellow-100 text-orange-600 border-yellow-200 dark:bg-orange-900/30 dark:text-orange-300 dark:border-orange-800' }
    case 'survey': return { icon: BarChart3, label: 'アンケート', color: 'bg-purple-100 text-purple-700 border-purple-200 dark:bg-purple-900/30 dark:text-purple-300 dark:border-purple-800' }
    case 'reminder': return { icon: Bell, label: '個人リマインダー', color: 'bg-green-100 text-green-700 border-green-200 dark:bg-green-900/30 dark:text-green-300 dark:border-green-800' }
    default: return { icon: StickyNote, label: '不明', color: 'bg-gray-100 text-gray-700 border-gray-300 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-700' }
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
      // 通知センターを更新
      window.dispatchEvent(new CustomEvent('notification-updated'))
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
      // 通知センターを更新
      window.dispatchEvent(new CustomEvent('notification-updated'))
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
  
  // 通知センターからの削除を検知してゴミ箱を更新
  window.addEventListener('trash-updated', handleTrashUpdate)
})

onUnmounted(() => {
  window.removeEventListener('trash-updated', handleTrashUpdate)
})

const handleTrashUpdate = () => {
  router.reload({ only: ['trashItems'], preserveScroll: true } as any)
}
</script>

<template>
  <Head title="ゴミ箱" />
  
  <div class="max-w-[1800px] mx-auto h-full p-6">
    <Card class="h-full overflow-hidden flex flex-col">
      <!-- ヘッダー部分 -->
      <div class="p-6 border-b border-border shrink-0 bg-background">
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
            <Trash2 class="h-6 w-6 text-muted-foreground" />
            <CardTitle class="text-2xl">ゴミ箱</CardTitle>
            
            <Button
              variant="ghost"
              size="icon"
              class="h-5 w-5 p-0 text-gray-500 hover:text-gray-700"
              @click="isHelpOpen = true"
              title="ゴミ箱の使い方"
            >
              <HelpCircle class="h-5 w-5" />
            </Button>
          </div>
          
          <!-- 検索・フィルター・アクションボタン -->
          <div class="flex items-center gap-2">
            <Select v-model="departmentFilter">
              <SelectTrigger class="w-[140px] h-10 border-gray-300 dark:border-gray-600 bg-background text-sm">
                <SelectValue placeholder="表示ゴミ箱" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">すべて</SelectItem>
                <SelectItem value="public">🌐 全社</SelectItem>
                <div class="px-2 py-1.5 text-xs font-semibold text-gray-500 bg-gray-50 border-t border-b mb-1">部署</div>
                <template v-if="$page.props.auth.user.role_type === 'company_admin'">
                  <!-- 全社管理者は全部署を選択可能 -->
                  <SelectItem v-for="dept in props.departments" :key="dept.id" :value="`dept_${dept.id}`">
                      {{ dept.name }} {{ props.userDepartmentId === dept.id ? '(自部署)' : '' }}
                  </SelectItem>
                </template>
                <template v-else>
                  <!-- 部署管理者/メンバーは自分の部署のみ選択可能 -->
                  <SelectItem 
                    v-for="dept in props.departments.filter(d => d.id === props.userDepartmentId)" 
                    :key="dept.id" 
                    :value="`dept_${dept.id}`"
                  >
                      {{ dept.name }} (自部署)
                  </SelectItem>
                </template>
              </SelectContent>
            </Select>
            <Button 
              variant="outline" 
              size="icon" 
              @click="showFilterDialog = !showFilterDialog" 
              :class="showFilterDialog ? 'bg-gray-100 dark:bg-muted' : ''"
            >
              <Filter class="h-4 w-4" />
            </Button>
            <div class="relative">
              <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
              <input
                ref="searchInputRef"
                placeholder="タイトルなどで検索"
                v-model="filterTitle"
                class="pl-9 pr-9 flex h-10 w-full rounded-md border border-gray-300 dark:border-gray-600 bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 w-[300px]"
              />
              <button v-if="filterTitle" @click="filterTitle = ''" class="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground">
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
          </div>
        </div>
        
        <!-- フィルターパネル -->
        <div v-if="showFilterDialog" class="space-y-3 p-4 bg-muted/50 rounded-lg border border-border mb-4">
          <div class="grid grid-cols-4 gap-4">
            <div>
              <label class="text-xs font-medium text-gray-700 mb-1.5 block">対象スコープ</label>
              <Select v-model="filterScope">
                <SelectTrigger class="h-9 border-gray-300">
                  <SelectValue placeholder="すべてのアイテム" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">すべてのアイテム</SelectItem>
                  <SelectItem value="mine">自分が削除したアイテム</SelectItem>
                  <SelectItem value="department">自部署のアイテム（他者）</SelectItem>
                </SelectContent>
              </Select>
            </div>
            <div>
              <label class="text-xs font-medium text-gray-700 mb-1.5 block">種類</label>
              <Select v-model="filterType">
                <SelectTrigger class="h-9 border-gray-300">
                  <SelectValue placeholder="すべての種類" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">すべての種類</SelectItem>
                  <SelectItem value="event" class="flex items-center gap-2">
                    <CalendarIcon class="h-4 w-4 inline text-blue-700" />
                    共有カレンダー
                  </SelectItem>
                  <SelectItem value="shared_note" class="flex items-center gap-2">
                    <StickyNote class="h-4 w-4 inline text-orange-600" />
                    共有メモ
                  </SelectItem>
                  <SelectItem value="survey" class="flex items-center gap-2">
                    <BarChart3 class="h-4 w-4 inline text-purple-700" />
                    アンケート
                  </SelectItem>
                  <SelectItem value="reminder" class="flex items-center gap-2">
                    <Bell class="h-4 w-4 inline text-green-700" />
                    個人リマインダー
                  </SelectItem>
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
              <label class="text-xs font-medium text-foreground mb-1.5 block">削除日</label>
              <div class="flex gap-2">
                <VueDatePicker
                  v-model="filterDateFrom"
                  :locale="ja"
                  :week-start="0"
                  auto-apply
                  teleport-center
                  placeholder="開始日"
                  class="flex-1"
                />
                <VueDatePicker
                  v-model="filterDateTo"
                  :locale="ja"
                  :week-start="0"
                  auto-apply
                  teleport-center
                  placeholder="終了日"
                  class="flex-1"
                />
              </div>
            </div>
          </div>
        </div>
        
        <div class="flex items-center justify-between">
          <p class="text-sm text-muted-foreground">
            削除されたアイテム <span class="font-semibold text-foreground">{{ sortedItems.length }}件</span>
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
      <div v-if="trashItems.length > 0" class="sticky top-0 z-10 bg-background border-b border-border shrink-0">
        <div class="grid grid-cols-12 gap-3 py-3 px-4 text-sm font-medium text-muted-foreground">
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
      <div class="flex-1 overflow-y-auto bg-muted/10">
        <!-- 空の状態 -->
        <div v-if="trashItems.length === 0" class="flex items-center justify-center h-full">
          <div class="text-center py-16">
            <Trash2 class="h-16 w-16 mx-auto mb-4 text-muted-foreground/50" />
            <h2 class="text-xl font-semibold mb-2 text-foreground">ゴミ箱は空です</h2>
            <p class="text-muted-foreground">削除されたアイテムはここに表示されます</p>
          </div>
        </div>
        
        <!-- アイテムリスト -->
        <div v-else class="p-2 space-y-3">
          <div 
            v-for="item in sortedItems" 
            :key="item.id" 
            :id="`item-${item.id}`" 
            :class="[
              'bg-card rounded-lg border transition-all duration-200 hover:shadow-md',
              selectedItems.has(item.id) ? 'border-blue-500 ring-2 ring-blue-100 dark:ring-blue-900' : 'border-border'
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
                
                <!-- バッジ（種類）と（所有属性） -->
                <div class="col-span-2 flex flex-col items-center justify-center gap-1">
                  <Badge 
                    v-if="item" 
                    variant="outline" 
                    :class="getItemTypeInfo(item.type).color"
                  >
                    <component :is="getItemTypeInfo(item.type).icon" class="h-4 w-4" />
                    <span class="text-xs font-medium ml-1">{{ getItemTypeInfo(item.type).label }}</span>
                  </Badge>
                  <Badge 
                    v-if="item" 
                    :variant="item.is_mine ? 'default' : 'secondary'"
                    class="text-[0.65rem] px-1 py-0 h-4"
                  >
                    {{ item.is_mine ? '自分のアイテム' : '自部署（共有）' }}
                  </Badge>
                </div>
                
                <!-- タイトルと詳細 -->
                <div class="col-span-6 min-w-0">
                  <!-- メイン行（列の基準） -->
                  <div class="flex items-center gap-2 min-w-0">
                    <h3 class="text-sm truncate font-semibold text-foreground">
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
                    class="w-[150px] flex items-center justify-center gap-2 hover:bg-green-50 dark:hover:bg-green-900/20 hover:border-green-300 hover:text-green-700"
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
                    class="w-[150px] flex items-center justify-center gap-2 bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-400 border-red-200 dark:border-red-800 hover:bg-red-600 hover:text-white hover:border-red-600"
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
      <AlertDialogContent>
        <AlertDialogHeader>
          <AlertDialogTitle>完全に削除しますか?</AlertDialogTitle>
          <AlertDialogDescription>このアイテムを完全に削除します。この操作は取り消せません。</AlertDialogDescription>
        </AlertDialogHeader>
        <AlertDialogFooter>
          <AlertDialogCancel @click="itemToDelete = null" class="hover:bg-gray-100 dark:hover:bg-gray-700 dark:hover:text-gray-100">キャンセル</AlertDialogCancel>
          <AlertDialogAction @click="handlePermanentDelete" class="bg-red-600 text-white border-red-600 hover:bg-red-700 hover:border-red-700 dark:bg-red-700 dark:hover:bg-red-800">
            完全に削除
          </AlertDialogAction>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>

    <AlertDialog :open="showRestoreSelectedDialog" @update:open="(open) => showRestoreSelectedDialog = open">
      <AlertDialogContent>
        <AlertDialogHeader>
          <AlertDialogTitle>
            {{ restoreMode === 'selected' ? `選択した${selectedItems.size}件を復元しますか?` : `選択以外の${sortedItems.length - selectedItems.size}件を復元しますか?` }}
          </AlertDialogTitle>
          <AlertDialogDescription>復元されたアイテムは元の場所に戻ります。</AlertDialogDescription>
        </AlertDialogHeader>
        <AlertDialogFooter>
          <AlertDialogCancel class="hover:bg-gray-100 dark:hover:bg-gray-700 dark:hover:text-gray-100">キャンセル</AlertDialogCancel>
          <AlertDialogAction @click="confirmRestoreSelected" class="bg-green-600 text-white border-green-600 hover:bg-green-700 hover:border-green-700 dark:bg-green-700 dark:hover:bg-green-800">
            復元
          </AlertDialogAction>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>

    <AlertDialog :open="showDeleteSelectedDialog" @update:open="(open) => showDeleteSelectedDialog = open">
      <AlertDialogContent>
        <AlertDialogHeader>
          <AlertDialogTitle>
            {{ deleteMode === 'selected' ? `選択した${selectedItems.size}件を削除しますか?` : `選択以外の${sortedItems.length - selectedItems.size}件を削除しますか?` }}
          </AlertDialogTitle>
          <AlertDialogDescription>この操作は取り消せません。</AlertDialogDescription>
        </AlertDialogHeader>
        <AlertDialogFooter>
          <AlertDialogCancel class="hover:bg-gray-100 dark:hover:bg-gray-700 dark:hover:text-gray-100">キャンセル</AlertDialogCancel>
          <AlertDialogAction @click="confirmDeleteSelected" class="bg-red-600 text-white border-red-600 hover:bg-red-700 hover:border-red-700 dark:bg-red-700 dark:hover:bg-red-800">
            削除
          </AlertDialogAction>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>

    <AlertDialog :open="showEmptyTrashDialog" @update:open="(open) => showEmptyTrashDialog = open">
      <AlertDialogContent>
        <AlertDialogHeader>
          <AlertDialogTitle>ゴミ箱を空にしますか?</AlertDialogTitle>
          <AlertDialogDescription>すべてのアイテムを完全に削除します。この操作は取り消せません。</AlertDialogDescription>
        </AlertDialogHeader>
        <AlertDialogFooter>
          <AlertDialogCancel class="hover:bg-gray-100 dark:hover:bg-gray-700 dark:hover:text-gray-100">キャンセル</AlertDialogCancel>
          <AlertDialogAction @click="handleEmptyTrash" class="bg-red-600 text-white border-red-600 hover:bg-red-700 hover:border-red-700 dark:bg-red-700 dark:hover:bg-red-800">
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
    
    <!-- ヘルプダイアログ -->
    <Dialog :open="isHelpOpen" @update:open="isHelpOpen = $event">
      <DialogContent class="max-w-3xl max-h-[90vh] flex flex-col">
        <DialogHeader>
          <DialogTitle class="flex items-center gap-2 text-xl">
            <Trash2 class="h-6 w-6 text-muted-foreground" />
            ゴミ箱の使い方
          </DialogTitle>
          <DialogDescription class="text-base">
            ゴミ箱の基本的な使い方をご説明します。削除したアイテムの復元や完全削除ができます。
          </DialogDescription>
        </DialogHeader>
        <div class="space-y-6 overflow-y-auto flex-1 pr-2">
          <!-- 基本操作 -->
          <div class="relative pl-4 border-l-4 border-blue-500 bg-gradient-to-r from-blue-50 to-transparent dark:from-blue-950/30 p-4 rounded-r-lg">
            <h3 class="font-semibold mb-3 text-lg">🔄 基本操作</h3>
            <div class="space-y-4">
              <div class="bg-white dark:bg-gray-800 p-3 rounded-lg shadow-sm">
                <div class="flex items-start gap-4">
                  <div class="flex-shrink-0 pt-1 pointer-events-none opacity-100">
                    <div class="flex gap-2 p-2 rounded-lg">
                      <Button 
                        variant="outline" 
                        size="sm" 
                        class="flex items-center justify-center gap-2 hover:bg-green-50 dark:hover:bg-green-900/20 hover:border-green-300 hover:text-green-700"
                        tabindex="-1"
                      >
                        <RotateCcw class="h-3.5 w-3.5" />
                        <span class="text-xs">元に戻す</span>
                      </Button>
                      <Button 
                        variant="outline" 
                        size="sm" 
                        class="flex items-center justify-center gap-2 bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-400 border-red-200 dark:border-red-800"
                        tabindex="-1"
                      >
                        <X class="h-3.5 w-3.5" />
                        <span class="text-xs">削除</span>
                      </Button>
                    </div>
                  </div>
                  <div class="flex-1">
                    <p class="font-medium text-sm mb-1">復元と完全削除</p>
                    <p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed">
                      「元に戻す」でアイテムを復元し、「削除」で完全に消去します（取り消せません）。
                    </p>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- 一括操作 -->
          <div class="relative pl-4 border-l-4 border-green-500 bg-gradient-to-r from-green-50 to-transparent dark:from-green-950/30 p-4 rounded-r-lg">
            <h3 class="font-semibold mb-3 text-lg">✅ 一括操作</h3>
            <div class="space-y-4">
              <div class="bg-white dark:bg-gray-800 p-3 rounded-lg shadow-sm">
                <div class="flex items-start gap-4">
                  <div class="flex-shrink-0 pt-1 pointer-events-none opacity-100">
                    <div class="p-2 rounded-lg space-y-2">
                      <div class="flex items-center gap-2">
                        <input 
                          type="checkbox" 
                          checked
                          class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                          tabindex="-1"
                          readonly
                        />
                        <span class="text-xs text-gray-600 dark:text-gray-400">選択中</span>
                      </div>
                      <div class="flex flex-col gap-1">
                        <Button 
                          variant="outline" 
                          size="sm"
                          class="gap-2 bg-green-600 text-white border-green-600 w-full text-xs h-8"
                          tabindex="-1"
                        >
                          <RotateCcw class="h-3 w-3" />
                          選択を復元
                        </Button>
                        <Button 
                          variant="outline" 
                          size="sm"
                          class="gap-2 bg-red-600 text-white border-red-600 w-full text-xs h-8"
                          tabindex="-1"
                        >
                          <Trash2 class="h-3 w-3" />
                          選択を完全削除
                        </Button>
                      </div>
                    </div>
                  </div>
                  <div class="flex-1">
                    <p class="font-medium text-sm mb-1">一括選択と操作</p>
                    <p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed">
                      チェックボックスで複数のアイテムを選択し、まとめて復元や削除ができます。効率的にゴミ箱を整理できます。
                    </p>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- フィルター機能 -->
          <div class="relative pl-4 border-l-4 border-purple-500 bg-gradient-to-r from-purple-50 to-transparent dark:from-purple-950/30 p-4 rounded-r-lg">
            <h3 class="font-semibold mb-3 text-lg">🔍 フィルター機能</h3>
            <div class="space-y-4">
              <div class="bg-white dark:bg-gray-800 p-3 rounded-lg shadow-sm">
                <div class="flex items-start gap-4">
                  <div class="flex-shrink-0 pt-1 pointer-events-none opacity-100 space-y-2">
                    <div class="flex gap-2 p-2 rounded-lg">
                      <Button 
                        variant="outline" 
                        size="icon" 
                        class="h-8 w-8 bg-gray-100 dark:bg-gray-700"
                        tabindex="-1"
                      >
                        <Filter class="h-4 w-4" />
                      </Button>
                      <div class="relative flex-1 min-w-[120px]">
                        <Search class="absolute left-2 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-gray-400" />
                        <div class="pl-8 pr-2 h-8 w-full rounded-md border border-input bg-background flex items-center text-xs text-muted-foreground">
                          検索...
                        </div>
                      </div>
                    </div>
                    <div class="flex flex-wrap gap-1 p-2 rounded-lg">
                      <Badge variant="outline" class="bg-blue-100 dark:bg-blue-900/20 text-blue-700 dark:text-blue-400 border-blue-200 dark:border-blue-800 text-[10px] px-1.5 py-0.5 gap-1">
                        <CalendarIcon class="h-3 w-3" />
                        共有カレンダー
                      </Badge>
                      <Badge variant="outline" class="bg-yellow-100 dark:bg-yellow-900/20 text-orange-600 dark:text-orange-400 border-yellow-200 dark:border-yellow-800 text-[10px] px-1.5 py-0.5 gap-1">
                        <StickyNote class="h-3 w-3" />
                        共有メモ
                      </Badge>
                    </div>
                  </div>
                  <div class="flex-1">
                    <p class="font-medium text-sm mb-1">検索と絞り込み</p>
                    <p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed">
                      タイトル検索や、種類・作成者・削除日での絞り込みが可能です。フィルターアイコンで詳細検索パネルを開けます。
                    </p>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- 注意事項 -->
          <div class="relative pl-4 border-l-4 border-red-500 bg-gradient-to-r from-red-50 to-transparent dark:from-red-950/30 p-4 rounded-r-lg">
            <h3 class="font-semibold mb-3 text-lg">⚠️ 注意事項</h3>
            <div class="bg-white dark:bg-gray-800 p-3 rounded-lg shadow-sm">
              <div class="flex items-start gap-4">
                <AlertCircle class="h-6 w-6 text-red-600 dark:text-red-400 flex-shrink-0 mt-0.5" />
                <div class="flex-1">
                  <p class="font-medium text-sm mb-1">自動削除について</p>
                  <p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed">
                    ゴミ箱のアイテムは一定期間（例：30日）経過すると自動的に完全に削除され、復元できなくなります。重要なアイテムは早めに復元しましょう。
                  </p>
                </div>
              </div>
            </div>
          </div>
        </div>
        
        <div class="mt-4 p-3 bg-blue-50 dark:bg-blue-950/30 rounded-lg border border-blue-200 dark:border-blue-800 flex-shrink-0">
          <p class="text-sm text-blue-800 dark:text-blue-200 flex items-center gap-2">
            <span class="text-lg">💡</span>
            <span>復元したアイテムは元の場所に戻ります</span>
          </p>
        </div>
      </DialogContent>
    </Dialog>
  </div>
</template>
