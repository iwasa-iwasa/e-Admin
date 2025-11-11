<script setup lang="ts">
import { ref } from 'vue'
import DashboardSidebar from '@/Layouts/Partials/DashboardSidebar.vue'
import DashboardHeader from '@/Layouts/Partials/DashboardHeader.vue'
import { Toaster } from '@/components/ui/toast'

const selectedMember = ref<string | null>(null)
</script>

<template>
  <div class="flex h-screen bg-gray-50">
    <DashboardSidebar v-model:selectedMember="selectedMember" />
    <div class="flex-1 flex flex-col overflow-hidden">
      <DashboardHeader />
      <main class="flex-1 overflow-auto p-6">
        <slot :selected-member="selectedMember" @update:selected-member="selectedMember = $event" />
      </main>
    </div>
    <Toaster />
  </div>
</template>
