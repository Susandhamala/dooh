<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import AlertBox from '@/components/ui/AlertBox.vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import { formatDateTime, formatRupees } from '@/lib/format'
import { errorMessage, fieldErrors, get, post } from '@/lib/http'
import { GATEWAYS, type Wallet, type WalletTransaction } from '@/lib/types'
import { useWalletStore } from '@/stores/wallet'

const wallets = useWalletStore()
const txs = ref<WalletTransaction[]>([])
const page = ref(1)
const lastPage = ref(1)
const loading = ref(true)
const error = ref<string | null>(null)

const presets = [500, 1000, 2500, 5000, 10000]
const amount = ref<number | null>(1000)
const gateway = ref('esewa')
const busy = ref(false)
const topupError = ref<string | null>(null)
const done = ref<string | null>(null)

const w = computed(() => wallets.wallet)

const TYPE_STYLE: Record<WalletTransaction['type'], { label: string; cls: string; icon: 'plus' | 'lock' | 'play' | 'arrow' }> = {
  topup: { label: 'Loaded', cls: 'bg-success/10 text-success', icon: 'plus' },
  hold: { label: 'Held', cls: 'bg-warning/10 text-warning', icon: 'lock' },
  charge: { label: 'Played', cls: 'bg-plum-100 text-plum-700', icon: 'play' },
  release: { label: 'Returned', cls: 'bg-teal-50 text-teal-dark', icon: 'arrow' },
}

async function loadTxs(p = 1) {
  if (!wallets.orgId) return
  const res = await get<WalletTransaction[]>(`/orgs/${wallets.orgId}/wallet/transactions`, { page: p })
  txs.value = p === 1 ? res.data : [...txs.value, ...res.data]
  page.value = p
  lastPage.value = Number(res.meta?.last_page ?? 1)
}

async function load() {
  error.value = null
  try {
    await wallets.load()
    await loadTxs(1)
  } catch (err) {
    error.value = errorMessage(err)
  } finally {
    loading.value = false
  }
}

async function topUp() {
  if (!wallets.orgId || !amount.value) return
  busy.value = true
  topupError.value = null
  done.value = null
  try {
    const res = await post<{ wallet: Wallet }>(`/orgs/${wallets.orgId}/wallet/topup`, { amount: amount.value, gateway: gateway.value })
    wallets.set(res.data.wallet)
    done.value = `${formatRupees(amount.value)} loaded via ${GATEWAYS[gateway.value]}.`
    await loadTxs(1)
  } catch (err) {
    topupError.value = fieldErrors(err).amount ?? errorMessage(err)
  } finally {
    busy.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="shell py-8 sm:py-10">
    <PageHeader eyebrow="Payments" title="Wallet" lede="Load money first. Booking holds the cost; each play is charged as it happens; anything not played comes back." />

    <AlertBox v-if="error" class="mb-6">{{ error }}</AlertBox>

    <div class="grid grid-cols-[minmax(0,1fr)] gap-6 lg:grid-cols-[minmax(0,1fr)_400px] lg:items-start">
      <div class="min-w-0 space-y-6">
        <!-- Balance -->
        <section class="overflow-hidden rounded-[22px] bg-gradient-to-br from-plum-800 to-plum-950 p-6 text-white shadow-modal sm:p-8">
          <p class="text-sm font-semibold text-white/70">Available to spend</p>
          <p v-if="w" class="mt-1 text-4xl font-extrabold tracking-tight [font-variant-numeric:tabular-nums]">{{ formatRupees(w.available) }}</p>
          <div v-else class="mt-2 h-10 w-40 animate-pulse rounded-lg bg-white/15" />
          <dl class="mt-6 grid grid-cols-2 gap-4 border-t border-white/15 pt-5 text-sm">
            <div>
              <dt class="text-white/60">Held for booked plays</dt>
              <dd class="mt-0.5 text-lg font-bold">{{ w ? formatRupees(w.reserved) : '–' }}</dd>
            </div>
            <div>
              <dt class="text-white/60">Total balance</dt>
              <dd class="mt-0.5 text-lg font-bold">{{ w ? formatRupees(w.balance) : '–' }}</dd>
            </div>
          </dl>
        </section>

        <!-- History -->
        <section class="card overflow-hidden" aria-labelledby="tx-h">
          <h2 id="tx-h" class="h-section border-b border-divider px-5 py-4">History</h2>
          <div v-if="loading" class="space-y-3 p-5"><div v-for="i in 4" :key="i" class="skeleton h-12" /></div>
          <p v-else-if="!txs.length" class="px-5 py-10 text-center text-muted">No money movements yet. Load money to get started.</p>
          <ul v-else class="divide-y divide-divider">
            <li v-for="t in txs" :key="t.id" class="flex items-center gap-3 px-5 py-3.5">
              <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl" :class="TYPE_STYLE[t.type].cls">
                <AppIcon :name="TYPE_STYLE[t.type].icon" :size="18" />
              </span>
              <span class="min-w-0 flex-1">
                <span class="line-clamp-2 block font-semibold">{{ t.description }}</span>
                <span class="block text-xs text-subtle">{{ TYPE_STYLE[t.type].label }} · {{ formatDateTime(t.created_at) }}</span>
              </span>
              <span class="text-right">
                <span class="price block" :class="Number(t.amount) >= 0 ? 'text-success' : 'text-ink'">
                  {{ Number(t.amount) >= 0 ? '+' : '−' }}{{ formatRupees(Math.abs(Number(t.amount))) }}
                </span>
                <span class="block text-xs text-subtle">left {{ formatRupees(t.available_after) }}</span>
              </span>
            </li>
          </ul>
          <div v-if="page < lastPage" class="border-t border-divider p-3 text-center">
            <button type="button" class="btn-ghost btn-sm" @click="loadTxs(page + 1)">Show older</button>
          </div>
        </section>
      </div>

      <!-- Top up -->
      <form class="card p-5 shadow-card sm:p-6 lg:sticky lg:top-24" novalidate @submit.prevent="topUp">
        <h2 class="h-section">Load money</h2>
        <fieldset class="mt-4">
          <legend class="field-label">Amount</legend>
          <div class="grid grid-cols-3 gap-2">
            <button
              v-for="p in presets"
              :key="p"
              type="button"
              class="rounded-xl border px-2 py-2.5 text-sm font-bold transition"
              :class="amount === p ? 'border-plum-700 bg-plum-50 text-plum-900' : 'border-line-strong text-ink-soft hover:border-plum-300'"
              @click="amount = p"
            >
              {{ formatRupees(p) }}
            </button>
            <label class="col-span-3 mt-1 flex items-center rounded-xl border border-line-strong bg-white focus-within:border-plum-700">
              <span class="pl-3.5 text-sm font-bold text-subtle">रू</span>
              <input
                v-model.number="amount"
                type="number"
                inputmode="numeric"
                :min="w?.min_topup ?? 100"
                :max="w?.max_topup ?? 200000"
                class="w-full bg-transparent px-2 py-2.5 text-[15px] font-semibold outline-none"
                aria-label="Other amount"
                placeholder="Other amount"
              />
            </label>
          </div>
          <p class="field-hint">From {{ formatRupees(w?.min_topup ?? 100) }} to {{ formatRupees(w?.max_topup ?? 200000) }}.</p>
        </fieldset>

        <fieldset class="mt-4">
          <legend class="field-label">Pay with</legend>
          <div class="space-y-2">
            <label
              v-for="(label, key) in GATEWAYS"
              :key="key"
              class="flex cursor-pointer items-center gap-3 rounded-xl border p-3 transition has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-plum-700"
              :class="gateway === key ? 'border-plum-700 bg-plum-50' : 'border-line-strong hover:border-plum-300'"
            >
              <input v-model="gateway" type="radio" name="gateway" :value="key" class="h-4 w-4 accent-plum-700" />
              <span
                class="grid h-8 w-8 place-items-center rounded-lg text-xs font-extrabold text-white"
                :class="{ 'bg-esewa': key === 'esewa', 'bg-khalti': key === 'khalti', 'bg-navy': key === 'bank' }"
                aria-hidden="true"
              >{{ label.charAt(0) }}</span>
              <span class="font-bold">{{ label }}</span>
            </label>
          </div>
        </fieldset>

        <AlertBox v-if="topupError" class="mt-4">{{ topupError }}</AlertBox>
        <AlertBox v-if="done" tone="success" class="mt-4">{{ done }}</AlertBox>

        <button class="btn-primary mt-5 w-full" :disabled="busy || !amount">
          {{ busy ? 'Processing…' : `Pay ${amount ? formatRupees(amount) : ''}` }}
        </button>
        <p class="mt-3 text-center text-xs text-subtle">Test mode: no real money moves. eSewa and Khalti go live later.</p>
      </form>
    </div>
  </div>
</template>
