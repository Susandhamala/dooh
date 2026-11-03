<script setup lang="ts">
import type { RouteLocationRaw } from 'vue-router'
import AppIcon from './AppIcon.vue'

defineProps<{ title: string; eyebrow?: string; lede?: string; back?: { to: RouteLocationRaw; label: string } }>()
</script>

<template>
  <header class="mb-6 sm:mb-8">
    <RouterLink v-if="back" :to="back.to" class="link mb-3 inline-flex items-center gap-1 text-sm">
      <AppIcon name="back" :size="16" /> {{ back.label }}
    </RouterLink>
    <div class="flex flex-wrap items-end justify-between gap-x-6 gap-y-4">
      <div class="min-w-0">
        <p v-if="eyebrow" class="eyebrow mb-1.5">{{ eyebrow }}</p>
        <h1 class="h-page">{{ title }}</h1>
        <p v-if="lede" class="lede mt-2">{{ lede }}</p>
        <slot name="meta" />
      </div>
      <div v-if="$slots.actions" class="flex flex-wrap gap-2">
        <slot name="actions" />
      </div>
    </div>
  </header>
</template>
