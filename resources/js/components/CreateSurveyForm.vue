<script setup lang="ts">
import { ref, h } from 'vue'
import { router } from '@inertiajs/vue3'
import { ArrowLeft, Plus, Save, Trash2, GripVertical, Calendar as CalendarIcon, CheckSquare, Circle, Type, Star, List, Clock, BarChart2 } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { Label } from '@/components/ui/label'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { ScrollArea } from '@/components/ui/scroll-area'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Checkbox } from '@/components/ui/checkbox'
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { useToast } from '@/components/ui/toast/use-toast'

type QuestionType =
  | 'single'
  | 'multiple'
  | 'text'
  | 'textarea'
  | 'rating'
  | 'scale'
  | 'dropdown'
  | 'date'

interface Question {
  id: string
  type: QuestionType
  question: string
  options: string[]
  required: boolean
  scaleMin?: number
  scaleMax?: number
  scaleMinLabel?: string
  scaleMaxLabel?: string
}

interface QuestionTemplate {
  type: QuestionType
  name: string
  description: string
  icon: any
  defaultOptions?: string[]
  scaleMin?: number
  scaleMax?: number
}

const title = ref('')
const description = ref('')
const category = ref('その他')
const deadline = ref('')
const questions = ref<Question[]>([])
const showTemplateDialog = ref(false)

const { toast } = useToast()

const questionTemplates: QuestionTemplate[] = [
  { type: 'single', name: '単一選択（ラジオボタン）', description: '複数の選択肢から1つを選ぶ形式', icon: Circle, defaultOptions: ['選択肢1', '選択肢2', '選択肢3'] },
  { type: 'multiple', name: '複数選択（チェックボックス）', description: '複数の選択肢から複数選べる形式', icon: CheckSquare, defaultOptions: ['選択肢1', '選択肢2', '選択肢3'] },
  { type: 'text', name: '自由記述（短文）', description: '短いテキストを入力する形式', icon: Type, defaultOptions: [] },
  { type: 'textarea', name: '自由記述（長文）', description: '長いテキストを入力する形式', icon: Type, defaultOptions: [] },
  { type: 'rating', name: '評価スケール（星評価）', description: '星で満足度などを評価する形式', icon: Star, defaultOptions: [], scaleMin: 1, scaleMax: 5 },
  { type: 'scale', name: '評価スケール（リッカート）', description: '段階的に評価する形式（例：5段階評価）', icon: BarChart2, defaultOptions: [], scaleMin: 1, scaleMax: 5 },
  { type: 'dropdown', name: 'ドロップダウン', description: 'リストから1つを選ぶ形式', icon: List, defaultOptions: ['選択肢1', '選択肢2', '選択肢3'] },
  { type: 'date', name: '日付/時刻', description: '特定の日時を入力する形式', icon: Clock, defaultOptions: [] },
]

const createQuestionFromTemplate = (template: QuestionTemplate) => {
  const newQuestion: Question = {
    id: String(Date.now()),
    type: template.type,
    question: '',
    options: template.defaultOptions || [],
    required: false,
    scaleMin: template.scaleMin,
    scaleMax: template.scaleMax,
    scaleMinLabel: template.scaleMin === 1 ? 'とても悪い' : '',
    scaleMaxLabel: template.scaleMax === 5 ? 'とても良い' : '',
  }
  questions.value.push(newQuestion)
  showTemplateDialog.value = false
}

const removeQuestion = (id: string) => {
  if (questions.value.length > 0) {
    questions.value = questions.value.filter((q) => q.id !== id)
  }
}

const updateQuestion = (id: string, field: keyof Question, value: any) => {
  questions.value = questions.value.map((q) => (q.id === id ? { ...q, [field]: value } : q))
}

const addOption = (questionId: string) => {
  questions.value = questions.value.map((q) => {
    if (q.id === questionId) {
      return { ...q, options: [...q.options, ''] }
    }
    return q
  })
}

const updateOption = (questionId: string, optionIndex: number, value: string) => {
  questions.value = questions.value.map((q) => {
    if (q.id === questionId) {
      const newOptions = [...q.options]
      newOptions[optionIndex] = value
      return { ...q, options: newOptions }
    }
    return q
  })
}

const removeOption = (questionId: string, optionIndex: number) => {
  questions.value = questions.value.map((q) => {
    if (q.id === questionId && q.options.length > 2) {
      const newOptions = q.options.filter((_, i) => i !== optionIndex)
      return { ...q, options: newOptions }
    }
    return q
  })
}

const handleSave = (isDraft: boolean = false) => {
  if (!title.value.trim()) {
    toast({ title: 'Error', description: 'アンケートのタイトルを入力してください', variant: 'destructive' })
    return
  }
  if (!deadline.value) {
    toast({ title: 'Error', description: '回答期限を設定してください', variant: 'destructive' })
    return
  }
  if (questions.value.length === 0) {
    toast({ title: 'Error', description: '最低1つの質問を追加してください', variant: 'destructive' })
    return
  }

  for (const question of questions.value) {
    if (!question.question.trim()) {
      toast({ title: 'Error', description: 'すべての質問を入力してください', variant: 'destructive' })
      return
    }
    if (['single', 'multiple', 'dropdown'].includes(question.type)) {
      const validOptions = question.options.filter((opt) => opt.trim())
      if (validOptions.length < 2) {
        toast({ title: 'Error', description: '選択肢形式の質問には最低2つの選択肢が必要です', variant: 'destructive' })
        return
      }
    }
  }

  if (isDraft) {
    toast({ title: 'Success', description: 'アンケートを一時保存しました' })
  } else {
    toast({ title: 'Success', description: 'アンケートを公開しました' })
  }
  router.get('/surveys')
}

const handleCancel = () => {
  if (title.value || description.value || questions.value.some((q) => q.question || q.options.some((o) => o))) {
    if (window.confirm('入力内容が失われますが、よろしいですか？')) {
      router.get('/surveys')
    }
  } else {
    router.get('/surveys')
  }
}

const getQuestionTypeLabel = (type: QuestionType) => {
  const template = questionTemplates.find((t) => t.type === type)
  return template?.name || type
}

</script>

<template>
  <div class="min-h-screen bg-gray-50">
    <header class="bg-white border-b border-gray-200 sticky top-0 z-10">
      <div class="max-w-4xl mx-auto px-4 sm:px-6 py-4">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-3">
            <Button variant="ghost" size="icon" @click="handleCancel">
              <ArrowLeft class="h-5 w-5" />
            </Button>
            <div>
              <h1 class="text-blue-600">新しいアンケートを作成</h1>
              <p class="text-xs text-gray-500">総務部 共同管理</p>
            </div>
          </div>
          <div class="flex items-center gap-2">
            <Button variant="outline" @click="handleSave(true)">一時保存</Button>
            <Button @click="handleSave(false)" class="gap-2">
              <Save class="h-4 w-4" />
              アンケートを公開
            </Button>
          </div>
        </div>
      </div>
    </header>

    <main class="max-w-4xl mx-auto px-4 sm:px-6 py-6">
      <ScrollArea class="h-[calc(100vh-120px)]">
        <div class="space-y-6 pb-6">
          <Card>
            <CardHeader>
              <CardTitle>基本情報</CardTitle>
            </CardHeader>
            <CardContent class="space-y-4">
              <div class="space-y-2">
                <Label for="title">アンケートタイトル *</Label>
                <Input id="title" placeholder="例：2025年度 忘年会の候補日アンケート" v-model="title" />
              </div>
              <div class="space-y-2">
                <Label for="description">説明</Label>
                <Textarea id="description" placeholder="アンケートの目的や注意事項を入力..." v-model="description" class="min-h-[80px]" />
              </div>
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="space-y-2">
                  <Label for="category">カテゴリ</Label>
                  <Select v-model="category">
                    <SelectTrigger id="category">
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="イベント">イベント</SelectItem>
                      <SelectItem value="備品">備品</SelectItem>
                      <SelectItem value="システム">システム</SelectItem>
                      <SelectItem value="オフィス環境">オフィス環境</SelectItem>
                      <SelectItem value="会議">会議</SelectItem>
                      <SelectItem value="その他">その他</SelectItem>
                    </SelectContent>
                  </Select>
                </div>
                <div class="space-y-2">
                  <Label for="deadline">回答期限 *</Label>
                  <Input id="deadline" type="date" v-model="deadline" />
                </div>
              </div>
            </CardContent>
          </Card>

          <div class="space-y-4">
            <Card v-if="questions.length === 0" class="border-dashed border-2">
              <CardContent class="py-12 text-center">
                <div class="mx-auto w-16 h-16 bg-blue-50 rounded-full flex items-center justify-center mb-4">
                  <Plus class="h-8 w-8 text-blue-600" />
                </div>
                <h3 class="mb-2">質問を追加しましょう</h3>
                <p class="text-sm text-gray-500 mb-6">下のボタンから質問形式を選択して追加できます</p>
                <Button @click="showTemplateDialog = true" class="gap-2">
                  <Plus class="h-4 w-4" />
                  新しい質問を追加
                </Button>
              </CardContent>
            </Card>
            <template v-else>
              <Card v-for="(question, index) in questions" :key="question.id">
                <CardHeader>
                  <div class="flex items-center justify-between">
                    <CardTitle class="text-base flex items-center gap-2">
                      <GripVertical class="h-4 w-4 text-gray-400" />
                      質問 {{ index + 1 }}
                    </CardTitle>
                    <Button variant="ghost" size="sm" @click="removeQuestion(question.id)">
                      <Trash2 class="h-4 w-4" />
                    </Button>
                  </div>
                </CardHeader>
                <CardContent class="space-y-4">
                  <div class="space-y-2">
                    <Label>質問文 *</Label>
                    <Input placeholder="質問を入力してください" :model-value="question.question" @update:model-value="updateQuestion(question.id, 'question', $event)" />
                  </div>
                  <div class="space-y-2">
                    <Label>回答形式</Label>
                    <div class="p-3 bg-gray-50 rounded-md border">
                      <p class="text-sm">{{ getQuestionTypeLabel(question.type) }}</p>
                    </div>
                  </div>
                  <div v-if="question.type === 'single'" class="space-y-2">
                    <Label>選択肢</Label>
                    <div class="space-y-2">
                      <div v-for="(option, optionIndex) in question.options" :key="optionIndex" class="flex items-center gap-2">
                        <div class="w-4 h-4 rounded-full border-2 border-gray-400 flex-shrink-0" />
                        <Input :placeholder="`選択肢 ${optionIndex + 1}`" :model-value="option" @update:model-value="updateOption(question.id, optionIndex, $event)" class="flex-1" />
                        <Button v-if="question.options.length > 2" variant="ghost" size="sm" @click="removeOption(question.id, optionIndex)">
                          <Trash2 class="h-4 w-4" />
                        </Button>
                      </div>
                    </div>
                    <Button variant="outline" size="sm" @click="addOption(question.id)" class="gap-2">
                      <Plus class="h-4 w-4" />
                      選択肢を追加
                    </Button>
                  </div>
                  <div v-if="question.type === 'multiple'" class="space-y-2">
                    <Label>選択肢</Label>
                    <div class="space-y-2">
                        <div v-for="(option, optionIndex) in question.options" :key="optionIndex" class="flex items-center gap-2">
                            <div class="w-4 h-4 rounded border-2 border-gray-400 flex-shrink-0"></div>
                            <Input :placeholder="`選択肢 ${optionIndex + 1}`" :model-value="option" @update:model-value="updateOption(question.id, optionIndex, $event)" class="flex-1" />
                            <Button v-if="question.options.length > 2" variant="ghost" size="sm" @click="removeOption(question.id, optionIndex)">
                                <Trash2 class="h-4 w-4" />
                            </Button>
                        </div>
                    </div>
                    <Button variant="outline" size="sm" @click="addOption(question.id)" class="gap-2">
                        <Plus class="h-4 w-4" />
                        選択肢を追加
                    </Button>
                  </div>
                  <div v-if="question.type === 'dropdown'" class="space-y-2">
                      <Label>選択肢</Label>
                      <div class="space-y-2">
                          <div v-for="(option, optionIndex) in question.options" :key="optionIndex" class="flex items-center gap-2">
                              <span class="text-sm text-gray-500 w-6">{{ optionIndex + 1 }}.</span>
                              <Input :placeholder="`選択肢 ${optionIndex + 1}`" :model-value="option" @update:model-value="updateOption(question.id, optionIndex, $event)" class="flex-1" />
                              <Button v-if="question.options.length > 2" variant="ghost" size="sm" @click="removeOption(question.id, optionIndex)">
                                  <Trash2 class="h-4 w-4" />
                              </Button>
                          </div>
                      </div>
                      <Button variant="outline" size="sm" @click="addOption(question.id)" class="gap-2">
                          <Plus class="h-4 w-4" />
                          選択肢を追加
                      </Button>
                  </div>
                  <div v-if="question.type === 'text'" class="p-4 bg-gray-50 rounded-md border">
                    <Input placeholder="回答者がここに短文を入力します" disabled />
                  </div>
                  <div v-if="question.type === 'textarea'" class="p-4 bg-gray-50 rounded-md border">
                    <Textarea placeholder="回答者がここに長文を入力します" disabled class="min-h-[100px]" />
                  </div>
                  <div v-if="question.type === 'rating'" class="space-y-3">
                    <div class="p-4 bg-gray-50 rounded-md border">
                      <div class="flex items-center justify-center gap-2">
                        <Star v-for="star in Array.from({ length: question.scaleMax || 5 }, (_, i) => i + 1)" :key="star" class="h-8 w-8 text-gray-300 fill-gray-200" />
                      </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                      <div class="space-y-2">
                        <Label>最小値</Label>
                        <Input type="number" :model-value="question.scaleMin || 1" @update:model-value="updateQuestion(question.id, 'scaleMin', parseInt($event))" />
                      </div>
                      <div class="space-y-2">
                        <Label>最大値</Label>
                        <Input type="number" :model-value="question.scaleMax || 5" @update:model-value="updateQuestion(question.id, 'scaleMax', parseInt($event))" />
                      </div>
                    </div>
                  </div>
                  <div v-if="question.type === 'scale'" class="space-y-3">
                    <div class="space-y-2">
                      <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-2">
                          <Label>最小値</Label>
                          <Input type="number" :model-value="question.scaleMin || 1" @update:model-value="updateQuestion(question.id, 'scaleMin', parseInt($event))" />
                        </div>
                        <div class="space-y-2">
                          <Label>最大値</Label>
                          <Input type="number" :model-value="question.scaleMax || 5" @update:model-value="updateQuestion(question.id, 'scaleMax', parseInt($event))" />
                        </div>
                      </div>
                      <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-2">
                          <Label>最小値ラベル（任意）</Label>
                          <Input placeholder="例：とても悪い" :model-value="question.scaleMinLabel || ''" @update:model-value="updateQuestion(question.id, 'scaleMinLabel', $event)" />
                        </div>
                        <div class="space-y-2">
                          <Label>最大値ラベル（任意）</Label>
                          <Input placeholder="例：とても良い" :model-value="question.scaleMaxLabel || ''" @update:model-value="updateQuestion(question.id, 'scaleMaxLabel', $event)" />
                        </div>
                      </div>
                    </div>
                    <div class="p-4 bg-gray-50 rounded-md border">
                      <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-600">{{ question.scaleMinLabel || question.scaleMin || 1 }}</span>
                        <div class="flex gap-2">
                          <div v-for="value in Array.from({ length: (question.scaleMax || 5) - (question.scaleMin || 1) + 1 }, (_, i) => i + (question.scaleMin || 1))" :key="value" class="w-10 h-10 rounded-full border-2 border-gray-300 flex items-center justify-center text-sm">
                            {{ value }}
                          </div>
                        </div>
                        <span class="text-sm text-gray-600">{{ question.scaleMaxLabel || question.scaleMax || 5 }}</span>
                      </div>
                    </div>
                  </div>
                  <div v-if="question.type === 'date'" class="p-4 bg-gray-50 rounded-md border">
                    <Input type="datetime-local" disabled />
                  </div>
                  <div class="flex items-center space-x-2">
                    <Checkbox :id="`required-${question.id}`" :checked="question.required" @update:checked="updateQuestion(question.id, 'required', $event)" />
                    <Label :for="`required-${question.id}`" class="text-sm cursor-pointer">必須項目にする</Label>
                  </div>
                </CardContent>
              </Card>
              <Button variant="outline" @click="showTemplateDialog = true" class="w-full gap-2">
                <Plus class="h-4 w-4" />
                新しい質問を追加
              </Button>
            </template>
          </div>

          <div class="flex flex-col sm:hidden gap-2 pt-4">
            <Button variant="outline" @click="handleSave(false)" class="w-full gap-2">
              <Save class="h-4 w-4" />
              アンケートを公開
            </Button>
            <Button variant="outline" @click="handleSave(true)" class="w-full">一時保存</Button>
            <Button variant="outline" @click="handleCancel" class="w-full">キャンセル</Button>
          </div>
        </div>
      </ScrollArea>
    </main>

    <Dialog v-model:open="showTemplateDialog">
      <DialogContent class="max-w-3xl max-h-[80vh] overflow-y-auto">
        <DialogHeader>
          <DialogTitle>質問形式を選択</DialogTitle>
          <DialogDescription>作成したい質問の形式を選択してください</DialogDescription>
        </DialogHeader>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
          <Card v-for="template in questionTemplates" :key="template.type" class="cursor-pointer hover:shadow-md hover:border-blue-500 transition-all" @click="createQuestionFromTemplate(template)">
            <CardContent class="p-6">
              <div class="flex items-start gap-4">
                <div class="p-3 bg-blue-50 rounded-lg text-blue-600">
                  <component :is="template.icon" class="h-6 w-6" />
                </div>
                <div class="flex-1">
                  <h3 class="mb-2">{{ template.name }}</h3>
                  <p class="text-sm text-gray-600">{{ template.description }}</p>
                </div>
              </div>
            </CardContent>
          </Card>
        </div>
      </DialogContent>
    </Dialog>
  </div>
</template>
