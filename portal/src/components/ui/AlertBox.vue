<script setup lang="ts">
import { computed } from 'vue'
import AppIcon from './AppIcon.vue'

const props = withDefaults(defineProps<{ tone?: 'danger' | 'info' | 'success' | 'warning'; title?: string }>(), {
  tone: 'danger',
  title: undefined,
})
defineEmits<{ retry: [] }>()

const STYLES = {
  danger: { cls: 'border-danger/25 bg-danger/5 text-danger', icon: 'alert' },
  warning: { cls: 'border-notice-line bg-notice text-notice-ink', icon: 'alert' },
  info: { cls: 'border-plum-200 bg-plum-50 text-plum-900', icon: 'info' },
  success: { cls: 'border-success/25 bg-success/5 text-success', icon: 'check' },
} as const

const style = computed(() => STYLES[props.tone])
</script>

<template>
  <div
    class="flex gap-3 rounded-xl border px-4 py-3 text-sm"
    :class="style.cls"
    :role="tone === 'danger' ? 'alert' : 'status'"
  >
    <AppIcon :name="style.icon" :size="18" class="mt-px" />
    <div class="min-w-0 flex-1">
      <p v-if="title" class="font-bold">{{ title }}</p>
      <div class="font-medium" :class="title ? 'mt-0.5 opacity-90' : ''"><slot /></div>
    </div>
    <slot name="action" />
  </div>
</template>
