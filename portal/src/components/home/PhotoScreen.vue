<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue'
import { quadTransform } from '@/lib/perspective'

/**
 * A photo with our own ads laid onto the screens in it. Each screen gives its four corners
 * (fractions of the photo, clockwise from top left; they may run past the edge) and the size
 * its content is drawn at. Fill each screen through the `screen-0`, `screen-1`… slots.
 * The box may be given another shape by CSS; the photo is then cropped like object-cover and the
 * screens follow it.
 */
const props = defineProps<{
  src: string
  alt: string
  width: number
  height: number
  /** led: draw LED pixels (screens) or not (printed boards). shade: darken 0–1 to match the light. */
  screens?: { corners: [number, number][]; w: number; h: number; led?: boolean; shade?: number }[]
}>()

const box = ref<HTMLElement>()
const transforms = ref<string[]>([])
let ro: ResizeObserver | undefined

function place() {
  const el = box.value
  if (!el) return
  // The photo fills the box like object-cover (scaled up and centred), so map corners the same way.
  const w = el.offsetWidth
  const h = el.offsetHeight
  const scale = Math.max(w / props.width, h / props.height)
  const dx = (w - props.width * scale) / 2
  const dy = (h - props.height * scale) / 2
  transforms.value = (props.screens ?? []).map((s) =>
    quadTransform(s.w, s.h, s.corners.map(([x, y]) => [dx + x * props.width * scale, dy + y * props.height * scale])),
  )
}
onMounted(() => {
  place()
  ro = new ResizeObserver(place)
  if (box.value) ro.observe(box.value)
})
onBeforeUnmount(() => ro?.disconnect())
</script>

<template>
  <div ref="box" class="relative overflow-hidden" :style="{ aspectRatio: `${width} / ${height}` }">
    <img :src="src" :alt="alt" :width="width" :height="height" class="absolute inset-0 h-full w-full object-cover" loading="lazy" />
    <div
      v-for="(s, i) in screens ?? []"
      :key="i"
      class="absolute top-0 left-0 origin-top-left overflow-hidden [backface-visibility:hidden]"
      :style="{ width: `${s.w}px`, height: `${s.h}px`, transform: transforms[i] }"
      aria-hidden="true"
    >
      <slot :name="`screen-${i}`" />
      <!-- LED pixels, so it reads as a lit screen -->
      <div v-if="s.led !== false" class="pointer-events-none absolute inset-0 bg-[radial-gradient(rgb(0_0_0/0.25)_1px,transparent_1.6px)] bg-[length:5px_5px]" />
      <div v-if="s.shade" class="pointer-events-none absolute inset-0 bg-black" :style="{ opacity: s.shade }" />
    </div>
    <slot />
  </div>
</template>
