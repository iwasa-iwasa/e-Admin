<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3'
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
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Users, History, Loader2, ChevronDown } from 'lucide-vue-next'
import axios from 'axios'

defineOptions({
  layout: AuthenticatedLayout,
})

const props = defineProps<{
  users: App.Data.UserData[]
}>()

const page = usePage()
const selectedUser = ref<App.Data.UserData | null>(null)

// Deactivate
const isDeactivateDialogOpen = ref(false)
const deactivationReason = ref('')

// Logs
const isLogDialogOpen = ref(false)
const userLogs = ref<any[]>([])
const isLoadingLogs = ref(false)

// Methods
const openDeactivateDialog = (user: App.Data.UserData) => {
  selectedUser.value = user
  deactivationReason.value = ''
  isDeactivateDialogOpen.value = true
}

const deactivateUser = () => {
  if (!selectedUser.value) return

  router.delete(route('admin.users.destroy', selectedUser.value.id), {
    data: { reason: deactivationReason.value },
    onSuccess: () => {
      isDeactivateDialogOpen.value = false
      selectedUser.value = null
    }
  })
}

const restoreUser = (user: App.Data.UserData) => {
  if (!confirm(`${user.name} さんのアカウントを有効化しますか？`)) return

  router.patch(route('admin.users.restore', user.id), {}, {})
}

const updateRole = (user: App.Data.UserData, role: 'member' | 'admin') => {
  if (user.role === role) return
  if (!confirm(`${user.name} さんの権限を ${role} に変更しますか？`)) return

  router.patch(route('admin.users.update-role', user.id), {
    role: role
  })
}

const openLogDialog = async (user: App.Data.UserData) => {
  selectedUser.value = user
  isLogDialogOpen.value = true
  isLoadingLogs.value = true
  userLogs.value = []

  try {
    const response = await axios.get(route('admin.users.logs', user.id))
    userLogs.value = response.data
  } catch (error) {
    console.error('Failed to fetch logs', error)
  } finally {
    isLoadingLogs.value = false
  }
}

const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleString('ja-JP', {
        year: 'numeric', month: '2-digit', day: '2-digit',
        hour: '2-digit', minute: '2-digit'
    })
}
</script>

<template>
  <Head title="ユーザー管理" />
  <div class="max-w-[1800px] mx-auto h-[calc(100vh-100px)] p-6 flex flex-col">
    <Card class="flex-1 flex flex-col overflow-hidden">
      <CardHeader>
        <div class="flex items-center gap-2">
           <Users class="h-6 w-6 text-blue-700" />
           <CardTitle>ユーザー管理</CardTitle>
        </div>
      </CardHeader>
      <CardContent class="flex-1 overflow-auto p-0">
        <div class="h-full overflow-y-auto relative">
          <Table>
            <TableHeader class="sticky top-0 bg-white z-10 shadow-sm">
              <TableRow>
                <TableHead>名前</TableHead>
                <TableHead>メールアドレス</TableHead>
                <TableHead>部署</TableHead>
                <TableHead>役割</TableHead>
                <TableHead>ステータス</TableHead>
                <TableHead class="w-[100px]">履歴</TableHead>
                <TableHead>アクション</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              <TableRow v-for="user in users" :key="user.id">
                <TableCell>{{ user.name }}</TableCell>
                <TableCell>{{ user.email }}</TableCell>
                <TableCell>{{ user.department }}</TableCell>
                <TableCell>
                  <div v-if="user.id === page.props.auth.user.id">
                     <Badge variant="outline">{{ user.role }}</Badge>
                  </div>
                  <DropdownMenu v-else>
                    <DropdownMenuTrigger as-child>
                       <Button variant="outline" size="sm" class="h-6 gap-1 px-2.5 rounded-full font-normal border-slate-200 hover:bg-gray-100 hover:text-slate-900 data-[state=open]:bg-gray-100 text-xs text-foreground">
                         {{ user.role }} <ChevronDown class="h-3 w-3 text-muted-foreground" />
                       </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent>
                      <DropdownMenuItem @click="updateRole(user, 'member')">member</DropdownMenuItem>
                      <DropdownMenuItem @click="updateRole(user, 'admin')">admin</DropdownMenuItem>
                    </DropdownMenuContent>
                  </DropdownMenu>
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
                    <Button variant="outline" size="sm" @click="openLogDialog(user)">
                        <History class="h-4 w-4 mr-1" />
                        履歴
                    </Button>
                </TableCell>
                <TableCell>
                  <div class="flex items-center gap-2">
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
                      variant="default"
                      size="sm"
                      @click="restoreUser(user)"
                    >
                      有効化
                    </Button>
                  </div>
                </TableCell>
              </TableRow>
            </TableBody>
          </Table>
        </div>
      </CardContent>
    </Card>

    <!-- Deactivate Dialog -->
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

    <!-- Role Dialog removed -->

    <!-- Logs Dialog -->
    <Dialog :open="isLogDialogOpen" @update:open="isLogDialogOpen = $event">
      <DialogContent class="max-w-2xl max-h-[80vh] flex flex-col">
        <DialogHeader>
          <DialogTitle>変更履歴: {{ selectedUser?.name }}</DialogTitle>
        </DialogHeader>
        <div class="flex-1 overflow-y-auto py-4">
            <div v-if="isLoadingLogs" class="flex justify-center p-4">
                <Loader2 class="h-6 w-6 animate-spin text-gray-400" />
            </div>
            <div v-else-if="userLogs.length === 0" class="text-center text-gray-500">
                履歴はありません。
            </div>
            <Table v-else>
                <TableHeader>
                    <TableRow>
                        <TableHead>日時</TableHead>
                        <TableHead>変更者</TableHead>
                        <TableHead>内容</TableHead>
                        <TableHead>変更前</TableHead>
                        <TableHead>変更後</TableHead>
                        <TableHead>理由</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="log in userLogs" :key="log.id">
                        <TableCell class="whitespace-nowrap">{{ formatDate(log.created_at) }}</TableCell>
                        <TableCell>{{ log.changer?.name || 'System' }}</TableCell>
                        <TableCell>
                            <span v-if="log.type === 'role'">権限変更</span>
                            <span v-else-if="log.type === 'status'">ステータス変更</span>
                            <span v-else>{{ log.type }}</span>
                        </TableCell>
                        <TableCell>{{ log.old_value }}</TableCell>
                        <TableCell>{{ log.new_value }}</TableCell>
                        <TableCell class="max-w-[200px] truncate" :title="log.reason">{{ log.reason }}</TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>
      </DialogContent>
    </Dialog>
  </div>
</template>


