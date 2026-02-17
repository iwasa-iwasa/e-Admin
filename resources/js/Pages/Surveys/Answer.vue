  <script setup lang="ts">
  import { ref, nextTick, computed } from 'vue'
  import { Head, useForm, router } from '@inertiajs/vue3'
  import { route } from 'ziggy-js'
  import { Button } from '@/components/ui/button'
  import { Input } from '@/components/ui/input'
  import { Textarea } from '@/components/ui/textarea'
  import { Label } from '@/components/ui/label'
  import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
  import { ArrowLeft, Star, AlertCircle, Save } from 'lucide-vue-next'
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
  
  const form = useForm({
      answers: props.existingAnswers ? JSON.parse(JSON.stringify(props.existingAnswers)) : {} as Record<number, any>,
      status: 'submitted'
  })
  
  // 全ての回答を初期化
const initializeAnswers = () => {
    // answersがオブジェクトであることを確認
    if (Array.isArray(form.answers)) {
        form.answers = {}
    }
    
    props.questions.forEach(question => {
        // 既に値がある場合はスキップ
        if (form.answers[question.question_id] !== undefined) return

        switch (question.question_type) {
            case 'multiple_choice':
                form.answers[question.question_id] = []
                break
            case 'dropdown':
                form.answers[question.question_id] = ''
                break
            default:
                form.answers[question.question_id] = null
        }
    })
}

// コンポーネントマウント時に初期化
initializeAnswers()
  
  const displayQuestions = computed(() => {
      return props.questions.map((q, index) => convertQuestionFromBackend(q, index));
  });

  // 既存回答を初期化
  if (props.existingAnswers) {
      // existingAnswersが配列の場合はオブジェクトに変換
      const answersObj = Array.isArray(props.existingAnswers) ? {} : props.existingAnswers
      
      Object.keys(answersObj).forEach(key => {
          const questionId = Number(key)
          const answer = answersObj[questionId]
          const question = props.questions.find(q => q.question_id === questionId)
          
          if (question?.question_type === 'multiple_choice') {
              if (Array.isArray(answer)) {
                  form.answers[questionId] = [...answer]
              } else if (typeof answer === 'string') {
                  const optionTexts = answer.includes(',') ? answer.split(', ') : [answer]
                  const optionIds: number[] = []
                  for (const text of optionTexts) {
                      const option = question.options?.find(o => o.option_text === text.trim())
                      if (option) {
                          optionIds.push(option.option_id)
                      }
                  }
                  form.answers[questionId] = optionIds
              }
          } else if (question?.question_type === 'single_choice') {
              if (answer && typeof answer === 'object') {
                  if (answer.option_id) {
                      form.answers[questionId] = answer.option_id
                  } else if (answer.answer_text) {
                      const option = question.options?.find(o => o.option_text === answer.answer_text.trim())
                      form.answers[questionId] = option ? option.option_id : null
                  }
              } else if (typeof answer === 'number') {
                  form.answers[questionId] = answer
              } else if (typeof answer === 'string') {
                  const option = question.options?.find(o => o.option_text === answer.trim())
                  form.answers[questionId] = option ? option.option_id : null
              } else {
                  form.answers[questionId] = null
              }
          } else if (question?.question_type === 'scale' && answer) {
              form.answers[questionId] = Number(answer)
          } else {
              form.answers[questionId] = answer
          }
      })
  }
  const clientValidationErrors = ref<Record<number, string>>({})
  
  const validateAnswers = () => {
      clientValidationErrors.value = {}
      
      console.log('=== Validation Debug ===')
      console.log('form.answers type:', typeof form.answers, 'isArray:', Array.isArray(form.answers))
      console.log('form.answers keys:', Object.keys(form.answers))
      console.log('form.answers values:', Object.values(form.answers))
      console.log('form.answers full:', JSON.parse(JSON.stringify(form.answers)))
      console.log('questions:', props.questions)
      
      for (const question of props.questions) {
          console.log(`\nChecking question ${question.question_id}:`, question.question_text)
          console.log('  is_required:', question.is_required)
          console.log('  question_type:', question.question_type)
          
          if (!question.is_required) {
              console.log('  -> Skipping (not required)')
              continue
          }
          
          const answer = form.answers[question.question_id]
          console.log('  answer:', answer, 'type:', typeof answer)
          
          if (question.question_type === 'multiple_choice') {
              if (!answer || (Array.isArray(answer) && answer.length === 0)) {
                  console.log('  -> ERROR: empty multiple choice')
                  clientValidationErrors.value[question.question_id] = '少なくとも1つ選択してください'
              }
          } else if (question.question_type === 'single_choice' || question.question_type === 'dropdown') {
              if (answer === null || answer === undefined || answer === '') {
                  console.log('  -> ERROR: empty single choice/dropdown')
                  clientValidationErrors.value[question.question_id] = 'この項目は必須です'
              }
          } else if (question.question_type === 'rating' || question.question_type === 'scale') {
              if (answer === null || answer === undefined) {
                  console.log('  -> ERROR: empty rating/scale')
                  clientValidationErrors.value[question.question_id] = 'この項目は必須です'
              }
          } else {
              // text, textarea, date など
              if (answer === null || answer === undefined || (typeof answer === 'string' && answer.trim() === '')) {
                  console.log('  -> ERROR: empty text field')
                  clientValidationErrors.value[question.question_id] = 'この項目は必須です'
              }
          }
      }
      
      console.log('\nValidation errors:', clientValidationErrors.value)
      console.log('Error count:', Object.keys(clientValidationErrors.value).length)
      console.log('=== End Validation ====')
      
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
  
  const submitAnswer = (status: 'draft' | 'submitted' = 'submitted') => {
      console.log('\n>>> submitAnswer called, status:', status)
      
      // 一時保存の場合はバリデーションをスキップ
      if (status === 'submitted') {
          console.log('Running validation...')
          const isValid = validateAnswers()
          console.log('Validation result:', isValid)
          if (!isValid) {
              console.log('>>> BLOCKED: Validation failed!')
              return
          }
      }

      form.status = status
      console.log('>>> Proceeding with submission...')
      
      form.post(route('surveys.submit', props.survey.survey_id), {
          preserveScroll: true,
          onSuccess: () => {
              console.log('>>> Submission successful')
          },
          onError: (errors) => {
              console.error('送信エラー:', errors);
              // サーバーエラーを表示
              if (errors.error) {
                  alert(errors.error);
              }
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
          <div class="p-4 border-b border-border shrink-0">
            <div class="flex items-center gap-3 mb-2">
              <Button variant="ghost" size="icon" @click="cancel">
                <ArrowLeft class="h-5 w-5" />
              </Button>
              <CardTitle>{{ survey.title }}</CardTitle>
            </div>
            <p class="text-sm text-muted-foreground">{{ survey.description }}</p>
          </div>
          
          <!-- 質問リスト (スクロール可能) -->
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
          
          <!-- カードフッター (固定) -->
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
  </template>