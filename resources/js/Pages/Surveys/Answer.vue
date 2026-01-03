<script setup lang="ts">
  import { ref, nextTick } from 'vue'
  import { Head, useForm, router } from '@inertiajs/vue3'
  import { route } from 'ziggy-js'
  import { Button } from '@/components/ui/button'
  import { Input } from '@/components/ui/input'
  import { Textarea } from '@/components/ui/textarea'
  import { Label } from '@/components/ui/label'
  import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
  import { ArrowLeft, Star, AlertCircle } from 'lucide-vue-next'
  import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
  
  defineOptions({
      layout: AuthenticatedLayout
  })
  
  interface Option {
      option_id: number
      option_text: string
  }
  
  interface Question {
      question_id: number
      question_text: string
      question_type: string
      is_required: boolean
      options: Option[]
      scale_min?: number
      scale_max?: number
      scale_min_label?: string
      scale_max_label?: string
  }
  
  interface Survey {
      survey_id: number
      title: string
      description: string
      deadline?: string
      deadline_date?: string
      deadline_time?: string
  }
  
  const props = defineProps<{
      survey: Survey
      questions: Question[]
      existingAnswers?: Record<number, any>
      isEditing?: boolean
  }>()
  
  const form = useForm({
      answers: props.existingAnswers ? JSON.parse(JSON.stringify(props.existingAnswers)) : {} as Record<number, any>
  })
  
  // ドロップダウンの初期値を空文字列に設定
  const initializeDropdownAnswers = () => {
      props.questions.forEach(question => {
          if (question.question_type === 'dropdown' && !form.answers[question.question_id]) {
              form.answers[question.question_id] = ''
          }
      })
  }
  
  // コンポーネントマウント時に初期化
  initializeDropdownAnswers()
  
  const multipleChoiceAnswers = ref<Record<number, number[]>>({})
  
  // 既存回答を初期化
  if (props.existingAnswers) {
      Object.keys(props.existingAnswers).forEach(key => {
          const questionId = Number(key)
          const answer = props.existingAnswers![questionId]
          const question = props.questions.find(q => q.question_id === questionId)
          
          if (question?.question_type === 'multiple_choice') {
              if (Array.isArray(answer)) {
                  // 配列の場合（option_idの配列）
                  multipleChoiceAnswers.value[questionId] = [...answer]
                  form.answers[questionId] = [...answer]
              } else if (typeof answer === 'string' && answer.includes(',')) {
                  // カンマ区切りテキストの場合、option_idに変換
                  const optionTexts = answer.split(', ')
                  const optionIds: number[] = []
                  for (const text of optionTexts) {
                      const option = question.options?.find(o => o.option_text === text.trim())
                      if (option) {
                          optionIds.push(option.option_id)
                      }
                  }
                  multipleChoiceAnswers.value[questionId] = optionIds
                  form.answers[questionId] = optionIds
              }
          } else if (question?.question_type === 'scale' && answer) {
              form.answers[questionId] = Number(answer)
          }
      })
  }
  const clientValidationErrors = ref<Record<number, string>>({})
  
  const handleMultipleChoiceChange = (questionId: number, optionId: number, checked: boolean) => {
      if (!multipleChoiceAnswers.value[questionId]) {
          multipleChoiceAnswers.value[questionId] = []
      }
      
      if (checked) {
          multipleChoiceAnswers.value[questionId].push(optionId)
      } else {
          multipleChoiceAnswers.value[questionId] = 
              multipleChoiceAnswers.value[questionId].filter(id => id !== optionId)
      }
      
      form.answers[questionId] = multipleChoiceAnswers.value[questionId]
  }
  
  const validateAnswers = () => {
      clientValidationErrors.value = {}
      
      for (const question of props.questions) {
          if (!question.is_required) continue
          
          const answer = form.answers[question.question_id]
          const multipleAnswer = multipleChoiceAnswers.value[question.question_id]
          
          if (question.question_type === 'multiple_choice') {
              if (!multipleAnswer || multipleAnswer.length === 0) {
                  clientValidationErrors.value[question.question_id] = '少なくとも1つ選択してください'
              }
          } else if (!answer || (typeof answer === 'string' && answer.trim() === '')) {
              clientValidationErrors.value[question.question_id] = 'この項目は必須です'
          }
      }
      
      if (Object.keys(clientValidationErrors.value).length > 0) {
          nextTick(() => {
              const firstErrorId = Object.keys(clientValidationErrors.value)[0]
              const element = document.getElementById(`question_${firstErrorId}`)
              element?.scrollIntoView({ behavior: 'smooth', block: 'start' })
          })
          return false
      }
      return true
  }
  
  const submitAnswer = () => {
      if (!validateAnswers()) {
          return
      }
      
      form.post(route('surveys.submit', props.survey.survey_id), {
          preserveScroll: true,
          onSuccess: () => {
              // 成功時の処理(オプション)
          },
          onError: (errors) => {
              console.error('送信エラー:', errors);
          },
      });
  }
  
  const cancel = () => {
      router.get(route('surveys'))
  }
  </script>
  
  <template>
    <Head :title="`${survey.title} - 回答`" />
    
    <!-- 画面いっぱいに広がるコンテナ -->
    <div class="h-full p-6">
      <!-- カードを画面高さいっぱいに -->
      <Card class="h-full flex flex-col overflow-hidden">
          <!-- カードヘッダー (固定) -->
          <div class="p-4 border-b shrink-0">
            <div class="flex items-center gap-3 mb-2">
              <Button variant="ghost" size="icon" @click="cancel">
                <ArrowLeft class="h-5 w-5" />
              </Button>
              <CardTitle>{{ survey.title }}</CardTitle>
            </div>
            <p class="text-sm text-gray-500">{{ survey.description }}</p>
          </div>
          
          <!-- 質問リスト (スクロール可能) -->
          <div class="flex-1 overflow-y-auto p-6 space-y-6">
          <div
            v-for="q in questions"
            :key="q.question_id"
            :id="`question_${q.question_id}`"
            class="pb-6 border-b last:border-b-0"
          >
            <Label class="block mb-2 font-medium">
              {{ q.question_text }}
              <span v-if="q.is_required" class="text-red-500">*</span>
            </Label>
            
            <div
              v-if="clientValidationErrors[q.question_id]"
              class="flex items-center gap-2 text-red-600 text-sm mb-2"
            >
              <AlertCircle class="h-4 w-4" />
              {{ clientValidationErrors[q.question_id] }}
            </div>
            
            <!-- Single -->
            <div v-if="q.question_type === 'single_choice'" class="space-y-2">
              <label v-for="o in q.options" :key="o.option_id" class="flex gap-2">
                <input type="radio" :value="o.option_id" v-model="form.answers[q.question_id]" />
                {{ o.option_text }}
              </label>
            </div>
            
            <!-- Multiple -->
            <div v-else-if="q.question_type === 'multiple_choice'" class="space-y-2">
              <label v-for="o in q.options" :key="o.option_id" class="flex gap-2">
                <input
                  type="checkbox"
                  :checked="multipleChoiceAnswers[q.question_id]?.includes(o.option_id)"
                  @change="handleMultipleChoiceChange(
                    q.question_id,
                    o.option_id,
                    ($event.target as HTMLInputElement).checked
                  )"
                />
                {{ o.option_text }}
              </label>
            </div>
            
            <!-- Text -->
            <Input
              v-else-if="q.question_type === 'text'"
              v-model="form.answers[q.question_id]"
            />
            
            <!-- Textarea -->
            <Textarea
              v-else-if="q.question_type === 'textarea'"
              v-model="form.answers[q.question_id]"
            />
            
            <!-- Date -->
            <Input
              v-else-if="q.question_type === 'date'"
              type="datetime-local"
              step="60"
              v-model="form.answers[q.question_id]"
            />
            
            <!-- Rating -->
            <div v-else-if="q.question_type === 'rating'" class="flex gap-1">
              <label v-for="n in (q.scale_max || 5)" :key="n">
                <input type="radio" class="sr-only" :value="n" v-model="form.answers[q.question_id]" />
                <Star
                  class="h-8 w-8 cursor-pointer"
                  :class="n <= (form.answers[q.question_id] || 0)
                    ? 'text-yellow-400 fill-yellow-400'
                    : 'text-gray-300'"
                />
              </label>
            </div>
            
            <!-- Scale -->
            <div v-else-if="q.question_type === 'scale'" class="flex gap-2">
              <label
                v-for="n in (q.scale_max || 5)"
                :key="n"
                class="w-10 h-10 flex items-center justify-center rounded-full border cursor-pointer"
                :class="n === form.answers[q.question_id]
                  ? 'bg-blue-500 text-white border-blue-500'
                  : 'border-gray-300'"
              >
                <input type="radio" class="sr-only" :value="n" v-model="form.answers[q.question_id]" />
                {{ n }}
              </label>
            </div>
            
            <!-- Dropdown -->
            <select
              v-else-if="q.question_type === 'dropdown'"
              v-model="form.answers[q.question_id]"
              class="w-full border rounded px-3 py-2"
              :class="!form.answers[q.question_id] ? 'text-gray-400' : 'text-gray-900'"
            >
              <option value="">選択してください</option>
              <option v-for="o in q.options" :key="o.option_id" :value="o.option_id">
                {{ o.option_text }}
              </option>
            </select>
          </div>
          </div>
          
          <!-- カードフッター (固定) -->
          <div class="p-4 border-t shrink-0 bg-white">
            <div class="flex justify-end gap-4">
              <Button variant="outline" @click="cancel">キャンセル</Button>
              <Button variant="outline" :disabled="form.processing" @click="submitAnswer">
                {{ isEditing ? '回答を更新' : '回答を送信' }}
              </Button>
            </div>
          </div>
        </Card>
      </div>
  </template>