<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import AlertBox from '@/components/ui/AlertBox.vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import { formatWindow, formatRange, formatRupees } from '@/lib/format'
import { errorMessage, get, mfaNeeded, post } from '@/lib/http'
import type { Booking } from '@/lib/types'

/** Every booking on every screen; the team can stop one (the advertiser is refunded). */
const router = useRouter()

type Filter = 'active' | 'live' | 'completed' | 'cancelled' | ''
const FILTERS: { value: Filter; label: string }[] = [
  { value: 'active', label: 'Upcoming and live' },
  { value: 'live', label: 'Live now' },
  { value: 'completed', label: 'Completed' },
  { value: 'cancelled', label: 'Cancelled' },
  { value: '', label: 'All' },
]

const bookings = ref<Booking[]>([])
const filter = ref<Filter>('active')
const search = ref('')
const page = ref(1)
const lastPage = ref(1)
const total = ref(0)
const loading = ref(true)
const error = ref<string | null>(null)
const notice = ref<string | null>(null)
const cancelling = ref<Booking | null>(null)
const busy = ref(false)

function needsMfa(err: unknown): boolean {
  if (!mfaNeeded(err)) return false
  router.push({ name: 'security', query: { next: '/admin' } })
  return true
}

async function load(p = 1) {
  if (p === 1) loading.value = true
  error.value = null
  try {
    const res = await get<Booking[]>('/admin/bookings', { status: filter.value || undefined, search: search.value.trim() || undefined, page: p })
    bookings.value = p === 1 ? res.data : [...bookings.value, ...res.data]
    page.value = p
    lastPage.value = Number(res.meta?.last_page ?? 1)
    total.value = Number(res.meta?.total ?? res.data.length)
  } catch (err) {
    if (!needsMfa(err)) error.value = errorMessage(err)
  } finally {
    loading.value = false
  }
}

async function confirmCancel() {
  if (!cancelling.value) return
  busy.value = true
  notice.value = null
  try {
    const refund = cancelling.value.refundable
    await post(`/admin/bookings/${cancelling.value.id}/cancel`)
    notice.value = `Booking cancelled. ${cancelling.value.organisation?.name} was refunded ${formatRupees(refund)}.`
    await load(1)
  } catch (err) {
    if (!needsMfa(err)) error.value = errorMessage(err)
  } finally {
    busy.value = false
    cancelling.value = null
  }
}

let timer: ReturnType<typeof setTimeout> | undefined
watch(search, () => {
  clearTimeout(timer)
  timer = setTimeout(() => load(1), 300)
})
watch(filter, () => load(1))
onMounted(() => load(1))
</script>

<template>
  <div>
    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center">
      <label class="relative block sm:w-72">
        <span class="sr-only">Search bookings</span>
        <AppIcon name="search" :size="18" class="absolute top-1/2 left-3 -translate-y-1/2 text-subtle" />
        <input v-model="search" type="search" class="input pl-10" placeholder="Screen or advertiser" />
      </label>
      <div class="flex flex-wrap gap-2" role="tablist" aria-label="Filter bookings">
        <button
          v-for="f in FILTERS"
          :key="f.value"
          type="button"
          role="tab"
          :aria-selected="filter === f.value"
          class="rounded-full border px-3.5 py-1.5 text-sm font-bold transition"
          :class="filter === f.value ? 'border-plum-700 bg-plum-100 text-plum-900' : 'border-line-strong bg-white text-ink-soft hover:border-plum-300'"
          @click="filter = f.value"
        >
          {{ f.label }}
        </button>
      </div>
    </div>

    <AlertBox v-if="notice" tone="success" class="mb-4">{{ notice }}</AlertBox>
    <AlertBox v-if="error" class="mb-4">{{ error }}</AlertBox>

    <div v-if="loading" class="space-y-2"><div v-for="i in 5" :key="i" class="skeleton h-16" /></div>
    <EmptyState v-else-if="!bookings.length" icon="calendar" title="No bookings here" />

    <template v-else>
      <p class="mb-2 text-sm text-subtle">{{ total.toLocaleString('en-IN') }} {{ total === 1 ? 'booking' : 'bookings' }}</p>
      <div class="card overflow-hidden">
        <ul class="divide-y divide-divider">
          <li v-for="b in bookings" :key="b.id" class="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-x-4 gap-y-2 p-4 md:grid-cols-[minmax(0,2fr)_minmax(0,1fr)_minmax(0,1fr)_6.5rem]">
            <div class="min-w-0">
              <p class="truncate font-bold">{{ b.screen?.name }}</p>
              <p class="truncate text-sm text-subtle">{{ b.organisation?.name }} · {{ b.creative?.name ?? '–' }}</p>
              <p class="text-xs text-subtle">{{ formatRange(b.start_date, b.end_date) }} · {{ formatWindow(b.start_time, b.end_time) }}</p>
            </div>
            <div class="row-start-2 md:row-start-auto"><StatusBadge :status="b.status" /></div>
            <div class="row-start-2 text-sm md:row-start-auto">
              <span class="price">{{ formatRupees(b.price) }}</span>
              <span class="block text-xs text-subtle">{{ b.plays_done.toLocaleString('en-IN') }} / {{ b.plays_total.toLocaleString('en-IN') }} plays</span>
            </div>
            <div class="row-span-2 text-right md:row-span-1">
              <button
                v-if="b.status === 'scheduled' || b.status === 'live'"
                type="button"
                class="btn-ghost btn-sm text-danger hover:bg-danger/8 hover:text-danger"
                @click="cancelling = b"
              >
                Cancel
              </button>
            </div>
          </li>
        </ul>
      </div>
      <div v-if="page < lastPage" class="mt-4 text-center">
        <button type="button" class="btn-outline btn-sm" @click="load(page + 1)">Show more</button>
      </div>
    </template>

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
        The ad stops on <strong class="text-ink">{{ cancelling.screen?.name }}</strong> and
        <strong class="text-ink">{{ cancelling.organisation?.name }}</strong> gets
        <strong class="text-ink">{{ formatRupees(cancelling.refundable) }}</strong> back for the plays that haven’t happened.
      </template>
    </ConfirmDialog>
  </div>
</template>
