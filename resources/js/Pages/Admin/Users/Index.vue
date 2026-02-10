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
import { Users, History, Loader2, ChevronDown, HelpCircle } from 'lucide-vue-next'
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

// Role Update
const isRoleDialogOpen = ref(false)
const pendingRoleUpdate = ref<{ user: App.Data.UserData, role: 'member' | 'admin' } | null>(null)

// Logs
const isLogDialogOpen = ref(false)
const userLogs = ref<any[]>([])
const isLoadingLogs = ref(false)
const isHelpOpen = ref(false)

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
  pendingRoleUpdate.value = { user, role }
  isRoleDialogOpen.value = true
}

const executeRoleUpdate = () => {
  if (!pendingRoleUpdate.value) return

  router.patch(route('admin.users.update-role', pendingRoleUpdate.value.user.id), {
    role: pendingRoleUpdate.value.role
  }, {
    onSuccess: () => {
        isRoleDialogOpen.value = false
        pendingRoleUpdate.value = null
    }
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
           
           <Button
             variant="ghost"
             size="icon"
             class="h-5 w-5 p-0 text-gray-500 hover:text-gray-700"
             @click="isHelpOpen = true"
             title="ユーザー管理の使い方"
           >
             <HelpCircle class="h-5 w-5" />
           </Button>
        </div>
      </CardHeader>
      <CardContent class="flex-1 overflow-auto p-0">
        <div class="h-full overflow-y-auto relative">
          <Table>
            <TableHeader class="sticky top-0 bg-white dark:bg-card z-10 shadow-sm">
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
                       <Badge variant="outline" class="cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700 dark:hover:text-gray-100 transition-colors">
                         {{ user.role }} <ChevronDown class="h-3 w-3 ml-1" />
                       </Badge>
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

    <!-- Role Update Dialog -->
    <Dialog :open="isRoleDialogOpen" @update:open="isRoleDialogOpen = $event">
      <DialogContent>
        <DialogHeader>
          <DialogTitle>権限の変更確認</DialogTitle>
          <DialogDescription>
            {{ pendingRoleUpdate?.user.name }} さんの権限を 
            <span class="font-bold">{{ pendingRoleUpdate?.role }}</span> に変更しますか？
          </DialogDescription>
        </DialogHeader>
        <DialogFooter>
          <Button variant="outline" @click="isRoleDialogOpen = false">キャンセル</Button>
          <Button @click="executeRoleUpdate">変更する</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>

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
    
        <!-- ヘルプダイアログ -->
    <Dialog :open="isHelpOpen" @update:open="isHelpOpen = $event">
      <DialogContent class="max-w-3xl max-h-[90vh] flex flex-col">
        <DialogHeader>
          <DialogTitle>ユーザー管理の使い方</DialogTitle>
        </DialogHeader>
        <div class="space-y-6 overflow-y-auto flex-1 pr-2">
          <!-- 権限管理 -->
          <div class="relative pl-4 border-l-4 border-blue-500 bg-gradient-to-r from-blue-50 to-transparent dark:from-blue-950/30 p-4 rounded-r-lg">
            <h3 class="font-semibold mb-3 text-lg">🔑 権限管理</h3>
            <div class="space-y-4">
              <div class="bg-white dark:bg-gray-800 p-3 rounded-lg shadow-sm">
                <div class="flex items-start gap-4">
                  <div class="flex-shrink-0 pt-1 w-32">
                    <Badge variant="outline" class="cursor-default hover:bg-gray-100 transition-colors opacity-100 pointer-events-none">
                      member <ChevronDown class="h-3 w-3 ml-1" />
                    </Badge>
                  </div>
                  <div class="flex-1">
                    <p class="font-medium text-sm mb-1">権限変更</p>
                    <p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed">役割バッジをクリックして、ユーザーの権限（member/admin）を変更できます。adminは管理者権限を持ち、ユーザー管理が可能になります。</p>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- 履歴とステータス -->
          <div class="relative pl-4 border-l-4 border-green-500 bg-gradient-to-r from-green-50 to-transparent dark:from-green-950/30 p-4 rounded-r-lg">
            <h3 class="font-semibold mb-3 text-lg">📊 履歴とステータス</h3>
            <div class="space-y-4">
              <div class="bg-white dark:bg-gray-800 p-3 rounded-lg shadow-sm">
                <div class="flex items-start gap-4">
                  <div class="flex-shrink-0 pt-1 w-32">
                    <Button variant="outline" size="sm" class="opacity-100 pointer-events-none" tabindex="-1">
                      <History class="h-4 w-4 mr-1" />
                      履歴
                    </Button>
                  </div>
                  <div class="flex-1">
                    <p class="font-medium text-sm mb-1">履歴確認</p>
                    <p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed">履歴ボタンをクリックすると、そのユーザーの権限変更やステータス変更の履歴を確認できます。</p>
                  </div>
                </div>
              </div>
              <div class="bg-white dark:bg-gray-800 p-3 rounded-lg shadow-sm">
                <div class="flex items-start gap-4">
                  <div class="flex-shrink-0 pt-1 w-32">
                    <div class="flex gap-2 pointer-events-none">
                      <Badge variant="default" class="opacity-100">有効</Badge>
                      <Badge variant="destructive" class="opacity-100">無効</Badge>
                    </div>
                  </div>
                  <div class="flex-1">
                    <p class="font-medium text-sm mb-1">ステータス表示</p>
                    <p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed">ユーザーのアカウント状態を表示します。無効の場合、ログインができなくなります。</p>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- アカウント操作 -->
          <div class="relative pl-4 border-l-4 border-orange-500 bg-gradient-to-r from-orange-50 to-transparent dark:from-orange-950/30 p-4 rounded-r-lg">
            <h3 class="font-semibold mb-3 text-lg">⚙️ アカウント操作</h3>
            <div class="space-y-4">
              <div class="bg-white dark:bg-gray-800 p-3 rounded-lg shadow-sm">
                <div class="flex items-start gap-4">
                  <div class="flex-shrink-0 pt-1 w-32">
                    <div class="flex gap-2 pointer-events-none">
                      <Button variant="destructive" size="sm" class="opacity-100" tabindex="-1">
                        無効化
                      </Button>
                      <Button variant="default" size="sm" class="opacity-100" tabindex="-1">
                        有効化
                      </Button>
                    </div>
                  </div>
                  <div class="flex-1">
                    <p class="font-medium text-sm mb-1">有効化・無効化</p>
                    <p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed">ユーザーを無効化（理由入力必須）したり、無効化されたユーザーを再度有効化したりできます。</p>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- 制限事項 -->
          <div class="relative pl-4 border-l-4 border-red-500 bg-gradient-to-r from-red-50 to-transparent dark:from-red-950/30 p-4 rounded-r-lg">
            <h3 class="font-semibold mb-3 text-lg">⚠️ 制限事項</h3>
            <div class="space-y-4">
              <div class="bg-white dark:bg-gray-800 p-3 rounded-lg shadow-sm">
                <div class="flex items-start gap-4">
                  <div class="flex-shrink-0 pt-1 w-32">
                    <div class="flex items-center gap-2 pointer-events-none">
                      <Users class="h-5 w-5 text-gray-400" />
                    </div>
                  </div>
                  <div class="flex-1">
                    <p class="font-medium text-sm mb-1">操作制限</p>
                    <p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed">自分自身や他の管理者を無効化することはできません。チームの安全性を保つための仕様です。</p>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="mt-4 p-3 bg-blue-50 dark:bg-blue-950/30 rounded-lg border border-blue-200 dark:border-blue-800 flex-shrink-0">
          <p class="text-sm text-blue-800 dark:text-blue-200 flex items-center gap-2">
            <span class="text-lg">💡</span>
            <span>ユーザー管理は管理者権限を持つユーザーのみがアクセスできます。慎重に権限を管理しましょう。</span>
          </p>
        </div>
      </DialogContent>
    </Dialog>
  </div>
</template>


