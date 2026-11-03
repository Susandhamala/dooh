<script setup lang="ts">
withDefaults(defineProps<{ count?: number; cols?: string }>(), { count: 3, cols: 'sm:grid-cols-2 lg:grid-cols-3' })
</script>

<template>
  <ul class="grid gap-4" :class="cols" aria-busy="true" aria-label="Loading">
    <li v-for="i in count" :key="i" class="card p-5">
      <div class="skeleton h-5 w-2/3" />
      <div class="skeleton mt-3 h-4 w-1/2" />
      <div class="skeleton mt-5 h-9 w-28" />
    </li>
  </ul>
</template>
