<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import AlertBox from '@/components/ui/AlertBox.vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import { formatRange, formatRupees, formatWindow } from '@/lib/format'
import { errorMessage, get, post } from '@/lib/http'
import { duration } from '@/lib/schedule'
import type { Booking } from '@/lib/types'
import { useAuthStore } from '@/stores/auth'
import { useOrgStore } from '@/stores/orgs'
import { useWalletStore } from '@/stores/wallet'

const auth = useAuthStore()
const orgs = useOrgStore()
const wallets = useWalletStore()

const orgId = ref<string | null>(null)
const bookings = ref<Booking[]>([])
const loading = ref(true)
const error = ref<string | null>(null)
const tab = ref<'active' | 'past'>('active')
const cancelling = ref<Booking | null>(null)
const busy = ref(false)
// Coming straight from booking: say what happens next.
const notice = ref<string | null>(
  useRoute().query.sent ? 'Sent to the screen owner. Your ad plays at its booked times once they approve it; if they don’t, you get every rupee back.' : null,
)

const ACTIVE = ['awaiting_owner', 'scheduled', 'live']
const active = computed(() => bookings.value.filter((b) => ACTIVE.includes(b.status)))
const past = computed(() => bookings.value.filter((b) => !ACTIVE.includes(b.status)))
const shown = computed(() => (tab.value === 'active' ? active.value : past.value))
const pct = (b: Booking) => Math.round((b.plays_done / Math.max(1, b.plays_total)) * 100)

async function load() {
  error.value = null
  try {
    const org = await orgs.ensureAdvertiser(auth.user?.name ?? 'My business')
    orgId.value = org.id
    bookings.value = (await get<Booking[]>(`/orgs/${org.id}/bookings`)).data
  } catch (err) {
    error.value = errorMessage(err)
  } finally {
    loading.value = false
  }
}

async function confirmCancel() {
  if (!cancelling.value || !orgId.value) return
  busy.value = true
  notice.value = null
  try {
    const refund = cancelling.value.refundable
    await post<Booking>(`/orgs/${orgId.value}/bookings/${cancelling.value.id}/cancel`)
    notice.value = `Booking cancelled. ${formatRupees(refund)} refunded to your wallet.`
    await Promise.all([load(), wallets.load()])
  } catch (err) {
    error.value = errorMessage(err)
  } finally {
    cancelling.value = null
    busy.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="shell py-8 sm:py-10">
    <PageHeader title="Bookings" lede="Where and when your ads play.">
      <template #actions>
        <RouterLink to="/screens" class="btn-primary"><AppIcon name="plus" :size="18" /> Book a screen</RouterLink>
      </template>
    </PageHeader>

    <div class="mb-5 flex gap-2" role="tablist" aria-label="Bookings">
      <button
        v-for="t in (['active', 'past'] as const)"
        :key="t"
        type="button"
        role="tab"
        :aria-selected="tab === t"
        class="rounded-full border px-4 py-2 text-sm font-bold transition"
        :class="tab === t ? 'border-plum-700 bg-plum-100 text-plum-900' : 'border-line-strong bg-white text-ink-soft hover:border-plum-300'"
        @click="tab = t"
      >
        {{ t === 'active' ? 'Upcoming and live' : 'Past' }}
        <span class="ml-1 text-subtle">{{ t === 'active' ? active.length : past.length }}</span>
      </button>
    </div>

    <AlertBox v-if="notice" tone="success" class="mb-4">{{ notice }}</AlertBox>
    <AlertBox v-if="error" class="mb-4">{{ error }}</AlertBox>

    <div v-if="loading" class="space-y-3"><div v-for="i in 3" :key="i" class="skeleton h-28" /></div>
    <EmptyState
      v-else-if="!shown.length"
      icon="calendar"
      :title="tab === 'active' ? 'No bookings yet' : 'Nothing here yet'"
      :body="tab === 'active' ? 'Find a screen on the map and book the times you want.' : 'Finished and cancelled bookings show up here.'"
    >
      <RouterLink v-if="tab === 'active'" to="/screens" class="btn-primary">Find screens</RouterLink>
    </EmptyState>

    <ul v-else class="space-y-3">
      <li v-for="b in shown" :key="b.id" class="card p-5">
        <div class="flex items-start justify-between gap-3">
          <div class="min-w-0">
            <RouterLink v-if="b.screen" :to="`/screens/${b.screen.id}`" class="block truncate text-lg font-extrabold hover:text-plum-800">{{ b.screen.name }}</RouterLink>
            <p class="mt-0.5 text-sm text-muted">
              {{ formatRange(b.start_date, b.end_date) }} · {{ formatWindow(b.start_time, b.end_time) }}
              <span v-if="b.creative" class="text-subtle"> · {{ b.creative.name }}</span>
            </p>
          </div>
          <StatusBadge :status="b.status" />
        </div>

        <!-- When it runs -->
        <div v-if="b.schedule" class="mt-4 rounded-xl border border-plum-100 bg-plum-50/50 p-3.5 text-sm text-body">
          <p>
            Plays <strong class="text-ink">{{ b.schedule.repeats === 1 ? 'once' : b.schedule.repeats === 2 ? 'twice' : `${b.schedule.repeats} times` }}</strong>
            in every {{ duration(b.schedule.loop_seconds) }} loop, {{ b.schedule.length_s }}&nbsp;s each time · {{ b.schedule.plays_per_day }} plays a day.
          </p>
          <p class="mt-1.5 flex flex-wrap items-center gap-1 text-xs text-subtle [font-variant-numeric:tabular-nums]">
            <span>Loops at</span>
            <span v-for="t in b.schedule.first_loops.slice(0, 3)" :key="t" class="rounded bg-white px-1.5 py-0.5 font-semibold text-ink shadow-sm">{{ t }}</span>
            <template v-if="b.schedule.last_loop && b.schedule.first_loops.length > 3">
              <span>…</span>
              <span class="rounded bg-white px-1.5 py-0.5 font-semibold text-ink shadow-sm">{{ b.schedule.last_loop }}</span>
            </template>
          </p>
          <p class="mt-1.5 text-xs text-subtle">The screen lines ads up with no gaps, so the exact second in each loop is set by the screen.</p>
        </div>

        <div class="mt-4 flex items-center gap-3">
          <div class="h-2 flex-1 overflow-hidden rounded-full bg-divider" role="progressbar" :aria-valuenow="pct(b)" aria-valuemin="0" aria-valuemax="100" :aria-label="`${pct(b)}% played`">
            <div class="h-full rounded-full bg-plum-700 transition-all" :style="{ width: `${pct(b)}%` }" />
          </div>
          <span class="shrink-0 text-xs font-semibold text-subtle">{{ b.plays_done.toLocaleString('en-IN') }} / {{ b.plays_total.toLocaleString('en-IN') }} plays</span>
        </div>

        <div class="mt-4 flex items-center justify-between gap-4 border-t border-divider pt-3">
          <p class="text-sm"><span class="text-subtle">Paid </span><span class="price">{{ formatRupees(b.price) }}</span></p>
          <button
            v-if="ACTIVE.includes(b.status)"
            type="button"
            class="btn-ghost btn-sm text-danger hover:bg-danger/8 hover:text-danger"
            @click="cancelling = b"
          >
            Cancel
          </button>
        </div>
      </li>
    </ul>

    <ConfirmDialog
      :open="cancelling !== null"
      title="Cancel this booking?"
      confirm-label="Cancel booking"
      danger
      :busy="busy"
      @confirm="confirmCancel"
      @cancel="cancelling = null"
    >
      <template v-if="cancelling">
        Your ad stops on <strong class="text-ink">{{ cancelling.screen?.name }}</strong> and the slot goes to someone else.
        You get <strong class="text-ink">{{ formatRupees(cancelling.refundable) }}</strong> back for the plays that haven’t happened.
      </template>
    </ConfirmDialog>
  </div>
</template>
