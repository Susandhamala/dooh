<script setup lang="ts">
import { computed } from 'vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import { formatRupees } from '@/lib/format'
import type { Screen } from '@/lib/types'

const props = defineProps<{ screen: Screen }>()

// A small outline of the screen's shape, so landscape and portrait are obvious at a glance.
const shape = computed(() => {
  const { width_px: w, height_px: h } = props.screen
  const max = 44
  return w >= h ? { width: max, height: Math.max(18, Math.round((max * h) / w)) } : { width: Math.max(18, Math.round((max * w) / h)), height: max }
})
</script>

<template>
  <RouterLink :to="`/screens/${screen.id}`" class="card-interactive group flex h-full flex-col p-5">
    <div class="flex items-start gap-4">
      <span class="grid h-14 w-14 shrink-0 place-items-center rounded-xl bg-plum-50" aria-hidden="true">
        <span
          class="rounded-[3px] border-2 border-plum-700 bg-plum-200"
          :style="{ width: `${shape.width * 0.8}px`, height: `${shape.height * 0.8}px` }"
        />
      </span>
      <div class="min-w-0 flex-1">
        <h3 class="font-extrabold text-ink group-hover:text-plum-800">{{ screen.name }}</h3>
        <p v-if="screen.address" class="mt-0.5 flex items-center gap-1 truncate text-sm text-subtle">
          <AppIcon name="pin" :size="14" /> {{ screen.address }}
        </p>
      </div>
    </div>
    <dl class="mt-4 flex flex-wrap gap-x-4 gap-y-1 text-sm text-muted">
      <div><dt class="sr-only">Resolution</dt><dd>{{ screen.width_px }}×{{ screen.height_px }} <span class="capitalize">{{ screen.orientation }}</span></dd></div>
      <div><dt class="sr-only">Plays</dt><dd>{{ screen.plays_per_hour }} plays/hour</dd></div>
      <div><dt class="sr-only">Hours</dt><dd>{{ screen.opens_at }}–{{ screen.closes_at }}</dd></div>
    </dl>
    <div class="mt-auto flex items-center justify-between pt-4">
      <span><span class="price text-lg">{{ formatRupees(screen.price_per_play) }}</span><span class="text-sm text-subtle"> / play</span></span>
      <span class="inline-flex items-center gap-1 text-sm font-bold text-plum-700">
        View <AppIcon name="arrow" :size="16" />
      </span>
    </div>
  </RouterLink>
</template>
