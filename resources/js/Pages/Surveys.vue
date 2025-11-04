<script setup lang="ts">
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import { BarChart3, Plus, Search, Filter, Clock, CheckCircle2, AlertCircle, Users, ArrowLeft, Calendar as CalendarIcon } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Progress } from '@/components/ui/progress'
import { Avatar, AvatarFallback } from '@/components/ui/avatar'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip'
import { ScrollArea } from '@/components/ui/scroll-area'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

defineOptions({
  layout: AuthenticatedLayout,
})

interface Member {
  id: string
  name: string
  initial: string
}

interface Survey {
  id: string
  title: string
  description: string
  deadline: string
  createdBy: string
  createdAt: string
  status: 'active' | 'closed' | 'draft'
  totalQuestions: number
  respondents: Member[]
  nonRespondents: Member[]
  category: string
}

const allMembers: Member[] = [
  { id: '1', name: '田中', initial: '田' },
  { id: '2', name: '佐藤', initial: '佐' },
  { id: '3', name: '鈴木', initial: '鈴' },
  { id: '4', name: '山田', initial: '山' },
]

const mockSurveys: Survey[] = [
  {
    id: '1',
    title: '2025年度 忘年会の候補日アンケート',
    description: '12月の忘年会について、参加可能な日程を教えてください。',
    deadline: '2025-10-20',
    createdBy: '田中',
    createdAt: '2025-10-10',
    status: 'active',
    totalQuestions: 3,
    respondents: [
      { id: '1', name: '田中', initial: '田' },
      { id: '2', name: '佐藤', initial: '佐' },
    ],
    nonRespondents: [
      { id: '3', name: '鈴木', initial: '鈴' },
      { id: '4', name: '山田', initial: '山' },
    ],
    category: 'イベント',
  },
  {
    id: '2',
    title: 'オフィス備品の購入希望アンケート',
    description:
      '来月の備品発注に向けて、必要な物品や優先順位をお聞かせください。',
    deadline: '2025-10-18',
    createdBy: '佐藤',
    createdAt: '2025-10-08',
    status: 'active',
    totalQuestions: 5,
    respondents: [
      { id: '2', name: '佐藤', initial: '佐' },
      { id: '3', name: '鈴木', initial: '鈴' },
      { id: '4', name: '山田', initial: '山' },
    ],
    nonRespondents: [{ id: '1', name: '田中', initial: '田' }],
    category: '備品',
  },
  {
    id: '3',
    title: '勤怠システム更新に関する意見募集',
    description: '新しい勤怠システムの使いやすさや改善点についてご意見ください。',
    deadline: '2025-10-25',
    createdBy: '鈴木',
    createdAt: '2025-10-12',
    status: 'active',
    totalQuestions: 8,
    respondents: [
      { id: '1', name: '田中', initial: '田' },
      { id: '2', name: '佐藤', initial: '佐' },
      { id: '3', name: '鈴木', initial: '鈴' },
      { id: '4', name: '山田', initial: '山' },
    ],
    nonRespondents: [],
    category: 'システム',
  },
  {
    id: '4',
    title: '休憩室のレイアウト変更案',
    description: '休憩室のレイアウト変更について、複数案から選択してください。',
    deadline: '2025-10-15',
    createdBy: '山田',
    createdAt: '2025-10-05',
    status: 'closed',
    totalQuestions: 4,
    respondents: [
      { id: '1', name: '田中', initial: '田' },
      { id: '2', name: '佐藤', initial: '佐' },
      { id: '3', name: '鈴木', initial: '鈴' },
      { id: '4', name: '山田', initial: '山' },
    ],
    nonRespondents: [],
    category: 'オフィス環境',
  },
  {
    id: '5',
    title: '月次ミーティングの時間帯調整',
    description: '定例ミーティングの時間帯について、最適な時間をお選びください。',
    deadline: '2025-09-30',
    createdBy: '田中',
    createdAt: '2025-09-20',
    status: 'closed',
    totalQuestions: 2,
    respondents: [
      { id: '1', name: '田中', initial: '田' },
      { id: '2', name: '佐藤', initial: '佐' },
      { id: '3', name: '鈴木', initial: '鈴' },
    ],
    nonRespondents: [{ id: '4', name: '山田', initial: '山' }],
    category: '会議',
  },
]

const searchQuery = ref('')
const categoryFilter = ref('all')
const activeTab = ref('active')

const filteredSurveys = computed(() => {
  return mockSurveys.filter((survey) => {
    const matchesSearch =
      survey.title.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
      survey.description.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
      survey.createdBy.toLowerCase().includes(searchQuery.value.toLowerCase())

    const matchesCategory = categoryFilter.value === 'all' || survey.category === categoryFilter.value

    const matchesTab = survey.status === activeTab.value

    return matchesSearch && matchesCategory && matchesTab
  })
})

const categories = computed(() => Array.from(new Set(mockSurveys.map((survey) => survey.category))))

const getResponseRate = (survey: Survey) => {
  const total = survey.respondents.length + survey.nonRespondents.length
  return total > 0 ? (survey.respondents.length / total) * 100 : 0
}

const getDaysUntilDeadline = (deadline: string) => {
  const today = new Date(2025, 9, 16)
  const deadlineDate = new Date(deadline)
  const diffTime = deadlineDate.getTime() - today.getTime()
  const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24))
  return diffDays
}

</script>

<template>
  <div class="min-h-screen bg-gray-50">
    <header class="bg-white border-b border-gray-200 sticky top-0 z-10">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 py-4">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-3">
            <Button variant="ghost" size="icon" @click="router.get('/')">
              <ArrowLeft class="h-5 w-5" />
            </Button>
            <div class="flex items-center gap-2">
              <BarChart3 class="h-6 w-6 text-blue-600" />
              <div>
                <h1 class="text-blue-600">アンケート管理</h1>
                <p class="text-xs text-gray-500">総務部 共同管理</p>
              </div>
            </div>
          </div>
          <Button class="gap-2" @click="router.get('/create-survey')">
            <Plus class="h-4 w-4" />
            新しいアンケートを作成
          </Button>
        </div>
      </div>
    </header>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 py-6">
      <div class="mb-6 space-y-4">
        <div class="flex flex-col sm:flex-row gap-3">
          <div class="flex-1 relative">
            <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
            <Input
              placeholder="アンケートのタイトル、説明、作成者で検索..."
              v-model="searchQuery"
              class="pl-9"
            />
          </div>
          <Select v-model="categoryFilter">
            <SelectTrigger class="w-full sm:w-[200px]">
              <div class="flex items-center gap-2">
                <Filter class="h-4 w-4" />
                <SelectValue placeholder="カテゴリ" />
              </div>
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="all">すべてのカテゴリ</SelectItem>
              <SelectItem v-for="category in categories" :key="category" :value="category">
                {{ category }}
              </SelectItem>
            </SelectContent>
          </Select>
        </div>
        <Tabs v-model="activeTab">
          <TabsList>
            <TabsTrigger value="active" class="gap-2">
              <CheckCircle2 class="h-4 w-4" />
              アクティブ ({{ mockSurveys.filter((s) => s.status === 'active').length }})
            </TabsTrigger>
            <TabsTrigger value="closed" class="gap-2">
              <Clock class="h-4 w-4" />
              終了済み ({{ mockSurveys.filter((s) => s.status === 'closed').length }})
            </TabsTrigger>
          </TabsList>
        </Tabs>
      </div>

      <ScrollArea class="h-[calc(100vh-280px)]">
        <div class="space-y-4 pb-6">
          <div v-if="filteredSurveys.length === 0">
            <Card>
              <CardContent class="py-12 text-center">
                <BarChart3 class="h-12 w-12 mx-auto mb-3 text-gray-300" />
                <p class="text-gray-500">
                  {{ searchQuery || categoryFilter !== 'all' ? '該当するアンケートが見つかりません' : 'アンケートがありません' }}
                </p>
              </CardContent>
            </Card>
          </div>
          <Card v-for="survey in filteredSurveys" :key="survey.id" class="hover:shadow-md transition-shadow">
            <CardHeader>
              <div class="flex items-start justify-between gap-4">
                <div class="flex-1">
                  <div class="flex items-center gap-2 mb-2">
                    <CardTitle class="text-lg cursor-pointer hover:text-blue-600 transition-colors" @click="router.get(`/survey-results/${survey.id}`)">
                      {{ survey.title }}
                    </CardTitle>
                    <Badge v-if="survey.status === 'active'" :variant="getDaysUntilDeadline(survey.deadline) < 0 ? 'destructive' : getDaysUntilDeadline(survey.deadline) <= 3 ? 'default' : 'secondary'" class="gap-1">
                        <AlertCircle v-if="getDaysUntilDeadline(survey.deadline) <= 0" class="h-3 w-3" />
                        <Clock v-else class="h-3 w-3" />
                        {{ getDaysUntilDeadline(survey.deadline) < 0 ? '期限切れ' : getDaysUntilDeadline(survey.deadline) === 0 ? '今日が期限' : `残り${getDaysUntilDeadline(survey.deadline)}日` }}
                    </Badge>
                  </div>
                  <p class="text-sm text-gray-600 mb-3">{{ survey.description }}</p>
                  <div class="flex flex-wrap items-center gap-3 text-xs text-gray-500">
                    <div class="flex items-center gap-1">
                      <CalendarIcon class="h-3 w-3" />
                      期限: {{ survey.deadline }}
                    </div>
                    <div class="flex items-center gap-1">
                      作成者: {{ survey.createdBy }}
                    </div>
                    <Badge variant="outline" class="text-xs">{{ survey.category }}</Badge>
                    <Badge variant="secondary" class="text-xs">{{ survey.totalQuestions }}問</Badge>
                  </div>
                </div>
                <div class="flex items-center gap-2 flex-shrink-0">
                  <Button v-if="survey.status === 'active'" class="gap-2">
                    <CheckCircle2 class="h-4 w-4" />
                    回答する
                  </Button>
                  <Button variant="outline" class="gap-2" @click="router.get(`/survey-results/${survey.id}`)">
                    <BarChart3 class="h-4 w-4" />
                    結果を見る
                  </Button>
                </div>
              </div>
            </CardHeader>
            <CardContent>
              <div class="space-y-4">
                <div class="space-y-2">
                  <div class="flex items-center justify-between text-sm">
                    <span class="text-gray-600">回答状況</span>
                    <span>
                      <span class="text-blue-600">{{ survey.respondents.length }}</span>
                      <span class="text-gray-400"> / {{ survey.respondents.length + survey.nonRespondents.length }}名</span>
                      <span class="text-gray-600 ml-2">({{ Math.round(getResponseRate(survey)) }}%)</span>
                    </span>
                  </div>
                  <Progress :model-value="getResponseRate(survey)" class="h-2" />
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div class="space-y-2">
                    <div class="flex items-center gap-2 text-sm">
                      <CheckCircle2 class="h-4 w-4 text-green-600" />
                      <span class="text-green-600">回答済み ({{ survey.respondents.length }}名)</span>
                    </div>
                    <div class="flex flex-wrap gap-2">
                      <TooltipProvider>
                        <Tooltip v-for="member in survey.respondents" :key="member.id">
                          <TooltipTrigger>
                            <div class="flex items-center gap-2 px-3 py-1.5 bg-green-50 border border-green-200 rounded-full">
                              <Avatar class="h-6 w-6 bg-green-500">
                                <AvatarFallback class="text-xs text-white">{{ member.initial }}</AvatarFallback>
                              </Avatar>
                              <span class="text-sm text-green-700">{{ member.name }}</span>
                            </div>
                          </TooltipTrigger>
                          <TooltipContent>
                            <p>{{ member.name }}さんは回答済みです</p>
                          </TooltipContent>
                        </Tooltip>
                      </TooltipProvider>
                      <span v-if="survey.respondents.length === 0" class="text-sm text-gray-400">まだ回答者がいません</span>
                    </div>
                  </div>
                  <div class="space-y-2">
                    <div class="flex items-center gap-2 text-sm">
                      <AlertCircle class="h-4 w-4 text-orange-600" />
                      <span class="text-orange-600">未回答 ({{ survey.nonRespondents.length }}名)</span>
                    </div>
                    <div class="flex flex-wrap gap-2">
                      <TooltipProvider>
                        <Tooltip v-for="member in survey.nonRespondents" :key="member.id">
                          <TooltipTrigger>
                            <div class="flex items-center gap-2 px-3 py-1.5 bg-orange-50 border border-orange-200 rounded-full">
                              <Avatar class="h-6 w-6 bg-orange-500">
                                <AvatarFallback class="text-xs text-white">{{ member.initial }}</AvatarFallback>
                              </Avatar>
                              <span class="text-sm text-orange-700">{{ member.name }}</span>
                            </div>
                          </TooltipTrigger>
                          <TooltipContent>
                            <p>{{ member.name }}さんはまだ回答していません</p>
                          </TooltipContent>
                        </Tooltip>
                      </TooltipProvider>
                      <div v-if="survey.nonRespondents.length === 0" class="flex items-center gap-1 text-sm text-green-600">
                        <CheckCircle2 class="h-4 w-4" />
                        <span>全員が回答済みです</span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </CardContent>
          </Card>
        </div>
      </ScrollArea>

      <div class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-4">
        <Card>
          <CardContent class="pt-6">
            <div class="flex items-center gap-3">
              <div class="p-3 bg-blue-100 rounded-lg">
                <BarChart3 class="h-6 w-6 text-blue-600" />
              </div>
              <div>
                <p class="text-sm text-gray-600">アクティブ</p>
                <p class="text-2xl">{{ mockSurveys.filter((s) => s.status === 'active').length }}</p>
              </div>
            </div>
          </CardContent>
        </Card>
        <Card>
          <CardContent class="pt-6">
            <div class="flex items-center gap-3">
              <div class="p-3 bg-green-100 rounded-lg">
                <CheckCircle2 class="h-6 w-6 text-green-600" />
              </div>
              <div>
                <p class="text-sm text-gray-600">終了済み</p>
                <p class="text-2xl">{{ mockSurveys.filter((s) => s.status === 'closed').length }}</p>
              </div>
            </div>
          </CardContent>
        </Card>
        <Card>
          <CardContent class="pt-6">
            <div class="flex items-center gap-3">
              <div class="p-3 bg-purple-100 rounded-lg">
                <Users class="h-6 w-6 text-purple-600" />
              </div>
              <div>
                <p class="text-sm text-gray-600">部署メンバー</p>
                <p class="text-2xl">{{ allMembers.length }}</p>
              </div>
            </div>
          </CardContent>
        </Card>
      </div>
    </main>
  </div>
</template>
