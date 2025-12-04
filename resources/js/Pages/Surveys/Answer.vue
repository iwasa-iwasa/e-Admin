<script setup lang="ts">
import { ref, nextTick } from 'vue'
import { Head, useForm, router } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { Label } from '@/components/ui/label'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { ScrollArea } from '@/components/ui/scroll-area'
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
}>()

const form = useForm({
    answers: {} as Record<number, any>
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
            element?.scrollIntoView({ behavior: 'smooth', block: 'center' })
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
            // 成功時の処理（オプション）
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
    
    <div class="max-w-[1800px] mx-auto h-full p-6">
        <Card class="h-full overflow-hidden">
            <div class="p-4 border-b border-gray-300">
                <div class="flex items-center gap-3 mb-2">
                    <Button variant="ghost" size="icon" @click="cancel">
                        <ArrowLeft class="h-5 w-5" />
                    </Button>
                    <CardTitle>{{ survey.title }}</CardTitle>
                </div>
                <p class="text-sm text-gray-500 mb-2">{{ survey.description }}</p>
                <p class="text-sm text-gray-500">
                    回答期限: 
                    <template v-if="survey.deadline_date">
                        {{ new Date(survey.deadline_date).toLocaleDateString('ja-JP') }}
                        <template v-if="survey.deadline_time">
                            {{ survey.deadline_time.substring(0, 5) }}
                        </template>
                    </template>
                    <template v-else-if="survey.deadline">
                        {{ new Date(survey.deadline).toLocaleDateString('ja-JP') }}
                    </template>
                </p>
            </div>
            
            <ScrollArea class="h-[calc(100vh-280px)]">
                <div class="p-6">
                    <form @submit.prevent="submitAnswer" class="space-y-6">
                        <div v-for="question in questions" :key="question.question_id" :id="`question_${question.question_id}`" class="border-b border-gray-200 pb-6 last:border-b-0">
                            <Label class="text-base font-medium block mb-4">
                                {{ question.question_text }}
                                <span v-if="question.is_required" class="text-red-500 ml-1">*</span>
                            </Label>
                            <div v-if="clientValidationErrors[question.question_id]" class="flex items-center gap-2 text-red-600 text-sm mb-2">
                                <AlertCircle class="h-4 w-4" />
                                <span>{{ clientValidationErrors[question.question_id] }}</span>
                            </div>
                            <div>
                                <!-- Single Choice -->
                                <div v-if="question.question_type === 'single_choice'" class="space-y-2">
                                    <div v-for="option in question.options" :key="option.option_id" class="flex items-center space-x-2">
                                        <input 
                                            type="radio" 
                                            :name="`question_${question.question_id}`"
                                            :value="option.option_id" 
                                            :id="`q${question.question_id}_${option.option_id}`"
                                            :required="question.is_required"
                                            v-model="form.answers[question.question_id]"
                                            class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300"
                                        />
                                        <Label :for="`q${question.question_id}_${option.option_id}`">{{ option.option_text }}</Label>
                                    </div>
                                </div>

                                <!-- Multiple Choice -->
                                <div v-else-if="question.question_type === 'multiple_choice'" class="space-y-2">
                                    <div v-for="option in question.options" :key="option.option_id" class="flex items-center space-x-2">
                                        <input 
                                            type="checkbox"
                                            :id="`q${question.question_id}_${option.option_id}`"
                                            @change="handleMultipleChoiceChange(question.question_id, option.option_id, ($event.target as HTMLInputElement).checked)"
                                            class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                                        />
                                        <Label :for="`q${question.question_id}_${option.option_id}`">{{ option.option_text }}</Label>
                                    </div>
                                </div>

                                <!-- Text Input -->
                                <Input 
                                    v-else-if="question.question_type === 'text'"
                                    :required="question.is_required"
                                    v-model="form.answers[question.question_id]"
                                    placeholder="回答を入力してください"
                                    class="placeholder:text-gray-400"
                                />

                                <!-- Textarea -->
                                <Textarea 
                                    v-else-if="question.question_type === 'textarea'"
                                    :required="question.is_required"
                                    v-model="form.answers[question.question_id]"
                                    placeholder="回答を入力してください"
                                    class="min-h-[100px] placeholder:text-gray-400"
                                />

                                <!-- Date -->
                                <Input 
                                    v-else-if="question.question_type === 'date'"
                                    :required="question.is_required"
                                    v-model="form.answers[question.question_id]"
                                    type="datetime-local"
                                />

                                <!-- Rating -->
                                <div v-else-if="question.question_type === 'rating'" class="space-y-2">
                                    <div class="flex items-center gap-2">
                                        <div v-for="rating in (question.scale_max || 5)" :key="rating" class="flex items-center">
                                            <input 
                                                type="radio" 
                                                :name="`question_${question.question_id}`"
                                                :value="rating" 
                                                :id="`q${question.question_id}_${rating}`"
                                                :required="question.is_required"
                                                v-model="form.answers[question.question_id]"
                                                class="sr-only"
                                            />
                                            <label :for="`q${question.question_id}_${rating}`" class="cursor-pointer">
                                                <Star 
                                                    class="h-8 w-8 transition-colors"
                                                    :class="rating <= (form.answers[question.question_id] || 0) 
                                                        ? 'text-yellow-400 fill-yellow-400' 
                                                        : 'text-gray-300'"
                                                />
                                            </label>
                                        </div>
                                    </div>
                                </div>

                                <!-- Scale -->
                                <div v-else-if="question.question_type === 'scale'" class="space-y-2">
                                    <div class="flex items-center gap-2">
                                        <div v-for="scale in 5" :key="scale" class="flex items-center">
                                            <input 
                                                type="radio" 
                                                :name="`question_${question.question_id}`"
                                                :value="scale" 
                                                :id="`q${question.question_id}_scale_${scale}`"
                                                :required="question.is_required"
                                                v-model="form.answers[question.question_id]"
                                                class="sr-only"
                                            />
                                            <label 
                                                :for="`q${question.question_id}_scale_${scale}`" 
                                                class="w-12 h-12 rounded-full border-2 transition-colors cursor-pointer flex items-center justify-center"
                                                :class="scale == form.answers[question.question_id]
                                                    ? 'border-blue-500 bg-blue-500 text-white'
                                                    : 'border-gray-300 hover:border-gray-400'"
                                            >
                                                {{ scale }}
                                            </label>
                                        </div>
                                    </div>
                                </div>

                                <!-- Dropdown -->
                                <select 
                                    v-else-if="question.question_type === 'dropdown'" 
                                        :required="question.is_required"
                                        v-model="form.answers[question.question_id]"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                                        :class="form.answers[question.question_id] === '' || !form.answers[question.question_id] ? 'text-gray-400' : 'text-gray-900'"
                                    >
                                        <option value="" selected>選択してください</option>
                                        <option v-for="option in question.options" :key="option.option_id" :value="option.option_id">
                                            {{ option.option_text }}
                                        </option>
                                    </select>
                            </div>
                        </div>

                        <!-- エラー表示 -->
                        <div v-if="Object.keys(form.errors).length > 0" class="bg-red-50 border border-red-200 rounded-md p-4">
                            <div class="text-red-800">
                                <div v-for="(error, key) in form.errors" :key="key" class="text-sm">
                                    {{ error }}
                                </div>
                            </div>
                        </div>

                        <div class="flex gap-4 justify-end pt-4 border-t border-gray-200">
                            <Button type="button" variant="outline" @click="cancel">
                                キャンセル
                            </Button>
                            <Button type="submit" variant="outline" :disabled="form.processing">
                                {{ form.processing ? '送信中...' : '回答を送信' }}
                            </Button>
                        </div>
                    </form>
                </div>
            </ScrollArea>
        </Card>
    </div>
</template>