<script setup lang="ts">
import { RouterView } from 'vue-router'
import AppFooter from '@/components/layout/AppFooter.vue'
import AppHeader from '@/components/layout/AppHeader.vue'
</script>

<template>
  <a
    href="#main"
    class="sr-only z-50 rounded-lg bg-plum-900 px-4 py-2 text-sm font-bold text-white focus:not-sr-only focus:fixed focus:top-3 focus:left-3"
  >
    Skip to content
  </a>
  <div class="flex min-h-screen flex-col">
    <AppHeader />
    <main id="main" class="flex-1" tabindex="-1">
      <RouterView />
    </main>
    <AppFooter />
  </div>
</template>
