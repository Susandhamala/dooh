<script setup lang="ts">
import { computed, ref } from 'vue'
import { toTime } from '@/lib/schedule'

/**
 * The screen's day as one bar, from switch-on to switch-off, coloured by what is still open on the
 * chosen days. Tap an available stretch to take it, tap an hour, or drag the two handles (5-minute
 * steps) to fine-tune.
 */
export interface HourCell {
  hour: number
  state: 'free' | 'busy' | 'full' | 'past'
}

const props = defineProps<{ open: number; close: number; hours: HourCell[]; start: number; end: number }>()
const emit = defineEmits<{ change: [range: { start: number; end: number }] }>()

const STEP = 5
const bar = ref<HTMLElement>()
const span = computed(() => Math.max(1, props.close - props.open))
const pct = (m: number) => `${((m - props.open) / span.value) * 100}%`
const snap = (m: number) => Math.min(props.close, Math.max(props.open, Math.round(m / STEP) * STEP))

function set(start: number, end: number) {
  start = snap(start)
  end = snap(end)
  if (end - start < STEP) return
  emit('change', { start, end })
}

function minuteAt(clientX: number): number {
  const r = bar.value!.getBoundingClientRect()
  return props.open + ((clientX - r.left) / r.width) * span.value
}

let dragging: 'start' | 'end' | null = null
function grab(which: 'start' | 'end', e: PointerEvent) {
  dragging = which
  ;(e.currentTarget as HTMLElement).setPointerCapture(e.pointerId)
}
function move(e: PointerEvent) {
  if (!dragging) return
  const m = snap(minuteAt(e.clientX))
  if (dragging === 'start') set(Math.min(m, props.end - STEP), props.end)
  else set(props.start, Math.max(m, props.start + STEP))
}
function drop() {
  dragging = null
}

/** Tap an hour: take that whole hour (within the screen's on time). */
function tapHour(h: HourCell) {
  if (h.state === 'past') return
  set(Math.max(props.open, h.hour * 60), Math.min(props.close, h.hour * 60 + 60))
}

function key(which: 'start' | 'end', e: KeyboardEvent) {
  const step = e.shiftKey ? 60 : STEP
  const d = e.key === 'ArrowLeft' || e.key === 'ArrowDown' ? -step : e.key === 'ArrowRight' || e.key === 'ArrowUp' ? step : 0
  if (!d) return
  e.preventDefault()
  if (which === 'start') set(Math.min(props.start + d, props.end - STEP), props.end)
  else set(props.start, Math.max(props.end + d, props.start + STEP))
}

/** Stretches of hours that are still open on every chosen day, to pick in one tap. */
const blocks = computed(() => {
  const out: { start: number; end: number }[] = []
  for (const h of props.hours) {
    const open = h.state === 'free' || h.state === 'busy'
    const from = Math.max(props.open, h.hour * 60)
    const to = Math.min(props.close, h.hour * 60 + 60)
    const last = out[out.length - 1]
    if (open && last && last.end === from) last.end = to
    else if (open) out.push({ start: from, end: to })
  }
  return out
})

const length = computed(() => {
  const m = props.end - props.start
  return [Math.floor(m / 60) && `${Math.floor(m / 60)} h`, m % 60 && `${m % 60} min`].filter(Boolean).join(' ')
})
const COLORS: Record<HourCell['state'], string> = {
  free: 'bg-success/15 hover:bg-success/25',
  busy: 'bg-warning/25 hover:bg-warning/35',
  full: 'bg-danger/20 [background-image:repeating-linear-gradient(135deg,transparent_0_4px,rgb(255_255_255/0.6)_4px_6px)]',
  past: 'bg-canvas',
}
</script>

<template>
  <div>
    <div class="relative select-none" @pointermove="move" @pointerup="drop" @pointercancel="drop">
      <div ref="bar" class="flex h-14 overflow-hidden rounded-xl border border-line-strong">
        <button
          v-for="h in hours"
          :key="h.hour"
          type="button"
          tabindex="-1"
          class="relative h-full flex-1 border-r border-white/70 transition last:border-r-0"
          :class="COLORS[h.state]"
          :style="{ flexGrow: Math.min(props.close, h.hour * 60 + 60) - Math.max(props.open, h.hour * 60) }"
          :aria-label="`${toTime(h.hour * 60)}: ${h.state}`"
          @click="tapHour(h)"
        />
      </div>
      <!-- the chosen time -->
      <div
        class="pointer-events-none absolute inset-y-0 rounded-xl border-2 border-plum-700 bg-plum-700/15 transition-[left,width] duration-75"
        :style="{ left: pct(start), width: `calc(${pct(end)} - ${pct(start)})` }"
      >
        <span class="absolute -top-6 left-0 -translate-x-1/2 rounded bg-plum-700 px-1.5 text-xs leading-5 font-bold whitespace-nowrap text-white [font-variant-numeric:tabular-nums]">{{ toTime(start) }}</span>
        <span class="absolute right-0 -bottom-6 translate-x-1/2 rounded bg-plum-700 px-1.5 text-xs leading-5 font-bold whitespace-nowrap text-white [font-variant-numeric:tabular-nums]">{{ toTime(end) }}</span>
      </div>
      <button
        v-for="which in ['start', 'end'] as const"
        :key="which"
        type="button"
        role="slider"
        :aria-label="which === 'start' ? 'From' : 'Until'"
        :aria-valuemin="open"
        :aria-valuemax="close"
        :aria-valuenow="which === 'start' ? start : end"
        :aria-valuetext="toTime(which === 'start' ? start : end)"
        class="absolute top-1/2 z-10 grid h-9 w-6 -translate-x-1/2 -translate-y-1/2 cursor-ew-resize touch-none place-items-center rounded-lg border-2 border-plum-700 bg-white shadow-md focus-visible:ring-2 focus-visible:ring-plum-700 focus-visible:ring-offset-2 focus-visible:outline-none"
        :style="{ left: pct(which === 'start' ? start : end) }"
        @pointerdown="grab(which, $event)"
        @keydown="key(which, $event)"
      >
        <span class="h-4 w-0.5 rounded bg-plum-700 shadow-[3px_0_0_var(--color-plum-700)]" />
      </button>
    </div>
    <!-- hour labels -->
    <div class="relative mt-7 h-4 text-[11px] text-subtle [font-variant-numeric:tabular-nums]">
      <span
        v-for="h in hours.filter((x) => x.hour % 2 === 0 && x.hour * 60 >= open)"
        :key="h.hour"
        class="absolute -translate-x-1/2"
        :style="{ left: pct(h.hour * 60) }"
      >{{ String(h.hour).padStart(2, '0') }}</span>
    </div>

    <p class="mt-3 text-sm font-semibold text-plum-900">{{ toTime(start) }}–{{ toTime(end) }} · {{ length }} a day</p>
    <div v-if="blocks.length" class="mt-3">
      <p class="text-xs font-semibold text-subtle">Available</p>
      <div class="mt-1.5 flex flex-wrap gap-1.5">
        <button
          v-for="b in blocks"
          :key="b.start"
          type="button"
          class="rounded-full border px-3 py-1 text-xs font-bold transition [font-variant-numeric:tabular-nums]"
          :class="b.start === start && b.end === end ? 'border-plum-700 bg-plum-700 text-white' : 'border-success/40 bg-success/8 text-ink hover:border-plum-300'"
          @click="set(b.start, b.end)"
        >
          {{ toTime(b.start) }}–{{ toTime(b.end) }}
        </button>
      </div>
    </div>
    <p v-else class="mt-3 text-sm font-semibold text-danger">Fully booked on these days. Try other days.</p>
    <div class="mt-3 flex flex-wrap gap-x-3 gap-y-1 text-xs text-subtle">
      <span class="flex items-center gap-1.5"><span class="h-3 w-3 rounded bg-success/25" /> Free</span>
      <span class="flex items-center gap-1.5"><span class="h-3 w-3 rounded bg-warning/35" /> Some booked</span>
      <span class="flex items-center gap-1.5"><span class="h-3 w-3 rounded bg-danger/25" /> Full for your ad</span>
    </div>
  </div>
</template>
