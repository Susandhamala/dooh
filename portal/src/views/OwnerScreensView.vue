<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import LocationPicker from '@/components/LocationPicker.vue'
import AlertBox from '@/components/ui/AlertBox.vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import LoadingCards from '@/components/ui/LoadingCards.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import { formatRupees } from '@/lib/format'
import { errorMessage, fieldErrors, get, mfaNeeded, patch, post } from '@/lib/http'
import { CONTENT_RULES, type Organisation, type Screen } from '@/lib/types'

const props = defineProps<{ org: string }>()
const router = useRouter()

const organisation = ref<Organisation | null>(null)
const screens = ref<Screen[]>([])
const loading = ref(true)
const loadError = ref<string | null>(null)
const actionError = ref<string | null>(null)
const errors = ref<Record<string, string>>({})
const formError = ref<string | null>(null)
const showForm = ref(false)
const busy = ref(false)
const updating = ref<string | null>(null)

const blank = () => ({
  name: '',
  address: '',
  description: '',
  audience_notes: '',
  lat: 27.7041,
  lng: 85.3075,
  width_px: 1920,
  height_px: 1080,
  orientation: 'landscape' as 'landscape' | 'portrait',
  opens_at: '06:00',
  closes_at: '22:00',
  loop_seconds: 120,
  spot_seconds: 10,
  price_per_play: 2,
  content_rules: [] as string[],
})
const form = ref(blank())

const slots = computed(() =>
  form.value.spot_seconds > 0 ? Math.floor(form.value.loop_seconds / form.value.spot_seconds) : 0,
)

async function load() {
  loadError.value = null
  try {
    const [o, s] = await Promise.all([get<Organisation>(`/orgs/${props.org}`), get<Screen[]>(`/orgs/${props.org}/screens`)])
    organisation.value = o.data
    screens.value = s.data
  } catch (err) {
    loadError.value = errorMessage(err)
  } finally {
    loading.value = false
  }
}

/** Owner changes need a two-factor session; send the user to get one and come back. */
function needsMfa(err: unknown): boolean {
  if (!mfaNeeded(err)) return false
  router.push({ name: 'security', query: { next: `/orgs/${props.org}/screens` } })
  return true
}

function openForm() {
  showForm.value = true
  errors.value = {}
  formError.value = null
}

/** Width and height follow the orientation, so a portrait screen is never stored as 1920×1080. */
function setOrientation(o: 'landscape' | 'portrait') {
  form.value.orientation = o
  const [a, b] = [form.value.width_px, form.value.height_px]
  if ((o === 'portrait' && a > b) || (o === 'landscape' && b > a)) {
    form.value.width_px = b
    form.value.height_px = a
  }
}

async function create() {
  busy.value = true
  errors.value = {}
  formError.value = null
  try {
    await post(`/orgs/${props.org}/screens`, {
      ...form.value,
      address: form.value.address || null,
      description: form.value.description || null,
      audience_notes: form.value.audience_notes || null,
    })
    form.value = blank()
    showForm.value = false
    await load()
  } catch (err) {
    if (needsMfa(err)) return
    errors.value = fieldErrors(err)
    formError.value = errorMessage(err)
  } finally {
    busy.value = false
  }
}

async function setStatus(s: Screen, status: Screen['status']) {
  updating.value = s.id
  actionError.value = null
  try {
    await patch(`/orgs/${props.org}/screens/${s.id}`, { status })
    await load()
  } catch (err) {
    if (!needsMfa(err)) actionError.value = errorMessage(err)
  } finally {
    updating.value = null
  }
}

onMounted(load)
</script>

<template>
  <div class="shell py-8 sm:py-10">
    <PageHeader
      :eyebrow="organisation?.name ?? 'Screen owner'"
      title="My screens"
      lede="Publish a screen to show it on the map. You approve every ad before it plays."
      :back="{ to: '/orgs', label: 'My organisations' }"
    >
      <template v-if="!showForm && screens.length" #actions>
        <button type="button" class="btn-primary" @click="openForm"><AppIcon name="plus" :size="18" /> Add screen</button>
      </template>
    </PageHeader>

    <AlertBox v-if="organisation && !organisation.verified" tone="warning" class="mb-6" title="Awaiting verification">
      An admin checks new screen owners before their screens go live to advertisers.
    </AlertBox>

    <form v-if="showForm" class="card mb-8 shadow-card" novalidate @submit.prevent="create">
      <div class="border-b border-divider px-5 py-4 sm:px-6">
        <h2 class="h-section">Add a screen</h2>
        <p class="text-sm text-muted">Saved as a draft. Publish it when you’re ready.</p>
      </div>

      <div class="grid gap-x-8 gap-y-6 p-5 sm:p-6 lg:grid-cols-2">
        <!-- Where -->
        <fieldset class="min-w-0 space-y-4">
          <legend class="mb-3 text-sm font-extrabold tracking-wide text-subtle uppercase">Where</legend>
          <div>
            <label class="field-label" for="s-name">Screen name</label>
            <input id="s-name" v-model="form.name" class="input" :class="{ 'input-error': errors.name }" placeholder="e.g. New Road Junction" required />
            <p v-if="errors.name" class="field-error">{{ errors.name }}</p>
          </div>
          <div>
            <label class="field-label" for="s-address">Address</label>
            <input id="s-address" v-model="form.address" class="input" placeholder="Street, area, city" autocomplete="street-address" />
          </div>
          <div>
            <span class="field-label">Location</span>
            <LocationPicker v-model:lat="form.lat" v-model:lng="form.lng" />
            <div class="mt-2 grid grid-cols-2 gap-3">
              <div>
                <label class="sr-only" for="s-lat">Latitude</label>
                <input id="s-lat" v-model.number="form.lat" type="number" step="0.000001" class="input font-mono text-sm" :class="{ 'input-error': errors.lat }" />
              </div>
              <div>
                <label class="sr-only" for="s-lng">Longitude</label>
                <input id="s-lng" v-model.number="form.lng" type="number" step="0.000001" class="input font-mono text-sm" :class="{ 'input-error': errors.lng }" />
              </div>
            </div>
            <p v-if="errors.lat || errors.lng" class="field-error">{{ errors.lat ?? errors.lng }}</p>
            <p v-else class="field-hint">Click the map to drop the pin where the screen stands.</p>
          </div>
        </fieldset>

        <!-- The screen -->
        <fieldset class="min-w-0 space-y-4">
          <legend class="mb-3 text-sm font-extrabold tracking-wide text-subtle uppercase">The screen</legend>
          <div>
            <span class="field-label">Orientation</span>
            <div class="grid grid-cols-2 gap-2" role="radiogroup" aria-label="Orientation">
              <button
                v-for="o in ['landscape', 'portrait'] as const"
                :key="o"
                type="button"
                role="radio"
                :aria-checked="form.orientation === o"
                class="flex items-center justify-center gap-2 rounded-xl border px-3 py-2.5 text-sm font-bold capitalize transition"
                :class="form.orientation === o ? 'border-plum-700 bg-plum-50 text-plum-900' : 'border-line-strong text-ink-soft hover:border-plum-300'"
                @click="setOrientation(o)"
              >
                <span class="rounded-[2px] border-2 border-current" :class="o === 'landscape' ? 'h-3 w-5' : 'h-5 w-3'" aria-hidden="true" />
                {{ o }}
              </button>
            </div>
          </div>
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="field-label" for="s-w">Width (px)</label>
              <input id="s-w" v-model.number="form.width_px" type="number" min="320" class="input" :class="{ 'input-error': errors.width_px }" />
              <p v-if="errors.width_px" class="field-error">{{ errors.width_px }}</p>
            </div>
            <div>
              <label class="field-label" for="s-h">Height (px)</label>
              <input id="s-h" v-model.number="form.height_px" type="number" min="320" class="input" :class="{ 'input-error': errors.height_px }" />
              <p v-if="errors.height_px" class="field-error">{{ errors.height_px }}</p>
            </div>
            <div>
              <label class="field-label" for="s-open">On from</label>
              <input id="s-open" v-model="form.opens_at" type="time" class="input" :class="{ 'input-error': errors.opens_at }" />
            </div>
            <div>
              <label class="field-label" for="s-close">Off at</label>
              <input id="s-close" v-model="form.closes_at" type="time" class="input" :class="{ 'input-error': errors.closes_at }" />
            </div>
            <div>
              <label class="field-label" for="s-spot">Ad length (s)</label>
              <input id="s-spot" v-model.number="form.spot_seconds" type="number" min="5" max="120" class="input" :class="{ 'input-error': errors.spot_seconds }" />
              <p v-if="errors.spot_seconds" class="field-error">{{ errors.spot_seconds }}</p>
            </div>
            <div>
              <label class="field-label" for="s-loop">Loop length (s)</label>
              <input id="s-loop" v-model.number="form.loop_seconds" type="number" min="10" class="input" :class="{ 'input-error': errors.loop_seconds }" />
              <p v-if="errors.loop_seconds" class="field-error">{{ errors.loop_seconds }}</p>
            </div>
          </div>
          <p class="rounded-xl bg-plum-50 px-3.5 py-2.5 text-sm text-plum-900">
            <strong>{{ slots }} {{ slots === 1 ? 'slot' : 'slots' }}</strong> per loop. Each ad plays once every
            {{ form.loop_seconds }} s.
          </p>
          <div>
            <label class="field-label" for="s-price">Price per play (रू)</label>
            <input id="s-price" v-model.number="form.price_per_play" type="number" min="0.1" step="0.1" class="input" :class="{ 'input-error': errors.price_per_play }" />
            <p v-if="errors.price_per_play" class="field-error">{{ errors.price_per_play }}</p>
            <p v-else class="field-hint">
              One advertiser for a full day: {{ formatRupees(form.price_per_play * Math.floor(3600 / Math.max(1, form.loop_seconds)) * 16) }} (16 hours).
              Fully booked: {{ formatRupees(form.price_per_play * Math.floor(3600 / Math.max(1, form.loop_seconds)) * 16 * slots) }} a day.
            </p>
          </div>
        </fieldset>

        <!-- About -->
        <div class="grid gap-4 lg:col-span-2 lg:grid-cols-2">
          <div>
            <label class="field-label" for="s-desc">About the screen <span class="font-medium text-subtle">(optional)</span></label>
            <textarea id="s-desc" v-model="form.description" rows="3" class="input" placeholder="Size, height above the road, what it faces…" />
          </div>
          <div>
            <label class="field-label" for="s-aud">Who sees it <span class="font-medium text-subtle">(optional)</span></label>
            <textarea id="s-aud" v-model="form.audience_notes" rows="3" class="input" placeholder="e.g. Office workers, 8–10 am traffic" />
          </div>
        </div>

        <fieldset class="lg:col-span-2">
          <legend class="field-label">Ads you won’t show</legend>
          <div class="flex flex-wrap gap-2">
            <label
              v-for="(label, key) in CONTENT_RULES"
              :key="key"
              class="flex cursor-pointer items-center gap-2 rounded-lg border px-3 py-2 text-sm font-semibold transition has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-plum-700"
              :class="form.content_rules.includes(key) ? 'border-danger/40 bg-danger/8 text-danger' : 'border-line-strong text-ink-soft hover:border-plum-300'"
            >
              <input v-model="form.content_rules" type="checkbox" :value="key" class="sr-only" />
              <AppIcon :name="form.content_rules.includes(key) ? 'x' : 'plus'" :size="14" />
              {{ label }}
            </label>
          </div>
        </fieldset>
      </div>

      <div class="border-t border-divider px-5 py-4 sm:px-6">
        <AlertBox v-if="formError && !Object.keys(errors).length" class="mb-4">{{ formError }}</AlertBox>
        <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
          <button type="button" class="btn-outline" @click="showForm = false">Cancel</button>
          <button class="btn-primary" :disabled="busy">{{ busy ? 'Saving…' : 'Save as draft' }}</button>
        </div>
      </div>
    </form>

    <AlertBox v-if="actionError" class="mb-4">{{ actionError }}</AlertBox>

    <AlertBox v-if="loadError" title="Couldn’t load your screens">
      {{ loadError }}
      <template #action><button type="button" class="link shrink-0 text-sm" @click="load">Try again</button></template>
    </AlertBox>
    <LoadingCards v-else-if="loading" :count="2" cols="sm:grid-cols-2" />
    <EmptyState
      v-else-if="!screens.length && !showForm"
      icon="screen"
      title="Add your first screen"
      body="Tell advertisers where it is, its size, when it’s on and what a slot costs."
    >
      <button type="button" class="btn-primary" @click="openForm"><AppIcon name="plus" :size="18" /> Add screen</button>
    </EmptyState>

    <ul v-else class="grid gap-4 sm:grid-cols-2">
      <li v-for="s in screens" :key="s.id" class="card flex min-w-0 flex-col p-5 sm:p-6">
        <div class="flex items-start justify-between gap-3">
          <div class="min-w-0">
            <h2 class="font-extrabold">{{ s.name }}</h2>
            <p v-if="s.address" class="mt-0.5 truncate text-sm text-subtle">{{ s.address }}</p>
          </div>
          <StatusBadge :status="s.status" />
        </div>
        <dl class="mt-4 grid grid-cols-3 gap-3 rounded-xl bg-canvas p-3 text-sm">
          <div><dt class="text-xs text-subtle">Size</dt><dd class="font-bold">{{ s.width_px }}×{{ s.height_px }}</dd></div>
          <div><dt class="text-xs text-subtle">Slots</dt><dd class="font-bold">{{ s.slots_per_loop }} × {{ s.spot_seconds }} s</dd></div>
          <div><dt class="text-xs text-subtle">Per play</dt><dd class="price">{{ formatRupees(s.price_per_play) }}</dd></div>
        </dl>
        <div class="mt-auto flex flex-wrap gap-2 pt-5">
          <button
            v-if="s.status !== 'active'"
            type="button"
            class="btn-plum btn-sm"
            :disabled="updating === s.id"
            @click="setStatus(s, 'active')"
          >
            <AppIcon name="play" :size="15" /> Publish
          </button>
          <button v-else type="button" class="btn-outline btn-sm" :disabled="updating === s.id" @click="setStatus(s, 'paused')">
            Pause
          </button>
          <RouterLink v-if="s.status === 'active'" :to="`/screens/${s.id}`" class="btn-ghost btn-sm">View public page</RouterLink>
        </div>
      </li>
    </ul>
  </div>
</template>
