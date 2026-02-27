<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Label } from '@/components/ui/label'
import { Input } from '@/components/ui/input'
import { ArrowLeft, Calendar, CheckCircle } from 'lucide-vue-next'
import { router } from '@inertiajs/vue3'
import { ref } from 'vue'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { CATEGORY_COLORS } from '@/constants/calendar'

defineOptions({
  layout: AuthenticatedLayout,
})

const props = defineProps<{
  settings: {
    default_view: string
    category_labels: Record<string, string>
  }
}>()

const form = useForm({
  default_view: props.settings.default_view,
  category_labels: { ...props.settings.category_labels },
})

const saveMessage = ref('')
const saveMessageTimer = ref<number | null>(null)

const getCategoryColor = (key: string) => {
  return CATEGORY_COLORS[key as keyof typeof CATEGORY_COLORS] || '#7F8C8D'
}

const submit = () => {
  form.post(route('calendar.settings.update'), {
    preserveScroll: true,
    onSuccess: () => {
      showMessage('設定を保存しました')
      // カテゴリーラベルを再読み込み
      window.dispatchEvent(new CustomEvent('category-labels-updated'))
    },
  })
}

const showMessage = (message: string) => {
  if (saveMessageTimer.value) {
    clearTimeout(saveMessageTimer.value)
  }
  
  saveMessage.value = message
  
  saveMessageTimer.value = setTimeout(() => {
    saveMessage.value = ''
  }, 3000)
}
</script>

<template>
  <Head title="カレンダー表示設定" />
  
  <div class="h-full p-6 overflow-y-auto">
    <Card class="max-w-2xl mx-auto">
      <CardHeader>
        <div class="flex items-center gap-3">
          <Button variant="ghost" size="icon" @click="router.get(route('dashboard'))">
            <ArrowLeft class="h-5 w-5" />
          </Button>
          <div class="flex items-center gap-2">
            <Calendar class="h-6 w-6 text-blue-600" />
            <CardTitle>カレンダー表示設定</CardTitle>
          </div>
        </div>
      </CardHeader>
      
      <CardContent class="space-y-6">
        <div class="space-y-4">
          <div class="space-y-2">
            <Label for="default_view">デフォルト表示</Label>
            <Select v-model="form.default_view">
              <SelectTrigger id="default_view">
                <SelectValue placeholder="表示モードを選択" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="yearView">年表示</SelectItem>
                <SelectItem value="dayGridMonth">月表示</SelectItem>
                <SelectItem value="timeGridWeek">週表示</SelectItem>
                <SelectItem value="timeGridDay">日表示</SelectItem>
              </SelectContent>
            </Select>
            <p class="text-sm text-muted-foreground">
              カレンダーを開いた時の初期表示モードを設定します
            </p>
          </div>
          
          <div v-if="$page.props.auth.user.role === 'admin'" class="space-y-4 pt-4 border-t">
            <div class="space-y-2">
              <Label class="text-base font-semibold">カレンダージャンル名称（管理者のみ）</Label>
              <p class="text-sm text-muted-foreground">
                カレンダーのジャンル名称をカスタマイズできます（色は変更できません）
              </p>
            </div>
            
            <div class="space-y-3">
              <div v-for="(label, key) in form.category_labels" :key="key" class="flex items-center gap-3">
                <div class="w-6 h-6 rounded-full flex-shrink-0" :style="{ backgroundColor: getCategoryColor(key) }"></div>
                <div class="flex-1 space-y-1">
                  <Label :for="`category_${key}`" class="text-sm text-muted-foreground">
                    {{ key }}
                  </Label>
                  <Input
                    :id="`category_${key}`"
                    v-model="form.category_labels[key]"
                    type="text"
                    :placeholder="key"
                    maxlength="100"
                  />
                </div>
              </div>
            </div>
          </div>
        </div>
        
        <div class="flex justify-end gap-3 pt-4 border-t">
          <Button variant="outline" @click="router.get(route('dashboard'))">
            キャンセル
          </Button>
          <Button @click="submit" :disabled="form.processing">
            保存
          </Button>
        </div>
      </CardContent>
    </Card>
  </div>
  
  <!-- 保存メッセージ -->
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
      class="fixed bottom-4 left-1/2 transform -translate-x-1/2 z-50 p-3 bg-green-500 text-white rounded-lg shadow-lg"
    >
      <div class="flex items-center gap-2">
        <CheckCircle class="h-5 w-5" />
        <span class="font-medium">{{ saveMessage }}</span>
      </div>
    </div>
  </Transition>
</template>
