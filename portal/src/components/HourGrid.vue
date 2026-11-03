<script setup lang="ts">
import { computed } from 'vue'
import { formatHour } from '@/lib/format'
import type { Availability } from '@/lib/types'

/**
 * Days × hours, each cell coloured by free slots. Click an hour column to start the window and a
 * second one to end it; the chosen hours are outlined on every day. Selects next to it do the same
 * for keyboard users.
 */
const props = defineProps<{ availability: Availability; start: number; end: number; slots: number; maxRows?: number }>()
const emit = defineEmits<{ pick: [hour: number] }>()

const rows = computed(() => Object.entries(props.availability.days).slice(0, props.maxRows ?? 14))
const hidden = computed(() => Math.max(0, Object.keys(props.availability.days).length - rows.value.length))

const nowNepal = computed(() => {
  const parts = new Intl.DateTimeFormat('en-CA', { timeZone: 'Asia/Kathmandu', year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', hourCycle: 'h23' })
    .formatToParts(new Date())
    .reduce<Record<string, string>>((acc, p) => ((acc[p.type] = p.value), acc), {})
  return { day: `${parts.year}-${parts.month}-${parts.day}`, hour: Number(parts.hour) }
})

function cell(day: string, hour: number, free: number) {
  const past = day < nowNepal.value.day || (day === nowNepal.value.day && hour <= nowNepal.value.hour)
  const inside = hour >= props.start && hour < props.end
  const tone = past
    ? 'bg-divider text-faint'
    : free <= 0
      ? 'bg-danger/20 text-danger'
      : free < props.slots / 2
        ? 'bg-warning/20 text-warning'
        : 'bg-success/15 text-success'
  return { past, inside, tone }
}

const dayLabel = (d: string) => new Date(`${d}T00:00`).toLocaleDateString('en-GB', { weekday: 'short', day: 'numeric' })
</script>

<template>
  <div>
    <div class="overflow-x-auto rounded-xl border border-line">
      <table class="w-full border-collapse text-center text-[11px]">
        <thead>
          <tr>
            <th scope="col" class="sticky left-0 z-10 bg-white px-2 py-1.5 text-left font-semibold text-subtle">Day</th>
            <th v-for="h in availability.hours" :key="h" scope="col" class="p-0">
              <button
                type="button"
                class="w-full px-1 py-1.5 font-bold transition hover:bg-plum-100"
                :class="h >= start && h < end ? 'bg-plum-700 text-white hover:bg-plum-800' : 'text-ink-soft'"
                :aria-label="`Choose ${formatHour(h)}`"
                @click="emit('pick', h)"
              >
                {{ String(h).padStart(2, '0') }}
              </button>
            </th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="[day, frees] in rows" :key="day" class="border-t border-divider">
            <th scope="row" class="sticky left-0 z-10 bg-white px-2 py-1 text-left font-semibold whitespace-nowrap text-ink-soft">{{ dayLabel(day) }}</th>
            <td v-for="(free, i) in frees" :key="i" class="p-[2px]">
              <span
                class="block min-w-6 rounded-[5px] py-1 font-bold"
                :class="[cell(day, availability.hours[i]!, free).tone, cell(day, availability.hours[i]!, free).inside ? 'ring-2 ring-plum-700 ring-inset' : '']"
                :title="`${dayLabel(day)} ${formatHour(availability.hours[i]!)}: ${free} of ${slots} free`"
              >
                {{ cell(day, availability.hours[i]!, free).past ? '·' : free }}
              </span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
    <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-subtle">
      <span class="flex items-center gap-1.5"><span class="h-3 w-3 rounded bg-success/30" /> Free slots</span>
      <span class="flex items-center gap-1.5"><span class="h-3 w-3 rounded bg-warning/30" /> Almost full</span>
      <span class="flex items-center gap-1.5"><span class="h-3 w-3 rounded bg-danger/30" /> Full</span>
      <span class="flex items-center gap-1.5"><span class="h-3 w-3 rounded ring-2 ring-plum-700" /> Your hours</span>
      <span v-if="hidden">+{{ hidden }} more days not shown</span>
    </div>
  </div>
</template>
