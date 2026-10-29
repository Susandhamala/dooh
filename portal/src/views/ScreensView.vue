<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { errorMessage, get } from '@/lib/http'

interface Screen {
  id: string
  name: string
  width_px: number
  height_px: number
  slot_price: string
}

const screens = ref<Screen[]>([])
const error = ref<string | null>(null)

onMounted(async () => {
  try {
    screens.value = (await get<Screen[]>('/screens')).data
  } catch (err) {
    error.value = errorMessage(err)
  }
})
</script>

<template>
  <section>
    <h1 class="text-2xl font-bold">Screens</h1>
    <p v-if="error" class="mt-4 text-sm text-red-600">{{ error }}</p>
    <ul class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
      <li v-for="s in screens" :key="s.id" class="rounded-lg border border-slate-200 bg-white p-4">
        <h2 class="font-semibold">{{ s.name }}</h2>
        <p class="text-sm text-slate-600">{{ s.width_px }}×{{ s.height_px }} · NPR {{ s.slot_price }} / slot</p>
      </li>
    </ul>
  </section>
</template>
