<template>
  <div v-if="isOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-md p-6">
      <h2 class="text-xl font-bold mb-4 text-gray-800">部署異動の確認</h2>
      
      <p class="text-gray-600 mb-4 text-sm">
        {{ userName }} さんを {{ newDepartmentName }} へ異動させようとしています。
      </p>

      <div v-if="requiresConfirmation" class="mb-4 p-4 border border-yellow-300 bg-yellow-50 rounded-md">
        <p class="text-sm text-yellow-800 font-semibold mb-2">
          以下の未来の予定（参加者が一人のみ）はどのように処理しますか？
        </p>
        <ul class="text-xs text-yellow-700 list-disc pl-4 mb-3">
          <li v-for="event in soloEvents" :key="event.id" class="mb-1">
            {{ event.date }} : {{ event.title }}
          </li>
        </ul>
        
        <div class="space-y-2 mt-3">
          <label class="flex items-start">
            <input type="radio" v-model="handlingMethod" value="transfer" class="mt-1 mr-2 text-indigo-600">
            <span class="text-sm">新しい部署に移動する（推奨）</span>
          </label>
          <label class="flex items-start">
            <input type="radio" v-model="handlingMethod" value="leave_to_old" class="mt-1 mr-2 text-indigo-600">
            <span class="text-sm">元の部署に残す（所有権は部署管理者に移譲されます）</span>
          </label>
        </div>
      </div>
      
      <div v-else class="mb-4 text-sm text-gray-500 bg-gray-50 p-3 rounded">
        ※過去の予定や繰り返し予定は自動的に整理・移譲されます。
      </div>

      <div class="flex justify-end gap-2 mt-6">
        <button 
          @click="close" 
          class="px-4 py-2 border border-gray-300 text-gray-700 rounded-md hover:bg-gray-50 transition-colors"
          :disabled="isProcessing"
        >
          キャンセル
        </button>
        <button 
          @click="confirm" 
          class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 transition-colors disabled:opacity-50 flex items-center"
          :disabled="isProcessing"
        >
          <span v-if="isProcessing" class="mr-2 h-4 w-4 rounded-full border-2 border-white border-t-transparent animate-spin"></span>
          実行する
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
  isOpen: Boolean,
  userName: String,
  newDepartmentName: String,
  requiresConfirmation: Boolean,
  soloEvents: {
    type: Array,
    default: () => []
  },
  isProcessing: Boolean
});

const emit = defineEmits(['close', 'confirm']);

const handlingMethod = ref('transfer');

const close = () => {
  emit('close');
};

const confirm = () => {
  emit('confirm', handlingMethod.value);
};
</script>
