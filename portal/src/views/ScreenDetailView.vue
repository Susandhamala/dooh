<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import HourGrid from '@/components/HourGrid.vue'
import ScreenMap from '@/components/ScreenMap.vue'
import AlertBox from '@/components/ui/AlertBox.vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import { isoDay } from '@/lib/dates'
import { daysInRange, formatHour, formatHours, formatRupees, quote } from '@/lib/format'
import { errorMessage, fieldErrors, gateNeeded, get, post } from '@/lib/http'
import { CATEGORIES, CONTENT_RULES, type Availability, type Booking, type Creative, type Screen } from '@/lib/types'
import { useAuthStore } from '@/stores/auth'
import { useWalletStore } from '@/stores/wallet'

const props = defineProps<{ id: string }>()
const auth = useAuthStore()
const wallets = useWalletStore()
const router = useRouter()

const screen = ref<Screen | null>(null)
const loading = ref(true)
const error = ref<string | null>(null)

const range = ref({ start: isoDay(1), end: isoDay(7) })
const hours = ref({ start: 8, end: 10 })
const grid = ref<Availability | null>(null)
const gridLoading = ref(false)
const gridError = ref<string | null>(null)

const ads = ref<Creative[]>([])
const adId = ref('')
const busy = ref(false)
const bookError = ref<string | null>(null)
const blocker = ref<'terms' | 'document' | 'money' | null>(null)

const BLOCKS: Record<string, string> = { no_alcohol: 'alcohol', no_tobacco: 'tobacco', no_political: 'political', no_religious: 'religious', no_gambling: 'gambling' }

const rangeValid = computed(() => range.value.end >= range.value.start && daysInRange(range.value.start, range.value.end) <= 92)
const hoursValid = computed(() => hours.value.end > hours.value.start)
const days = computed(() => (rangeValid.value ? daysInRange(range.value.start, range.value.end) : 0))
const cost = computed(() =>
  screen.value
    ? quote({ days: days.value, startHour: hours.value.start, endHour: hours.value.end, playsPerHour: screen.value.plays_per_hour, pricePerPlay: screen.value.price_per_play })
    : { hours: 0, playsPerDay: 0, plays: 0, total: 0 },
)
const available = computed(() => Number(wallets.wallet?.available ?? 0))
const shortBy = computed(() => Math.max(0, cost.value.total - available.value))

/** Lowest free slot count in the chosen hours across the chosen days. */
const minFree = computed(() => {
  if (!grid.value) return null
  const idx = grid.value.hours.map((h, i) => (h >= hours.value.start && h < hours.value.end ? i : -1)).filter((i) => i >= 0)
  const all = Object.values(grid.value.days).flatMap((row) => idx.map((i) => row[i] ?? 0))
  return all.length ? Math.min(...all) : 0
})

const hourOptions = computed(() => {
  const [open, close] = screen.value?.bookable_hours ?? [0, 24]
  return Array.from({ length: close - open + 1 }, (_, i) => open + i)
})

/** Why an ad can't go on this screen, or null if it can. */
function unfit(a: Creative): string | null {
  const s = screen.value
  if (!s) return null
  if (a.status === 'processing') return 'Still being checked'
  if (a.status === 'failed') return 'File problem: see My ads'
  if (a.status === 'rejected') return 'Refused by review'
  if (Math.abs(a.width_px / a.height_px - s.width_px / s.height_px) > 0.01) return `Wrong shape (${a.orientation})`
  if (a.kind === 'video' && (a.duration_s ?? 0) > s.spot_seconds + 0.5) return `Longer than ${s.spot_seconds} s`
  if (s.content_rules?.some((r) => BLOCKS[r] === a.category)) return `${CATEGORIES[a.category]} not allowed here`
  return null
}
const fitting = computed(() => ads.value.filter((a) => !unfit(a)))
const chosenAd = computed(() => ads.value.find((a) => a.id === adId.value) ?? null)

const canBook = computed(
  () => auth.isLoggedIn && !!chosenAd.value && rangeValid.value && hoursValid.value && (minFree.value ?? 0) > 0 && shortBy.value === 0 && !gridLoading.value,
)

function pickHour(h: number) {
  // First click starts a new window of one hour; a click after it extends the window to that hour.
  if (h >= hours.value.start && h < hours.value.end && hours.value.end - hours.value.start === 1) return
  if (h > hours.value.start && hours.value.end - hours.value.start === 1) hours.value.end = h + 1
  else hours.value = { start: h, end: h + 1 }
}

let seq = 0
async function loadGrid() {
  if (!rangeValid.value) return
  const mine = ++seq
  gridLoading.value = true
  gridError.value = null
  try {
    const res = await get<Availability>(`/screens/${props.id}/availability`, { from: range.value.start, to: range.value.end })
    if (mine === seq) grid.value = res.data
  } catch (err) {
    if (mine === seq) gridError.value = errorMessage(err)
  } finally {
    if (mine === seq) gridLoading.value = false
  }
}

async function load() {
  loading.value = true
  error.value = null
  try {
    screen.value = (await get<Screen>(`/screens/${props.id}`)).data
    document.title = `${screen.value.name} · Screen Ads`
    const [open, close] = screen.value.bookable_hours
    hours.value = { start: Math.min(Math.max(open, 8), close - 1), end: Math.min(Math.max(open, 8) + 2, close) }
    await loadGrid()
    if (auth.isLoggedIn) {
      await wallets.load()
      if (wallets.orgId) {
        ads.value = (await get<Creative[]>(`/orgs/${wallets.orgId}/creatives`)).data
        adId.value = fitting.value.find((a) => a.status === 'approved')?.id ?? fitting.value[0]?.id ?? ''
      }
    }
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
  blocker.value = null
  try {
    await post<Booking>(`/orgs/${wallets.orgId}/bookings`, {
      screen_id: props.id,
      creative_id: adId.value,
      start_date: range.value.start,
      end_date: range.value.end,
      start_hour: hours.value.start,
      end_hour: hours.value.end,
    })
    await wallets.load()
    router.push({ name: 'bookings' })
  } catch (err) {
    const gate = gateNeeded(err)
    const status = (err as { response?: { status?: number } }).response?.status
    if (gate) blocker.value = gate
    else if (status === 402) blocker.value = 'money'
    else {
      const f = fieldErrors(err)
      bookError.value = f.creative_id ?? f.start_hour ?? f.start_date ?? errorMessage(err)
    }
    if (status === 409) loadGrid()
  } finally {
    busy.value = false
  }
}

onMounted(load)
watch(range, loadGrid, { deep: true })
watch(
  () => hours.value.start,
  (s) => {
    if (hours.value.end <= s) hours.value.end = s + 1
  },
)
</script>

<template>
  <div class="shell py-8 sm:py-10">
    <div v-if="loading" aria-busy="true" aria-label="Loading screen">
      <div class="skeleton h-4 w-24" />
      <div class="skeleton mt-4 h-9 w-2/3 max-w-md" />
      <div class="mt-8 grid grid-cols-[minmax(0,1fr)] gap-6 lg:grid-cols-[minmax(0,1fr)_420px]">
        <div class="skeleton h-72" />
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

      <div class="grid grid-cols-[minmax(0,1fr)] gap-6 lg:grid-cols-[minmax(0,1fr)_420px] lg:items-start">
        <div class="min-w-0 space-y-6">
          <div class="card grid gap-6 p-5 sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)] sm:p-6">
            <div class="grid place-items-center rounded-xl bg-plum-950 p-5">
              <div
                class="grid place-items-center rounded-md bg-gradient-to-br from-plum-700 to-navy text-center text-white"
                :class="screen.height_px > screen.width_px ? 'h-56 w-auto' : 'w-full'"
                :style="{ aspectRatio: `${screen.width_px} / ${screen.height_px}` }"
              >
                <span class="px-2 text-xs font-bold tracking-wide text-white/80">{{ screen.width_px }} × {{ screen.height_px }}</span>
              </div>
            </div>
            <dl class="grid grid-cols-2 content-center gap-4 text-sm">
              <div><dt class="text-subtle">Price per play</dt><dd class="price text-lg">{{ formatRupees(screen.price_per_play) }}</dd></div>
              <div><dt class="text-subtle">Plays per hour</dt><dd class="font-bold">{{ screen.plays_per_hour }}</dd></div>
              <div><dt class="text-subtle">On air</dt><dd class="font-bold">{{ screen.opens_at }}–{{ screen.closes_at }}</dd></div>
              <div><dt class="text-subtle">Ad length</dt><dd class="font-bold">up to {{ screen.spot_seconds }} s</dd></div>
              <div><dt class="text-subtle">Shape</dt><dd class="font-bold capitalize">{{ screen.orientation }}</dd></div>
              <div><dt class="text-subtle">Advertisers at once</dt><dd class="font-bold">{{ screen.slots_per_loop }}</dd></div>
            </dl>
          </div>

          <!-- Availability -->
          <section class="card p-5 sm:p-6" aria-labelledby="avail-h">
            <div class="flex flex-wrap items-baseline justify-between gap-2">
              <h2 id="avail-h" class="h-section">Free slots by hour</h2>
              <span v-if="gridLoading" class="text-xs text-subtle">Updating…</span>
            </div>
            <p class="mt-1 text-sm text-muted">Each number is how many more advertisers that hour can take. Click an hour to start, another to end.</p>
            <AlertBox v-if="gridError" class="mt-4">{{ gridError }}</AlertBox>
            <HourGrid
              v-else-if="grid && grid.hours.length"
              class="mt-4"
              :class="{ 'opacity-60': gridLoading }"
              :availability="grid"
              :start="hours.start"
              :end="hours.end"
              :slots="screen.slots_per_loop"
              @pick="pickHour"
            />
            <p v-else-if="grid" class="mt-4 text-sm text-muted">This screen has no bookable hours.</p>
            <div v-else class="skeleton mt-4 h-48" />
          </section>

          <div v-if="screen.description || screen.audience_notes" class="card space-y-4 p-5 sm:p-6">
            <div v-if="screen.description">
              <h2 class="h-section">About this screen</h2>
              <p class="mt-2 text-[15px] whitespace-pre-line text-body">{{ screen.description }}</p>
            </div>
            <div v-if="screen.audience_notes">
              <h2 class="h-section">Who sees it</h2>
              <p class="mt-2 text-[15px] whitespace-pre-line text-body">{{ screen.audience_notes }}</p>
            </div>
          </div>

          <div class="card p-5 sm:p-6">
            <h2 class="h-section">Content rules</h2>
            <p class="mt-1 text-sm text-muted">On top of our content policy, this screen doesn’t show:</p>
            <ul v-if="screen.content_rules?.length" class="mt-3 flex flex-wrap gap-2">
              <li v-for="r in screen.content_rules" :key="r" class="badge bg-danger/8 text-danger"><AppIcon name="x" :size="14" /> {{ CONTENT_RULES[r] ?? r }}</li>
            </ul>
            <p v-else class="mt-3 text-sm font-semibold text-success">No extra rules.</p>
          </div>

          <ScreenMap :screens="[screen]" height="h-64" />
        </div>

        <!-- Booking -->
        <aside class="card p-5 shadow-card sm:p-6 lg:sticky lg:top-24" aria-labelledby="book-h">
          <h2 id="book-h" class="h-section">Book this screen</h2>

          <div class="mt-4 grid grid-cols-2 gap-3">
            <div>
              <label class="field-label" for="from">From</label>
              <input id="from" v-model="range.start" type="date" class="input px-3" :min="isoDay(0)" />
            </div>
            <div>
              <label class="field-label" for="to">To</label>
              <input id="to" v-model="range.end" type="date" class="input px-3" :class="{ 'input-error': !rangeValid }" :min="range.start" :max="isoDay(92)" />
            </div>
            <div>
              <label class="field-label" for="h-from">Start hour</label>
              <select id="h-from" v-model.number="hours.start" class="input px-3">
                <option v-for="h in hourOptions.slice(0, -1)" :key="h" :value="h">{{ formatHour(h) }}</option>
              </select>
            </div>
            <div>
              <label class="field-label" for="h-to">End hour</label>
              <select id="h-to" v-model.number="hours.end" class="input px-3">
                <option v-for="h in hourOptions.filter((x) => x > hours.start)" :key="h" :value="h">{{ h === 24 ? '24:00' : formatHour(h) }}</option>
              </select>
            </div>
          </div>
          <p v-if="!rangeValid" class="field-error">Pick an end date on or after the start, at most 92 days.</p>
          <p v-else-if="minFree === 0" class="mt-3 text-sm font-semibold text-danger">Those hours are full on at least one day. Try other hours or dates.</p>
          <p v-else-if="minFree" class="mt-3 text-sm font-semibold text-success">Available: {{ minFree }} of {{ screen.slots_per_loop }} places free in those hours.</p>

          <!-- Your ad -->
          <div v-if="auth.isLoggedIn" class="mt-5">
            <span class="field-label">Your ad</span>
            <div v-if="!ads.length" class="rounded-xl border border-dashed border-line-strong p-4 text-center">
              <p class="text-sm text-muted">Upload an ad first. It can be booked while it is in review.</p>
              <RouterLink to="/ads" class="btn-outline btn-sm mt-3">Upload ad</RouterLink>
            </div>
            <div v-else class="max-h-56 space-y-2 overflow-y-auto pr-1">
              <label
                v-for="a in ads"
                :key="a.id"
                class="flex items-center gap-3 rounded-xl border p-2 transition"
                :class="[
                  unfit(a) ? 'cursor-not-allowed border-line opacity-55' : 'cursor-pointer has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-plum-700',
                  adId === a.id ? 'border-plum-700 bg-plum-50' : unfit(a) ? '' : 'border-line-strong hover:border-plum-300',
                ]"
              >
                <input v-model="adId" type="radio" name="ad" :value="a.id" :disabled="!!unfit(a)" class="sr-only" />
                <span class="grid h-10 w-16 shrink-0 place-items-center overflow-hidden rounded-md bg-plum-950 text-white/70">
                  <img v-if="a.thumb_url" :src="a.thumb_url" alt="" class="h-full w-full object-cover" />
                  <AppIcon v-else :name="a.kind === 'video' ? 'film' : 'image'" :size="16" />
                </span>
                <span class="min-w-0 flex-1">
                  <span class="block truncate text-sm font-bold">{{ a.name }}</span>
                  <span class="block text-xs" :class="unfit(a) ? 'text-danger' : a.status === 'approved' ? 'text-success' : 'text-warning'">
                    {{ unfit(a) ?? (a.status === 'approved' ? 'Approved' : 'In review: plays after approval') }}
                  </span>
                </span>
              </label>
            </div>
          </div>

          <!-- The sum -->
          <div class="mt-5 rounded-xl bg-canvas p-4 text-sm">
            <div class="flex justify-between gap-3"><span class="text-muted">Hours each day</span><span class="font-semibold">{{ formatHours(hours.start, hours.end) }} ({{ cost.hours }} h)</span></div>
            <div class="mt-1.5 flex justify-between gap-3"><span class="text-muted">Plays each day</span><span class="font-semibold">{{ cost.hours }} h × {{ screen.plays_per_hour }} = {{ cost.playsPerDay.toLocaleString('en-IN') }}</span></div>
            <div class="mt-1.5 flex justify-between gap-3"><span class="text-muted">Total plays</span><span class="font-semibold">{{ days }} days × {{ cost.playsPerDay.toLocaleString('en-IN') }} = {{ cost.plays.toLocaleString('en-IN') }}</span></div>
            <div class="mt-1.5 flex justify-between gap-3"><span class="text-muted">Price per play</span><span class="font-semibold">{{ formatRupees(screen.price_per_play) }}</span></div>
            <div class="mt-3 flex items-baseline justify-between gap-3 border-t border-line pt-3">
              <span class="font-bold">Total</span>
              <span class="price text-2xl">{{ formatRupees(cost.total) }}</span>
            </div>
            <template v-if="auth.isLoggedIn && wallets.wallet">
              <div class="mt-2 flex justify-between gap-3 text-xs"><span class="text-subtle">Your available balance</span><span class="font-bold">{{ formatRupees(available) }}</span></div>
              <div v-if="!shortBy" class="mt-1 flex justify-between gap-3 text-xs"><span class="text-subtle">Left after booking</span><span class="font-bold">{{ formatRupees(available - cost.total) }}</span></div>
            </template>
          </div>
          <p class="mt-2 text-xs text-subtle">The total is held from your wallet now. Each play is charged as it happens; unplayed time comes back.</p>

          <div class="mt-5 space-y-3">
            <RouterLink v-if="!auth.isLoggedIn" :to="{ name: 'login', query: { next: `/screens/${id}` } }" class="btn-primary w-full">Sign in to book</RouterLink>
            <template v-else>
              <AlertBox v-if="shortBy > 0 || blocker === 'money'" tone="warning" title="Not enough balance">
                Load at least {{ formatRupees(Math.ceil(shortBy)) }} more to book this.
                <template #action><RouterLink to="/wallet" class="link shrink-0 text-sm">Load money</RouterLink></template>
              </AlertBox>
              <AlertBox v-if="blocker === 'document'" tone="warning" title="Verify your identity first">
                Upload your citizenship, passport or company papers. It takes a minute.
                <template #action><RouterLink to="/verification" class="link shrink-0 text-sm">Upload</RouterLink></template>
              </AlertBox>
              <AlertBox v-if="blocker === 'terms'" tone="warning" title="Accept the terms first">
                <template #action><RouterLink :to="{ name: 'welcome', query: { next: `/screens/${id}` } }" class="link shrink-0 text-sm">Read terms</RouterLink></template>
              </AlertBox>
              <AlertBox v-if="bookError">{{ bookError }}</AlertBox>
              <button type="button" class="btn-primary w-full" :disabled="!canBook || busy" @click="book">
                {{ busy ? 'Booking…' : `Book for ${formatRupees(cost.total)}` }}
              </button>
            </template>
          </div>
        </aside>
      </div>
    </template>
  </div>
</template>
