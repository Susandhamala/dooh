<script setup lang="ts">
import { ref } from 'vue'

/**
 * The next few weeks as a strip of day cards. Tap a day to start, tap a later day to run until it.
 * Each card shows how busy the chosen time is that day: free, some places booked, full, or past.
 */
export interface DayCell {
  date: string
  state: 'free' | 'busy' | 'full' | 'past'
}

const props = defineProps<{ days: DayCell[]; start: string; end: string }>()
const emit = defineEmits<{ pick: [range: { start: string; end: string }] }>()

const strip = ref<HTMLElement>()

function pick(d: DayCell) {
  if (d.state === 'past') return
  // A one-day selection grows to a later day; anything else starts again on the tapped day.
  if (props.start === props.end && d.date > props.start) emit('pick', { start: props.start, end: d.date })
  else emit('pick', { start: d.date, end: d.date })
}

function parts(date: string) {
  const d = new Date(`${date}T12:00:00`)
  return {
    weekday: d.toLocaleDateString('en-GB', { weekday: 'short' }),
    day: d.getDate(),
    month: d.toLocaleDateString('en-GB', { month: 'short' }),
  }
}

const LABEL: Record<DayCell['state'], string> = { free: 'free', busy: 'some places booked', full: 'full at your time', past: 'past' }

function scroll(dir: number) {
  strip.value?.scrollBy({ left: dir * strip.value.clientWidth * 0.8, behavior: 'smooth' })
}
</script>

<template>
  <div class="relative">
    <div ref="strip" class="-mx-1 flex snap-x scroll-px-1 gap-1.5 overflow-x-auto px-1 pt-1 pb-2 [scrollbar-width:thin]" role="group" aria-label="Days">
      <button
        v-for="d in days"
        :key="d.date"
        type="button"
        class="relative flex w-[3.6rem] shrink-0 snap-start flex-col items-center rounded-xl border px-1 pt-1.5 pb-2 transition [font-variant-numeric:tabular-nums] focus-visible:ring-2 focus-visible:ring-plum-700 focus-visible:outline-none disabled:cursor-not-allowed"
        :class="
          d.state === 'past'
            ? 'border-line bg-canvas text-faint'
            : d.date === start || d.date === end
              ? 'border-plum-700 bg-plum-700 text-white shadow-sm'
              : d.date > start && d.date < end
                ? 'border-plum-200 bg-plum-50 text-plum-900'
                : 'border-line-strong bg-white text-ink hover:border-plum-300'
        "
        :disabled="d.state === 'past'"
        :aria-pressed="d.date >= start && d.date <= end"
        :aria-label="`${parts(d.date).weekday} ${parts(d.date).day} ${parts(d.date).month}: ${LABEL[d.state]}`"
        @click="pick(d)"
      >
        <span class="text-[11px] font-semibold uppercase opacity-80">{{ parts(d.date).weekday }}</span>
        <span class="text-lg leading-6 font-extrabold">{{ parts(d.date).day }}</span>
        <span class="text-[11px] opacity-80">{{ parts(d.date).month }}</span>
        <span
          class="absolute inset-x-2.5 bottom-1 h-1 rounded-full"
          :class="{ 'bg-success': d.state === 'free', 'bg-warning': d.state === 'busy', 'bg-danger': d.state === 'full', 'bg-transparent': d.state === 'past' }"
        />
      </button>
    </div>
    <div class="mt-1 flex flex-wrap items-center justify-between gap-2">
      <div class="flex flex-wrap gap-x-3 gap-y-1 text-xs text-subtle">
        <span class="flex items-center gap-1.5"><span class="h-1 w-3 rounded-full bg-success" /> Free</span>
        <span class="flex items-center gap-1.5"><span class="h-1 w-3 rounded-full bg-warning" /> Some booked</span>
        <span class="flex items-center gap-1.5"><span class="h-1 w-3 rounded-full bg-danger" /> Full at your time</span>
      </div>
      <div class="hidden gap-1 sm:flex">
        <button type="button" class="btn-ghost btn-sm !px-2" aria-label="Earlier days" @click="scroll(-1)">‹</button>
        <button type="button" class="btn-ghost btn-sm !px-2" aria-label="Later days" @click="scroll(1)">›</button>
      </div>
    </div>
  </div>
</template>
