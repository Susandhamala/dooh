<script setup lang="ts">
import L from 'leaflet'
import { onBeforeUnmount, onMounted, ref, watch } from 'vue'

/** Click or drag the pin to set where a screen stands. */
const lat = defineModel<number>('lat', { required: true })
const lng = defineModel<number>('lng', { required: true })

const el = ref<HTMLElement>()
let map: L.Map | undefined
let marker: L.CircleMarker | undefined

function place(ll: L.LatLng) {
  lat.value = Number(ll.lat.toFixed(6))
  lng.value = Number(ll.lng.toFixed(6))
}

onMounted(() => {
  const plum = getComputedStyle(document.documentElement).getPropertyValue('--color-plum-700').trim() || '#6c39a9'
  map = L.map(el.value!, { scrollWheelZoom: false }).setView([lat.value, lng.value], 14)
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap contributors',
    maxZoom: 19,
  }).addTo(map)
  marker = L.circleMarker([lat.value, lng.value], { radius: 10, color: '#fff', weight: 3, fillColor: plum, fillOpacity: 1 }).addTo(map)
  map.on('click', (e: L.LeafletMouseEvent) => place(e.latlng))
})

// Typed coordinates move the pin too.
watch([lat, lng], ([a, b]) => {
  if (!Number.isFinite(a) || !Number.isFinite(b)) return
  marker?.setLatLng([a, b])
  if (map && !map.getBounds().contains([a, b])) map.panTo([a, b])
})

onBeforeUnmount(() => map?.remove())
</script>

<template>
  <div ref="el" class="h-56 w-full overflow-hidden rounded-xl border border-line-strong" role="region" aria-label="Click the map to place the screen" />
</template>
