<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import AlertBox from '@/components/ui/AlertBox.vue'
import AppIcon, { type IconName } from '@/components/ui/AppIcon.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import { formatHours, formatRange, formatRupees } from '@/lib/format'
import { errorMessage, get } from '@/lib/http'
import type { Booking, Creative } from '@/lib/types'
import { useAuthStore } from '@/stores/auth'
import { useOrgStore } from '@/stores/orgs'
import { useWalletStore } from '@/stores/wallet'

const auth = useAuthStore()
const orgs = useOrgStore()
const wallets = useWalletStore()

const ads = ref<Creative[]>([])
const bookings = ref<Booking[]>([])
const loading = ref(true)
const error = ref<string | null>(null)

const firstName = computed(() => (auth.user?.name ?? '').split(' ')[0])
const doc = computed(() => auth.user?.document_status ?? 'none')
const approvedAds = computed(() => ads.value.filter((a) => a.status === 'approved').length)
const reviewAds = computed(() => ads.value.filter((a) => a.status === 'in_review' || a.status === 'processing').length)
const activeBookings = computed(() => bookings.value.filter((b) => ['checking', 'scheduled', 'live'].includes(b.status)))
const liveCount = computed(() => bookings.value.filter((b) => b.status === 'live').length)

interface Step {
  title: string
  body: string
  done: boolean
  icon: IconName
  to: string
  cta: string
  waiting?: boolean
}

const steps = computed<Step[]>(() => [
  { title: 'Verify your number', body: auth.user?.phone_masked ?? 'Signed in', done: true, icon: 'phone', to: '/dashboard', cta: '' },
  {
    title: 'Accept the terms and content policy',
    body: 'No sexual, harmful, illegal or hateful content, ever.',
    done: auth.user?.terms_accepted === true,
    icon: 'shield',
    to: '/welcome',
    cta: 'Read and accept',
  },
  {
    title: 'Verify your identity',
    body:
      doc.value === 'pending'
        ? 'Your document is being checked. You can carry on meanwhile.'
        : doc.value === 'rejected'
          ? 'Your document was refused. Please upload a clear one.'
          : 'Upload your citizenship, passport, national ID or company papers.',
    done: doc.value === 'approved',
    waiting: doc.value === 'pending',
    icon: 'idcard',
    to: '/verification',
    cta: doc.value === 'rejected' ? 'Upload again' : 'Upload document',
  },
  {
    title: 'Load money into your wallet',
    body: 'Pay with eSewa, Khalti or bank. Each play is charged from it.',
    done: Number(wallets.wallet?.balance ?? 0) > 0,
    icon: 'wallet',
    to: '/wallet',
    cta: 'Load money',
  },
  {
    title: 'Upload your ad',
    body: 'An image or a short video. Our team checks every ad.',
    done: ads.value.length > 0,
    waiting: ads.value.length > 0 && approvedAds.value === 0,
    icon: 'upload',
    to: '/ads',
    cta: 'Upload ad',
  },
  {
    title: 'Book a screen',
    body: 'Pick a place on the map, the dates and the hours.',
    done: bookings.value.length > 0,
    icon: 'map',
    to: '/screens',
    cta: 'Find screens',
  },
])
const doneCount = computed(() => steps.value.filter((s) => s.done).length)
const nextStep = computed(() => steps.value.find((s) => !s.done && !s.waiting))

async function load() {
  loading.value = true
  error.value = null
  try {
    await auth.reloadMe()
    await wallets.load()
    const org = orgs.advertiser
    if (org) {
      const [a, b] = await Promise.all([get<Creative[]>(`/orgs/${org.id}/creatives`), get<Booking[]>(`/orgs/${org.id}/bookings`)])
      ads.value = a.data
      bookings.value = b.data
    }
  } catch (err) {
    error.value = errorMessage(err)
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<template>
  <div>
    <section class="page-wash">
      <div class="shell py-8 sm:py-10">
        <p class="eyebrow">Dashboard</p>
        <h1 class="h-page mt-1.5">Namaste, {{ firstName }} 🙏</h1>
        <p class="lede mt-2">
          <template v-if="doneCount < steps.length">You’re {{ doneCount }} of {{ steps.length }} steps from your first ad on a screen.</template>
          <template v-else-if="liveCount">Your ads are playing on {{ liveCount }} {{ liveCount === 1 ? 'screen' : 'screens' }} right now.</template>
          <template v-else>Everything is set up. Book another screen any time.</template>
        </p>

        <!-- Stats -->
        <div class="mt-6 grid gap-3 sm:grid-cols-3">
          <RouterLink to="/wallet" class="card-interactive flex items-center gap-4 p-5">
            <span class="grid h-12 w-12 shrink-0 place-items-center rounded-xl bg-plum-700 text-white"><AppIcon name="wallet" /></span>
            <span class="min-w-0">
              <span class="block text-sm text-subtle">Available balance</span>
              <span v-if="wallets.wallet" class="price block text-2xl">{{ formatRupees(wallets.wallet.available) }}</span>
              <span v-else class="skeleton mt-1 block h-7 w-24" />
            </span>
          </RouterLink>
          <RouterLink to="/ads" class="card-interactive flex items-center gap-4 p-5">
            <span class="grid h-12 w-12 shrink-0 place-items-center rounded-xl bg-teal-50 text-teal-dark"><AppIcon name="film" /></span>
            <span>
              <span class="block text-sm text-subtle">Ads approved</span>
              <span class="block text-2xl font-extrabold">{{ loading ? '–' : approvedAds }}</span>
              <span v-if="reviewAds" class="text-xs font-semibold text-warning">{{ reviewAds }} in review</span>
            </span>
          </RouterLink>
          <RouterLink to="/bookings" class="card-interactive flex items-center gap-4 p-5">
            <span class="grid h-12 w-12 shrink-0 place-items-center rounded-xl bg-coral-700/10 text-coral-700"><AppIcon name="calendar" /></span>
            <span>
              <span class="block text-sm text-subtle">Active bookings</span>
              <span class="block text-2xl font-extrabold">{{ loading ? '–' : activeBookings.length }}</span>
              <span v-if="liveCount" class="text-xs font-semibold text-success">{{ liveCount }} live now</span>
            </span>
          </RouterLink>
        </div>
      </div>
    </section>

    <div class="shell grid grid-cols-[minmax(0,1fr)] gap-6 py-8 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
      <AlertBox v-if="error" class="lg:col-span-2">{{ error }}</AlertBox>

      <!-- Getting started -->
      <section class="card p-5 sm:p-6" aria-labelledby="steps-h">
        <div class="flex items-center justify-between gap-4">
          <h2 id="steps-h" class="h-section">Get started</h2>
          <span class="text-sm font-bold text-subtle">{{ doneCount }}/{{ steps.length }}</span>
        </div>
        <div class="mt-3 h-2 overflow-hidden rounded-full bg-divider" aria-hidden="true">
          <div class="h-full rounded-full bg-success transition-all" :style="{ width: `${(doneCount / steps.length) * 100}%` }" />
        </div>
        <ol class="mt-4 divide-y divide-divider">
          <li v-for="s in steps" :key="s.title" class="flex items-center gap-3 py-3.5">
            <span
              class="grid h-9 w-9 shrink-0 place-items-center rounded-full"
              :class="s.done ? 'bg-success text-white' : s.waiting ? 'bg-warning/15 text-warning' : 'bg-plum-100 text-plum-700'"
            >
              <AppIcon :name="s.done ? 'check' : s.waiting ? 'clock' : s.icon" :size="18" />
            </span>
            <span class="min-w-0 flex-1">
              <span class="block font-bold" :class="s.done ? 'text-muted line-through decoration-1' : 'text-ink'">{{ s.title }}</span>
              <span class="block text-sm text-subtle">{{ s.body }}</span>
            </span>
            <RouterLink
              v-if="!s.done && s.cta"
              :to="s.to"
              class="btn-sm shrink-0"
              :class="nextStep?.title === s.title ? 'btn-primary' : 'btn-outline'"
            >
              {{ s.cta }}
            </RouterLink>
          </li>
        </ol>
      </section>

      <!-- Recent bookings -->
      <section class="card p-5 sm:p-6" aria-labelledby="recent-h">
        <div class="flex items-center justify-between gap-4">
          <h2 id="recent-h" class="h-section">Your bookings</h2>
          <RouterLink to="/bookings" class="link text-sm">See all</RouterLink>
        </div>
        <div v-if="loading" class="mt-4 space-y-3"><div v-for="i in 3" :key="i" class="skeleton h-16" /></div>
        <div v-else-if="!bookings.length" class="mt-6 flex flex-col items-center py-6 text-center">
          <span class="grid h-14 w-14 place-items-center rounded-2xl bg-plum-100 text-plum-700"><AppIcon name="map" :size="26" /></span>
          <p class="mt-3 font-bold">No bookings yet</p>
          <p class="mt-1 text-sm text-muted">Find a screen near your customers to get started.</p>
          <RouterLink to="/screens" class="btn-primary btn-sm mt-4">Find screens</RouterLink>
        </div>
        <ul v-else class="mt-3 divide-y divide-divider">
          <li v-for="b in bookings.slice(0, 5)" :key="b.id" class="py-3">
            <div class="flex items-start justify-between gap-3">
              <div class="min-w-0">
                <p class="truncate font-bold">{{ b.screen?.name }}</p>
                <p class="text-sm text-subtle">{{ formatRange(b.start_date, b.end_date) }} · {{ formatHours(b.start_hour, b.end_hour) }}</p>
              </div>
              <StatusBadge :status="b.status" />
            </div>
            <div class="mt-2 flex items-center gap-3">
              <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-divider">
                <div class="h-full rounded-full bg-plum-700" :style="{ width: `${(b.plays_done / Math.max(1, b.plays_total)) * 100}%` }" />
              </div>
              <span class="shrink-0 text-xs font-semibold text-subtle">{{ b.plays_done.toLocaleString('en-IN') }} / {{ b.plays_total.toLocaleString('en-IN') }} plays</span>
            </div>
          </li>
        </ul>
      </section>
    </div>
  </div>
</template>
