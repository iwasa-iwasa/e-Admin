<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import { ref } from 'vue'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Building, Plus, Trash2, Edit, Merge } from 'lucide-vue-next'
import MergeDialog from './MergeDialog.vue'

defineOptions({
  layout: AuthenticatedLayout,
})

const props = defineProps<{
  departments: Array<{
    id: number
    name: string
    is_active: boolean
    users_count: number
    adminUser?: { id: number, name: string } | null
  }>
  users: Array<{
    id: number
    name: string
  }>
}>()

// Dialog states
const isCreateDialogOpen = ref(false)
const isEditDialogOpen = ref(false)
const isDeactivateDialogOpen = ref(false)
const isMergeDialogOpen = ref(false)

const selectedDepartment = ref<typeof props.departments[0] | null>(null)

// Form states
const form = ref({
  name: '',
  admin_user_id: '' as number | ''
})

// Handlers
const openCreateDialog = () => {
  form.value = { name: '', admin_user_id: '' }
  isCreateDialogOpen.value = true
}

const openEditDialog = (department: typeof props.departments[0]) => {
  selectedDepartment.value = department
  form.value = { 
    name: department.name, 
    admin_user_id: department.adminUser ? department.adminUser.id : '' 
  }
  isEditDialogOpen.value = true
}

const openDeactivateDialog = (department: typeof props.departments[0]) => {
  selectedDepartment.value = department
  isDeactivateDialogOpen.value = true
}

// Actions
const createDepartment = () => {
  router.post(route('admin.departments.store'), form.value, {
    onSuccess: () => {
      isCreateDialogOpen.value = false
    }
  })
}

const updateDepartment = () => {
  if (!selectedDepartment.value) return
  router.put(route('admin.departments.update', selectedDepartment.value.id), { 
    name: form.value.name,
    admin_user_id: form.value.admin_user_id
  }, {
    onSuccess: () => {
      isEditDialogOpen.value = false
    }
  })
}

const deactivateDepartment = () => {
  if (!selectedDepartment.value) return
  router.delete(route('admin.departments.destroy', selectedDepartment.value.id), {
    onSuccess: () => {
      isDeactivateDialogOpen.value = false
    }
  })
}
</script>

<template>
  <Head title="部署管理" />
  <div class="max-w-[1200px] mx-auto p-6 flex flex-col h-full">
    <Card class="flex-1 flex flex-col overflow-hidden">
      <CardHeader class="flex flex-row items-center justify-between">
        <div class="flex items-center gap-2">
           <Building class="h-6 w-6 text-blue-700" />
           <CardTitle>部署管理</CardTitle>
        </div>
        <div class="flex gap-2">
            <Button variant="outline" @click="isMergeDialogOpen = true" class="bg-blue-50 text-blue-700 hover:bg-blue-100 border-blue-200">
              <Merge class="h-4 w-4 mr-2" /> 統廃合
            </Button>
            <Button @click="openCreateDialog">
              <Plus class="h-4 w-4 mr-2" /> 新規作成
            </Button>
        </div>
      </CardHeader>
      <CardContent class="flex-1 overflow-auto p-0">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>部署名</TableHead>
              <TableHead>管理者</TableHead>
              <TableHead class="text-right">所属人数</TableHead>
              <TableHead class="w-[100px]">ステータス</TableHead>
              <TableHead class="text-right">アクション</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            <TableRow v-for="dept in departments" :key="dept.id">
              <TableCell class="font-medium">{{ dept.name }}</TableCell>
              <TableCell>{{ dept.adminUser?.name || '-' }}</TableCell>
              <TableCell class="text-right">{{ dept.users_count }} 名</TableCell>
              <TableCell>
                <Badge :variant="dept.is_active ? 'default' : 'secondary'">
                  {{ dept.is_active ? '有効' : '無効' }}
                </Badge>
              </TableCell>
              <TableCell class="text-right">
                <div class="flex justify-end gap-2">
                  <Button variant="outline" size="sm" @click="openEditDialog(dept)" :disabled="!dept.is_active">
                    <Edit class="h-4 w-4" />
                  </Button>
                  <Button variant="destructive" size="sm" @click="openDeactivateDialog(dept)" :disabled="!dept.is_active || dept.users_count > 0">
                    <Trash2 class="h-4 w-4" />
                  </Button>
                </div>
              </TableCell>
            </TableRow>
            <TableRow v-if="departments.length === 0">
                <TableCell colspan="5" class="text-center text-gray-500 py-8">
                    部署が登録されていません。
                </TableCell>
            </TableRow>
          </TableBody>
        </Table>
      </CardContent>
    </Card>

    <!-- Create Dialog -->
    <Dialog :open="isCreateDialogOpen" @update:open="isCreateDialogOpen = $event">
      <DialogContent>
        <DialogHeader>
          <DialogTitle>部署の新規作成</DialogTitle>
          <DialogDescription>
            新しい部署を作成します。部署の基本設定を行ってください。
          </DialogDescription>
        </DialogHeader>
        <div class="grid gap-4 py-4">
          <div class="grid gap-2">
            <Label htmlFor="name">部署名</Label>
            <Input id="name" v-model="form.name" placeholder="例：営業部" />
          </div>
          <div class="grid gap-2">
            <Label htmlFor="admin">部署管理者</Label>
            <Select v-model="form.admin_user_id">
                <SelectTrigger>
                    <SelectValue placeholder="管理者を選択" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem :value="user.id" v-for="user in users" :key="user.id">
                        {{ user.name }}
                    </SelectItem>
                </SelectContent>
            </Select>
            <p class="text-xs text-gray-500">※選択されたユーザーは自動で部署管理者となります。</p>
          </div>
        </div>
        <DialogFooter>
          <Button variant="outline" @click="isCreateDialogOpen = false">キャンセル</Button>
          <Button @click="createDepartment" :disabled="!form.name || !form.admin_user_id">作成</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>

    <!-- Edit Dialog -->
    <Dialog :open="isEditDialogOpen" @update:open="isEditDialogOpen = $event">
      <DialogContent>
        <DialogHeader>
          <DialogTitle>部署名の変更</DialogTitle>
        </DialogHeader>
        <div class="grid gap-4 py-4">
          <div class="grid gap-2">
            <Label htmlFor="edit-name">部署名</Label>
            <Input id="edit-name" v-model="form.name" />
          </div>
          <div class="grid gap-2">
            <Label htmlFor="edit-admin">部署管理者</Label>
            <Select v-model="form.admin_user_id">
                <SelectTrigger>
                    <SelectValue placeholder="管理者を選択" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem :value="user.id" v-for="user in users" :key="user.id">
                        {{ user.name }}
                    </SelectItem>
                </SelectContent>
            </Select>
            <p class="text-xs text-gray-500">※変更すると既存の管理者は一般ユーザーに戻ります。</p>
          </div>
        </div>
        <DialogFooter>
          <Button variant="outline" @click="isEditDialogOpen = false">キャンセル</Button>
          <Button @click="updateDepartment" :disabled="!form.name || !form.admin_user_id || (form.name === selectedDepartment?.name && form.admin_user_id === selectedDepartment?.adminUser?.id)">更新</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>

    <!-- Deactivate Dialog -->
    <Dialog :open="isDeactivateDialogOpen" @update:open="isDeactivateDialogOpen = $event">
      <DialogContent>
        <DialogHeader>
          <DialogTitle>部署の無効化確認</DialogTitle>
          <DialogDescription class="text-red-500 font-bold mt-2">
            「{{ selectedDepartment?.name }}」を無効化しますか？
          </DialogDescription>
        </DialogHeader>
        <div class="py-4">
            <p class="text-sm">所属ユーザーが0名であることを確認しました。無効化すると新規ユーザーの割り当て等ができなくなります。</p>
        </div>
        <DialogFooter>
          <Button variant="outline" @click="isDeactivateDialogOpen = false">キャンセル</Button>
          <Button variant="destructive" @click="deactivateDepartment">無効化する</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>

    <!-- Merge Dialog -->
    <MergeDialog 
      v-model:show="isMergeDialogOpen" 
      :departments="departments" 
    />
  </div>
</template>
