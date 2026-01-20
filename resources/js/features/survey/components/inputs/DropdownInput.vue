<script setup lang="ts">
import { computed } from 'vue';
import { BaseInputProps } from './types';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';

const props = defineProps<BaseInputProps>();
const emit = defineEmits(['update:modelValue']);

const normalizedOptions = computed(() => {
    return props.question.options.map((opt, index) => {
        if (typeof opt === 'string') {
            return { id: `opt-${index}`, value: opt, label: opt || `選択肢 ${index + 1}` };
        }
        return {
            id: opt.option_id ?? `opt-${index}`,
            value: String(opt.option_id ?? opt.text), // Select value must be string usually
            label: opt.option_text ?? opt.text ?? ''
        };
    });
});
</script>

<template>
    <div class="w-full">
        <template v-if="mode === 'read'">
             <div class="p-3 bg-gray-50 rounded border text-gray-700">
                {{ normalizedOptions.find(o => o.value == modelValue)?.label || modelValue || '(未回答)' }}
            </div>
        </template>
        <template v-else>
            <Select 
                :model-value="String(modelValue || '')" 
                @update:model-value="$emit('update:modelValue', $event)"
                :disabled="mode === 'preview'"
            >
                <SelectTrigger>
                    <SelectValue placeholder="選択してください" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem v-for="opt in normalizedOptions" :key="opt.id" :value="opt.value">
                        {{ opt.label }}
                    </SelectItem>
                </SelectContent>
            </Select>
        </template>
    </div>
</template>
