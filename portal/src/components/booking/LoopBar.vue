<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { duration, loopTime } from '@/lib/schedule'

/**
 * One loop of the screen, for illustration: ads already booked, then yours, then free time. The
 * screen packs every loop like this (no gaps between ads), so you don't pick a place. The thin
 * line shows where the screen is in its loop right now.
 */
const props = defineProps<{ loop: number; spot: number; slots: number; used: number; need: number; repeats: number }>()

const mine = computed(() => props.need * props.repeats)
const fits = computed(() => props.used + mine.value <= props.slots)
function kind(i: number): 'booked' | 'mine' | 'free' {
  if (i < props.used) return 'booked'
  if (fits.value && i < props.used + mine.value) return 'mine'
  return 'free'
}
const every = computed(() => Math.max(1, Math.ceil(props.slots / 4)))

const now = ref(-1)
let timer: ReturnType<typeof setInterval> | undefined
function tick() {
  const secs = (Date.now() / 1000 + 20700) % 3600
  now.value = secs < Math.floor(3600 / props.loop) * props.loop ? (secs % props.loop) / props.loop : -1
}
onMounted(() => {
  tick()
  timer = setInterval(tick, 250)
})
onBeforeUnmount(() => clearInterval(timer))
</script>

<template>
  <div>
    <div class="relative rounded-xl bg-night p-1.5 pb-6" role="img" :aria-label="`${duration(loop)} loop: ${used} slots booked, ${fits ? mine : 0} yours, ${Math.max(0, slots - used - (fits ? mine : 0))} free`">
      <div class="relative flex h-9 gap-0.5">
        <span
          v-for="i in slots"
          :key="i"
          class="min-w-0 flex-1 rounded"
          :class="{
            'bg-white/15': kind(i - 1) === 'booked',
            'bg-coral': kind(i - 1) === 'mine',
            'bg-white/85': kind(i - 1) === 'free',
          }"
        />
        <div v-if="now >= 0" class="pointer-events-none absolute -inset-y-1 w-0.5 rounded bg-teal shadow-[0_0_8px_var(--color-teal)]" :style="{ left: `${now * 100}%` }" />
      </div>
      <div class="absolute inset-x-1.5 bottom-1 h-4 text-[10px] text-white/60 [font-variant-numeric:tabular-nums]">
        <span v-for="i in slots" v-show="(i - 1) % every === 0" :key="i" class="absolute" :style="{ left: `${((i - 1) / slots) * 100}%` }">{{ loopTime((i - 1) * spot) }}</span>
        <span class="absolute right-0">{{ loopTime(loop) }}</span>
      </div>
    </div>
    <div class="mt-2 flex flex-wrap gap-x-3 gap-y-1 text-xs text-subtle">
      <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm bg-night/40" /> Booked</span>
      <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm bg-coral" /> Your ad</span>
      <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm border border-line-strong bg-white" /> Free</span>
    </div>
  </div>
</template>
