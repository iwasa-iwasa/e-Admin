<script setup lang="ts">
import { computed } from 'vue';
import { Question } from '../../domain/models';
import * as Inputs from '../inputs';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';

const props = defineProps<{
    question: Question;
    modelValue: any;
    mode: 'answer' | 'read';
    error?: string;
    questionNumber?: number;
}>();

const emit = defineEmits(['update:modelValue']);

const InputComponent = computed(() => {
     switch (props.question.type) {
        case 'single': return Inputs.SingleChoice;
        case 'multiple': return Inputs.MultipleChoice;
        case 'text': return Inputs.TextInput;
        case 'textarea': return Inputs.TextareaInput;
        case 'rating': return Inputs.RatingInput;
        case 'scale': return Inputs.ScaleInput;
        case 'dropdown': return Inputs.DropdownInput;
        case 'date': return Inputs.DateInput;
        default: return Inputs.TextInput;
    }
});
</script>

<template>
    <div class="space-y-3 p-4 bg-card rounded-lg border border-border shadow-sm text-card-foreground">
        <div class="flex items-start justify-between gap-4">
             <div class="space-y-1 flex-1">
                 <Label class="text-base font-medium leading-relaxed text-foreground block" :class="{'text-muted-foreground': mode === 'read'}">
                     <span v-if="questionNumber" class="text-muted-foreground mr-2 font-bold">{{ questionNumber }}.</span>
                     {{ question.question }}
                 </Label>
                 <p v-if="question.required && mode === 'answer'" class="text-red-500 text-xs font-semibold mt-1 sm:hidden">※必須</p>
             </div>
             <Badge v-if="question.required && mode === 'answer'" variant="outline" class="hidden sm:inline-flex shrink-0 text-red-600 border-red-200 bg-red-50 dark:bg-red-900/20 dark:border-red-800 dark:text-red-400">必須</Badge>
        </div>

        <div class="pt-2">
            <component
                :is="InputComponent"
                :question="question"
                :model-value="modelValue"
                @update:model-value="$emit('update:modelValue', $event)"
                :mode="mode === 'answer' ? 'edit' : 'read'"
            />
        </div>

        <p v-if="error" class="text-sm text-red-600 font-medium flex items-center gap-1 animate-pulse">
            <span class="text-base">⚠</span> {{ error }}
        </p>
    </div>
</template>
