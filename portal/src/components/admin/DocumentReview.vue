<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import AlertBox from '@/components/ui/AlertBox.vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import { formatDateTime } from '@/lib/format'
import { errorMessage, get, mfaNeeded, post } from '@/lib/http'
import type { IdentityDocument } from '@/lib/types'

/** Identity documents: open the file (short-lived link), then approve or refuse. The file is deleted either way. */
const emit = defineEmits<{ count: [n: number] }>()
const router = useRouter()

const filter = ref<'pending' | 'approved' | 'rejected'>('pending')
const docs = ref<IdentityDocument[]>([])
const loading = ref(true)
const error = ref<string | null>(null)
const notice = ref<string | null>(null)
const working = ref<string | null>(null)
const notes = ref<Record<string, string>>({})

function needsMfa(err: unknown): boolean {
  if (!mfaNeeded(err)) return false
  router.push({ name: 'security', query: { next: '/admin' } })
  return true
}

async function load() {
  loading.value = true
  error.value = null
  try {
    const res = await get<IdentityDocument[]>('/admin/documents', { status: filter.value })
    docs.value = res.data
    if (filter.value === 'pending') emit('count', Number(res.meta?.total ?? res.data.length))
  } catch (err) {
    if (!needsMfa(err)) error.value = errorMessage(err)
  } finally {
    loading.value = false
  }
}

async function decide(doc: IdentityDocument, approve: boolean) {
  working.value = doc.id
  error.value = null
  notice.value = null
  try {
    const note = notes.value[doc.id]?.trim() || null
    notice.value = (await post(`/admin/documents/${doc.id}/${approve ? 'approve' : 'reject'}`, { note })).message
    await load()
  } catch (err) {
    if (!needsMfa(err)) error.value = errorMessage(err)
  } finally {
    working.value = null
  }
}

watch(filter, load)
onMounted(load)
</script>

<template>
  <div>
    <div class="mb-5 flex flex-wrap gap-2" role="tablist" aria-label="Filter documents">
      <button
        v-for="f in (['pending', 'approved', 'rejected'] as const)"
        :key="f"
        type="button"
        role="tab"
        :aria-selected="filter === f"
        class="rounded-full border px-4 py-2 text-sm font-bold capitalize transition"
        :class="filter === f ? 'border-plum-700 bg-plum-100 text-plum-900' : 'border-line-strong bg-white text-ink-soft hover:border-plum-300'"
        @click="filter = f"
      >
        {{ f === 'pending' ? 'Waiting' : f === 'approved' ? 'Verified' : 'Refused' }}
      </button>
    </div>

    <AlertBox v-if="notice" tone="success" class="mb-4">{{ notice }}</AlertBox>
    <AlertBox v-if="error" class="mb-4">{{ error }}</AlertBox>

    <div v-if="loading" class="space-y-3"><div v-for="i in 3" :key="i" class="skeleton h-28" /></div>
    <EmptyState v-else-if="!docs.length" icon="idcard" :title="filter === 'pending' ? 'All caught up' : 'Nothing here'" :body="filter === 'pending' ? 'No documents are waiting.' : undefined" />

    <ul v-else class="space-y-4">
      <li v-for="d in docs" :key="d.id" class="card p-5">
        <div class="flex flex-wrap items-start gap-4">
          <span class="grid h-12 w-12 shrink-0 place-items-center rounded-xl bg-plum-100 text-plum-700"><AppIcon name="idcard" /></span>
          <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-2">
              <p class="font-extrabold">{{ d.user?.name }}</p>
              <StatusBadge :status="d.status" :label="d.status === 'approved' ? 'Verified' : d.status === 'rejected' ? 'Refused' : undefined" />
            </div>
            <p class="text-sm text-subtle">{{ d.user?.phone ?? d.user?.email }}</p>
            <p class="mt-1 text-sm">{{ d.type_label }} · <span class="font-mono">{{ d.number }}</span> · sent {{ formatDateTime(d.submitted_at) }}</p>
            <p v-if="d.review_note" class="mt-1 text-sm text-muted">Note: {{ d.review_note }}</p>
          </div>
          <a v-if="d.file_url" :href="d.file_url" target="_blank" rel="noopener" class="btn-outline btn-sm"><AppIcon name="eye" :size="16" /> Open file</a>
        </div>

        <div v-if="d.status === 'pending'" class="mt-4 flex flex-col gap-2 border-t border-divider pt-4 sm:flex-row sm:items-center">
          <label class="sr-only" :for="`dnote-${d.id}`">Note</label>
          <input :id="`dnote-${d.id}`" v-model="notes[d.id]" class="input flex-1" placeholder="Note (needed to refuse), e.g. Photo is blurry" maxlength="500" />
          <div class="flex gap-2">
            <button type="button" class="btn-plum btn-sm flex-1" :disabled="working === d.id" @click="decide(d, true)"><AppIcon name="check" :size="16" /> Verify</button>
            <button type="button" class="btn-outline btn-sm flex-1 text-danger" :disabled="working === d.id || !notes[d.id]?.trim()" @click="decide(d, false)">Refuse</button>
          </div>
        </div>
      </li>
    </ul>
  </div>
</template>
