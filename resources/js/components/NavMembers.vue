<script setup lang="ts">
import { usePage, router } from '@inertiajs/vue3'
import { computed } from 'vue'
import NavLink from '@/components/NavLink.vue'
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar'
import { X } from 'lucide-vue-next'

const page = usePage()
const members = computed(() => page.props.teamMembers as App.Models.User[])
const filteredMemberId = computed(() => page.props.filteredMemberId as number | null)

const filterByMember = (memberId: number) => {
  router.get(route('dashboard'), { member_id: memberId }, {
    preserveState: true,
    replace: true,
  })
}

const clearFilter = () => {
  router.get(route('dashboard'), {}, {
    preserveState: true,
    replace: true,
  })
}
</script>

<template>
  <nav class="grid items-start gap-1 px-2 text-sm font-medium">
    <div class="flex items-center justify-between px-2 py-2">
      <h2 class="text-xs font-semibold text-muted-foreground">
        部署メンバー
      </h2>
      <button
        v-if="filteredMemberId"
        @click="clearFilter"
        class="flex items-center gap-1 text-xs text-muted-foreground hover:text-foreground"
      >
        <X class="h-3 w-3" />
        クリア
      </button>
    </div>
    <NavLink
      v-for="member in members"
      :key="member.id"
      href="#"
      @click.prevent="filterByMember(member.id)"
      class="flex items-center gap-3"
      :class="{ 'bg-accent text-accent-foreground': filteredMemberId === member.id }"
    >
      <Avatar class="h-6 w-6">
        <AvatarImage :src="member.avatar || ''" :alt="member.name" />
        <AvatarFallback>{{ member.name.charAt(0) }}</AvatarFallback>
      </Avatar>
      {{ member.name }}
    </NavLink>
  </nav>
</template>