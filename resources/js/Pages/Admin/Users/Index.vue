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
import { Users } from 'lucide-vue-next'

defineOptions({
  layout: AuthenticatedLayout,
})

const props = defineProps<{
  users: App.Models.User[]
}>()

const selectedUser = ref<App.Models.User | null>(null)
const isDeactivateDialogOpen = ref(false)
const deactivationReason = ref('')

const openDeactivateDialog = (user: App.Models.User) => {
  selectedUser.value = user
  deactivationReason.value = ''
  isDeactivateDialogOpen.value = true
}

const deactivateUser = () => {
  if (!selectedUser.value) return

  router.delete(route('admin.users.destroy', selectedUser.value.id), {
    data: {
      reason: deactivationReason.value
    },
    onSuccess: () => {
      isDeactivateDialogOpen.value = false
      selectedUser.value = null
    }
  })
}

const restoreUser = (user: App.Models.User) => {
  if (!confirm(`${user.name} さんのアカウントを有効化しますか？`)) return

  router.patch(route('admin.users.restore', user.id), {}, {
    onSuccess: () => {
      // flash message handled globaly or just refresh
    }
  })
}
</script>

<template>
  <Head title="ユーザー管理" />
  <div class="max-w-[1800px] mx-auto h-full p-6">
    <Card class="h-full flex flex-col">
      <CardHeader>
        <div class="flex items-center gap-2">
           <Users class="h-6 w-6 text-blue-700" />
           <CardTitle>ユーザー管理</CardTitle>
        </div>
      </CardHeader>
      <CardContent>
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>名前</TableHead>
              <TableHead>メールアドレス</TableHead>
              <TableHead>部署</TableHead>
              <TableHead>役割</TableHead>
              <TableHead>ステータス</TableHead>
              <TableHead>アクション</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            <TableRow v-for="user in users" :key="user.id">
              <TableCell>{{ user.name }}</TableCell>
              <TableCell>{{ user.email }}</TableCell>
              <TableCell>{{ user.department }}</TableCell>
              <TableCell>
                 <Badge variant="outline">{{ user.role }}</Badge>
              </TableCell>
              <TableCell>
                <Badge :variant="user.is_active ? 'default' : 'destructive'">
                  {{ user.is_active ? '有効' : '無効' }}
                </Badge>
                <div v-if="!user.is_active" class="text-xs text-gray-500 mt-1">
                  {{ user.deactivated_at ? new Date(user.deactivated_at).toLocaleDateString() : '' }}<br>
                  理由: {{ user.reason }}
                </div>
              </TableCell>
              <TableCell>
                <Button 
                  v-if="user.is_active && user.role !== 'admin'" 
                  variant="destructive" 
                  size="sm"
                  @click="openDeactivateDialog(user)"
                >
                  無効化
                </Button>

                <Button 
                  v-else-if="!user.is_active" 
                  variant="outline" 
                  size="sm"
                  class="text-green-600 hover:text-green-700 hover:bg-green-50"
                  @click="restoreUser(user)"
                >
                  有効化
                </Button>
              </TableCell>
            </TableRow>
          </TableBody>
        </Table>
      </CardContent>
    </Card>

    <Dialog :open="isDeactivateDialogOpen" @update:open="isDeactivateDialogOpen = $event">
      <DialogContent>
        <DialogHeader>
          <DialogTitle>ユーザーの無効化</DialogTitle>
          <DialogDescription>
            {{ selectedUser?.name }} さんのアカウントを無効化します。<br>
            この操作を行うと、ユーザーはログインできなくなります。
          </DialogDescription>
        </DialogHeader>
        <div class="grid gap-4 py-4">
          <div class="grid gap-2">
            <Label htmlFor="reason">無効化の理由</Label>
            <Input id="reason" v-model="deactivationReason" placeholder="例：退職のため" />
          </div>
        </div>
        <DialogFooter>
          <Button variant="outline" @click="isDeactivateDialogOpen = false">キャンセル</Button>
          <Button variant="destructive" @click="deactivateUser" :disabled="!deactivationReason">実行</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  </div>
</template>


