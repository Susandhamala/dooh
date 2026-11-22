<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import DayStrip, { type DayCell } from '@/components/booking/DayStrip.vue'
import LoopBar from '@/components/booking/LoopBar.vue'
import TimeBar, { type HourCell } from '@/components/booking/TimeBar.vue'
import ScreenMap from '@/components/ScreenMap.vue'
import AlertBox from '@/components/ui/AlertBox.vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import { daysInRange, formatRange, formatRupees } from '@/lib/format'
import { nepalDay, nepalNow, slotsFor } from '@/lib/hours'
import { errorMessage, fieldErrors, gateNeeded, get, post } from '@/lib/http'
import { clock, duration, loopStarts, quote, toMinutes, toTime } from '@/lib/schedule'
import { CATEGORIES, CONTENT_RULES, type Availability, type Booking, type Creative, type Screen } from '@/lib/types'
import { useAuthStore } from '@/stores/auth'
import { useWalletStore } from '@/stores/wallet'

const props = defineProps<{ id: string }>()
const auth = useAuthStore()
const wallets = useWalletStore()
const router = useRouter()

/** Days shown in the strip, from today. */
const STRIP_DAYS = 28
const REPEATS = [1, 2, 3]

const screen = ref<Screen | null>(null)
const loading = ref(true)
const error = ref<string | null>(null)

const grid = ref<Availability | null>(null)
const range = ref({ start: nepalDay(1), end: nepalDay(7) })
/** Minutes after midnight, [start, end). */
const win = ref({ start: 8 * 60, end: 10 * 60 })
/** Times the ad plays in every loop. */
const repeats = ref(1)

const ads = ref<Creative[]>([])
const adId = ref('')
const busy = ref(false)
const bookError = ref<string | null>(null)

const BLOCKS: Record<string, string> = { no_alcohol: 'alcohol', no_tobacco: 'tobacco', no_political: 'political', no_religious: 'religious', no_gambling: 'gambling' }

const hoursOn = computed(() => {
  const s = screen.value
  if (!s) return { open: 0, close: 1440 }
  const close = toMinutes(s.closes_at)
  return { open: toMinutes(s.opens_at), close: close === 0 ? 1440 : close }
})
const days = computed(() => daysInRange(range.value.start, range.value.end))
const chosen = computed(() => ads.value.find((a) => a.id === adId.value))
/** Slots one play of the chosen ad takes (a longer video takes several). */
const need = computed(() => (screen.value && chosen.value ? slotsFor(chosen.value, screen.value.spot_seconds) : 1))
/** Slots the booking takes in every loop. */
const takes = computed(() => need.value * repeats.value)
const now = ref(nepalNow())

/** Free slots in each bookable hour of a day: hour → free. */
function freeOn(date: string): Map<number, number> {
  const g = grid.value
  const out = new Map<number, number>()
  g?.hours.forEach((h, i) => out.set(h, g.days[date]?.[i] ?? g.slots_per_loop))
  return out
}
const hoursOfWindow = computed(() => {
  const out: number[] = []
  for (let h = Math.floor(win.value.start / 60); h * 60 < win.value.end; h++) out.push(h)
  return out
})
const state = (frees: number[]) =>
  frees.some((f) => f < takes.value) ? ('full' as const) : frees.some((f) => f < (grid.value?.slots_per_loop ?? 0)) ? ('busy' as const) : ('free' as const)

const dayCells = computed<DayCell[]>(() =>
  Array.from({ length: STRIP_DAYS }, (_, i) => {
    const date = nepalDay(i)
    if (date < now.value.day || (date === now.value.day && win.value.start <= now.value.minute)) return { date, state: 'past' as const }
    const row = freeOn(date)
    return { date, state: state(hoursOfWindow.value.map((h) => row.get(h)).filter((f): f is number => f !== undefined)) }
  }),
)
const selectedDates = computed(() => dayCells.value.map((d) => d.date).filter((d) => d >= range.value.start && d <= range.value.end))
const hourCells = computed<HourCell[]>(() => {
  const { open, close } = hoursOn.value
  const rows = selectedDates.value.map(freeOn)
  const out: HourCell[] = []
  for (let h = Math.floor(open / 60); h * 60 < close; h++) {
    const past = range.value.start === now.value.day && (h + 1) * 60 <= now.value.minute
    out.push({ hour: h, state: past ? 'past' : state(rows.map((r) => r.get(h)).filter((f): f is number => f !== undefined)) })
  }
  return out
})
/** Slots already booked in the busiest hour of the chosen time (what the loop preview shows). */
const usedNow = computed(() => {
  const slots = grid.value?.slots_per_loop ?? 0
  let used = 0
  for (const r of selectedDates.value.map(freeOn)) for (const h of hoursOfWindow.value) used = Math.max(used, slots - (r.get(h) ?? slots))
  return used
})
const fits = computed(() => usedNow.value + takes.value <= (grid.value?.slots_per_loop ?? 0))

const loops = computed(() => (screen.value ? loopStarts(screen.value.loop_seconds, win.value.start, win.value.end) : []))
const cost = computed(() =>
  screen.value
    ? quote({ days: days.value, loopsPerDay: loops.value.length, repeats: repeats.value, pricePerPlay: screen.value.price_per_play, slots: need.value })
    : { plays: 0, perPlay: 0, total: 0 },
)
const balance = computed(() => Number(wallets.wallet?.available ?? 0))
const shortBy = computed(() => Math.max(0, cost.value.total - balance.value))
const verified = computed(() => auth.user?.document_status === 'approved')
const startsPast = computed(() => range.value.start < now.value.day || (range.value.start === now.value.day && win.value.start <= now.value.minute))

/** Why this ad can't play on this screen, or null when it fits. Mirrors the API's checks. */
function misfit(a: Creative): string | null {
  const s = screen.value
  if (!s) return 'Loading the screen…'
  if (Math.abs(a.width_px / a.height_px - s.width_px / s.height_px) > 0.01) {
    return `Wrong shape: this ad is ${a.width_px}×${a.height_px}, the screen needs ${s.orientation} ${s.width_px}×${s.height_px}.`
  }
  if (slotsFor(a, s.spot_seconds) > s.slots_per_loop) {
    return `Too long: this video is ${Math.round(a.duration_s ?? 0)} s, the screen’s whole loop is ${s.loop_seconds} s.`
  }
  if (s.content_rules?.some((r) => BLOCKS[r] === a.category)) return `This screen doesn’t show ${CATEGORIES[a.category] ?? a.category} ads.`
  return null
}
const pending = (a: Creative) => a.status === 'in_review' || a.status === 'processing'
/** Every approved ad, plus ones still being checked; the ones that can't be booked here say why. */
const choices = computed(() =>
  ads.value
    .filter((a) => a.status === 'approved' || pending(a))
    .map((a) => ({ ad: a, why: pending(a) ? 'Being checked by our team. You can book it once approved.' : misfit(a) }))
    .sort((x, y) => Number(!!x.why) - Number(!!y.why)),
)
const bookable = computed(() => choices.value.filter((c) => !c.why).map((c) => c.ad))

const canBook = computed(
  () =>
    auth.isLoggedIn && verified.value && !!adId.value && days.value > 0 && fits.value && loops.value.length > 0 && !startsPast.value && shortBy.value === 0,
)

/** Start on the first open stretch from 08:00 (two hours), within the screen's on time. */
function suggestTime() {
  const { open, close } = hoursOn.value
  const ok = (h: HourCell) => h.state === 'free' || h.state === 'busy'
  const first = hourCells.value.find((h) => ok(h) && h.hour >= 8) ?? hourCells.value.find(ok)
  const start = Math.max(open, (first?.hour ?? 8) * 60)
  win.value = { start, end: Math.min(close, start + 120) }
}

async function loadGrid() {
  grid.value = (await get<Availability>(`/screens/${props.id}/availability`, { from: nepalDay(0), to: nepalDay(STRIP_DAYS - 1) })).data
}

async function load() {
  loading.value = true
  error.value = null
  try {
    screen.value = (await get<Screen>(`/screens/${props.id}`)).data
    document.title = `${screen.value.name} · Screen Ads`
    await loadGrid()
    if (auth.isLoggedIn) {
      await Promise.all([auth.reloadMe(), wallets.load()])
      if (wallets.orgId) {
        ads.value = (await get<Creative[]>(`/orgs/${wallets.orgId}/creatives`)).data
        adId.value = bookable.value[0]?.id ?? ''
      }
    }
    suggestTime()
  } catch (err) {
    error.value = errorMessage(err)
  } finally {
    loading.value = false
  }
}

async function book() {
  if (!wallets.orgId || !canBook.value) return
  busy.value = true
  bookError.value = null
  try {
    await post<Booking>(`/orgs/${wallets.orgId}/bookings`, {
      screen_id: props.id,
      creative_id: adId.value,
      start_date: range.value.start,
      end_date: range.value.end,
      start_time: toTime(win.value.start),
      end_time: toTime(win.value.end),
      repeats: repeats.value,
    })
    await wallets.load()
    router.push({ name: 'bookings' })
  } catch (err) {
    const gate = gateNeeded(err)
    const status = (err as { response?: { status?: number } }).response?.status
    if (gate === 'terms') router.push({ name: 'welcome', query: { next: `/screens/${props.id}` } })
    else if (gate === 'document') await auth.reloadMe()
    else if (status === 402) await wallets.load()
    else {
      const f = fieldErrors(err)
      bookError.value = f.creative_id ?? f.repeats ?? f.start_time ?? f.end_time ?? f.start_date ?? errorMessage(err)
    }
    // Someone paid for that time first: show the new picture.
    if (status === 409) await loadGrid()
  } finally {
    busy.value = false
  }
}

onMounted(load)
const clockTimer = setInterval(() => (now.value = nepalNow()), 30_000)
onBeforeUnmount(() => clearInterval(clockTimer))

const loopsPerHour = computed(() => (screen.value ? Math.floor(3600 / screen.value.loop_seconds) : 0))
const perDayAllDay = computed(() => (screen.value ? Number(screen.value.price_per_play) * screen.value.plays_per_hour * 16 : 0))
const timesWord = (n: number) => (n === 1 ? 'Once' : n === 2 ? 'Twice' : `${n} times`)
</script>

<template>
  <div class="shell py-8 sm:py-10">
    <div v-if="loading" aria-busy="true" aria-label="Loading screen">
      <div class="skeleton h-4 w-24" />
      <div class="skeleton mt-4 h-9 w-2/3 max-w-md" />
      <div class="mt-8 grid grid-cols-[minmax(0,1fr)] gap-6 lg:grid-cols-[minmax(0,1fr)_400px]">
        <div class="skeleton h-[32rem]" />
        <div class="skeleton h-96" />
      </div>
    </div>

    <EmptyState v-else-if="!screen" icon="screen" title="We couldn’t open this screen" :body="error ?? undefined">
      <button type="button" class="btn-outline" @click="load">Try again</button>
      <RouterLink to="/screens" class="btn-primary">All screens</RouterLink>
    </EmptyState>

    <template v-else>
      <PageHeader :title="screen.name" :back="{ to: '/screens', label: 'All screens' }">
        <template #meta>
          <p v-if="screen.address" class="mt-2 flex items-center gap-1.5 text-muted"><AppIcon name="pin" :size="18" /> {{ screen.address }}</p>
        </template>
      </PageHeader>

      <div class="grid grid-cols-[minmax(0,1fr)] gap-6 lg:grid-cols-[minmax(0,1fr)_400px] lg:items-start">
        <!-- Plan -->
        <section class="card min-w-0 p-5 sm:p-6 lg:col-start-1" aria-labelledby="plan-h">
          <h2 id="plan-h" class="h-section">Book this screen</h2>
          <p class="mt-1 text-sm text-muted">
            Ads take turns in a {{ duration(screen.loop_seconds) }} loop, {{ loopsPerHour }} times an hour. Choose your ad, the days and a time that is still
            open.
          </p>

          <!-- 1. Ad -->
          <div class="mt-6">
            <h3 class="field-label"><span class="text-plum-700">1.</span> Your ad</h3>
            <template v-if="auth.isLoggedIn">
              <div v-if="choices.length" class="grid gap-2 sm:grid-cols-2">
                <label
                  v-for="{ ad: a, why } in choices"
                  :key="a.id"
                  class="flex items-center gap-3 rounded-xl border p-2 transition has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-plum-700"
                  :class="
                    why
                      ? 'cursor-not-allowed border-dashed border-line-strong bg-canvas'
                      : adId === a.id
                        ? 'cursor-pointer border-plum-700 bg-plum-50'
                        : 'cursor-pointer border-line-strong hover:border-plum-300'
                  "
                >
                  <input v-model="adId" type="radio" name="ad" :value="a.id" :disabled="!!why" class="sr-only" />
                  <span class="relative grid h-10 w-16 shrink-0 place-items-center overflow-hidden rounded-md bg-plum-950 text-white/70" :class="{ 'opacity-50': why }">
                    <img v-if="a.thumb_url" :src="a.thumb_url" alt="" class="h-full w-full object-cover" />
                    <AppIcon v-else :name="a.kind === 'video' ? 'film' : 'image'" :size="16" />
                    <span v-if="a.kind === 'video'" class="absolute right-0.5 bottom-0.5 rounded bg-black/70 px-1 text-[10px] leading-4 font-bold text-white">
                      {{ Math.round(a.duration_s ?? 0) }}s
                    </span>
                  </span>
                  <span class="min-w-0 flex-1">
                    <span class="block truncate text-sm font-bold" :class="{ 'text-muted': why }">{{ a.name }}</span>
                    <span v-if="why" class="block text-xs font-medium text-danger">{{ why }}</span>
                    <span v-else class="block text-xs text-subtle">{{ a.kind === 'video' ? 'Video' : 'Image' }} · {{ slotsFor(a, screen.spot_seconds) * screen.spot_seconds }}&nbsp;s a play</span>
                  </span>
                  <AppIcon v-if="!why && adId === a.id" name="check" :size="18" class="shrink-0 text-plum-700" />
                </label>
              </div>
              <div v-else class="rounded-xl border border-dashed border-line-strong p-4 text-center text-sm text-muted">
                Upload an ad first. Our team approves it, then you can book.
                <RouterLink to="/ads" class="btn-outline btn-sm mt-3">Upload ad</RouterLink>
              </div>
              <p v-if="choices.length && !bookable.length" class="field-hint">
                None of your ads can play here yet. Upload a {{ screen.orientation }} {{ screen.width_px }}×{{ screen.height_px }} version, videos up to
                {{ screen.loop_seconds }}&nbsp;s. <RouterLink to="/ads" class="link">Upload ad</RouterLink>
              </p>
            </template>
            <p v-else class="text-sm text-muted">
              <RouterLink :to="{ name: 'login', query: { next: `/screens/${id}` } }" class="link">Sign in</RouterLink> to choose one of your approved ads. You can look around
              first.
            </p>

            <div class="mt-4">
              <p class="text-xs font-semibold text-subtle">Plays in every loop</p>
              <div class="mt-1.5 inline-flex rounded-xl border border-line-strong p-1" role="radiogroup" aria-label="Plays in every loop">
                <button
                  v-for="n in REPEATS"
                  :key="n"
                  type="button"
                  role="radio"
                  :aria-checked="repeats === n"
                  class="rounded-lg px-3.5 py-1.5 text-sm font-bold transition"
                  :class="repeats === n ? 'bg-plum-700 text-white shadow-sm' : 'text-body hover:bg-plum-50'"
                  @click="repeats = n"
                >
                  {{ timesWord(n) }}
                </button>
              </div>
            </div>
          </div>

          <!-- 2. Days -->
          <div class="mt-7">
            <div class="flex flex-wrap items-baseline justify-between gap-2">
              <h3 class="field-label"><span class="text-plum-700">2.</span> Days</h3>
              <span class="text-sm font-semibold text-plum-900">{{ formatRange(range.start, range.end) }} · {{ days }} {{ days === 1 ? 'day' : 'days' }}</span>
            </div>
            <DayStrip :days="dayCells" :start="range.start" :end="range.end" @pick="range = $event" />
            <p class="field-hint">Tap a day to start, then a later day to end. Same time every day.</p>
          </div>

          <!-- 3. Time -->
          <div class="mt-7">
            <h3 class="field-label"><span class="text-plum-700">3.</span> Time</h3>
            <div class="pt-7">
              <TimeBar :open="hoursOn.open" :close="hoursOn.close" :hours="hourCells" :start="win.start" :end="win.end" @change="win = $event" />
            </div>
          </div>
        </section>

        <!-- Summary -->
        <aside class="card p-5 shadow-card sm:p-6 lg:sticky lg:top-24 lg:col-start-2 lg:row-span-2 lg:row-start-1" aria-labelledby="sum-h">
          <h2 id="sum-h" class="h-section">Your ad will run</h2>
          <ul class="mt-4 space-y-3 text-sm">
            <li class="flex gap-3">
              <AppIcon name="calendar" :size="18" class="mt-0.5 shrink-0 text-plum-700" />
              <span><strong class="text-ink">{{ formatRange(range.start, range.end) }}</strong><span class="block text-subtle">{{ days }} {{ days === 1 ? 'day' : 'days' }}</span></span>
            </li>
            <li class="flex gap-3">
              <AppIcon name="clock" :size="18" class="mt-0.5 shrink-0 text-plum-700" />
              <span><strong class="text-ink">{{ toTime(win.start) }}–{{ toTime(win.end) }}</strong> every day<span class="block text-subtle">Nepal time</span></span>
            </li>
            <li class="flex gap-3">
              <AppIcon name="play" :size="18" class="mt-0.5 shrink-0 text-plum-700" />
              <span>
                <strong class="text-ink">{{ timesWord(repeats) }}</strong> in every {{ duration(screen.loop_seconds) }} loop
                <span class="block text-subtle">{{ need * screen.spot_seconds }}&nbsp;s a play · {{ cost.plays.toLocaleString('en-IN') }} plays in total</span>
              </span>
            </li>
          </ul>

          <div class="mt-4">
            <LoopBar :loop="screen.loop_seconds" :spot="screen.spot_seconds" :slots="screen.slots_per_loop" :used="usedNow" :need="need" :repeats="repeats" />
            <p v-if="fits" class="mt-2 text-xs text-subtle">
              The screen lines ads up with no gaps, so your ad goes into the free time in every loop. Loops start at
              <span class="font-semibold text-ink [font-variant-numeric:tabular-nums]">{{ loops.slice(0, 3).map(clock).join(', ') }}</span><template v-if="loops.length > 3"> …
                <span class="font-semibold text-ink [font-variant-numeric:tabular-nums]">{{ clock(loops[loops.length - 1]!) }}</span></template>.
            </p>
            <p v-else class="mt-2 text-sm font-semibold text-danger">
              Not enough free time at {{ toTime(win.start) }}–{{ toTime(win.end) }} on all these days. Tap an available time, fewer plays per loop or other days.
            </p>
          </div>

          <div class="mt-4 rounded-xl bg-canvas p-4">
            <div class="flex items-baseline justify-between gap-3">
              <span class="font-bold">Total</span>
              <span class="price text-2xl">{{ formatRupees(cost.total) }}</span>
            </div>
            <p class="mt-1 text-xs text-subtle">
              {{ cost.plays.toLocaleString('en-IN') }} plays × {{ formatRupees(cost.perPlay) }}<template v-if="need > 1"> ({{ need }} slots of {{ formatRupees(screen.price_per_play) }})</template>
            </p>
            <p v-if="auth.isLoggedIn && wallets.wallet" class="mt-2 flex justify-between gap-3 border-t border-line pt-2 text-xs">
              <span class="text-subtle">Your balance</span>
              <span class="font-bold">{{ formatRupees(balance) }}</span>
            </p>
          </div>

          <div class="mt-4 space-y-3">
            <RouterLink v-if="!auth.isLoggedIn" :to="{ name: 'login', query: { next: `/screens/${id}` } }" class="btn-primary w-full">Sign in to book</RouterLink>
            <template v-else>
              <AlertBox v-if="!verified" tone="warning" :title="auth.user?.document_status === 'pending' ? 'Your identity is being checked' : 'Verify your identity first'">
                {{ auth.user?.document_status === 'pending' ? 'You can book as soon as our team verifies your document.' : 'Upload your citizenship, passport or company papers. It takes a minute.' }}
                <template v-if="auth.user?.document_status !== 'pending'" #action><RouterLink to="/verification" class="link shrink-0 text-sm">Upload</RouterLink></template>
              </AlertBox>
              <AlertBox v-else-if="shortBy > 0" tone="warning" title="Not enough balance">
                Load {{ formatRupees(Math.ceil(shortBy)) }} more to book this.
                <template #action><RouterLink to="/wallet" class="link shrink-0 text-sm">Load money</RouterLink></template>
              </AlertBox>
              <AlertBox v-if="startsPast" tone="warning">That time has already passed today. Pick a later time or start tomorrow.</AlertBox>
              <AlertBox v-if="bookError">{{ bookError }}</AlertBox>
              <button type="button" class="btn-primary w-full" :disabled="!canBook || busy" @click="book">
                {{ busy ? 'Paying…' : `Pay ${formatRupees(cost.total)} and book` }}
              </button>
              <p class="text-center text-xs text-subtle">Paid from your wallet. Cancel any time: plays that haven’t happened are refunded.</p>
            </template>
          </div>
        </aside>

        <!-- About the screen -->
        <div class="min-w-0 space-y-6 lg:col-start-1">
          <div class="card overflow-hidden">
            <ScreenMap :screens="[screen]" height="h-56" />
            <dl class="grid grid-cols-2 gap-4 p-5 text-sm sm:grid-cols-4 sm:p-6">
              <div>
                <dt class="text-subtle">Price</dt>
                <dd class="price text-lg">{{ formatRupees(screen.price_per_play) }}</dd>
                <dd class="text-xs text-subtle">per {{ screen.spot_seconds }}&nbsp;s play</dd>
              </div>
              <div>
                <dt class="text-subtle">Plays per hour</dt>
                <dd class="text-lg font-bold">{{ screen.plays_per_hour }}</dd>
                <dd class="text-xs text-subtle">once every {{ duration(screen.loop_seconds) }}</dd>
              </div>
              <div>
                <dt class="text-subtle">On air</dt>
                <dd class="text-lg font-bold">{{ screen.opens_at }}–{{ screen.closes_at }}</dd>
                <dd class="text-xs text-subtle">≈ {{ formatRupees(Math.round(perDayAllDay)) }} for 16 h</dd>
              </div>
              <div>
                <dt class="text-subtle">Your ad must be</dt>
                <dd class="font-bold capitalize">{{ screen.orientation }} · {{ screen.width_px }}×{{ screen.height_px }}</dd>
                <dd class="text-xs text-subtle">any length up to {{ screen.loop_seconds }}&nbsp;s</dd>
              </div>
            </dl>
          </div>

          <div class="card space-y-5 p-5 sm:p-6">
            <div v-if="screen.description">
              <h2 class="h-section">About this screen</h2>
              <p class="mt-2 text-[15px] whitespace-pre-line text-body">{{ screen.description }}</p>
            </div>
            <div v-if="screen.audience_notes">
              <h2 class="h-section">Who sees it</h2>
              <p class="mt-2 text-[15px] whitespace-pre-line text-body">{{ screen.audience_notes }}</p>
            </div>
            <div>
              <h2 class="h-section">Not shown on this screen</h2>
              <ul v-if="screen.content_rules?.length" class="mt-3 flex flex-wrap gap-2">
                <li v-for="r in screen.content_rules" :key="r" class="badge bg-danger/8 text-danger"><AppIcon name="x" :size="14" /> {{ CONTENT_RULES[r] ?? r }}</li>
              </ul>
              <p v-else class="mt-2 text-sm text-muted">No extra rules beyond our content policy.</p>
            </div>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>
