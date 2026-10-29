<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import ScreenMap from '@/components/ScreenMap.vue'
import { errorMessage, get } from '@/lib/http'
import type { Screen } from '@/lib/types'

const router = useRouter()
const screens = ref<Screen[]>([])
const error = ref<string | null>(null)

async function load(bbox?: string) {
  try {
    screens.value = (await get<Screen[]>('/screens', bbox ? { bbox } : undefined)).data
  } catch (err) {
    error.value = errorMessage(err)
  }
}

onMounted(() => load())
</script>

<template>
  <section>
    <h1 class="text-2xl font-bold">Screens</h1>
    <p class="mt-1 text-sm text-slate-600">Move the map to find screens near your customers.</p>
    <ScreenMap class="mt-6" :screens="screens" @moved="load" @select="(id) => router.push(`/screens/${id}`)" />
    <p v-if="error" class="mt-4 text-sm text-red-600">{{ error }}</p>
    <p v-else-if="!screens.length" class="mt-6 text-slate-600">No screens in this area yet.</p>
    <ul class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
      <li v-for="s in screens" :key="s.id">
        <RouterLink :to="`/screens/${s.id}`" class="card block hover:border-brand">
          <h2 class="font-semibold">{{ s.name }}</h2>
          <p class="text-sm text-slate-600">
            {{ s.width_px }}×{{ s.height_px }} {{ s.orientation }} · {{ s.spot_seconds }} s spots
          </p>
          <p class="mt-2 text-sm font-medium">NPR {{ s.slot_price }} / day</p>
        </RouterLink>
      </li>
    </ul>
  </section>
</template>
