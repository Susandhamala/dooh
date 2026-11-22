<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import AlertBox from '@/components/ui/AlertBox.vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import { formatDate, formatRupees } from '@/lib/format'
import { errorMessage, get, mfaNeeded, post } from '@/lib/http'
import type { AdminUser } from '@/lib/types'

/** Everyone who signed up: verification, money, bookings; suspend or reinstate. */
const router = useRouter()

type Filter = '' | 'pending' | 'approved' | 'none' | 'rejected' | 'suspended'
const FILTERS: { value: Filter; label: string }[] = [
  { value: '', label: 'All' },
  { value: 'pending', label: 'Waiting for check' },
  { value: 'approved', label: 'Verified' },
  { value: 'none', label: 'No document' },
  { value: 'rejected', label: 'Refused' },
  { value: 'suspended', label: 'Suspended' },
]

const users = ref<AdminUser[]>([])
const filter = ref<Filter>('')
const search = ref('')
const page = ref(1)
const lastPage = ref(1)
const total = ref(0)
const loading = ref(true)
const error = ref<string | null>(null)
const notice = ref<string | null>(null)
const suspending = ref<AdminUser | null>(null)
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
    const res = await get<AdminUser[]>('/admin/users', { status: filter.value || undefined, search: search.value.trim() || undefined, page: p })
    users.value = p === 1 ? res.data : [...users.value, ...res.data]
    page.value = p
    lastPage.value = Number(res.meta?.last_page ?? 1)
    total.value = Number(res.meta?.total ?? res.data.length)
  } catch (err) {
    if (!needsMfa(err)) error.value = errorMessage(err)
  } finally {
    loading.value = false
  }
}

async function setSuspended(u: AdminUser, suspend: boolean) {
  busy.value = true
  notice.value = null
  error.value = null
  try {
    const res = await post<AdminUser>(`/admin/users/${u.id}/${suspend ? 'suspend' : 'reinstate'}`)
    users.value = users.value.map((x) => (x.id === u.id ? res.data : x))
    notice.value = `${u.name}: ${res.message}`
  } catch (err) {
    if (!needsMfa(err)) error.value = errorMessage(err)
  } finally {
    busy.value = false
    suspending.value = null
  }
}

const docBadge = (s: AdminUser['document_status']) =>
  s === 'approved' ? { status: 'approved', label: 'Verified' } : s === 'none' ? { status: 'draft', label: 'No document' } : s === 'rejected' ? { status: 'rejected', label: 'Refused' } : { status: 'pending', label: undefined }

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
        <span class="sr-only">Search users</span>
        <AppIcon name="search" :size="18" class="absolute top-1/2 left-3 -translate-y-1/2 text-subtle" />
        <input v-model="search" type="search" class="input pl-10" placeholder="Name or phone number" />
      </label>
      <div class="flex flex-wrap gap-2" role="tablist" aria-label="Filter users">
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
    <EmptyState v-else-if="!users.length" icon="user" title="No users found" :body="search ? 'Try another name or number.' : undefined" />

    <template v-else>
      <p class="mb-2 text-sm text-subtle">{{ total.toLocaleString('en-IN') }} {{ total === 1 ? 'user' : 'users' }}</p>
      <div class="card overflow-hidden">
        <ul class="divide-y divide-divider">
          <li v-for="u in users" :key="u.id" class="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-x-4 gap-y-2 p-4 md:grid-cols-[minmax(0,2fr)_minmax(0,1.2fr)_minmax(0,1fr)_6.5rem]">
            <div class="min-w-0">
              <p class="flex items-center gap-2 truncate font-bold">
                {{ u.name }}
                <span v-if="u.roles.includes('admin')" class="badge bg-plum-100 text-plum-800">Admin</span>
                <span v-if="u.status === 'suspended'" class="badge bg-danger/10 text-danger">Suspended</span>
              </p>
              <p class="truncate text-sm text-subtle">{{ u.phone ?? u.email ?? '–' }} · joined {{ formatDate(u.created_at.slice(0, 10)) }}</p>
              <p v-if="u.organisations.length" class="truncate text-xs text-subtle">{{ u.organisations.map((o) => o.name).join(', ') }}</p>
            </div>
            <div class="row-start-2 md:row-start-auto">
              <StatusBadge :status="docBadge(u.document_status).status" :label="docBadge(u.document_status).label" />
            </div>
            <div class="row-start-2 text-sm md:row-start-auto">
              <span class="price">{{ formatRupees(u.balance) }}</span>
              <span class="block text-xs text-subtle">{{ u.bookings }} {{ u.bookings === 1 ? 'booking' : 'bookings' }}</span>
            </div>
            <div class="row-span-2 text-right md:row-span-1">
              <button
                v-if="u.status === 'suspended'"
                type="button"
                class="btn-outline btn-sm"
                :disabled="busy"
                @click="setSuspended(u, false)"
              >
                Reinstate
              </button>
              <button
                v-else-if="!u.roles.includes('admin')"
                type="button"
                class="btn-ghost btn-sm text-danger hover:bg-danger/8 hover:text-danger"
                @click="suspending = u"
              >
                Suspend
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
      :open="suspending !== null"
      title="Suspend this user?"
      confirm-label="Suspend"
      danger
      :busy="busy"
      @confirm="suspending && setSuspended(suspending, true)"
      @cancel="suspending = null"
    >
      <template v-if="suspending">
        <strong class="text-ink">{{ suspending.name }}</strong> is signed out at once and can’t sign in again until you reinstate them.
        Their bookings keep running; cancel them in Bookings if needed.
      </template>
    </ConfirmDialog>
  </div>
</template>
