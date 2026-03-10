<template>
  <div v-if="isOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-md p-6">
      <h2 class="text-xl font-bold mb-4 text-red-600 flex items-center">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
        </svg>
        退職者（アカウント無効化）の確認
      </h2>
      
      <p class="text-gray-700 mb-4 text-sm font-medium">
        本当に {{ userName }} さんのアカウントを無効化しますか？
      </p>

      <div class="bg-red-50 p-4 border border-red-200 rounded-md mb-4">
        <p class="text-sm text-red-800 mb-2 font-semibold">この操作を実行すると以下の処理が行われます：</p>
        <ul class="text-sm text-red-700 list-disc pl-5 space-y-1">
          <li>本人はシステムにログインできなくなります。</li>
          <li>このユーザーが作成した予定は、部署管理者に所有権が移管されます。</li>
          <li>将来の単発予定（本人のみ参加）は削除されます。</li>
          <li>共有メモ・アンケートなどのデータも適切に処理されます。</li>
        </ul>
      </div>

      <div class="mb-4">
        <label for="reason" class="block text-sm font-medium text-gray-700 mb-1">無効化の理由 <span class="text-red-500">*</span></label>
        <input 
          id="reason" 
          type="text" 
          v-model="internalReason" 
          placeholder="例：退職のため" 
          class="w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm p-2 border"
        />
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
          class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 transition-colors disabled:opacity-50 flex items-center"
          :disabled="isProcessing || !internalReason"
        >
          <span v-if="isProcessing" class="mr-2 h-4 w-4 rounded-full border-2 border-white border-t-transparent animate-spin"></span>
          無効化を実行する
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue';

const props = defineProps({
  isOpen: Boolean,
  userName: String,
  isProcessing: Boolean
});

const emit = defineEmits(['close', 'confirm']);
const internalReason = ref('');

watch(() => props.isOpen, (newVal) => {
  if (newVal) {
    internalReason.value = '';
  }
});

const close = () => {
  emit('close');
};

const confirm = () => {
  emit('confirm', internalReason.value);
};
</script>
