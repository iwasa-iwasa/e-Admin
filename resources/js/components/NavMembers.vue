<script setup lang="ts">
import { usePage } from '@inertiajs/vue3'
import { computed } from 'vue'
import NavLink from '@/components/NavLink.vue'
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar'

const page = usePage()
const members = computed(() => page.props.teamMembers as App.Models.User[])

</script>

<template>
  <nav class="grid items-start gap-1 px-2 text-sm font-medium">
    <h2 class="px-2 py-2 text-xs font-semibold text-muted-foreground">
      部署メンバー
    </h2>
    <NavLink
      v-for="member in members"
      :key="member.id"
      href="#"
      class="flex items-center gap-3"
    >
      <Avatar class="h-6 w-6">
        <AvatarImage :src="member.avatar || ''" :alt="member.name" />
        <AvatarFallback>{{ member.name.charAt(0) }}</AvatarFallback>
      </Avatar>
      {{ member.name }}
    </NavLink>
  </nav>
</template>