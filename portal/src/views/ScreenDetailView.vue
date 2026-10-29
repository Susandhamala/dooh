<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { isoDay } from '@/lib/dates'
import { errorMessage, fieldErrors, get, post } from '@/lib/http'
import { CONTENT_RULES, type Booking, type Campaign, type Screen } from '@/lib/types'
import { useAuthStore } from '@/stores/auth'
import { useOrgStore } from '@/stores/orgs'

const props = defineProps<{ id: string }>()
const auth = useAuthStore()
const orgs = useOrgStore()
const router = useRouter()

const screen = ref<Screen | null>(null)
const days = ref<Record<string, number>>({})
const range = ref({ start_date: isoDay(1), end_date: isoDay(7) })
const error = ref<string | null>(null)

const orgId = ref('')
const campaigns = ref<Campaign[]>([])
const campaignId = ref('')
const bookingErrors = ref<Record<string, string>>({})
const bookingError = ref<string | null>(null)
const busy = ref(false)

const minFree = computed(() => {
  const values = Object.values(days.value)
  return values.length ? Math.min(...values) : 0
})

async function loadAvailability() {
  if (range.value.end_date < range.value.start_date) return
  try {
    days.value = (
      await get<{ days: Record<string, number> }>(`/screens/${props.id}/availability`, {
        from: range.value.start_date,
        to: range.value.end_date,
      })
    ).data.days
  } catch (err) {
    error.value = errorMessage(err)
  }
}

onMounted(async () => {
  try {
    screen.value = (await get<Screen>(`/screens/${props.id}`)).data
    await loadAvailability()
    if (auth.isLoggedIn) {
      await orgs.load()
      orgId.value = orgs.advertisers[0]?.id ?? ''
    }
  } catch (err) {
    error.value = errorMessage(err)
  }
})

watch(range, loadAvailability, { deep: true })
watch(orgId, async (id) => {
  campaigns.value = id ? (await get<Campaign[]>(`/orgs/${id}/campaigns`)).data : []
  campaignId.value = campaigns.value[0]?.id ?? ''
})

async function book() {
  busy.value = true
  bookingError.value = null
  bookingErrors.value = {}
  try {
    await post<Booking>(`/orgs/${orgId.value}/campaigns/${campaignId.value}/bookings`, {
      screen_id: props.id,
      ...range.value,
    })
    router.push(`/orgs/${orgId.value}/campaigns/${campaignId.value}`)
  } catch (err) {
    bookingError.value = errorMessage(err)
    bookingErrors.value = fieldErrors(err)
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <section v-if="screen" class="grid gap-6 lg:grid-cols-3">
    <div class="lg:col-span-2">
      <h1 class="text-2xl font-bold">{{ screen.name }}</h1>
      <p v-if="screen.address" class="text-slate-600">{{ screen.address }}</p>
      <dl class="card mt-6 grid grid-cols-2 gap-4 text-sm sm:grid-cols-3">
        <div><dt class="text-slate-500">Resolution</dt><dd>{{ screen.width_px }}×{{ screen.height_px }}</dd></div>
        <div><dt class="text-slate-500">Orientation</dt><dd class="capitalize">{{ screen.orientation }}</dd></div>
        <div><dt class="text-slate-500">Hours</dt><dd>{{ screen.opens_at }}–{{ screen.closes_at }}</dd></div>
        <div>
          <dt class="text-slate-500">Loop</dt>
          <dd>{{ screen.spot_seconds }} s spot in a {{ screen.loop_seconds }} s loop</dd>
        </div>
        <div><dt class="text-slate-500">Price</dt><dd>NPR {{ screen.slot_price }} / day</dd></div>
        <div>
          <dt class="text-slate-500">Plays per hour</dt>
          <dd>{{ Math.floor(3600 / screen.loop_seconds) }}</dd>
        </div>
      </dl>
      <p v-if="screen.description" class="mt-4 text-sm whitespace-pre-line">{{ screen.description }}</p>
      <div v-if="screen.content_rules?.length" class="mt-4 flex flex-wrap gap-2">
        <span v-for="r in screen.content_rules" :key="r" class="badge">{{ CONTENT_RULES[r] ?? r }}</span>
      </div>
    </div>

    <aside class="card h-fit space-y-4">
      <h2 class="font-semibold">Book a slot</h2>
      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="label" for="from">From</label>
          <input id="from" v-model="range.start_date" type="date" class="input" :min="isoDay(0)" />
        </div>
        <div>
          <label class="label" for="to">To</label>
          <input id="to" v-model="range.end_date" type="date" class="input" :min="range.start_date" />
        </div>
      </div>
      <p class="text-sm" :class="minFree ? 'text-emerald-700' : 'text-red-600'">
        {{ minFree ? `${minFree} of ${screen.slots_per_loop} slots free every day` : 'Fully booked on some days' }}
      </p>

      <template v-if="!auth.isLoggedIn">
        <RouterLink :to="{ name: 'login', query: { next: `/screens/${id}` } }" class="btn w-full">Sign in to book</RouterLink>
      </template>
      <template v-else-if="!orgs.advertisers.length">
        <p class="text-sm text-slate-600">Create an advertiser organisation first.</p>
        <RouterLink to="/orgs" class="btn-secondary w-full">My organisations</RouterLink>
      </template>
      <template v-else>
        <div v-if="orgs.advertisers.length > 1">
          <label class="label" for="org">Organisation</label>
          <select id="org" v-model="orgId" class="input">
            <option v-for="o in orgs.advertisers" :key="o.id" :value="o.id">{{ o.name }}</option>
          </select>
        </div>
        <div v-if="campaigns.length">
          <label class="label" for="campaign">Campaign</label>
          <select id="campaign" v-model="campaignId" class="input">
            <option v-for="c in campaigns" :key="c.id" :value="c.id">{{ c.name }} ({{ c.start_date }} – {{ c.end_date }})</option>
          </select>
        </div>
        <RouterLink v-else :to="`/orgs/${orgId}/campaigns`" class="btn-secondary w-full">Create a campaign first</RouterLink>
        <p v-if="bookingErrors.start_date" class="field-error">{{ bookingErrors.start_date }}</p>
        <p v-else-if="bookingError" class="text-sm text-red-600">{{ bookingError }}</p>
        <button v-if="campaigns.length" class="btn w-full" :disabled="busy || !minFree" @click="book">Hold this slot</button>
        <p class="text-xs text-slate-500">Holds last 15 minutes while you upload your ad.</p>
      </template>
    </aside>
  </section>
  <p v-else-if="error" class="text-red-600">{{ error }}</p>
</template>
