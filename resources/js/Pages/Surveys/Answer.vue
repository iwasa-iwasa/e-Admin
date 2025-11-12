<script setup lang="ts">
import { ref } from 'vue'
import { Head, useForm, router } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { Label } from '@/components/ui/label'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { ArrowLeft, Star } from 'lucide-vue-next'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

defineOptions({
    layout: AuthenticatedLayout,
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
}

interface Survey {
    survey_id: number
    title: string
    description: string
    deadline: string
}

const props = defineProps<{
    survey: Survey
    questions: Question[]
}>()

const form = useForm({
    answers: {} as Record<number, any>
})

const multipleChoiceAnswers = ref<Record<number, number[]>>({})

const handleMultipleChoiceChange = (questionId: number, optionId: number, checked: boolean) => {
    if (!multipleChoiceAnswers.value[questionId]) {
        multipleChoiceAnswers.value[questionId] = []
    }
    if (checked) {
        multipleChoiceAnswers.value[questionId].push(optionId)
    } else {
        multipleChoiceAnswers.value[questionId] = multipleChoiceAnswers.value[questionId].filter(id => id !== optionId)
    }
    form.answers[questionId] = multipleChoiceAnswers.value[questionId]
}

const submitAnswer = () => {
    form.post(route('surveys.submit', props.survey.survey_id))
}

const cancel = () => {
    router.get(route('surveys'))
}
</script>

<template>
    <Head :title="`${survey.title} - 回答`" />
    
    <div class="min-h-screen bg-gray-50">
        <header class="bg-white border-b border-gray-200 sticky top-0 z-10">
            <div class="max-w-4xl mx-auto px-4 sm:px-6 py-4">
                <div class="flex items-center gap-3">
                    <Button variant="ghost" size="icon" @click="cancel">
                        <ArrowLeft class="h-5 w-5" />
                    </Button>
                    <div>
                        <h1 class="text-xl font-semibold text-gray-900">{{ survey.title }}</h1>
                        <p class="text-sm text-gray-500">回答期限: {{ new Date(survey.deadline).toLocaleDateString() }}</p>
                    </div>
                </div>
            </div>
        </header>

        <main class="max-w-4xl mx-auto px-4 sm:px-6 py-6">
            <Card class="mb-6">
                <CardHeader>
                    <CardTitle>{{ survey.title }}</CardTitle>
                </CardHeader>
                <CardContent>
                    <p class="text-gray-600">{{ survey.description }}</p>
                </CardContent>
            </Card>

            <form @submit.prevent="submitAnswer" class="space-y-6">
                <Card v-for="question in questions" :key="question.question_id">
                    <CardHeader>
                        <Label class="text-base font-medium">
                            {{ question.question_text }}
                            <span v-if="question.is_required" class="text-red-500 ml-1">*</span>
                        </Label>
                    </CardHeader>
                    <CardContent>
                        <!-- Single Choice -->
                        <div v-if="question.question_type === 'single_choice'" class="space-y-2">
                            <div v-for="option in question.options" :key="option.option_id" class="flex items-center space-x-2">
                                <input 
                                    type="radio" 
                                    :name="`question_${question.question_id}`"
                                    :value="option.option_id" 
                                    :id="`q${question.question_id}_${option.option_id}`"
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
                            v-model="form.answers[question.question_id]"
                            placeholder="回答を入力してください"
                        />

                        <!-- Textarea -->
                        <Textarea 
                            v-else-if="question.question_type === 'textarea'"
                            v-model="form.answers[question.question_id]"
                            placeholder="回答を入力してください"
                            class="min-h-[100px]"
                        />

                        <!-- Date -->
                        <Input 
                            v-else-if="question.question_type === 'date'"
                            v-model="form.answers[question.question_id]"
                            type="date"
                        />

                        <!-- Rating -->
                        <div v-else-if="question.question_type === 'rating'" class="space-y-2">
                            <div class="flex items-center gap-2">
                                <div v-for="rating in 5" :key="rating" class="flex items-center">
                                    <input 
                                        type="radio" 
                                        :name="`question_${question.question_id}`"
                                        :value="rating" 
                                        :id="`q${question.question_id}_${rating}`"
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
                            v-model="form.answers[question.question_id]"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                        >
                            <option value="">選択してください</option>
                            <option v-for="option in question.options" :key="option.option_id" :value="option.option_id">
                                {{ option.option_text }}
                            </option>
                        </select>
                    </CardContent>
                </Card>

                <div class="flex gap-4 justify-end">
                    <Button type="button" variant="outline" @click="cancel">
                        キャンセル
                    </Button>
                    <Button type="submit" variant="outline" :disabled="form.processing">
                        {{ form.processing ? '送信中...' : '回答を送信' }}
                    </Button>
                </div>
            </form>
        </main>
    </div>
</template>