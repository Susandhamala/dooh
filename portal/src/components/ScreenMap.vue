<script setup lang="ts">
import L from 'leaflet'
import { onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { formatRupees } from '@/lib/format'
import type { Screen } from '@/lib/types'

const props = withDefaults(defineProps<{ screens: Screen[]; fit?: boolean; height?: string }>(), {
  fit: true,
  height: 'h-80 sm:h-[420px]',
})
const emit = defineEmits<{ select: [id: string]; moved: [bbox: string] }>()

const el = ref<HTMLElement>()
let map: L.Map | undefined
let layer: L.LayerGroup | undefined
let fitted = false

// Leaflet draws on a canvas/SVG layer, so it needs real colours rather than token names.
const css = (name: string) => getComputedStyle(document.documentElement).getPropertyValue(name).trim()

function draw() {
  if (!map || !layer) return
  layer.clearLayers()
  const plum = css('--color-plum-700') || '#6c39a9'
  for (const s of props.screens) {
    L.circleMarker([s.lat, s.lng], { radius: 9, color: '#fff', weight: 2, fillColor: plum, fillOpacity: 0.95 })
      .bindTooltip(`<strong>${escapeHtml(s.name)}</strong><br>${formatRupees(s.price_per_play)} per play`)
      .on('click', () => emit('select', s.id))
      .addTo(layer)
  }
  // Zoom to the screens once, so the first view shows all of them.
  if (props.fit && !fitted && props.screens.length) {
    fitted = true
    const bounds = L.latLngBounds(props.screens.map((s) => [s.lat, s.lng] as [number, number]))
    map.fitBounds(bounds, { padding: [40, 40], maxZoom: 15 })
  }
}

function escapeHtml(text: string): string {
  return text.replace(/[&<>"']/g, (c) => `&#${c.charCodeAt(0)};`)
}

onMounted(() => {
  map = L.map(el.value!, { scrollWheelZoom: false }).setView([27.7, 85.32], 12) // Kathmandu
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap contributors',
    maxZoom: 19,
  }).addTo(map)
  layer = L.layerGroup().addTo(map)
  // Wheel zoom only after a click, so scrolling the page never gets stuck in the map.
  map.on('click', () => map?.scrollWheelZoom.enable())
  map.on('mouseout', () => map?.scrollWheelZoom.disable())
  map.on('moveend', () => emit('moved', map!.getBounds().toBBoxString()))
  draw()
})

watch(() => props.screens, draw)
onBeforeUnmount(() => map?.remove())
</script>

<template>
  <div ref="el" class="w-full overflow-hidden rounded-2xl border border-line" :class="height" role="region" aria-label="Map of screens" />
</template>
