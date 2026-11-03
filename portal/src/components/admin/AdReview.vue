<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import AlertBox from '@/components/ui/AlertBox.vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import { formatDateTime } from '@/lib/format'
import { errorMessage, get, mfaNeeded, post } from '@/lib/http'
import { CATEGORIES, type Creative } from '@/lib/types'

/** The ad review queue: watch each ad, then approve it or refuse it with a reason the advertiser sees. */
const emit = defineEmits<{ count: [n: number] }>()
const router = useRouter()

type Filter = 'in_review' | 'approved' | 'rejected'
const filter = ref<Filter>('in_review')
const ads = ref<Creative[]>([])
const reasons = ref<{ value: string; label: string }[]>([])
const loading = ref(true)
const error = ref<string | null>(null)
const notice = ref<string | null>(null)
const working = ref<string | null>(null)
const refusing = ref<string | null>(null)
const reason = ref('sexual')
const note = ref('')

const FILTERS: { value: Filter; label: string }[] = [
  { value: 'in_review', label: 'Waiting' },
  { value: 'approved', label: 'Approved' },
  { value: 'rejected', label: 'Refused' },
]

function needsMfa(err: unknown): boolean {
  if (!mfaNeeded(err)) return false
  router.push({ name: 'security', query: { next: '/admin' } })
  return true
}

async function load() {
  loading.value = true
  error.value = null
  try {
    const res = await get<Creative[]>('/admin/creatives', { status: filter.value })
    ads.value = res.data
    reasons.value = (res.meta?.reasons as typeof reasons.value) ?? []
    if (filter.value === 'in_review') emit('count', Number(res.meta?.total ?? res.data.length))
  } catch (err) {
    if (!needsMfa(err)) error.value = errorMessage(err)
  } finally {
    loading.value = false
  }
}

async function approve(ad: Creative) {
  working.value = ad.id
  error.value = null
  notice.value = null
  try {
    notice.value = (await post(`/admin/creatives/${ad.id}/approve`)).message
    await load()
  } catch (err) {
    if (!needsMfa(err)) error.value = errorMessage(err)
  } finally {
    working.value = null
  }
}

function startRefuse(ad: Creative) {
  refusing.value = ad.id
  reason.value = reasons.value[0]?.value ?? 'other'
  note.value = ''
}

async function refuse(ad: Creative) {
  working.value = ad.id
  error.value = null
  notice.value = null
  try {
    notice.value = (await post(`/admin/creatives/${ad.id}/reject`, { reason: reason.value, note: note.value.trim() || null })).message
    refusing.value = null
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
    <div class="mb-5 flex flex-wrap gap-2" role="tablist" aria-label="Filter ads">
      <button
        v-for="f in FILTERS"
        :key="f.value"
        type="button"
        role="tab"
        :aria-selected="filter === f.value"
        class="rounded-full border px-4 py-2 text-sm font-bold transition"
        :class="filter === f.value ? 'border-plum-700 bg-plum-100 text-plum-900' : 'border-line-strong bg-white text-ink-soft hover:border-plum-300'"
        @click="filter = f.value"
      >
        {{ f.label }}
      </button>
    </div>

    <AlertBox v-if="notice" tone="success" class="mb-4">{{ notice }}</AlertBox>
    <AlertBox v-if="error" class="mb-4">{{ error }}</AlertBox>

    <div v-if="loading" class="grid gap-4 lg:grid-cols-2"><div v-for="i in 2" :key="i" class="skeleton h-96" /></div>
    <EmptyState v-else-if="!ads.length" icon="check" :title="filter === 'in_review' ? 'All caught up' : 'Nothing here'" :body="filter === 'in_review' ? 'No ads are waiting for review.' : undefined" />

    <ul v-else class="grid gap-5 lg:grid-cols-2">
      <li v-for="ad in ads" :key="ad.id" class="card flex min-w-0 flex-col overflow-hidden">
        <div class="grid place-items-center overflow-hidden bg-black" :class="ad.orientation === 'portrait' ? 'h-96' : 'aspect-video'">
          <video
            v-if="ad.kind === 'video' && ad.media_url"
            :src="ad.media_url"
            class="max-h-full max-w-full"
            :class="ad.orientation === 'portrait' ? 'h-96' : 'w-full'"
            controls
            muted
            playsinline
            preload="metadata"
            :poster="ad.thumb_url ?? undefined"
          />
          <img v-else-if="ad.media_url" :src="ad.media_url" :alt="ad.name" class="max-h-full max-w-full object-contain" />
        </div>
        <div class="flex flex-1 flex-col p-5">
          <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
              <h3 class="truncate text-lg font-extrabold">{{ ad.name }}</h3>
              <p class="text-sm text-subtle">
                {{ CATEGORIES[ad.category] ?? ad.category }} · {{ ad.width_px }}×{{ ad.height_px }}<template v-if="ad.duration_s"> · {{ Math.round(ad.duration_s) }} s</template>
              </p>
            </div>
            <StatusBadge :status="ad.status" :label="ad.status === 'rejected' ? 'Refused' : undefined" />
          </div>

          <dl class="mt-4 grid grid-cols-2 gap-3 rounded-xl bg-canvas p-3 text-sm">
            <div>
              <dt class="text-xs text-subtle">Advertiser</dt>
              <dd class="truncate font-bold">{{ ad.organisation?.name }}</dd>
              <dd class="text-xs text-subtle">{{ ad.uploader?.name }} · {{ ad.uploader?.phone }}</dd>
            </div>
            <div>
              <dt class="text-xs text-subtle">Identity</dt>
              <dd>
                <StatusBadge
                  :status="ad.uploader?.document_status === 'approved' ? 'approved' : ad.uploader?.document_status === 'pending' ? 'pending' : 'rejected'"
                  :label="ad.uploader?.document_status === 'approved' ? 'Verified' : ad.uploader?.document_status === 'pending' ? 'Being checked' : 'Not verified'"
                />
              </dd>
            </div>
            <div class="col-span-2 text-xs text-subtle">Declared clean {{ ad.declared_at ? formatDateTime(ad.declared_at) : '–' }} · uploaded {{ formatDateTime(ad.created_at) }}</div>
          </dl>

          <p v-if="ad.reject_label" class="mt-3 text-sm font-semibold text-danger">{{ ad.reject_label }}<span v-if="ad.review_note" class="font-normal text-muted"> · {{ ad.review_note }}</span></p>

          <!-- Decision -->
          <div v-if="ad.status === 'in_review' || ad.status === 'approved'" class="mt-auto pt-4">
            <form v-if="refusing === ad.id" class="space-y-3 rounded-xl border border-danger/25 bg-danger/[0.03] p-4" @submit.prevent="refuse(ad)">
              <div>
                <label class="field-label" :for="`reason-${ad.id}`">Reason (the advertiser sees this)</label>
                <select :id="`reason-${ad.id}`" v-model="reason" class="input">
                  <option v-for="r in reasons" :key="r.value" :value="r.value">{{ r.label }}</option>
                </select>
              </div>
              <div>
                <label class="field-label" :for="`note-${ad.id}`">Note <span class="font-medium text-subtle">(what to fix)</span></label>
                <textarea :id="`note-${ad.id}`" v-model="note" rows="2" maxlength="500" class="input" placeholder="e.g. The woman’s outfit at 0:04 is too revealing for a public screen." />
              </div>
              <div class="flex gap-2">
                <button type="button" class="btn-outline btn-sm" @click="refusing = null">Back</button>
                <button class="btn-danger btn-sm flex-1" :disabled="working === ad.id">{{ ad.status === 'approved' ? 'Pull this ad' : 'Refuse ad' }}</button>
              </div>
            </form>
            <div v-else class="flex gap-2">
              <button
                v-if="ad.status === 'in_review'"
                type="button"
                class="btn-plum btn-sm flex-1"
                :disabled="working === ad.id || ad.uploader?.document_status !== 'approved'"
                :title="ad.uploader?.document_status !== 'approved' ? 'Verify the advertiser’s identity first' : undefined"
                @click="approve(ad)"
              >
                <AppIcon name="check" :size="16" /> Approve
              </button>
              <button type="button" class="btn-outline btn-sm flex-1 text-danger" :disabled="working === ad.id" @click="startRefuse(ad)">
                <AppIcon name="ban" :size="16" /> {{ ad.status === 'approved' ? 'Pull ad' : 'Refuse' }}
              </button>
            </div>
            <p v-if="ad.status === 'in_review' && ad.uploader?.document_status !== 'approved'" class="mt-2 text-xs text-warning">
              Approve the advertiser’s identity document first (Documents tab).
            </p>
          </div>
        </div>
      </li>
    </ul>
  </div>
</template>
