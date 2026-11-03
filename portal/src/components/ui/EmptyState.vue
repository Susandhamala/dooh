<script setup lang="ts">
import AppIcon, { type IconName } from './AppIcon.vue'

withDefaults(defineProps<{ title: string; body?: string; icon?: IconName }>(), { icon: 'inbox', body: undefined })
</script>

<template>
  <div class="card flex flex-col items-center px-6 py-12 text-center">
    <span class="grid h-14 w-14 place-items-center rounded-2xl bg-plum-100 text-plum-700">
      <AppIcon :name="icon" :size="26" />
    </span>
    <h2 class="h-section mt-4">{{ title }}</h2>
    <p v-if="body" class="mt-1.5 max-w-md text-[15px] text-muted">{{ body }}</p>
    <div v-if="$slots.default" class="mt-5 flex flex-wrap justify-center gap-2">
      <slot />
    </div>
  </div>
</template>
