<script setup lang="ts">
import { computed } from 'vue';
import { BaseInputProps } from './types';
import { Checkbox } from '@/components/ui/checkbox';

const props = defineProps<BaseInputProps>();
const emit = defineEmits(['update:modelValue']);

const normalizedOptions = computed(() => {
    return props.question.options.map((opt, index) => {
        if (typeof opt === 'string') {
            return { id: `opt-${index}`, value: opt, label: opt || `選択肢 ${index + 1}` };
        }
        return {
            id: opt.option_id ?? `opt-${index}`,
            value: opt.option_id ?? opt.text,
            label: opt.option_text ?? opt.text ?? ''
        };
    });
});

const isSelected = (value: any) => {
    if (!Array.isArray(props.modelValue)) return false;
    // Loose comparison for ID/Value match
    return props.modelValue.some(v => String(v) === String(value));
};

const handleUpdate = (value: any, checked: boolean) => {
    console.log(value, checked);
    const current = Array.isArray(props.modelValue) ? [...props.modelValue] : [];
    if (checked) {
        const exists = current.some(v => String(v) === String(value));
        if (!exists) current.push(value);
    } else {
        const index = current.findIndex(v => String(v) === String(value));
        if (index > -1) current.splice(index, 1);
    }
    emit('update:modelValue', current);
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
                <Checkbox
                    :default-value="isSelected(opt.value)"
                    @update:modelValue="handleUpdate(opt.value, Boolean($event))"
                    :disabled="mode === 'preview' || mode === 'read'"
                />
                <span 
                    class="text-sm cursor-pointer select-none"
                    :class="{'font-medium text-gray-900': isSelected(opt.value), 'text-gray-700': !isSelected(opt.value)}"                >
                    {{ opt.label }}
                </span>
            </label>
        </template>
        <div v-if="normalizedOptions.length === 0" class="text-gray-400 text-sm italic p-2">
            (選択肢を追加してください)
        </div>
    </div>
</template>
