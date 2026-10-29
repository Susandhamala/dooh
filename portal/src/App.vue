<script setup lang="ts">
import { RouterLink, RouterView } from 'vue-router'
</script>

<template>
  <div class="min-h-screen bg-slate-50 text-slate-900">
    <header class="border-b border-slate-200 bg-white">
      <nav class="mx-auto flex max-w-6xl items-center gap-6 px-4 py-3">
        <RouterLink to="/" class="font-semibold text-brand">Rentall Screen Ads</RouterLink>
        <RouterLink to="/screens" class="text-sm text-slate-600 hover:text-slate-900">Screens</RouterLink>
      </nav>
    </header>
    <main class="mx-auto max-w-6xl px-4 py-8">
      <RouterView />
    </main>
  </div>
</template>
