<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Label } from '@/components/ui/label'
import { Input } from '@/components/ui/input'
import { Switch } from '@/components/ui/switch'
import { ArrowLeft, Calendar, CheckCircle, RotateCcw } from 'lucide-vue-next'
import { router } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { CATEGORY_COLORS } from '@/constants/calendar'

defineOptions({
  layout: AuthenticatedLayout,
})

const props = defineProps<{
  settings: {
    default_view: string
    category_labels: Record<string, string>
    heatmap_settings: {
      genre_weights: Record<string, number>
      importance_weights: Record<string, number>
      time_weight_enabled: boolean
    } | null
  }
}>()

const defaultHeatmapSettings = {
  genre_weights: {
    '来客': 4,
    '出張': 3,
    '業務': 3,
    '会議': 2,
    'その他': 1,
    '休暇': 0,
  },
  importance_weights: {
    '重要': 3,
    '中': 2,
    '低': 1,
  },
  time_weight_enabled: true,
}

const genreOrder = ['来客', '出張', '業務', '会議', 'その他', '休暇']
const importanceOrder = ['重要', '中', '低']

const sortedGenreWeights = computed(() => {
  return genreOrder.map(genre => ({
    genre,
    weight: form.heatmap_settings.genre_weights[genre]
  }))
})

const sortedImportanceWeights = computed(() => {
  return importanceOrder.map(importance => ({
    importance,
    weight: form.heatmap_settings.importance_weights[importance]
  }))
})

const form = useForm({
  default_view: props.settings.default_view,
  category_labels: { ...props.settings.category_labels },
  heatmap_settings: props.settings.heatmap_settings 
    ? { ...props.settings.heatmap_settings }
    : { ...defaultHeatmapSettings },
})

const timeWeightEnabled = ref(form.heatmap_settings.time_weight_enabled)

const saveMessage = ref('')
const saveMessageTimer = ref<number | null>(null)

const getCategoryColor = (key: string) => {
  return CATEGORY_COLORS[key as keyof typeof CATEGORY_COLORS] || '#7F8C8D'
}

const resetHeatmapSettings = () => {
  form.heatmap_settings = { ...defaultHeatmapSettings }
}

const isDefaultSettings = computed(() => {
  return JSON.stringify(form.heatmap_settings) === JSON.stringify(defaultHeatmapSettings)
})

const submit = () => {
  form.heatmap_settings.time_weight_enabled = timeWeightEnabled.value
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
    <Card class="max-w-4xl mx-auto">
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
          
          <!-- ヒートマップ設定 -->
          <div class="space-y-4 pt-4 border-t">
            <div class="flex items-center justify-between">
              <div class="space-y-1">
                <Label class="text-base font-semibold">年間ヒートマップの重み付け</Label>
                <p class="text-sm text-muted-foreground">
                  年間表示の忙しさ表示（ヒートマップ）の計算方法をカスタマイズできます
                </p>
              </div>
              <Button 
                variant="outline" 
                size="sm"
                @click="resetHeatmapSettings"
                :disabled="isDefaultSettings"
              >
                <RotateCcw class="h-4 w-4 mr-2" />
                デフォルトに戻す
              </Button>
            </div>
            
            <!-- ジャンルの重み -->
            <div class="space-y-3">
              <Label class="text-sm font-medium">ジャンルの重み</Label>
              <div class="grid grid-cols-2 gap-3">
                <div v-for="item in sortedGenreWeights" :key="item.genre" class="flex items-center gap-2">
                  <div class="w-4 h-4 rounded-full flex-shrink-0" :style="{ backgroundColor: getCategoryColor(item.genre) }"></div>
                  <span class="text-sm flex-shrink-0 w-16">{{ item.genre }}</span>
                  <Select 
                    :model-value="String(item.weight)"
                    @update:model-value="(val) => form.heatmap_settings.genre_weights[item.genre] = Number(val)"
                  >
                    <SelectTrigger class="h-8 w-16">
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="0">0</SelectItem>
                      <SelectItem value="1">1</SelectItem>
                      <SelectItem value="2">2</SelectItem>
                      <SelectItem value="3">3</SelectItem>
                      <SelectItem value="4">4</SelectItem>
                      <SelectItem value="5">5</SelectItem>
                    </SelectContent>
                  </Select>
                </div>
              </div>
            </div>
            
            <!-- 重要度の重み -->
            <div class="space-y-3 pt-2">
              <Label class="text-sm font-medium">重要度の重み</Label>
              <div class="grid grid-cols-3 gap-3">
                <div v-for="item in sortedImportanceWeights" :key="item.importance" class="flex items-center gap-2">
                  <span class="text-sm flex-shrink-0 w-12">{{ item.importance }}</span>
                  <Select 
                    :model-value="String(item.weight)"
                    @update:model-value="(val) => form.heatmap_settings.importance_weights[item.importance] = Number(val)"
                  >
                    <SelectTrigger class="h-8 w-16">
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="1">1</SelectItem>
                      <SelectItem value="2">2</SelectItem>
                      <SelectItem value="3">3</SelectItem>
                      <SelectItem value="4">4</SelectItem>
                      <SelectItem value="5">5</SelectItem>
                    </SelectContent>
                  </Select>
                </div>
              </div>
            </div>
            
            <!-- 時間重み付け -->
            <div class="flex items-center justify-between pt-2 p-3 rounded-lg border">
              <div class="space-y-1">
                <Label class="text-sm font-medium">時間の重み付けを有効化</Label>
                <p class="text-xs text-muted-foreground">
                  オフにすると、時間に関係なくイベント数で評価します
                </p>
              </div>
              <div class="flex items-center gap-3">
                <div @click="timeWeightEnabled = !timeWeightEnabled" class="cursor-pointer">
                  <Switch 
                    :checked="timeWeightEnabled"
                  />
                </div>
                <span 
                  class="text-sm font-medium px-3 py-1 rounded transition-colors duration-200"
                  :class="timeWeightEnabled ? 'bg-blue-500 text-white dark:bg-blue-600' : 'bg-gray-200 text-gray-600 dark:bg-gray-700 dark:text-gray-300'"
                >
                  {{ timeWeightEnabled ? 'オン' : 'オフ' }}
                </span>
              </div>
            </div>
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
                <div class="w-6 h-6 rounded-full flex-shrink-0" :style="{ backgroundColor: getCategoryColor(String(key)) }"></div>
                <div class="flex-1 space-y-1">
                  <Label :for="`category_${String(key)}`" class="text-sm text-muted-foreground">
                    {{ String(key) }}
                  </Label>
                  <Input
                    :id="`category_${String(key)}`"
                    v-model="form.category_labels[key]"
                    type="text"
                    :placeholder="String(key)"
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
