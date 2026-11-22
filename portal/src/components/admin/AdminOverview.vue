<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import AlertBox from '@/components/ui/AlertBox.vue'
import AppIcon, { type IconName } from '@/components/ui/AppIcon.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import { formatDateTime, formatWindow, formatRange, formatRupees } from '@/lib/format'
import { errorMessage, get, mfaNeeded } from '@/lib/http'
import type { AdminOverview } from '@/lib/types'

/** The admin dashboard: what needs doing, how busy the screens are, and the money. */
const emit = defineEmits<{ go: [tab: string]; counts: [c: { documents: number; ads: number }] }>()
const router = useRouter()

const data = ref<AdminOverview | null>(null)
const error = ref<string | null>(null)
const hover = ref<number | null>(null)

async function load() {
  error.value = null
  try {
    data.value = (await get<AdminOverview>('/admin/overview')).data
    emit('counts', { documents: data.value.documents.pending, ads: data.value.ads.in_review })
  } catch (err) {
    if (mfaNeeded(err)) router.push({ name: 'security', query: { next: '/admin' } })
    else error.value = errorMessage(err)
  }
}

const todo = computed(() => {
  const d = data.value
  if (!d) return []
  return [
    { tab: 'documents', icon: 'idcard' as IconName, count: d.documents.pending, label: 'identity documents to check' },
    { tab: 'ads', icon: 'film' as IconName, count: d.ads.in_review, label: 'ads to review' },
  ]
})

const stats = computed(() => {
  const d = data.value
  if (!d) return []
  return [
    { label: 'Users', value: d.users.total.toLocaleString('en-IN'), sub: `+${d.users.new_week} this week`, icon: 'user' as IconName, tab: 'users' },
    { label: 'Live now', value: String(d.bookings.live), sub: `${d.bookings.scheduled} booked ahead`, icon: 'play' as IconName, tab: 'bookings' },
    { label: 'Paid for bookings', value: formatRupees(Math.round(Number(d.money.paid))), sub: 'after refunds', icon: 'calendar' as IconName, tab: 'bookings' },
    { label: 'In wallets', value: formatRupees(Math.round(Number(d.money.balances))), sub: `${formatRupees(Math.round(Number(d.money.loaded)))} loaded in total`, icon: 'wallet' as IconName, tab: 'users' },
  ]
})

/** Daily booking money, last 14 days. One series, so no legend: the heading names it. */
const chart = computed(() => {
  const days = data.value?.daily ?? []
  const max = Math.max(1, ...days.map((d) => Number(d.amount)))
  // A round axis top: 1, 2 or 5 × a power of ten.
  const pow = 10 ** Math.floor(Math.log10(max))
  const top = [1, 2, 5, 10].map((m) => m * pow).find((v) => v >= max) ?? max
  return { days, top, total: days.reduce((s, d) => s + Number(d.amount), 0) }
})
const dayLabel = (iso: string) => new Date(`${iso}T00:00`).toLocaleDateString('en-GB', { day: 'numeric', month: 'short' })

onMounted(load)
</script>

<template>
  <div>
    <AlertBox v-if="error" class="mb-4">{{ error }}</AlertBox>
    <div v-if="!data && !error" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4"><div v-for="i in 8" :key="i" class="skeleton h-28" /></div>

    <template v-else-if="data">
      <!-- Waiting for you -->
      <section aria-labelledby="todo-h" class="mb-6">
        <h2 id="todo-h" class="sr-only">Waiting for you</h2>
        <div class="grid gap-3 sm:grid-cols-2">
          <button
            v-for="t in todo"
            :key="t.tab"
            type="button"
            class="card-interactive flex items-center gap-4 p-5 text-left"
            :class="t.count ? 'border-coral-700/30' : ''"
            @click="emit('go', t.tab)"
          >
            <span class="grid h-12 w-12 shrink-0 place-items-center rounded-xl" :class="t.count ? 'bg-coral-700 text-white' : 'bg-success/10 text-success'">
              <AppIcon :name="t.count ? t.icon : 'check'" />
            </span>
            <span class="min-w-0 flex-1">
              <span class="block text-2xl font-extrabold">{{ t.count }}</span>
              <span class="block text-sm text-muted">{{ t.count ? t.label : `No ${t.label}` }}</span>
            </span>
            <AppIcon name="arrow" :size="18" class="text-subtle" />
          </button>
        </div>
      </section>

      <!-- Numbers -->
      <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <button v-for="s in stats" :key="s.label" type="button" class="card-interactive p-5 text-left" @click="emit('go', s.tab)">
          <span class="flex items-center gap-2 text-sm text-subtle"><AppIcon :name="s.icon" :size="16" /> {{ s.label }}</span>
          <span class="mt-1 block text-2xl font-extrabold [font-variant-numeric:tabular-nums]">{{ s.value }}</span>
          <span class="block text-xs text-subtle">{{ s.sub }}</span>
        </button>
      </div>

      <div class="mt-6 grid grid-cols-[minmax(0,1fr)] gap-6 lg:grid-cols-[minmax(0,3fr)_minmax(0,2fr)]">
        <!-- Chart -->
        <section class="card p-5 sm:p-6" aria-labelledby="chart-h">
          <div class="flex flex-wrap items-baseline justify-between gap-2">
            <h2 id="chart-h" class="h-section">Booking money, last 14 days</h2>
            <span class="text-sm text-subtle">{{ formatRupees(Math.round(chart.total)) }} total</span>
          </div>
          <div class="relative mt-5 h-48">
            <!-- Recessive axis: the top value and a baseline. -->
            <span class="absolute top-0 left-0 text-[11px] text-faint">{{ formatRupees(chart.top) }}</span>
            <div class="absolute inset-x-0 top-2 border-t border-dashed border-divider" aria-hidden="true" />
            <div class="absolute inset-x-0 bottom-6 border-t border-line" aria-hidden="true" />
            <div class="absolute inset-x-0 top-2 bottom-6 flex items-end gap-[2px]" aria-hidden="true">
              <div
                v-for="(d, i) in chart.days"
                :key="d.date"
                class="relative flex h-full flex-1 items-end"
                @mouseenter="hover = i"
                @mouseleave="hover = null"
              >
                <div
                  class="w-full rounded-t-[4px] transition-colors"
                  :class="hover === i ? 'bg-plum-900' : 'bg-plum-700'"
                  :style="{ height: `${(Number(d.amount) / chart.top) * 100}%`, minHeight: Number(d.amount) > 0 ? '2px' : '0' }"
                />
                <div
                  v-if="hover === i"
                  class="pointer-events-none absolute bottom-full z-10 mb-1 -translate-x-1/2 rounded-lg bg-ink px-2.5 py-1.5 text-xs whitespace-nowrap text-white shadow-modal"
                  :class="i < 2 ? 'left-0 translate-x-0' : i > chart.days.length - 3 ? 'right-0 left-auto translate-x-0' : 'left-1/2'"
                >
                  <span class="block text-white/70">{{ dayLabel(d.date) }}</span>
                  <span class="font-bold">{{ formatRupees(Number(d.amount)) }}</span>
                </div>
              </div>
            </div>
            <div class="absolute inset-x-0 bottom-0 flex justify-between text-[11px] text-subtle" aria-hidden="true">
              <span>{{ chart.days[0] ? dayLabel(chart.days[0].date) : '' }}</span>
              <span>Today</span>
            </div>
          </div>
          <!-- Tables don't shrink to sr-only's 1px, so the wrapper hides it. -->
          <div class="sr-only">
            <table>
              <caption>Booking money per day, after refunds</caption>
              <thead><tr><th scope="col">Day</th><th scope="col">Amount</th></tr></thead>
              <tbody><tr v-for="d in chart.days" :key="d.date"><td>{{ dayLabel(d.date) }}</td><td>{{ formatRupees(Number(d.amount)) }}</td></tr></tbody>
            </table>
          </div>
        </section>

        <!-- Platform at a glance -->
        <section class="card p-5 sm:p-6" aria-labelledby="glance-h">
          <h2 id="glance-h" class="h-section">At a glance</h2>
          <dl class="mt-4 divide-y divide-divider text-sm">
            <div class="flex justify-between gap-3 py-2.5"><dt class="text-muted">Verified users</dt><dd class="font-bold">{{ data.documents.approved }}</dd></div>
            <div class="flex justify-between gap-3 py-2.5"><dt class="text-muted">Approved ads</dt><dd class="font-bold">{{ data.ads.approved }}</dd></div>
            <div class="flex justify-between gap-3 py-2.5"><dt class="text-muted">Active screens</dt><dd class="font-bold">{{ data.screens.active }} <span class="font-normal text-subtle">of {{ data.screens.active + data.screens.draft + data.screens.paused }}</span></dd></div>
            <div class="flex justify-between gap-3 py-2.5"><dt class="text-muted">Completed bookings</dt><dd class="font-bold">{{ data.bookings.completed }}</dd></div>
            <div class="flex justify-between gap-3 py-2.5"><dt class="text-muted">Suspended users</dt><dd class="font-bold">{{ data.users.suspended }}</dd></div>
          </dl>
        </section>
      </div>

      <div class="mt-6 grid grid-cols-[minmax(0,1fr)] gap-6 lg:grid-cols-2">
        <section class="card p-5 sm:p-6" aria-labelledby="rb-h">
          <div class="flex items-center justify-between gap-3">
            <h2 id="rb-h" class="h-section">Latest bookings</h2>
            <button type="button" class="link text-sm" @click="emit('go', 'bookings')">See all</button>
          </div>
          <p v-if="!data.recent_bookings.length" class="mt-4 text-sm text-muted">No bookings yet.</p>
          <ul v-else class="mt-3 divide-y divide-divider">
            <li v-for="b in data.recent_bookings" :key="b.id" class="flex items-center justify-between gap-3 py-3">
              <div class="min-w-0">
                <p class="truncate font-bold">{{ b.screen?.name }}</p>
                <p class="truncate text-sm text-muted">{{ b.organisation?.name }} · {{ formatRupees(b.price) }}</p>
                <p class="text-xs text-subtle">{{ formatRange(b.start_date, b.end_date) }} · {{ formatWindow(b.start_time, b.end_time) }}</p>
              </div>
              <StatusBadge :status="b.status" />
            </li>
          </ul>
        </section>

        <section class="card p-5 sm:p-6" aria-labelledby="ru-h">
          <div class="flex items-center justify-between gap-3">
            <h2 id="ru-h" class="h-section">New users</h2>
            <button type="button" class="link text-sm" @click="emit('go', 'users')">See all</button>
          </div>
          <ul class="mt-3 divide-y divide-divider">
            <li v-for="u in data.recent_users" :key="u.id" class="flex items-center justify-between gap-3 py-3">
              <div class="min-w-0">
                <p class="truncate font-bold">{{ u.name }}</p>
                <p class="text-xs text-subtle">{{ u.phone ?? u.email }} · joined {{ formatDateTime(u.created_at) }}</p>
              </div>
              <StatusBadge
                :status="u.document_status === 'none' ? 'draft' : u.document_status"
                :label="u.document_status === 'approved' ? 'Verified' : u.document_status === 'none' ? 'No document' : undefined"
              />
            </li>
          </ul>
        </section>
      </div>
    </template>
  </div>
</template>
