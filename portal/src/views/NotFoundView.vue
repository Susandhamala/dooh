<script setup lang="ts">
import EmptyState from '@/components/ui/EmptyState.vue'
</script>

<template>
  <div class="shell py-16">
    <EmptyState icon="screen" title="This page isn’t showing" body="The link may be old, or you may not have access to it.">
      <RouterLink to="/screens" class="btn-primary">Find screens</RouterLink>
      <RouterLink to="/" class="btn-outline">Home</RouterLink>
    </EmptyState>
  </div>
</template>
