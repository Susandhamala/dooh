<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { errorMessage, fieldErrors, get, mfaNeeded, patch, post } from '@/lib/http'
import { CONTENT_RULES, type Screen } from '@/lib/types'

const props = defineProps<{ org: string }>()
const router = useRouter()

const screens = ref<Screen[]>([])
const error = ref<string | null>(null)
const errors = ref<Record<string, string>>({})
const showForm = ref(false)
const blank = () => ({
  name: '',
  address: '',
  lat: 27.7041,
  lng: 85.3075,
  width_px: 1920,
  height_px: 1080,
  orientation: 'landscape',
  loop_seconds: 120,
  spot_seconds: 10,
  slot_price: 500,
  content_rules: [] as string[],
})
const form = ref(blank())

async function load() {
  try {
    screens.value = (await get<Screen[]>(`/orgs/${props.org}/screens`)).data
  } catch (err) {
    error.value = errorMessage(err)
  }
}

/** Owner changes need a two-factor session; send the user to get one and come back. */
function handle(err: unknown) {
  const mfa = mfaNeeded(err)
  if (mfa) {
    router.push({ name: 'security', query: { next: `/orgs/${props.org}/screens` } })
    return
  }
  errors.value = fieldErrors(err)
  error.value = errorMessage(err)
}

async function create() {
  errors.value = {}
  error.value = null
  try {
    await post(`/orgs/${props.org}/screens`, form.value)
    form.value = blank()
    showForm.value = false
    await load()
  } catch (err) {
    handle(err)
  }
}

async function setStatus(s: Screen, status: Screen['status']) {
  try {
    await patch(`/orgs/${props.org}/screens/${s.id}`, { status })
    await load()
  } catch (err) {
    handle(err)
  }
}

onMounted(load)
</script>

<template>
  <section>
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-bold">My screens</h1>
      <button class="btn" @click="showForm = !showForm">Add screen</button>
    </div>

    <form v-if="showForm" class="card mt-6 grid gap-4 sm:grid-cols-4" @submit.prevent="create">
      <div class="sm:col-span-2">
        <label class="label" for="s-name">Name</label>
        <input id="s-name" v-model="form.name" class="input" placeholder="New Road Junction" required />
        <p v-if="errors.name" class="field-error">{{ errors.name }}</p>
      </div>
      <div class="sm:col-span-2">
        <label class="label" for="s-address">Address</label>
        <input id="s-address" v-model="form.address" class="input" />
      </div>
      <div>
        <label class="label" for="s-lat">Latitude</label>
        <input id="s-lat" v-model.number="form.lat" type="number" step="0.000001" class="input" />
        <p v-if="errors.lat" class="field-error">{{ errors.lat }}</p>
      </div>
      <div>
        <label class="label" for="s-lng">Longitude</label>
        <input id="s-lng" v-model.number="form.lng" type="number" step="0.000001" class="input" />
        <p v-if="errors.lng" class="field-error">{{ errors.lng }}</p>
      </div>
      <div>
        <label class="label" for="s-w">Width (px)</label>
        <input id="s-w" v-model.number="form.width_px" type="number" class="input" />
      </div>
      <div>
        <label class="label" for="s-h">Height (px)</label>
        <input id="s-h" v-model.number="form.height_px" type="number" class="input" />
      </div>
      <div>
        <label class="label" for="s-o">Orientation</label>
        <select id="s-o" v-model="form.orientation" class="input">
          <option value="landscape">Landscape</option>
          <option value="portrait">Portrait</option>
        </select>
      </div>
      <div>
        <label class="label" for="s-spot">Spot length (s)</label>
        <input id="s-spot" v-model.number="form.spot_seconds" type="number" class="input" />
      </div>
      <div>
        <label class="label" for="s-loop">Loop length (s)</label>
        <input id="s-loop" v-model.number="form.loop_seconds" type="number" class="input" />
        <p v-if="errors.loop_seconds" class="field-error">{{ errors.loop_seconds }}</p>
      </div>
      <div>
        <label class="label" for="s-price">Price per slot per day (NPR)</label>
        <input id="s-price" v-model.number="form.slot_price" type="number" class="input" />
      </div>
      <fieldset class="sm:col-span-4">
        <legend class="label">Content you won't show</legend>
        <div class="flex flex-wrap gap-4">
          <label v-for="(label, key) in CONTENT_RULES" :key="key" class="flex items-center gap-2 text-sm">
            <input v-model="form.content_rules" type="checkbox" :value="key" />
            {{ label }}
          </label>
        </div>
      </fieldset>
      <div class="sm:col-span-4"><button class="btn">Save screen</button></div>
    </form>

    <p v-if="error && !Object.keys(errors).length" class="mt-4 text-sm text-red-600">{{ error }}</p>

    <ul class="mt-6 grid gap-4 sm:grid-cols-2">
      <li v-for="s in screens" :key="s.id" class="card">
        <div class="flex items-center gap-2">
          <h2 class="font-semibold">{{ s.name }}</h2>
          <span class="badge" :class="s.status === 'active' ? 'bg-emerald-100 text-emerald-800' : ''">{{ s.status }}</span>
        </div>
        <p class="mt-1 text-sm text-slate-600">
          {{ s.width_px }}×{{ s.height_px }} · {{ s.slots_per_loop }} slots · NPR {{ s.slot_price }}/day
        </p>
        <div class="mt-4 flex gap-2">
          <button v-if="s.status !== 'active'" class="btn" @click="setStatus(s, 'active')">Publish</button>
          <button v-else class="btn-secondary" @click="setStatus(s, 'paused')">Pause</button>
        </div>
      </li>
    </ul>
  </section>
</template>
