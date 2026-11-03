<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import AlertBox from '@/components/ui/AlertBox.vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import { daysInRange, formatHours, formatRange, formatRupees } from '@/lib/format'
import { errorMessage, get, post } from '@/lib/http'
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
const notice = ref<string | null>(null)

const ACTIVE = ['checking', 'scheduled', 'live', 'draft', 'approved', 'awaiting_owner']
const CANCELLABLE = ['draft', 'checking', 'awaiting_owner', 'approved', 'scheduled', 'live']
const active = computed(() => bookings.value.filter((b) => ACTIVE.includes(b.status)))
const past = computed(() => bookings.value.filter((b) => !ACTIVE.includes(b.status)))
const shown = computed(() => (tab.value === 'active' ? active.value : past.value))

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
    const res = await post<Booking>(`/orgs/${orgId.value}/bookings/${cancelling.value.id}/cancel`)
    notice.value = `${res.message} ${formatRupees(cancelling.value.held)} returned.`
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
    <PageHeader eyebrow="Upload" title="Bookings" lede="Where and when your ads play, how many plays have happened, and what they cost.">
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
        {{ t === 'active' ? 'Active' : 'Past' }}
        <span class="ml-1 text-subtle">{{ t === 'active' ? active.length : past.length }}</span>
      </button>
    </div>

    <AlertBox v-if="notice" tone="success" class="mb-4">{{ notice }}</AlertBox>
    <AlertBox v-if="error" class="mb-4">{{ error }}</AlertBox>

    <div v-if="loading" class="space-y-3"><div v-for="i in 3" :key="i" class="skeleton h-36" /></div>
    <EmptyState
      v-else-if="!shown.length"
      icon="calendar"
      :title="tab === 'active' ? 'No active bookings' : 'Nothing here yet'"
      :body="tab === 'active' ? 'Find a screen on the map and book the hours you want.' : 'Finished, cancelled and refused bookings show up here.'"
    >
      <RouterLink v-if="tab === 'active'" to="/screens" class="btn-primary">Find screens</RouterLink>
    </EmptyState>

    <ul v-else class="space-y-4">
      <li v-for="b in shown" :key="b.id" class="card p-5 sm:p-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div class="min-w-0">
            <RouterLink v-if="b.screen" :to="`/screens/${b.screen.id}`" class="text-lg font-extrabold hover:text-plum-800">{{ b.screen.name }}</RouterLink>
            <p v-if="b.screen?.address" class="flex items-center gap-1 text-sm text-subtle"><AppIcon name="pin" :size="14" /> {{ b.screen.address }}</p>
          </div>
          <StatusBadge :status="b.status" />
        </div>

        <dl class="mt-4 grid grid-cols-2 gap-x-4 gap-y-3 text-sm sm:grid-cols-4">
          <div>
            <dt class="text-subtle">Dates</dt>
            <dd class="font-bold">{{ formatRange(b.start_date, b.end_date) }}</dd>
            <dd class="text-xs text-subtle">{{ daysInRange(b.start_date, b.end_date) }} days</dd>
          </div>
          <div>
            <dt class="text-subtle">Hours each day</dt>
            <dd class="font-bold">{{ formatHours(b.start_hour, b.end_hour) }}</dd>
          </div>
          <div>
            <dt class="text-subtle">Ad</dt>
            <dd class="flex items-center gap-1 truncate font-bold"><AppIcon :name="b.creative?.kind === 'video' ? 'film' : 'image'" :size="14" /> {{ b.creative?.name ?? '–' }}</dd>
          </div>
          <div>
            <dt class="text-subtle">Price per play</dt>
            <dd class="font-bold">{{ formatRupees(b.price_per_play) }}</dd>
          </div>
        </dl>

        <div class="mt-5">
          <div class="flex items-center justify-between text-sm">
            <span class="font-bold">{{ b.plays_done.toLocaleString('en-IN') }} of {{ b.plays_total.toLocaleString('en-IN') }} plays</span>
            <span class="text-subtle">{{ Math.round((b.plays_done / Math.max(1, b.plays_total)) * 100) }}%</span>
          </div>
          <div class="mt-1.5 h-2.5 overflow-hidden rounded-full bg-divider">
            <div class="h-full rounded-full bg-gradient-to-r from-plum-700 to-coral transition-all" :style="{ width: `${(b.plays_done / Math.max(1, b.plays_total)) * 100}%` }" />
          </div>
        </div>

        <div class="mt-5 flex flex-wrap items-center justify-between gap-4 border-t border-divider pt-4">
          <dl class="flex flex-wrap gap-x-6 gap-y-1 text-sm">
            <div><dt class="inline text-subtle">Spent </dt><dd class="price inline">{{ formatRupees(b.spent) }}</dd></div>
            <div v-if="Number(b.held) > 0"><dt class="inline text-subtle">Held </dt><dd class="price inline">{{ formatRupees(b.held) }}</dd></div>
            <div><dt class="inline text-subtle">Booked </dt><dd class="price inline">{{ formatRupees(b.price) }}</dd></div>
          </dl>
          <button
            v-if="CANCELLABLE.includes(b.status)"
            type="button"
            class="btn-ghost btn-sm text-danger hover:bg-danger/8 hover:text-danger"
            @click="cancelling = b"
          >
            Cancel booking
          </button>
        </div>
        <p v-if="b.status === 'checking'" class="mt-3 flex items-center gap-1.5 text-sm text-warning">
          <AppIcon name="clock" :size="15" /> Starts playing once our team approves the ad. If it is refused, the money comes back.
        </p>
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
        The rest of <strong class="text-ink">{{ cancelling.screen?.name }}</strong> stops, and
        <strong class="text-ink">{{ formatRupees(cancelling.held) }}</strong> goes back to your wallet. Plays that already
        happened stay paid.
      </template>
    </ConfirmDialog>
  </div>
</template>
