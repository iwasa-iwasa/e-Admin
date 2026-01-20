<script setup lang="ts">
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Plus, Trash2 } from 'lucide-vue-next';

const props = defineProps<{
    modelValue: (string | any)[];
    type: 'single' | 'multiple' | 'dropdown';
}>();

const emit = defineEmits(['update:modelValue']);

// Sync with parent
const options = computed({
    get: () => props.modelValue,
    set: (val) => emit('update:modelValue', val)
});

const addOption = () => {
    // Add empty string for simplicity in draft mode
    // (If using objects later, we'd add {text: ''})
    const newOptions = [...options.value, ""];
    emit('update:modelValue', newOptions);
};

const updateOptionText = (index: number, text: string) => {
    const newOptions = [...options.value];
    if (typeof newOptions[index] === 'string') {
        newOptions[index] = text;
    } else {
        newOptions[index] = { ...newOptions[index], text: text, option_text: text };
    }
    emit('update:modelValue', newOptions);
};

const removeOption = (index: number) => {
    if (options.value.length <= 2) return; // Min 2 options constraint
    const newOptions = options.value.filter((_, i) => i !== index);
    emit('update:modelValue', newOptions);
};

const getOptionText = (opt: string | any) => {
    if (typeof opt === 'string') return opt;
    return opt.text || opt.option_text || '';
};
</script>

<template>
    <div class="space-y-3">
        <label class="text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70">
            選択肢
        </label>
        <div class="space-y-2">
            <div v-for="(opt, index) in options" :key="index" class="flex items-center gap-2">
                 <!-- Type Icon -->
                <div v-if="type === 'single'" class="w-4 h-4 rounded-full border-2 border-gray-400 flex-shrink-0" />
                <div v-else-if="type === 'multiple'" class="w-4 h-4 rounded border-2 border-gray-400 flex-shrink-0" />
                <span v-else-if="type === 'dropdown'" class="text-sm text-gray-500 w-6">{{ index + 1 }}.</span>

                <Input
                    :model-value="getOptionText(opt)"
                    @update:model-value="updateOptionText(index, String($event))"
                    :placeholder="`選択肢 ${index + 1}`"
                    class="flex-1"
                />

                <Button
                    v-if="options.length > 2"
                    variant="ghost"
                    size="sm"
                    @click="removeOption(index)"
                    type="button"
                >
                    <Trash2 class="h-4 w-4 text-gray-500 hover:text-red-500" />
                </Button>
            </div>
        </div>
        <Button variant="outline" size="sm" @click="addOption" class="gap-2" type="button">
            <Plus class="h-4 w-4" />
            選択肢を追加
        </Button>
    </div>
</template>
