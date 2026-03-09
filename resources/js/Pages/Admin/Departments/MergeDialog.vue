<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Checkbox } from '@/components/ui/checkbox'
import { Input } from '@/components/ui/input'
import { AlertTriangle, ArrowRight } from 'lucide-vue-next'

const props = defineProps<{
  show: boolean
  departments: Array<{
    id: number
    name: string
    is_active: boolean
    users_count: number
  }>
}>()

const emit = defineEmits<{
  (e: 'update:show', value: boolean): void
  (e: 'success'): void
}>()

const isOpen = computed({
  get: () => props.show,
  set: (value) => emit('update:show', value)
})

const sourceDepartmentIds = ref<number[]>([])
const targetDepartmentId = ref<number | ''>('')
const reason = ref('')
const isSubmitting = ref(false)

// Reset form when opened
watch(() => props.show, (newVal) => {
  if (newVal) {
    sourceDepartmentIds.value = []
    targetDepartmentId.value = ''
    reason.value = ''
    isSubmitting.value = false
  }
})

const availableTargetDepartments = computed(() => {
  return props.departments.filter(d => d.is_active && !sourceDepartmentIds.value.includes(d.id))
})

const affectedUsersCount = computed(() => {
  return props.departments
    .filter(d => sourceDepartmentIds.value.includes(d.id))
    .reduce((sum, d) => sum + d.users_count, 0)
})

const submitMerge = () => {
  if (sourceDepartmentIds.value.length === 0 || !targetDepartmentId.value) return
  
  isSubmitting.value = true
  
  router.post(route('admin.departments.merge'), {
    source_department_ids: sourceDepartmentIds.value,
    target_department_id: targetDepartmentId.value,
    reason: reason.value
  }, {
    onSuccess: () => {
      isOpen.value = false
      emit('success')
    },
    onFinish: () => {
      isSubmitting.value = false
    }
  })
}

const toggleSourceDepartment = (id: number) => {
  if (sourceDepartmentIds.value.includes(id)) {
    sourceDepartmentIds.value = sourceDepartmentIds.value.filter(dId => dId !== id)
  } else {
    sourceDepartmentIds.value.push(id)
  }
}
</script>

<template>
  <Dialog :open="isOpen" @update:open="isOpen = $event">
    <DialogContent class="max-w-[600px]">
      <DialogHeader>
        <DialogTitle>部署の統廃合</DialogTitle>
        <DialogDescription>
          廃止する部署（複数選択可）と、吸収先（ターゲット）となる部署を選択してください。
          <br>廃止元のユーザーや予定、関連データはすべて吸収先の部署に引き継がれます。
        </DialogDescription>
      </DialogHeader>

      <div class="grid gap-6 py-4">
        <!-- 元部署の選択 -->
        <div class="space-y-3 border rounded-md p-4 bg-muted/30">
          <Label class="text-base font-semibold">1. 廃止する部署を選択 (複数可)</Label>
          <div class="grid grid-cols-2 gap-2 max-h-[150px] overflow-y-auto pr-2">
            <template v-for="dept in departments" :key="dept.id">
              <label 
                v-if="dept.id !== targetDepartmentId"
                class="flex items-center gap-2 p-2 rounded hover:bg-muted cursor-pointer border shadow-sm"
                :class="sourceDepartmentIds.includes(dept.id) ? 'border-primary bg-primary/5' : 'border-transparent bg-background'"
              >
                <Checkbox 
                  :checked="sourceDepartmentIds.includes(dept.id)" 
                  @update:checked="toggleSourceDepartment(dept.id)"
                />
                <span class="text-sm truncate" :title="dept.name">{{ dept.name }} </span>
                <span class="text-xs text-muted-foreground ml-auto">{{ dept.users_count }}名</span>
              </label>
            </template>
          </div>
          <p v-if="sourceDepartmentIds.length === 0" class="text-sm text-red-500 font-medium pt-1">
            少なくとも1つの部署を選択してください。
          </p>
        </div>

        <div class="flex justify-center -my-2 text-muted-foreground z-10">
          <div class="bg-background p-1 rounded-full border shadow-sm">
            <ArrowRight class="h-5 w-5 rotate-90" />
          </div>
        </div>

        <!-- 統合先の選択 -->
        <div class="space-y-3 border rounded-md p-4 bg-muted/30">
          <Label class="text-base font-semibold">2. 統合先（吸収先）となる部署を選択</Label>
          <Select v-model="targetDepartmentId">
            <SelectTrigger>
              <SelectValue placeholder="統合先の部署を選択してください" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem 
                v-for="dept in availableTargetDepartments" 
                :key="dept.id" 
                :value="dept.id"
              >
                {{ dept.name }} (現在: {{ dept.users_count }}名)
              </SelectItem>
            </SelectContent>
          </Select>
          <p v-if="!targetDepartmentId && sourceDepartmentIds.length > 0" class="text-sm text-red-500 font-medium pt-1">
            統合先を選択してください。
          </p>
        </div>

        <!-- 理由・備考 -->
        <div class="space-y-2">
          <Label htmlFor="reason">統廃合の理由・備考 (任意)</Label>
          <Input id="reason" v-model="reason" placeholder="組織改編のため" />
        </div>

        <!-- 警告メッセージ -->
        <div v-if="sourceDepartmentIds.length > 0 && targetDepartmentId" class="bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-900 rounded-md p-4 flex gap-3 text-amber-800 dark:text-amber-500">
          <AlertTriangle class="h-5 w-5 shrink-0 mt-0.5" />
          <div class="text-sm space-y-1">
            <p class="font-bold">以下の統廃合が実行されます：</p>
            <ul class="list-disc pl-4 space-y-0.5 mt-1">
              <li>選択された {{ sourceDepartmentIds.length }} つの部署が「無効化」されます</li>
              <li>合計 <strong>{{ affectedUsersCount }}名</strong> のユーザーが新しい部署に自動で異動します</li>
              <li>廃止される部署に紐づく予定・共有メモの所有権が移管されます</li>
            </ul>
            <p class="pt-2 text-xs opacity-90">※この操作は元に戻せません。実行前に十分確認してください。</p>
          </div>
        </div>
      </div>

      <DialogFooter>
        <Button variant="outline" @click="isOpen = false" :disabled="isSubmitting">キャンセル</Button>
        <Button 
          @click="submitMerge" 
          :disabled="isSubmitting || sourceDepartmentIds.length === 0 || !targetDepartmentId"
          class="bg-blue-600 hover:bg-blue-700 text-white"
        >
          {{ isSubmitting ? '処理中...' : '統廃合を実行する' }}
        </Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>
