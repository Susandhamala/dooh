<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import AlertBox from '@/components/ui/AlertBox.vue'
import AppIcon, { type IconName } from '@/components/ui/AppIcon.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import LoadingCards from '@/components/ui/LoadingCards.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import { errorMessage, fieldErrors } from '@/lib/http'
import { useOrgStore } from '@/stores/orgs'

const orgs = useOrgStore()
const blank = () => ({ name: '', type: 'advertiser' as 'advertiser' | 'owner', pan_number: '' })
const form = ref(blank())
const errors = ref<Record<string, string>>({})
const formError = ref<string | null>(null)
const loadError = ref<string | null>(null)
const showForm = ref(false)
const busy = ref(false)

const types: { value: 'advertiser' | 'owner'; title: string; body: string; icon: IconName }[] = [
  { value: 'advertiser', title: 'Advertise on screens', body: 'Upload ads and book screens.', icon: 'megaphone' },
  { value: 'owner', title: 'List my screens', body: 'Earn from screens you own. You approve every ad.', icon: 'screen' },
]

const ROLE_LABELS: Record<string, string> = { admin: 'Admin', editor: 'Editor', viewer: 'Viewer' }

const empty = computed(() => orgs.loaded && !orgs.list.length)

async function load() {
  loadError.value = null
  try {
    await orgs.load(true)
  } catch (err) {
    loadError.value = errorMessage(err)
  }
}

function openForm() {
  showForm.value = true
  errors.value = {}
  formError.value = null
}

async function create() {
  busy.value = true
  errors.value = {}
  formError.value = null
  try {
    await orgs.create({ ...form.value, pan_number: form.value.pan_number || undefined })
    form.value = blank()
    showForm.value = false
  } catch (err) {
    errors.value = fieldErrors(err)
    formError.value = errorMessage(err)
  } finally {
    busy.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="shell py-8 sm:py-10">
    <PageHeader
      eyebrow="Your account"
      title="My organisations"
      lede="Campaigns and screens belong to an organisation, so your team can share them."
    >
      <template v-if="!showForm && !empty" #actions>
        <button type="button" class="btn-primary" @click="openForm">
          <AppIcon name="plus" :size="18" /> New organisation
        </button>
      </template>
    </PageHeader>

    <form v-if="showForm" class="card mb-8 p-5 shadow-card sm:p-6" novalidate @submit.prevent="create">
      <h2 class="h-section">New organisation</h2>

      <fieldset class="mt-5">
        <legend class="field-label">What will you do?</legend>
        <div class="grid gap-3 sm:grid-cols-2">
          <label
            v-for="t in types"
            :key="t.value"
            class="flex cursor-pointer gap-3 rounded-xl border p-4 transition has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-plum-700"
            :class="form.type === t.value ? 'border-plum-700 bg-plum-50' : 'border-line-strong hover:border-plum-300'"
          >
            <input v-model="form.type" type="radio" name="org-type" :value="t.value" class="sr-only" />
            <span
              class="grid h-10 w-10 shrink-0 place-items-center rounded-xl"
              :class="form.type === t.value ? 'bg-plum-700 text-white' : 'bg-plum-100 text-plum-700'"
            >
              <AppIcon :name="t.icon" />
            </span>
            <span>
              <span class="block font-bold">{{ t.title }}</span>
              <span class="block text-sm text-muted">{{ t.body }}</span>
            </span>
          </label>
        </div>
      </fieldset>

      <div class="mt-5 grid gap-4 sm:grid-cols-2">
        <div>
          <label class="field-label" for="org-name">Business name</label>
          <input
            id="org-name"
            v-model="form.name"
            class="input"
            :class="{ 'input-error': errors.name }"
            autocomplete="organization"
            required
          />
          <p v-if="errors.name" class="field-error">{{ errors.name }}</p>
        </div>
        <div>
          <label class="field-label" for="org-pan">PAN number <span class="font-medium text-subtle">(optional)</span></label>
          <input
            id="org-pan"
            v-model="form.pan_number"
            class="input"
            :class="{ 'input-error': errors.pan_number }"
            inputmode="numeric"
            maxlength="9"
            placeholder="9 digits"
          />
          <p v-if="errors.pan_number" class="field-error">{{ errors.pan_number }}</p>
          <p v-else class="field-hint">Verified businesses get approved faster.</p>
        </div>
      </div>

      <AlertBox v-if="formError && !Object.keys(errors).length" class="mt-4">{{ formError }}</AlertBox>

      <div class="mt-6 flex flex-col-reverse gap-2 sm:flex-row">
        <button type="button" class="btn-outline" @click="showForm = false">Cancel</button>
        <button class="btn-primary" :disabled="busy">{{ busy ? 'Creating…' : 'Create organisation' }}</button>
      </div>
    </form>

    <AlertBox v-if="loadError" title="Couldn’t load your organisations">
      {{ loadError }}
      <template #action><button type="button" class="link shrink-0 text-sm" @click="load">Try again</button></template>
    </AlertBox>
    <LoadingCards v-else-if="!orgs.loaded" :count="2" cols="sm:grid-cols-2" />
    <EmptyState
      v-else-if="empty && !showForm"
      icon="building"
      title="Start with an organisation"
      body="Create one to start advertising, or to list the screens you own."
    >
      <button type="button" class="btn-primary" @click="openForm"><AppIcon name="plus" :size="18" /> New organisation</button>
    </EmptyState>

    <ul v-else class="grid gap-4 sm:grid-cols-2">
      <li v-for="org in orgs.list" :key="org.id" class="card flex min-w-0 flex-col p-5 sm:p-6">
        <div class="flex items-start gap-3">
          <span
            class="grid h-11 w-11 shrink-0 place-items-center rounded-xl"
            :class="org.type === 'owner' ? 'bg-teal-50 text-teal-dark' : 'bg-plum-100 text-plum-700'"
          >
            <AppIcon :name="org.type === 'owner' ? 'screen' : 'megaphone'" />
          </span>
          <div class="min-w-0 flex-1">
            <h2 class="font-extrabold">{{ org.name }}</h2>
            <p class="text-sm text-subtle">
              {{ org.type === 'owner' ? 'Screen owner' : 'Advertiser' }} · you are {{ ROLE_LABELS[org.my_role ?? ''] ?? org.my_role }}
            </p>
          </div>
        </div>
        <div class="mt-4 flex flex-wrap gap-2">
          <StatusBadge v-if="org.status === 'suspended'" status="suspended" />
          <StatusBadge v-else-if="org.verified" status="approved" label="Verified" />
          <StatusBadge v-else status="awaiting_owner" label="Awaiting verification" />
        </div>
        <div class="mt-auto pt-5">
          <RouterLink
            :to="org.type === 'owner' ? `/orgs/${org.id}/screens` : '/ads'"
            class="btn-outline btn-sm"
          >
            {{ org.type === 'owner' ? 'Manage screens' : 'My ads' }} <AppIcon name="arrow" :size="16" />
          </RouterLink>
        </div>
      </li>
    </ul>
  </div>
</template>
