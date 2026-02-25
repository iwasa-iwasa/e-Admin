<script setup lang="ts">
  import { ref, nextTick, computed, watch, onMounted } from 'vue'
  import { Head, useForm, router, usePage } from '@inertiajs/vue3'
  import { route } from 'ziggy-js'
  import { Button } from '@/components/ui/button'
  import { Input } from '@/components/ui/input'
  import { Textarea } from '@/components/ui/textarea'
  import { Label } from '@/components/ui/label'
  import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
  import { ArrowLeft, Star, AlertCircle, Save, CheckCircle } from 'lucide-vue-next'
  import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
  import QuestionViewer from '@/features/survey/components/SurveyRespondent/QuestionViewer.vue'
  import { convertQuestionFromBackend } from '@/features/survey/domain/factory'
  
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
      existingAnswers?: any
      isEditing?: boolean
      errors?: any
      auth?: any
      ziggy?: any
      flash?: any
  }>()
  
  // 全ての回答を初期化（一度だけ実行）
const initialAnswers: Record<number, any> = {}

props.questions.forEach(question => {
    switch (question.question_type) {
        case 'multiple_choice':
            initialAnswers[question.question_id] = []
            break
        case 'dropdown':
            initialAnswers[question.question_id] = ''
            break
        default:
            initialAnswers[question.question_id] = null
    }
})

// 2. 既存の回答がある場合、データ型を調整して initialAnswers に上書き
// (mainブランチのロジックを採用：文字列パースやオブジェクト変換処理)
if (props.existingAnswers) {
    // existingAnswersが配列の場合はオブジェクトに変換
    const answersObj = Array.isArray(props.existingAnswers) ? {} : props.existingAnswers

    Object.keys(answersObj).forEach(key => {
        const questionId = Number(key)
        const answer = answersObj[questionId]
        const question = props.questions.find(q => q.question_id === questionId)

        // 該当する質問が存在しない場合はスキップ
        if (!question) return

        if (question.question_type === 'multiple_choice') {
            if (Array.isArray(answer)) {
                initialAnswers[questionId] = [...answer]
            } else if (typeof answer === 'string') {
                const optionTexts = answer.includes(',') ? answer.split(', ') : [answer]
                const optionIds: number[] = []
                for (const text of optionTexts) {
                    const option = question.options?.find(o => o.option_text === text.trim())
                    if (option) {
                        optionIds.push(option.option_id)
                    }
                }
                initialAnswers[questionId] = optionIds
            }
        } else if (question.question_type === 'single_choice') {
            if (answer && typeof answer === 'object') {
                if (answer.option_id) {
                    initialAnswers[questionId] = answer.option_id
                } else if (answer.answer_text) {
                    const option = question.options?.find(o => o.option_text === answer.answer_text.trim())
                    initialAnswers[questionId] = option ? option.option_id : null
                }
            } else if (typeof answer === 'number') {
                initialAnswers[questionId] = answer
            } else if (typeof answer === 'string') {
                const option = question.options?.find(o => o.option_text === answer.trim())
                initialAnswers[questionId] = option ? option.option_id : null
            }
        } else if (question.question_type === 'scale' && answer) {
            initialAnswers[questionId] = Number(answer)
        } else {
            // text, textarea, date など
            initialAnswers[questionId] = answer
        }
    })
}

// 3. フォームの初期化
const form = useForm({
    answers: initialAnswers,
    status: 'submitted'
})
  
  const displayQuestions = computed(() => {
      return props.questions.map((q, index) => convertQuestionFromBackend(q, index));
  });
  const clientValidationErrors = ref<Record<number, string>>({})
  
  const validateAnswers = () => {
      const errors: Record<number, string> = {}
      
      for (const question of props.questions) {
          if (!question.is_required) continue
          
          const answer = form.answers[question.question_id]
          if (question.question_type === 'multiple_choice') {
              if (!answer || (Array.isArray(answer) && answer.length === 0)) {
                  errors[question.question_id] = '少なくとも1つ選択してください'
              }
          } else if (!answer || (typeof answer === 'string' && answer.trim() === '')) {
              errors[question.question_id] = 'この項目は必須です'
          }
      }
      
      clientValidationErrors.value = errors
      
      if (Object.keys(errors).length > 0) {
          nextTick(() => {
              const firstErrorId = Object.keys(errors)[0]
              const element = document.getElementById(`question_${firstErrorId}`)
              element?.scrollIntoView({ behavior: 'smooth', block: 'start' })
          })
          return false
      }
      return true
  }
  
  const submitAnswer = (status: 'draft' | 'submitted' = 'submitted') => {
      // 一時保存の場合はバリデーションをスキップ
      if (status === 'submitted' && !validateAnswers()) {
          return
      }

      form.status = status
      
      form.post(route('surveys.submit', props.survey.survey_id), {
          preserveScroll: true,
          onError: (errors) => {
              console.error('送信エラー:', errors);
          },
      });
  }
  
  const cancel = () => {
      router.get(route('surveys'))
  }
  
  const saveMessage = ref('')
  const saveMessageType = ref<'success' | 'error'>('success')
  const saveMessageTimer = ref<number | null>(null)
  
  const showSaveMessage = (message: string, type: 'success' | 'error' = 'success') => {
      if (saveMessageTimer.value) {
          clearTimeout(saveMessageTimer.value)
      }
      
      saveMessage.value = message
      saveMessageType.value = type
      
      saveMessageTimer.value = setTimeout(() => {
          saveMessage.value = ''
      }, 4000)
  }
  
  const page = usePage()
  
  onMounted(() => {
    const flash = (page.props as any).flash?.success
    if (flash) {
      showSaveMessage(flash, 'success')
    }
  })
  </script>
  
  <template>
    <Head :title="`${survey.title} - 回答`" />

    <div class="h-full p-6">
        <Card class="h-full flex flex-col overflow-hidden">
            <div class="p-4 border-b border-border shrink-0">
                <div class="flex items-center gap-3 mb-2">
                    <Button variant="ghost" size="icon" @click="cancel">
                        <ArrowLeft class="h-5 w-5" />
                    </Button>
                    <CardTitle>{{ survey.title }}</CardTitle>
                </div>
                <p class="text-sm text-muted-foreground">{{ survey.description }}</p>
            </div>

            <div class="flex-1 overflow-y-auto p-6 space-y-6">
                <QuestionViewer
                    v-for="(q, index) in displayQuestions"
                    :key="q.id"
                    :question="q"
                    :model-value="form.answers[q.id]"
                    @update:model-value="form.answers[q.id] = $event"
                    mode="answer"
                    :error="clientValidationErrors[Number(q.id)]"
                    :question-number="index + 1"
                    :id="`question_${q.id}`"
                />
            </div>

            <div class="p-4 border-t border-border shrink-0 bg-background">
                <div class="flex justify-end gap-4">
                    <Button variant="outline" @click="cancel">キャンセル</Button>
                    <Button variant="outline" :disabled="form.processing" @click="submitAnswer('draft')">
                        一時保存
                    </Button>
                    <Button :disabled="form.processing" @click="submitAnswer('submitted')">
                        {{ isEditing ? '回答を更新' : '回答を送信' }}
                    </Button>
                </div>
            </div>
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
          :class="['fixed bottom-4 left-1/2 transform -translate-x-1/2 z-50 p-3 text-white rounded-lg shadow-lg',
            saveMessageType === 'success' ? 'bg-green-500' : 'bg-red-500']"
        >
          <div class="flex items-center gap-2">
            <CheckCircle v-if="saveMessageType === 'success'" class="h-5 w-5" />
            <AlertCircle v-else class="h-5 w-5" />
            <span class="font-medium">{{ saveMessage }}</span>
          </div>
        </div>
      </Transition>
  </template>
