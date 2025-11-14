<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import { Trash2, ArrowLeft, RotateCcw, X, Calendar as CalendarIcon, StickyNote, BarChart3, ArrowUp, ArrowDown } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { ScrollArea } from '@/components/ui/scroll-area'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle } from '@/components/ui/alert-dialog'
import { useToast } from '@/components/ui/toast/use-toast'
import { Toaster } from '@/components/ui/toast/toaster'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

defineOptions({
  layout: AuthenticatedLayout,
})

type ItemType = 'shared_note' | 'event' | 'survey'

interface TrashItem {
  id: string
  type: ItemType
  title: string
  deletedAt: string
  item_id: string
  permanent_delete_at: string
}

type SortField = 'title' | 'deletedAt' | 'type'
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

const { toast } = useToast()

const getItemTypeInfo = (type: ItemType) => {
  switch (type) {
    case 'event': return { icon: CalendarIcon, label: '予定', color: 'bg-blue-100 text-blue-700 border-blue-200' }
    case 'shared_note': return { icon: StickyNote, label: 'メモ', color: 'bg-yellow-100 text-yellow-700 border-yellow-200' }
    case 'survey': return { icon: BarChart3, label: 'アンケート', color: 'bg-purple-100 text-purple-700 border-purple-200' }
    default: return { icon: StickyNote, label: '不明', color: 'bg-gray-100 text-gray-700 border-gray-200' }
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

const sortedItems = computed(() => {
  return [...trashItems.value].sort((a, b) => {
    let comparison = 0
    if (sortField.value === 'title') {
      comparison = a.title.localeCompare(b.title, 'ja')
    } else if (sortField.value === 'deletedAt') {
      comparison = a.deletedAt.localeCompare(b.deletedAt)
    } else if (sortField.value === 'type') {
      comparison = a.type.localeCompare(b.type)
    }
    return sortOrder.value === 'asc' ? comparison : -comparison
  })
})

const handleRestore = (id: string) => {
  const item = trashItems.value.find((i) => i.id === id)
  router.post(route('trash.restore', id), {}, {
    preserveScroll: true,
    onSuccess: () => {
      if (item) {
        trashItems.value = trashItems.value.filter((i) => i.id !== id)
        toast({ title: 'メモが元に戻されました' })
      }
    },
    onError: (errors) => {
      console.error('Restore error:', errors)
      toast({ title: '復元に失敗しました', variant: 'destructive' })
    }
  })
}

const handlePermanentDelete = () => {
  if (itemToDelete.value) {
    const item = trashItems.value.find((i) => i.id === itemToDelete.value)
    router.delete(route('trash.destroy', itemToDelete.value), {
      preserveScroll: true,
      onSuccess: () => {
        if (item) {
          trashItems.value = trashItems.value.filter((i) => i.id !== itemToDelete.value)
          toast({ title: `「${item.title}」を完全に削除しました` })
        }
      }
    })
    itemToDelete.value = null
  }
}

const handleEmptyTrash = () => {
  router.delete(route('trash.empty'), {
    preserveScroll: true,
    onSuccess: () => {
      trashItems.value = []
      toast({ title: 'ゴミ箱を空にしました' })
    }
  })
  showEmptyTrashDialog.value = false
}

</script>

<template>
  <Head title="ゴミ箱" />
  <div class="min-h-screen bg-gray-50">
    <header class="bg-white border-b border-gray-200 sticky top-0 z-10">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 py-4">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-3">
            <Button variant="ghost" size="icon" @click="router.get('/')">
              <ArrowLeft class="h-5 w-5" />
            </Button>
            <div class="flex items-center gap-2">
              <Trash2 class="h-6 w-6 text-gray-600" />
              <div>
                <h1 class="text-gray-900">ゴミ箱</h1>
                <p class="text-xs text-gray-500">削除されたアイテム ({{ trashItems.length }}件)</p>
              </div>
            </div>
          </div>
          <Button v-if="trashItems.length > 0" variant="outline" @click="showEmptyTrashDialog = true" class="gap-2">
            <Trash2 class="h-4 w-4" />
            ゴミ箱を空にする
          </Button>
        </div>
      </div>
    </header>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 py-6">
      <div v-if="trashItems.length === 0">
        <Card>
          <CardContent class="py-16 text-center">
            <Trash2 class="h-16 w-16 mx-auto mb-4 text-gray-300" />
            <h2 class="mb-2 text-gray-900">ゴミ箱は空です</h2>
            <p class="text-gray-500">削除されたアイテムはここに表示されます</p>
          </CardContent>
        </Card>
      </div>
      <Card v-else>
        <ScrollArea class="h-[calc(100vh-200px)]">
          <Table>
            <TableHeader>
              <TableRow>
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
                <TableHead class="cursor-pointer hover:bg-gray-50 select-none" @click="handleSort('deletedAt')">
                  削除した日時
                  <ArrowUp v-if="sortField === 'deletedAt' && sortOrder === 'asc'" class="h-4 w-4 inline ml-1" />
                  <ArrowDown v-if="sortField === 'deletedAt' && sortOrder === 'desc'" class="h-4 w-4 inline ml-1" />
                </TableHead>
                <TableHead class="text-right">アクション</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              <TableRow v-for="item in sortedItems" :key="item.id || 'unknown'">
                <TableCell v-if="item">
                  <Badge variant="outline" :class="['gap-1', getItemTypeInfo(item.type || 'shared_note').color]">
                    <component :is="getItemTypeInfo(item.type || 'shared_note').icon" class="h-4 w-4" />
                    {{ getItemTypeInfo(item.type || 'shared_note').label }}
                  </Badge>
                </TableCell>
                <TableCell v-else>
                  <Badge variant="outline" class="gap-1 bg-gray-100 text-gray-700 border-gray-200">
                    <StickyNote class="h-4 w-4" />
                    不明
                  </Badge>
                </TableCell>
                <TableCell>{{ item?.title || '不明' }}</TableCell>
                <TableCell class="text-gray-600">{{ item?.deletedAt || '不明' }}</TableCell>
                <TableCell class="text-right">
                  <div class="flex items-center justify-end gap-2">
                    <Button variant="outline" size="sm" @click="handleRestore(item.id)" class="gap-2" :disabled="!item">
                      <RotateCcw class="h-4 w-4" />
                      元に戻す
                    </Button>
                    <Button variant="outline" size="sm" @click="itemToDelete = item.id" class="gap-2 bg-red-500 hover:bg-red-600 hover:text-white" :disabled="!item">
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
    </main>

    <AlertDialog :open="itemToDelete !== null" @update:open="(open) => !open && (itemToDelete = null)">
      <AlertDialogContent>
        <AlertDialogHeader>
          <AlertDialogTitle>完全に削除しますか？</AlertDialogTitle>
          <AlertDialogDescription>このアイテムを完全に削除します。この操作は取り消せません。</AlertDialogDescription>
        </AlertDialogHeader>
        <AlertDialogFooter>
          <AlertDialogCancel>キャンセル</AlertDialogCancel>
          <AlertDialogAction @click="handlePermanentDelete" class="bg-red-600 hover:bg-red-700">
            完全に削除
          </AlertDialogAction>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>

    <AlertDialog :open="showEmptyTrashDialog" @update:open="(open) => showEmptyTrashDialog = open">
      <AlertDialogContent>
        <AlertDialogHeader>
          <AlertDialogTitle>ゴミ箱を空にしますか？</AlertDialogTitle>
          <AlertDialogDescription>すべてのアイテムを完全に削除します。この操作は取り消せません。</AlertDialogDescription>
        </AlertDialogHeader>
        <AlertDialogFooter>
          <AlertDialogCancel>キャンセル</AlertDialogCancel>
          <AlertDialogAction @click="handleEmptyTrash" class="bg-red-600 hover:bg-red-700">
            ゴミ箱を空にする
          </AlertDialogAction>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>

    <Toaster />
  </div>
</template>
