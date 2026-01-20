<script setup lang="ts">
import { Textarea } from '@/components/ui/textarea';
import { BaseInputProps } from './types';

defineProps<BaseInputProps>();
defineEmits(['update:modelValue']);
</script>

<template>
    <div class="w-full">
        <template v-if="mode === 'read'">
            <div class="p-3 bg-gray-50 rounded border text-gray-700 min-h-[80px] whitespace-pre-wrap">
                {{ modelValue || '(未回答)' }}
            </div>
        </template>
        <template v-else>
            <Textarea
                :model-value="modelValue"
                @update:model-value="$emit('update:modelValue', $event)"
                :disabled="mode === 'preview'"
                :placeholder="mode === 'preview' ? '回答者がここに長文を入力します' : '回答を入力'"
                class="min-h-[100px]"
            />
        </template>
    </div>
</template>
