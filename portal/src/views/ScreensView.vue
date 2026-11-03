<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import ScreenCard from '@/components/ScreenCard.vue'
import ScreenMap from '@/components/ScreenMap.vue'
import AlertBox from '@/components/ui/AlertBox.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import LoadingCards from '@/components/ui/LoadingCards.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import { errorMessage, get } from '@/lib/http'
import type { Screen } from '@/lib/types'

const router = useRouter()
const screens = ref<Screen[]>([])
const total = ref(0)
const loading = ref(true)
const error = ref<string | null>(null)
const lastBbox = ref<string | undefined>()

let seq = 0
let timer: ReturnType<typeof setTimeout> | undefined

async function load(bbox?: string) {
  const mine = ++seq
  lastBbox.value = bbox
  error.value = null
  try {
    const res = await get<Screen[]>('/screens', bbox ? { bbox } : undefined)
    // A slower, older request must not overwrite a newer map area.
    if (mine === seq) {
      screens.value = res.data
      total.value = typeof res.meta?.total === 'number' ? res.meta.total : res.data.length
    }
  } catch (err) {
    if (mine === seq) error.value = errorMessage(err)
  } finally {
    if (mine === seq) loading.value = false
  }
}

/** The map fires moveend for every pan and zoom; wait until it settles. */
function onMoved(bbox: string) {
  clearTimeout(timer)
  timer = setTimeout(() => load(bbox), 300)
}

onMounted(() => load())
onBeforeUnmount(() => clearTimeout(timer))
</script>

<template>
  <div class="shell py-8 sm:py-10">
    <PageHeader
      eyebrow="Find screens"
      title="Screens near your customers"
      lede="Move the map to an area. The list below shows the screens in view."
    />

    <ScreenMap :screens="screens" @moved="onMoved" @select="(id) => router.push(`/screens/${id}`)" />

    <div class="mt-8 flex items-baseline justify-between gap-4">
      <h2 class="h-section">
        <template v-if="loading">Loading screens…</template>
        <template v-else>{{ total }} {{ total === 1 ? 'screen' : 'screens' }} in this area</template>
      </h2>
      <span v-if="!loading && total > screens.length" class="text-sm text-subtle">
        Showing {{ screens.length }} · zoom in to narrow it down
      </span>
    </div>

    <div class="mt-4">
      <AlertBox v-if="error" title="Couldn’t load screens">
        {{ error }}
        <template #action>
          <button type="button" class="link shrink-0 text-sm" @click="load(lastBbox)">Try again</button>
        </template>
      </AlertBox>
      <LoadingCards v-else-if="loading" :count="3" />
      <EmptyState
        v-else-if="!screens.length"
        icon="map"
        title="No screens here yet"
        body="Zoom out or move the map to another part of the city."
      />
      <ul v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <li v-for="s in screens" :key="s.id" class="min-w-0"><ScreenCard :screen="s" /></li>
      </ul>
    </div>
  </div>
</template>
