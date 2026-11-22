<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import AlertBox from '@/components/ui/AlertBox.vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import LoadingCards from '@/components/ui/LoadingCards.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import { formatRupees } from '@/lib/format'
import { errorMessage, get } from '@/lib/http'
import type { OwnerOverview, Screen } from '@/lib/types'
import { useOrgStore } from '@/stores/orgs'

/** Screen owner's home: how their screens and players are doing, and a way into each schedule. */
const orgs = useOrgStore()
const orgId = ref<string>('')
const overview = ref<OwnerOverview | null>(null)
const screens = ref<Screen[]>([])
const loading = ref(true)
const error = ref<string | null>(null)

const org = computed(() => orgs.owners.find((o) => o.id === orgId.value) ?? null)

async function load() {
  if (!orgId.value) return
  loading.value = true
  error.value = null
  try {
    const [o, s] = await Promise.all([
      get<OwnerOverview>(`/orgs/${orgId.value}/owner/overview`),
      get<Screen[]>(`/orgs/${orgId.value}/screens`),
    ])
    overview.value = o.data
    screens.value = s.data
  } catch (e) {
    error.value = errorMessage(e)
  } finally {
    loading.value = false
  }
}

onMounted(async () => {
  await orgs.load()
  orgId.value = orgs.owners[0]?.id ?? ''
  if (!orgId.value) loading.value = false
})
watch(orgId, load)

const tiles = computed(() => {
  const o = overview.value
  if (!o) return []
  return [
    { label: 'Waiting for you', value: String(o.waiting), note: o.waiting ? 'open a screen to approve' : 'nothing to approve', icon: 'inbox' as const },
    { label: 'Screens', value: String(o.screens), note: `${o.published} on the map`, icon: 'screen' as const },
    { label: 'Players online', value: `${o.players_online} / ${o.players}`, note: o.players ? 'Raspberry Pi connected' : 'none connected yet', icon: 'play' as const },
    { label: 'Ads today', value: String(o.today), note: `${o.live} live · ${o.upcoming} coming up`, icon: 'calendar' as const },
    { label: 'Earned', value: formatRupees(o.earned), note: 'from plays shown', icon: 'wallet' as const },
  ]
})
</script>

<template>
  <main class="shell py-8 sm:py-10">
    <PageHeader eyebrow="Screen owner" title="Owner dashboard" lede="Your screens, their players and the ads booked on them.">
      <template v-if="orgs.owners.length > 1" #actions>
        <select v-model="orgId" class="input w-auto" aria-label="Organisation">
          <option v-for="o in orgs.owners" :key="o.id" :value="o.id">{{ o.name }}</option>
        </select>
      </template>
      <template v-else-if="org" #actions>
        <RouterLink :to="`/orgs/${org.id}/screens`" class="btn-outline"><AppIcon name="plus" :size="18" /> Add or edit screens</RouterLink>
      </template>
    </PageHeader>

    <EmptyState
      v-if="!loading && !orgs.owners.length"
      icon="screen"
      title="You don't list any screens yet"
      body="Create a screen owner organisation, add your screen and connect its Raspberry Pi. Ads booked on it then play over HDMI."
    >
      <RouterLink to="/orgs" class="btn-primary mt-5">Become a screen owner</RouterLink>
    </EmptyState>

    <AlertBox v-else-if="error" tone="danger" title="Couldn't load your dashboard">
      {{ error }} <button type="button" class="link ml-2" @click="load">Try again</button>
    </AlertBox>

    <LoadingCards v-else-if="loading" />

    <template v-else>
      <section class="grid grid-cols-2 gap-3 lg:grid-cols-5" aria-label="Summary">
        <div v-for="t in tiles" :key="t.label" class="card p-4 sm:p-5">
          <p class="flex items-center gap-2 text-sm font-semibold text-muted"><AppIcon :name="t.icon" :size="16" /> {{ t.label }}</p>
          <p class="mt-2 text-2xl font-extrabold text-plum-900 [font-variant-numeric:tabular-nums]">{{ t.value }}</p>
          <p class="mt-0.5 text-xs text-subtle">{{ t.note }}</p>
        </div>
      </section>

      <h2 class="h-section mt-8 mb-3">Screens</h2>
      <EmptyState v-if="!screens.length" icon="screen" title="No screens yet" body="Add your first screen, then connect its player.">
        <RouterLink :to="`/orgs/${orgId}/screens`" class="btn-primary mt-5">Add a screen</RouterLink>
      </EmptyState>
      <ul v-else class="grid gap-3">
        <li v-for="s in screens" :key="s.id" class="card flex flex-wrap items-center gap-4 p-4 sm:p-5">
          <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-teal-50 text-teal-dark"><AppIcon name="screen" /></span>
          <div class="min-w-0 flex-1">
            <p class="font-bold text-ink">{{ s.name }}</p>
            <p class="text-sm text-muted">
              {{ s.address ?? 'No address' }} · {{ s.slots_per_loop }} slots of {{ s.spot_seconds }} s · {{ formatRupees(s.price_per_play) }} a play
            </p>
          </div>
          <StatusBadge :status="s.status" />
          <span
            class="badge"
            :class="s.device?.online ? 'bg-success/10 text-success' : s.device ? 'bg-warning/10 text-warning' : 'bg-divider text-muted'"
          >
            <span class="h-2 w-2 rounded-full" :class="s.device?.online ? 'bg-success' : s.device ? 'bg-warning' : 'bg-subtle'" />
            {{ s.device?.online ? 'Player online' : s.device ? 'Player offline' : 'No player' }}
          </span>
          <RouterLink :to="`/orgs/${orgId}/screens/${s.id}/schedule`" class="btn-primary">
            <AppIcon name="calendar" :size="18" /> Booked slots
          </RouterLink>
        </li>
      </ul>
    </template>
  </main>
</template>
