<script setup lang="ts">
import { computed } from 'vue';
import { BaseInputProps } from './types';

const props = defineProps<BaseInputProps>();
const emit = defineEmits(['update:modelValue']);

const normalizedOptions = computed(() => {
    return props.question.options.map((opt, index) => {
        if (typeof opt === 'string') {
            return { id: `opt-${index}`, value: opt, label: opt || `選択肢 ${index + 1}` }; // Show placeholder if empty
        }
        return {
            id: opt.option_id ?? `opt-${index}`,
            value: opt.option_id ?? opt.text,
            label: opt.option_text ?? opt.text ?? ''
        };
    });
});

const isSelected = (value: any) => {
    return props.modelValue == value;
};
</script>

<template>
    <div class="space-y-2">
        <template v-for="opt in normalizedOptions" :key="opt.id">
            <label 
                class="flex items-center gap-2 p-2 rounded transition-colors"
                :class="{
                    'cursor-pointer hover:bg-gray-50': mode !== 'read' && mode !== 'preview',
                    'opacity-70': mode === 'preview'
                }"
            >
                <div class="relative flex items-center">
                   <input
                        type="radio"
                        :name="`question-${question.id}`"
                        :value="opt.value"
                        :checked="isSelected(opt.value)"
                        @change="$emit('update:modelValue', opt.value)"
                        :disabled="mode === 'preview' || mode === 'read'"
                        class="peer sr-only" 
                    />
                    <div class="w-4 h-4 rounded-full border border-gray-400 peer-checked:border-blue-600 peer-checked:border-4 transition-all bg-white"></div>
                </div>
                <span :class="{'font-medium text-gray-900': isSelected(opt.value), 'text-gray-700': !isSelected(opt.value)}">
                    {{ opt.label }}
                </span>
            </label>
        </template>
        <div v-if="normalizedOptions.length === 0" class="text-gray-400 text-sm italic p-2">
            (選択肢を追加してください)
        </div>
    </div>
</template>
