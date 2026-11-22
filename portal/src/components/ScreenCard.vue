<script setup lang="ts">
import { computed } from 'vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import { formatRupees } from '@/lib/format'
import type { Screen } from '@/lib/types'

const props = defineProps<{ screen: Screen }>()

// A small screen in its real shape (wide or tall), glowing with a sample colour.
const GLOWS = [
  'from-coral-700 to-plum-800',
  'from-plum-700 to-navy',
  'from-teal-dark to-plum-900',
  'from-sand to-coral-700',
]
const glow = computed(() => GLOWS[[...props.screen.id].reduce((s, c) => s + c.charCodeAt(0), 0) % GLOWS.length])
const wide = computed(() => props.screen.width_px >= props.screen.height_px)
</script>

<template>
  <RouterLink
    :to="`/screens/${screen.id}`"
    class="group block h-full rounded-2xl border border-line bg-white p-2.5 transition duration-300 hover:-translate-y-1 hover:border-line-strong hover:shadow-[0_18px_40px_-18px_rgb(38_22_49/0.35)]"
  >
    <div class="relative grid aspect-[16/10] place-items-center overflow-hidden rounded-xl bg-night">
      <div class="absolute inset-0 bg-[radial-gradient(60%_60%_at_50%_45%,rgb(108_57_169/0.35),transparent)]" aria-hidden="true" />
      <div
        class="relative rounded-[4px] bg-gradient-to-br p-[3px] shadow-[0_0_40px_-6px_rgb(244_93_72/0.45)] transition-transform duration-500 group-hover:scale-105"
        :class="[glow, wide ? 'aspect-video w-[62%]' : 'aspect-[9/16] h-[78%]']"
        aria-hidden="true"
      >
        <div class="h-full w-full rounded-[2px] bg-[radial-gradient(rgb(0_0_0/0.25)_1px,transparent_1.4px)] bg-[length:4px_4px]" />
      </div>
      <span class="absolute top-2.5 left-2.5 rounded-full bg-black/45 px-2 py-0.5 text-[10px] font-semibold text-white/90 backdrop-blur">
        {{ screen.width_px }}×{{ screen.height_px }}
      </span>
      <span class="absolute top-2.5 right-2.5 rounded-full bg-white/90 px-2 py-0.5 text-[10px] font-bold text-night">{{ screen.plays_per_hour }}× / hr</span>
    </div>

    <div class="px-1.5 pt-3 pb-1">
      <h3 class="truncate font-semibold tracking-[-0.01em] text-ink">{{ screen.name }}</h3>
      <p class="mt-0.5 truncate text-sm text-subtle">{{ screen.address }}</p>
      <div class="mt-3 flex items-center justify-between border-t border-divider pt-3">
        <span class="text-sm text-subtle">
          <span class="price text-base">{{ formatRupees(screen.price_per_play) }}</span> a show
          <span class="text-faint">·</span>
          <span :title="`On air ${screen.opens_at}–${screen.closes_at}`">{{ screen.opens_at.slice(0, 2) }}–{{ screen.closes_at.slice(0, 2) }} h</span>
        </span>
        <span class="grid h-8 w-8 place-items-center rounded-full bg-canvas text-ink-soft transition group-hover:bg-coral-700 group-hover:text-white">
          <AppIcon name="arrow" :size="16" />
        </span>
      </div>
    </div>
  </RouterLink>
</template>
