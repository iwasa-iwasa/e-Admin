<script setup lang="ts">
import { router } from '@inertiajs/vue3'

// Props の定義
defineProps<{
  logoSrc: string // required: true は型定義で自動的に適用されます
}>()

// ダッシュボードへの遷移メソッド
const goToHome = () => {
  router.visit('/dashboard')
}
</script>

<template>
  <a href="/" @click.prevent="goToHome">
    <img src="../../../ロゴ・タイトル.svg" alt="Logo" />
  </a>
</template>

<style scoped>
a {
  cursor: pointer;
  display: inline-block; /* transformを適用するために必要 */
}

img {
  width: 100%;
  height: auto;
  transition: opacity 0.3s ease-in-out;
}

a:hover img {
  opacity: 0.6;
}
</style>
