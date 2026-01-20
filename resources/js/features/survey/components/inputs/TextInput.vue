<script setup lang="ts">
import { Input } from '@/components/ui/input';
import { BaseInputProps } from './types';

defineProps<BaseInputProps>();
defineEmits(['update:modelValue']);
</script>

<template>
    <div class="w-full">
        <template v-if="mode === 'read'">
            <div class="p-3 bg-gray-50 rounded border text-gray-700 min-h-[40px]">
                {{ modelValue || '(未回答)' }}
            </div>
        </template>
        <template v-else>
            <Input
                :model-value="modelValue"
                @update:model-value="$emit('update:modelValue', $event)"
                :disabled="mode === 'preview'"
                :placeholder="mode === 'preview' ? '回答者がここに短文を入力します' : '回答を入力'"
                class="w-full"
            />
        </template>
    </div>
</template>
