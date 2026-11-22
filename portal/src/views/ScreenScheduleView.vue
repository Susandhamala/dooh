<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import AlertBox from '@/components/ui/AlertBox.vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import LoadingCards from '@/components/ui/LoadingCards.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import { formatDate, formatRupees, formatWindow } from '@/lib/format'
import { nepalDay, nepalNow } from '@/lib/hours'
import { errorMessage, get, mfaNeeded, post } from '@/lib/http'
import { loopTime } from '@/lib/schedule'
import type { OwnerSchedule, Screen } from '@/lib/types'

/**
 * A screen's booked slots for its owner: pick a day and an hour, see every ad in the loop in the
 * order the screen plays them, and move an ad earlier or later. The Raspberry Pi gets the new
 * order with its next signed playlist (within about a minute).
 */
const props = defineProps<{ org: string; screen: string }>()

const screenInfo = ref<Screen | null>(null)
const data = ref<OwnerSchedule | null>(null)
const day = ref(nepalDay(0))
const hour = ref<number | null>(null)
const loading = ref(true)
const error = ref<string | null>(null)
const notice = ref<{ tone: 'success' | 'danger' | 'warning'; text: string } | null>(null)
const busy = ref<string | null>(null)

const days = Array.from({ length: 14 }, (_, i) => nepalDay(i))
const dayLabel = (d: string, i: number) =>
  i === 0 ? 'Today' : i === 1 ? 'Tomorrow' : new Date(d + 'T00:00:00').toLocaleDateString('en-GB', { weekday: 'short', day: 'numeric' })

const COLORS = ['bg-plum-700', 'bg-coral', 'bg-teal', 'bg-warning', 'bg-plum-300', 'bg-success', 'bg-navy', 'bg-coral-800']
const colorOf = computed(() => Object.fromEntries((data.value?.bookings ?? []).map((b, i) => [b.id, COLORS[i % COLORS.length]!])))
const byId = computed(() => Object.fromEntries((data.value?.bookings ?? []).map((b) => [b.id, b])))
const selected = computed(() => data.value?.hours.find((h) => h.hour === hour.value) ?? null)

/** The loop for the selected hour, slot by slot: which booking (or nothing) is on each slot. */
const cells = computed(() => {
  const d = data.value
  if (!d || !selected.value) return []
  const out: { booking: string | null; first: boolean }[] = Array.from({ length: d.slots_per_loop }, () => ({ booking: null, first: false }))
  for (const ad of selected.value.ads) {
    for (const start of ad.slots) {
      for (let k = 0; k < ad.slot_count && start + k < out.length; k++) out[start + k] = { booking: ad.booking, first: k === 0 }
    }
  }
  return out
})

async function load(keepHour = false) {
  loading.value = !data.value
  error.value = null
  try {
    data.value = (await get<OwnerSchedule>(`/orgs/${props.org}/screens/${props.screen}/schedule`, { date: day.value })).data
    if (!keepHour || !data.value.hours.some((h) => h.hour === hour.value)) pickDefaultHour()
  } catch (e) {
    error.value = errorMessage(e)
  } finally {
    loading.value = false
  }
}

function pickDefaultHour() {
  const hours = data.value?.hours ?? []
  const now = day.value === nepalDay(0) ? nepalNow().hour : -1
  hour.value = (hours.find((h) => h.hour === now) ?? hours.find((h) => h.used > 0) ?? hours[0])?.hour ?? null
}

async function move(booking: string, direction: 'up' | 'down') {
  busy.value = booking + direction
  notice.value = null
  try {
    const res = await post<OwnerSchedule>(`/orgs/${props.org}/screens/${props.screen}/bookings/${booking}/move`, {
      direction,
      date: day.value,
      hour: hour.value,
    })
    data.value = res.data
    notice.value = { tone: 'success', text: 'Play order changed. The screen picks it up within a minute.' }
  } catch (e) {
    const mfa = mfaNeeded(e)
    notice.value = mfa
      ? { tone: 'warning', text: mfa === 'setup' ? 'Set up two-factor security (Account → Two-factor) to change the play order.' : 'Verify your two-factor code to change the play order.' }
      : { tone: 'danger', text: errorMessage(e) }
  } finally {
    busy.value = null
  }
}

/** One click: approve (plays at its booked times) or reject (refunded). */
async function decide(booking: string, approve: boolean) {
  busy.value = booking + (approve ? 'approve' : 'reject')
  notice.value = null
  try {
    const res = await post<OwnerSchedule>(`/orgs/${props.org}/screens/${props.screen}/bookings/${booking}/${approve ? 'approve' : 'reject'}`, {
      date: day.value,
    })
    data.value = res.data
    notice.value = { tone: 'success', text: res.message ?? (approve ? 'Approved.' : 'Rejected.') }
  } catch (e) {
    const mfa = mfaNeeded(e)
    notice.value = mfa
      ? { tone: 'warning', text: 'Set up or verify two-factor security to approve bookings.' }
      : { tone: 'danger', text: errorMessage(e) }
  } finally {
    busy.value = null
  }
}

const when = (iso: string | null) =>
  iso ? new Date(iso).toLocaleString('en-GB', { timeZone: 'Asia/Kathmandu', day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' }) : ''

onMounted(async () => {
  get<Screen>(`/orgs/${props.org}/screens/${props.screen}`).then((r) => (screenInfo.value = r.data)).catch(() => {})
  await load()
})
watch(day, () => load(true))

const pad = (h: number) => String(h).padStart(2, '0')
</script>

<template>
  <main class="shell py-8 sm:py-10">
    <PageHeader
      eyebrow="Booked slots"
      :title="screenInfo?.name ?? 'Screen'"
      :lede="data ? `${data.slots_per_loop} slots of ${data.spot_seconds} s in a ${data.loop_seconds} s loop. Ads play in the order below, back to back.` : undefined"
      :back="{ to: { name: 'owner' }, label: 'Owner dashboard' }"
    />

    <AlertBox v-if="error" tone="danger" title="Couldn't load the schedule">
      {{ error }} <button type="button" class="link ml-2" @click="load()">Try again</button>
    </AlertBox>
    <LoadingCards v-else-if="loading" />

    <template v-else-if="data">
      <!-- Waiting for the owner: one click to put it on the screen -->
      <section class="card mb-4 border-warning/40 p-4 sm:p-5" aria-labelledby="pending-h">
        <div class="flex flex-wrap items-baseline justify-between gap-2">
          <h2 id="pending-h" class="h-section">Waiting for your approval</h2>
          <span class="badge bg-warning/10 text-warning">{{ data.pending.length }} waiting</span>
        </div>
        <AlertBox v-if="notice && !selected" :tone="notice.tone" class="mt-3">{{ notice.text }}</AlertBox>
        <p v-if="!data.pending.length" class="mt-2 text-sm text-muted">Nothing to approve. New bookings appear here.</p>
        <ul v-else class="mt-3 grid gap-3">
          <li v-for="p in data.pending" :key="p.id" class="flex flex-wrap items-center gap-4 rounded-xl border border-line p-3">
            <video v-if="p.kind === 'video' && p.media" :src="p.media" class="h-20 w-36 shrink-0 rounded-lg bg-night object-cover" muted loop autoplay playsinline />
            <img v-else-if="p.media || p.thumb" :src="(p.thumb ?? p.media)!" alt="" class="h-20 w-36 shrink-0 rounded-lg bg-canvas object-cover" />
            <span v-else class="grid h-20 w-36 shrink-0 place-items-center rounded-lg bg-canvas text-subtle"><AppIcon name="film" /></span>
            <div class="min-w-0 flex-1">
              <p class="font-bold text-ink">{{ p.ad }}</p>
              <p class="text-sm text-ink-soft">
                {{ p.advertiser }} · booked by <strong>{{ p.booked_by ?? 'unknown' }}</strong>
                <span v-if="p.booked_by_phone" class="text-muted">({{ p.booked_by_phone }})</span>
              </p>
              <p class="text-sm text-muted">
                Plays {{ formatDate(p.starts_on) }}<template v-if="p.ends_on !== p.starts_on"> – {{ formatDate(p.ends_on) }}</template>,
                {{ formatWindow(p.start_time, p.end_time) }} · {{ p.plays_total }} plays · {{ formatRupees(p.price) }}
              </p>
              <p class="text-xs text-subtle">Booked {{ when(p.booked_at) }}</p>
            </div>
            <div class="flex shrink-0 gap-2">
              <button type="button" class="btn-outline btn-sm" :disabled="!!busy" @click="decide(p.id, false)">Reject</button>
              <button type="button" class="btn-primary btn-sm" :disabled="!!busy" @click="decide(p.id, true)">
                <AppIcon name="check" :size="16" /> {{ busy === p.id + 'approve' ? 'Approving…' : 'Approve & play' }}
              </button>
            </div>
          </li>
        </ul>
      </section>

      <!-- Day -->
      <div class="-mx-4 flex gap-2 overflow-x-auto px-4 pb-2 sm:mx-0 sm:flex-wrap sm:px-0" role="radiogroup" aria-label="Day">
        <button
          v-for="(d, i) in days"
          :key="d"
          type="button"
          role="radio"
          :aria-checked="d === day"
          class="shrink-0 rounded-xl border px-3.5 py-2 text-sm font-bold transition"
          :class="d === day ? 'border-plum-700 bg-plum-700 text-white' : 'border-line bg-white text-ink hover:border-plum-300'"
          @click="day = d"
        >
          {{ dayLabel(d, i) }}
        </button>
      </div>

      <!-- Hours: how full each hour's loop is -->
      <section class="card mt-4 p-4 sm:p-5">
        <h2 class="text-sm font-bold text-ink-soft">{{ formatDate(data.date) }} · pick an hour</h2>
        <div class="mt-3 grid grid-cols-6 gap-1.5 sm:grid-cols-9 lg:grid-cols-12">
          <button
            v-for="h in data.hours"
            :key="h.hour"
            type="button"
            class="rounded-lg border p-1.5 text-left transition"
            :class="h.hour === hour ? 'border-plum-700 ring-2 ring-plum-700/30' : 'border-line hover:border-plum-300'"
            :aria-pressed="h.hour === hour"
            :aria-label="`${pad(h.hour)}:00, ${h.used} of ${data.slots_per_loop} slots booked`"
            @click="hour = h.hour"
          >
            <span class="block text-xs font-bold text-ink [font-variant-numeric:tabular-nums]">{{ pad(h.hour) }}:00</span>
            <span class="mt-1 block h-1.5 overflow-hidden rounded bg-divider">
              <span
                class="block h-full rounded"
                :class="h.used >= data.slots_per_loop ? 'bg-danger' : h.used ? 'bg-warning' : ''"
                :style="{ width: `${Math.min(100, (h.used / data.slots_per_loop) * 100)}%` }"
              />
            </span>
            <span class="mt-0.5 block text-[11px] text-subtle">{{ h.used }}/{{ data.slots_per_loop }}</span>
          </button>
        </div>
      </section>

      <!-- The loop for that hour -->
      <section v-if="selected" class="card mt-4 p-4 sm:p-5">
        <div class="flex flex-wrap items-baseline justify-between gap-2">
          <h2 class="h-section">{{ pad(selected.hour) }}:00–{{ pad(selected.hour + 1) }}:00</h2>
          <p class="text-sm text-muted">{{ selected.used }} of {{ data.slots_per_loop }} slots booked · every loop this hour looks like this</p>
        </div>

        <div class="mt-4 rounded-xl bg-night p-1.5 pb-6">
          <div class="relative flex h-10 gap-0.5">
            <span
              v-for="(c, i) in cells"
              :key="i"
              class="min-w-0 flex-1 rounded"
              :class="c.booking ? colorOf[c.booking] : 'bg-white/15'"
              :title="c.booking ? `${byId[c.booking]?.ad} · ${loopTime(i * data.spot_seconds)}` : `Free · ${loopTime(i * data.spot_seconds)}`"
            />
          </div>
          <div class="relative mt-1 h-4 text-[10px] text-white/60 [font-variant-numeric:tabular-nums]">
            <span class="absolute left-0">0:00</span>
            <span class="absolute left-1/2 -translate-x-1/2">{{ loopTime(data.loop_seconds / 2) }}</span>
            <span class="absolute right-0">{{ loopTime(data.loop_seconds) }}</span>
          </div>
        </div>

        <AlertBox v-if="notice" :tone="notice.tone" class="mt-4">{{ notice.text }}</AlertBox>

        <p v-if="!selected.ads.length" class="mt-4 text-sm text-muted">No ads booked in this hour. The whole loop is free.</p>
        <ol v-else class="mt-4 divide-y divide-line rounded-xl border border-line">
          <li v-for="(a, i) in selected.ads" :key="a.booking" class="flex items-center gap-3 p-3">
            <span class="w-5 text-center text-sm font-bold text-subtle">{{ i + 1 }}</span>
            <span class="h-10 w-1.5 shrink-0 rounded" :class="colorOf[a.booking]" />
            <img v-if="byId[a.booking]?.thumb" :src="byId[a.booking]!.thumb!" alt="" class="h-10 w-16 shrink-0 rounded-md bg-canvas object-cover" />
            <span v-else class="grid h-10 w-16 shrink-0 place-items-center rounded-md bg-canvas text-subtle"><AppIcon name="film" :size="18" /></span>
            <div class="min-w-0 flex-1">
              <p class="truncate font-bold text-ink">{{ byId[a.booking]?.ad ?? 'Ad' }}</p>
              <p class="truncate text-xs text-muted">
                {{ byId[a.booking]?.advertiser }} · {{ a.slot_count * data.spot_seconds }} s ·
                {{ a.slots.length === 1 ? 'once' : `${a.slots.length} times` }} a loop, at
                {{ a.slots.map((s) => loopTime(s * data!.spot_seconds)).join(', ') }}
              </p>
            </div>
            <div class="flex shrink-0 gap-1">
              <button
                type="button"
                class="btn-quiet btn-sm"
                :disabled="i === 0 || !!busy"
                :aria-label="`Play ${byId[a.booking]?.ad} earlier`"
                @click="move(a.booking, 'up')"
              >
                ▲
              </button>
              <button
                type="button"
                class="btn-quiet btn-sm"
                :disabled="i === selected.ads.length - 1 || !!busy"
                :aria-label="`Play ${byId[a.booking]?.ad} later`"
                @click="move(a.booking, 'down')"
              >
                ▼
              </button>
            </div>
          </li>
        </ol>
      </section>

      <!-- Every booking that day -->
      <section class="card mt-4 p-4 sm:p-5">
        <h2 class="h-section">All bookings on {{ formatDate(data.date) }}</h2>
        <p v-if="!data.bookings.length" class="mt-2 text-sm text-muted">Nothing booked on this day yet.</p>
        <div v-else class="mt-3 overflow-x-auto">
          <table class="w-full min-w-[640px] text-left text-sm">
            <thead class="text-xs text-subtle">
              <tr>
                <th class="py-2 pr-3 font-semibold">Ad</th>
                <th class="py-2 pr-3 font-semibold">Booked by</th>
                <th class="py-2 pr-3 font-semibold">Time</th>
                <th class="py-2 pr-3 font-semibold">Dates</th>
                <th class="py-2 pr-3 font-semibold">Plays a day</th>
                <th class="py-2 font-semibold">Status</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-line">
              <tr v-for="b in data.bookings" :key="b.id">
                <td class="py-2.5 pr-3">
                  <span class="flex items-center gap-2 font-semibold text-ink"><span class="h-3 w-3 shrink-0 rounded-sm" :class="colorOf[b.id]" />{{ b.ad }}</span>
                </td>
                <td class="py-2.5 pr-3 text-muted">
                  {{ b.booked_by ?? '—' }}<span class="block text-xs text-subtle">{{ b.advertiser }} · {{ when(b.booked_at) }}</span>
                </td>
                <td class="py-2.5 pr-3 [font-variant-numeric:tabular-nums]">{{ formatWindow(b.start_time, b.end_time) }}</td>
                <td class="py-2.5 pr-3 text-muted">{{ formatDate(b.starts_on) }} – {{ formatDate(b.ends_on) }}</td>
                <td class="py-2.5 pr-3 [font-variant-numeric:tabular-nums]">{{ b.plays_per_day }}</td>
                <td class="py-2.5"><StatusBadge :status="b.status" /></td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </template>
  </main>
</template>
