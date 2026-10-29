<script setup lang="ts">
import L from 'leaflet'
import { onBeforeUnmount, onMounted, ref, watch } from 'vue'
import type { Screen } from '@/lib/types'

const props = defineProps<{ screens: Screen[] }>()
const emit = defineEmits<{ select: [id: string]; moved: [bbox: string] }>()

const el = ref<HTMLElement>()
let map: L.Map | undefined
let layer: L.LayerGroup | undefined

function draw() {
  if (!map || !layer) return
  layer.clearLayers()
  for (const s of props.screens) {
    L.circleMarker([s.lat, s.lng], { radius: 8, color: '#0f766e', fillOpacity: 0.8 })
      .bindTooltip(`${s.name} · NPR ${s.slot_price}/day`)
      .on('click', () => emit('select', s.id))
      .addTo(layer)
  }
}

onMounted(() => {
  map = L.map(el.value!).setView([27.7, 85.32], 12) // Kathmandu
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap contributors',
  }).addTo(map)
  layer = L.layerGroup().addTo(map)
  map.on('moveend', () => emit('moved', map!.getBounds().toBBoxString()))
  draw()
})

watch(() => props.screens, draw)
onBeforeUnmount(() => map?.remove())
</script>

<template>
  <div ref="el" class="h-80 w-full rounded-lg border border-slate-200 sm:h-96" />
</template>
