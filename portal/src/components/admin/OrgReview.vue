<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import AlertBox from '@/components/ui/AlertBox.vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import { formatDate } from '@/lib/format'
import { errorMessage, get, mfaNeeded, post } from '@/lib/http'
import type { Organisation } from '@/lib/types'

type Filter = 'pending' | 'verified' | 'all'

const router = useRouter()
const filter = ref<Filter>('pending')
const page = ref(1)
const lastPage = ref(1)
const total = ref(0)
const orgs = ref<Organisation[]>([])
const loading = ref(true)
const error = ref<string | null>(null)
const notice = ref<string | null>(null)
const working = ref<string | null>(null)
const suspending = ref<Organisation | null>(null)

const FILTERS: { value: Filter; label: string }[] = [
  { value: 'pending', label: 'Awaiting verification' },
  { value: 'verified', label: 'Verified' },
  { value: 'all', label: 'All' },
]

/** Admin calls need a two-factor session; get one and come back. */
function needsMfa(err: unknown): boolean {
  if (!mfaNeeded(err)) return false
  router.push({ name: 'security', query: { next: '/admin' } })
  return true
}

async function load() {
  loading.value = true
  error.value = null
  try {
    const params: Record<string, unknown> = { page: page.value }
    if (filter.value !== 'all') params.verified = filter.value === 'verified' ? 1 : 0
    const res = await get<Organisation[]>('/admin/orgs', params)
    orgs.value = res.data
    lastPage.value = Number(res.meta?.last_page ?? 1)
    total.value = Number(res.meta?.total ?? res.data.length)
  } catch (err) {
    if (!needsMfa(err)) error.value = errorMessage(err)
  } finally {
    loading.value = false
  }
}

async function act(org: Organisation, action: 'verify' | 'suspend' | 'reinstate') {
  working.value = org.id
  error.value = null
  notice.value = null
  try {
    const res = await post<Organisation>(`/admin/orgs/${org.id}/${action}`)
    notice.value = res.message
    await load()
  } catch (err) {
    if (!needsMfa(err)) error.value = errorMessage(err)
  } finally {
    working.value = null
    suspending.value = null
  }
}

watch(filter, () => {
  page.value = 1
  load()
})
watch(page, load)
onMounted(load)
</script>

<template>
  <div>

    <div class="mb-5 flex flex-wrap gap-2" role="tablist" aria-label="Filter organisations">
      <button
        v-for="f in FILTERS"
        :key="f.value"
        type="button"
        role="tab"
        :aria-selected="filter === f.value"
        class="rounded-full border px-4 py-2 text-sm font-bold whitespace-nowrap transition"
        :class="filter === f.value ? 'border-plum-700 bg-plum-100 text-plum-900' : 'border-line-strong bg-white text-ink-soft hover:border-plum-300'"
        @click="filter = f.value"
      >
        {{ f.label }}
      </button>
    </div>

    <AlertBox v-if="notice" tone="success" class="mb-4">{{ notice }}</AlertBox>
    <AlertBox v-if="error" class="mb-4">
      {{ error }}
      <template #action><button type="button" class="link shrink-0 text-sm" @click="load">Try again</button></template>
    </AlertBox>

    <div v-if="loading && !orgs.length" class="card divide-y divide-divider" aria-busy="true" aria-label="Loading">
      <div v-for="i in 4" :key="i" class="flex items-center gap-4 p-5">
        <div class="skeleton h-10 w-10" />
        <div class="flex-1"><div class="skeleton h-4 w-1/3" /><div class="skeleton mt-2 h-3 w-1/4" /></div>
      </div>
    </div>

    <EmptyState
      v-else-if="!orgs.length && !error"
      icon="check"
      :title="filter === 'pending' ? 'Nothing waiting' : 'No organisations'"
      :body="filter === 'pending' ? 'Every organisation has been checked.' : undefined"
    />

    <section v-else-if="orgs.length" class="card overflow-hidden" :class="{ 'opacity-60': loading }">
      <ul class="divide-y divide-divider">
        <li v-for="o in orgs" :key="o.id" class="flex flex-wrap items-center gap-x-4 gap-y-3 p-5">
          <span
            class="grid h-10 w-10 shrink-0 place-items-center rounded-xl"
            :class="o.type === 'owner' ? 'bg-teal-50 text-teal-dark' : 'bg-plum-100 text-plum-700'"
          >
            <AppIcon :name="o.type === 'owner' ? 'screen' : 'megaphone'" />
          </span>
          <div class="min-w-0 flex-1 basis-48">
            <p class="font-extrabold">{{ o.name }}</p>
            <p class="text-sm text-subtle">
              {{ o.type === 'owner' ? 'Screen owner' : 'Advertiser' }}
              <template v-if="o.created_at"> · joined {{ formatDate(o.created_at.slice(0, 10)) }}</template>
            </p>
          </div>
          <div class="flex flex-wrap items-center gap-2">
            <StatusBadge v-if="o.status === 'suspended'" status="suspended" />
            <StatusBadge v-else-if="o.verified" status="approved" label="Verified" />
            <StatusBadge v-else status="awaiting_owner" label="Unverified" />
          </div>
          <div class="flex w-full flex-wrap gap-2 sm:w-auto">
            <button
              v-if="!o.verified"
              type="button"
              class="btn-plum btn-sm"
              :disabled="working === o.id"
              @click="act(o, 'verify')"
            >
              <AppIcon name="check" :size="15" /> Verify
            </button>
            <button
              v-if="o.status === 'suspended'"
              type="button"
              class="btn-outline btn-sm"
              :disabled="working === o.id"
              @click="act(o, 'reinstate')"
            >
              Reinstate
            </button>
            <button
              v-else
              type="button"
              class="btn-ghost btn-sm text-danger hover:bg-danger/8 hover:text-danger"
              :disabled="working === o.id"
              @click="suspending = o"
            >
              Suspend
            </button>
          </div>
        </li>
      </ul>

      <nav
        v-if="lastPage > 1"
        class="flex items-center justify-between border-t border-divider px-5 py-3 text-sm"
        aria-label="Pages"
      >
        <span class="text-subtle">{{ total }} organisations · page {{ page }} of {{ lastPage }}</span>
        <span class="flex gap-2">
          <button type="button" class="btn-outline btn-sm" :disabled="page <= 1 || loading" @click="page--">Previous</button>
          <button type="button" class="btn-outline btn-sm" :disabled="page >= lastPage || loading" @click="page++">Next</button>
        </span>
      </nav>
    </section>

    <ConfirmDialog
      :open="suspending !== null"
      title="Suspend this organisation?"
      confirm-label="Suspend"
      danger
      :busy="working !== null"
      @confirm="suspending && act(suspending, 'suspend')"
      @cancel="suspending = null"
    >
      <strong class="text-ink">{{ suspending?.name }}</strong> and its members are locked out of it until you reinstate
      it.
    </ConfirmDialog>
  </div>
</template>
