<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import AlertBox from '@/components/ui/AlertBox.vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import FileDrop from '@/components/ui/FileDrop.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import { formatDateTime } from '@/lib/format'
import { errorMessage, fieldErrors, get, upload } from '@/lib/http'
import { DOCUMENT_TYPES, type DocumentStatus, type IdentityDocument } from '@/lib/types'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const docs = ref<IdentityDocument[]>([])
const status = ref<DocumentStatus>('none')
const loading = ref(true)
const loadError = ref<string | null>(null)

const form = ref({ type: 'citizenship', number: '' })
const file = ref<File | null>(null)
const errors = ref<Record<string, string>>({})
const error = ref<string | null>(null)
const busy = ref(false)
const progress = ref(0)

const canUpload = computed(() => status.value === 'none' || status.value === 'rejected')
const latest = computed(() => docs.value[0] ?? null)

async function load() {
  loadError.value = null
  try {
    const res = await get<IdentityDocument[]>('/me/documents')
    docs.value = res.data
    status.value = (res.meta?.status as DocumentStatus) ?? 'none'
  } catch (err) {
    loadError.value = errorMessage(err)
  } finally {
    loading.value = false
  }
}

async function submit() {
  errors.value = {}
  error.value = null
  if (!file.value) {
    errors.value = { file: 'Choose a photo or PDF of the document.' }
    return
  }
  busy.value = true
  progress.value = 0
  const body = new FormData()
  body.append('type', form.value.type)
  body.append('number', form.value.number.trim())
  body.append('file', file.value)
  try {
    await upload('/me/documents', body, (p) => (progress.value = p))
    file.value = null
    form.value.number = ''
    await Promise.all([load(), auth.reloadMe()])
  } catch (err) {
    errors.value = fieldErrors(err)
    error.value = errorMessage(err)
  } finally {
    busy.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="shell py-8 sm:py-10">
    <div class="mx-auto max-w-2xl">
      <PageHeader
        eyebrow="Your account"
        title="Identity verification"
        lede="Every advertiser on public screens is a real, known person or business. We check your document once."
      />

      <div v-if="loading" class="skeleton h-40" />
      <AlertBox v-else-if="loadError">{{ loadError }}</AlertBox>

      <template v-else>
        <!-- Current state -->
        <div v-if="latest" class="card mb-6 flex items-start gap-4 p-5">
          <span
            class="grid h-12 w-12 shrink-0 place-items-center rounded-xl"
            :class="{
              'bg-success/10 text-success': status === 'approved',
              'bg-warning/10 text-warning': status === 'pending',
              'bg-danger/10 text-danger': status === 'rejected',
            }"
          >
            <AppIcon :name="status === 'approved' ? 'shield' : status === 'pending' ? 'clock' : 'alert'" />
          </span>
          <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-2">
              <h2 class="font-extrabold">
                {{ status === 'approved' ? 'You are verified' : status === 'pending' ? 'We are checking your document' : 'Your document was refused' }}
              </h2>
              <StatusBadge :status="latest.status" />
            </div>
            <p class="mt-1 text-sm text-muted">
              {{ latest.type_label }} · {{ latest.number }} · sent {{ formatDateTime(latest.submitted_at) }}
            </p>
            <p v-if="status === 'pending'" class="mt-2 text-sm text-body">
              This usually takes less than a day. You can load money, upload and book meanwhile; ads go live once you are verified.
            </p>
            <p v-if="status === 'approved'" class="mt-2 text-sm text-body">For your privacy, we deleted the copy of your document after checking it.</p>
            <p v-if="latest.review_note" class="mt-2 rounded-lg bg-canvas px-3 py-2 text-sm"><strong>Reviewer:</strong> {{ latest.review_note }}</p>
          </div>
        </div>

        <!-- Upload -->
        <form v-if="canUpload" class="card p-5 shadow-card sm:p-6" novalidate @submit.prevent="submit">
          <h2 class="h-section">{{ status === 'rejected' ? 'Upload a new document' : 'Upload a document' }}</h2>
          <p class="mt-1 text-sm text-muted">A clear photo or scan where every detail can be read.</p>

          <div class="mt-5 grid gap-4 sm:grid-cols-2">
            <div>
              <label class="field-label" for="doc-type">Document</label>
              <select id="doc-type" v-model="form.type" class="input">
                <option v-for="(label, key) in DOCUMENT_TYPES" :key="key" :value="key">{{ label }}</option>
              </select>
            </div>
            <div>
              <label class="field-label" for="doc-number">Document number</label>
              <input
                id="doc-number"
                v-model="form.number"
                class="input font-mono"
                :class="{ 'input-error': errors.number }"
                placeholder="e.g. 27-01-75-01234"
                maxlength="40"
                autocomplete="off"
              />
              <p v-if="errors.number" class="field-error">{{ errors.number }}</p>
            </div>
          </div>

          <div class="mt-4">
            <span class="field-label">Photo or scan</span>
            <FileDrop id="doc-file" v-model="file" accept="image/jpeg,image/png,image/webp,application/pdf" hint="JPG, PNG or PDF, up to 5 MB" :error="errors.file" />
          </div>

          <ul class="mt-4 space-y-1.5 text-sm text-muted">
            <li class="flex gap-2"><AppIcon name="lock" :size="16" class="mt-0.5 shrink-0 text-success" /> Encrypted as soon as it arrives; only our review team can open it.</li>
            <li class="flex gap-2"><AppIcon name="trash" :size="16" class="mt-0.5 shrink-0 text-success" /> Deleted after the check. We keep only the result.</li>
          </ul>

          <AlertBox v-if="error && !Object.keys(errors).length" class="mt-4">{{ error }}</AlertBox>

          <div v-if="busy" class="mt-5 h-2 overflow-hidden rounded-full bg-divider" role="progressbar" :aria-valuenow="progress" aria-valuemin="0" aria-valuemax="100">
            <div class="h-full bg-plum-700 transition-all" :style="{ width: `${progress}%` }" />
          </div>
          <button class="btn-primary mt-5 w-full sm:w-auto" :disabled="busy">{{ busy ? `Uploading ${progress}%…` : 'Send for checking' }}</button>
        </form>

        <RouterLink v-else to="/dashboard" class="btn-outline">Back to dashboard</RouterLink>
      </template>
    </div>
  </div>
</template>
