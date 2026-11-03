<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import MediaPreview from '@/components/MediaPreview.vue'
import AlertBox from '@/components/ui/AlertBox.vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import FileDrop from '@/components/ui/FileDrop.vue'
import LoadingCards from '@/components/ui/LoadingCards.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import { formatBytes, formatDateTime } from '@/lib/format'
import { del, errorMessage, fieldErrors, gateNeeded, get, upload } from '@/lib/http'
import { CATEGORIES, type Creative, type Resolution } from '@/lib/types'
import { useAuthStore } from '@/stores/auth'
import { useOrgStore } from '@/stores/orgs'

const auth = useAuthStore()
const orgs = useOrgStore()
const router = useRouter()

const orgId = ref<string | null>(null)
const ads = ref<Creative[]>([])
const meta = ref<{ resolutions: Resolution[]; max_image_mb: number; max_video_mb: number; max_video_seconds: number; declaration: string } | null>(null)
const loading = ref(true)
const loadError = ref<string | null>(null)

const showForm = ref(false)
const form = ref({ name: '', category: 'retail', resolution: '1920x1080' })
const file = ref<File | null>(null)
const declared = ref(false)
const errors = ref<Record<string, string>>({})
const formError = ref<string | null>(null)
const busy = ref(false)
const progress = ref(0)
const drop = ref<InstanceType<typeof FileDrop>>()

const previewing = ref<Creative | null>(null)
const deleting = ref<Creative | null>(null)
const deleteBusy = ref(false)
const actionError = ref<string | null>(null)

let poll: ReturnType<typeof setTimeout> | undefined
const pending = computed(() => ads.value.some((a) => a.status === 'processing'))

async function load() {
  loadError.value = null
  try {
    const org = await orgs.ensureAdvertiser(auth.user?.name ?? 'My business')
    orgId.value = org.id
    const res = await get<Creative[]>(`/orgs/${org.id}/creatives`)
    ads.value = res.data
    meta.value = res.meta as typeof meta.value
    if (!ads.value.length) showForm.value = true
  } catch (err) {
    loadError.value = errorMessage(err)
  } finally {
    loading.value = false
  }
  // While files are being checked, look again every few seconds.
  clearTimeout(poll)
  if (pending.value) poll = setTimeout(load, 4000)
}

async function submit() {
  errors.value = {}
  formError.value = null
  if (!file.value) errors.value.file = 'Choose an image or video.'
  if (!declared.value) errors.value.declaration = 'Tick the box to confirm your ad follows the content policy.'
  if (Object.keys(errors.value).length || !orgId.value) return

  busy.value = true
  progress.value = 0
  const body = new FormData()
  body.append('name', form.value.name.trim())
  body.append('category', form.value.category)
  body.append('resolution', form.value.resolution)
  body.append('declaration', '1')
  body.append('file', file.value!)
  try {
    await upload(`/orgs/${orgId.value}/creatives`, body, (p) => (progress.value = p))
    form.value.name = ''
    declared.value = false
    drop.value?.clear()
    showForm.value = false
    await load()
  } catch (err) {
    const gate = gateNeeded(err)
    if (gate === 'terms') return void router.push({ name: 'welcome', query: { next: '/ads' } })
    errors.value = fieldErrors(err)
    formError.value = errorMessage(err)
  } finally {
    busy.value = false
  }
}

async function confirmDelete() {
  if (!deleting.value || !orgId.value) return
  deleteBusy.value = true
  actionError.value = null
  try {
    await del(`/orgs/${orgId.value}/creatives/${deleting.value.id}`)
    await load()
  } catch (err) {
    actionError.value = errorMessage(err)
  } finally {
    deleting.value = null
    deleteBusy.value = false
  }
}

const shape = (r: Resolution) => (r.width >= r.height ? { width: '28px', height: `${(28 * r.height) / r.width}px` } : { width: `${(28 * r.width) / r.height}px`, height: '28px' })

onMounted(load)
onBeforeUnmount(() => clearTimeout(poll))
</script>

<template>
  <div class="shell py-8 sm:py-10">
    <PageHeader eyebrow="Create" title="My ads" lede="Upload once, book on any screen of the same shape. Our review team checks every ad before it plays.">
      <template v-if="!showForm && ads.length" #actions>
        <button type="button" class="btn-primary" @click="showForm = true"><AppIcon name="upload" :size="18" /> Upload ad</button>
      </template>
    </PageHeader>

    <!-- Upload -->
    <form v-if="showForm && meta" class="card mb-8 overflow-hidden shadow-card" novalidate @submit.prevent="submit">
      <div class="border-b border-divider px-5 py-4 sm:px-6">
        <h2 class="h-section">Upload an ad</h2>
        <p class="text-sm text-muted">
          Images up to {{ meta.max_image_mb }} MB · videos up to {{ meta.max_video_mb }} MB and {{ meta.max_video_seconds }} s. Sound is removed: screens play silently.
        </p>
      </div>

      <div class="grid gap-6 p-5 sm:p-6 lg:grid-cols-2">
        <div class="min-w-0 space-y-4">
          <fieldset>
            <legend class="field-label">Screen shape and resolution</legend>
            <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
              <label
                v-for="r in meta.resolutions"
                :key="`${r.width}x${r.height}`"
                class="flex cursor-pointer items-center gap-2.5 rounded-xl border p-2.5 transition has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-plum-700"
                :class="form.resolution === `${r.width}x${r.height}` ? 'border-plum-700 bg-plum-50' : 'border-line-strong hover:border-plum-300'"
              >
                <input v-model="form.resolution" type="radio" name="resolution" :value="`${r.width}x${r.height}`" class="sr-only" />
                <span class="grid h-8 w-8 shrink-0 place-items-center" aria-hidden="true">
                  <span class="rounded-[3px] border-2 border-plum-700 bg-plum-200" :style="shape(r)" />
                </span>
                <span class="min-w-0 leading-tight">
                  <span class="block text-sm font-bold">{{ r.width }}×{{ r.height }}</span>
                  <span class="block truncate text-xs text-subtle">{{ r.label }}</span>
                </span>
              </label>
            </div>
            <p class="field-hint">Your file must have this shape. We resize it to exactly this size.</p>
          </fieldset>

          <div class="grid gap-4 sm:grid-cols-2">
            <div>
              <label class="field-label" for="ad-name">Ad name</label>
              <input id="ad-name" v-model="form.name" class="input" :class="{ 'input-error': errors.name }" placeholder="e.g. Dashain offer" maxlength="150" />
              <p v-if="errors.name" class="field-error">{{ errors.name }}</p>
            </div>
            <div>
              <label class="field-label" for="ad-cat">What it promotes</label>
              <select id="ad-cat" v-model="form.category" class="input">
                <option v-for="(label, key) in CATEGORIES" :key="key" :value="key">{{ label }}</option>
              </select>
            </div>
          </div>
        </div>

        <div class="min-w-0">
          <span class="field-label">File</span>
          <FileDrop
            id="ad-file"
            ref="drop"
            v-model="file"
            accept="image/jpeg,image/png,image/webp,video/mp4,video/quicktime,video/webm"
            hint="JPG, PNG, WebP, MP4, MOV or WebM"
            :error="errors.file"
          />
        </div>

        <!-- The per-upload declaration -->
        <label
          class="flex cursor-pointer gap-3 rounded-xl border p-4 transition lg:col-span-2"
          :class="declared ? 'border-plum-700 bg-plum-50' : errors.declaration ? 'border-danger/50 bg-danger/[0.03]' : 'border-notice-line bg-notice'"
        >
          <input v-model="declared" type="checkbox" class="mt-1 h-5 w-5 shrink-0 accent-plum-700" />
          <span class="text-sm text-ink">
            <strong class="mb-0.5 flex items-center gap-1.5 text-notice-ink"><AppIcon name="gavel" :size="16" /> Content declaration</strong>
            {{ meta.declaration }}
          </span>
        </label>
        <p v-if="errors.declaration" class="field-error -mt-4 lg:col-span-2">{{ errors.declaration }}</p>
      </div>

      <div class="border-t border-divider px-5 py-4 sm:px-6">
        <AlertBox v-if="formError && !Object.keys(errors).length" class="mb-4">{{ formError }}</AlertBox>
        <div v-if="busy" class="mb-4 h-2 overflow-hidden rounded-full bg-divider" role="progressbar" :aria-valuenow="progress" aria-valuemin="0" aria-valuemax="100">
          <div class="h-full bg-plum-700 transition-all" :style="{ width: `${progress}%` }" />
        </div>
        <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
          <button v-if="ads.length" type="button" class="btn-outline" @click="showForm = false">Cancel</button>
          <button class="btn-primary" :disabled="busy">{{ busy ? `Uploading ${progress}%…` : 'Upload for review' }}</button>
        </div>
      </div>
    </form>

    <AlertBox v-if="actionError" class="mb-4">{{ actionError }}</AlertBox>
    <AlertBox v-if="loadError">{{ loadError }}</AlertBox>
    <LoadingCards v-else-if="loading" :count="3" />
    <EmptyState v-else-if="!ads.length && !showForm" icon="film" title="No ads yet" body="Upload an image or a short video to get started.">
      <button type="button" class="btn-primary" @click="showForm = true">Upload ad</button>
    </EmptyState>

    <ul v-else-if="ads.length" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
      <li v-for="a in ads" :key="a.id" class="card flex min-w-0 flex-col overflow-hidden">
        <button
          type="button"
          class="group relative grid aspect-video place-items-center overflow-hidden bg-plum-950"
          :disabled="!a.media_url"
          :aria-label="`Preview ${a.name}`"
          @click="previewing = a"
        >
          <img v-if="a.thumb_url" :src="a.thumb_url" alt="" class="h-full w-full object-contain transition group-hover:scale-[1.02]" loading="lazy" />
          <span v-else class="flex flex-col items-center text-white/70">
            <span v-if="a.status === 'processing'" class="h-8 w-8 animate-spin rounded-full border-4 border-white/20 border-t-white" aria-hidden="true" />
            <AppIcon v-else :name="a.kind === 'video' ? 'film' : 'image'" :size="32" />
            <span class="mt-2 text-xs font-semibold">{{ a.status === 'processing' ? 'Checking the file…' : 'No preview' }}</span>
          </span>
          <span v-if="a.media_url" class="absolute inset-0 grid place-items-center bg-black/0 opacity-0 transition group-hover:bg-black/30 group-hover:opacity-100">
            <span class="grid h-12 w-12 place-items-center rounded-full bg-white/90 text-plum-900"><AppIcon :name="a.kind === 'video' ? 'play' : 'eye'" /></span>
          </span>
          <span class="absolute top-2 left-2 rounded-md bg-black/50 px-2 py-0.5 text-[11px] font-bold text-white">
            {{ a.width_px }}×{{ a.height_px }}<template v-if="a.duration_s"> · {{ Math.round(a.duration_s) }} s</template>
          </span>
        </button>

        <div class="flex flex-1 flex-col p-4">
          <div class="flex items-start justify-between gap-2">
            <h2 class="min-w-0 truncate font-extrabold">{{ a.name }}</h2>
            <StatusBadge :status="a.status" :label="a.status === 'approved' ? 'Approved' : a.status === 'rejected' ? 'Refused' : undefined" />
          </div>
          <p class="mt-0.5 text-xs text-subtle">{{ CATEGORIES[a.category] ?? a.category }} · {{ formatBytes(a.size_bytes) }} · {{ formatDateTime(a.created_at) }}</p>

          <AlertBox v-if="a.status === 'failed'" class="mt-3" title="File problem">
            <span v-for="e in a.check.errors" :key="e" class="block">{{ e }}</span>
          </AlertBox>
          <AlertBox v-else-if="a.status === 'rejected'" class="mt-3" :title="a.reject_label ?? 'Not approved'">
            {{ a.review_note || 'The review team refused this ad.' }}
          </AlertBox>
          <p v-else-if="a.status === 'in_review'" class="mt-3 flex items-center gap-1.5 text-sm text-warning">
            <AppIcon name="clock" :size="15" /> Our team is reviewing it. You can already book it.
          </p>
          <p v-else-if="a.status === 'approved'" class="mt-3 flex items-center gap-1.5 text-sm text-success">
            <AppIcon name="shield" :size="15" /> Ready to play on {{ a.orientation }} screens.
          </p>

          <div class="mt-auto flex gap-2 pt-4">
            <RouterLink v-if="a.status === 'approved' || a.status === 'in_review'" to="/screens" class="btn-primary btn-sm flex-1">Book a screen</RouterLink>
            <button type="button" class="btn-ghost btn-sm text-danger hover:bg-danger/8 hover:text-danger" :aria-label="`Delete ${a.name}`" @click="deleting = a">
              <AppIcon name="trash" :size="16" />
            </button>
          </div>
        </div>
      </li>
    </ul>

    <MediaPreview :creative="previewing" @close="previewing = null" />
    <ConfirmDialog
      :open="deleting !== null"
      title="Delete this ad?"
      confirm-label="Delete"
      danger
      :busy="deleteBusy"
      @confirm="confirmDelete"
      @cancel="deleting = null"
    >
      <strong class="text-ink">{{ deleting?.name }}</strong> is removed for good. Ads with open bookings can’t be deleted.
    </ConfirmDialog>
  </div>
</template>
