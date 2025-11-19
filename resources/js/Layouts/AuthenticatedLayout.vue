<script setup lang="ts">
import DashboardSidebar from '@/Layouts/Partials/DashboardSidebar.vue'
import DashboardHeader from '@/Layouts/Partials/DashboardHeader.vue'
import { Toaster } from '@/components/ui/toast'
</script>

<template>
  <div class="flex h-screen bg-gray-50">
    <DashboardSidebar />
    <div class="flex-1 flex flex-col overflow-hidden">
      <DashboardHeader />
      <main class="flex-1 overflow-hidden">
        <slot />
      </main>
    </div>
    <Toaster />
  </div>
</template>
