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
  
  // 全ての回答を初期化（一度だけ実行）
const initialAnswers: Record<number, any> = {}
props.questions.forEach(question => {
    // 既存の回答がある場合はそれを使用
    if (props.existingAnswers && props.existingAnswers[question.question_id] !== undefined) {
        initialAnswers[question.question_id] = props.existingAnswers[question.question_id]
    } else {
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
    }
})

const form = useForm({
    answers: initialAnswers,
    status: 'submitted'
})
  
  const displayQuestions = computed(() => {
      return props.questions.map((q, index) => convertQuestionFromBackend(q, index));
  });
  const clientValidationErrors = ref<Record<number, string>>({})
  
  const validateAnswers = () => {
      clientValidationErrors.value = {}
      
      for (const question of props.questions) {
          if (!question.is_required) continue
          
          const answer = form.answers[question.question_id]
          if (question.question_type === 'multiple_choice') {
              if (!answer || (Array.isArray(answer) && answer.length === 0)) {
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
  
  const submitAnswer = (status: 'draft' | 'submitted' = 'submitted') => {
      // 一時保存の場合はバリデーションをスキップ
      if (status === 'submitted' && !validateAnswers()) {
          return
      }

      form.status = status
      
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