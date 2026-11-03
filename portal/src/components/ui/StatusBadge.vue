<script setup lang="ts">
import { computed } from 'vue'
import { statusMeta, type Tone } from '@/lib/format'

const props = defineProps<{ status: string; label?: string }>()

const TONES: Record<Tone, string> = {
  neutral: 'bg-divider text-muted',
  plum: 'bg-plum-100 text-plum-800',
  success: 'bg-success/10 text-success',
  warning: 'bg-warning/10 text-warning',
  danger: 'bg-danger/10 text-danger',
  teal: 'bg-teal-50 text-teal-dark',
}

const meta = computed(() => statusMeta(props.status))
</script>

<template>
  <span class="badge" :class="TONES[meta.tone]">
    <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-current" aria-hidden="true" />
    {{ label ?? meta.label }}
  </span>
</template>
