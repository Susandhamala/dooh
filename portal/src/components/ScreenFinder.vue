<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import ScreenCard from '@/components/ScreenCard.vue'
import ScreenMap from '@/components/ScreenMap.vue'
import AlertBox from '@/components/ui/AlertBox.vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import { errorMessage, get } from '@/lib/http'
import type { Screen } from '@/lib/types'

/**
 * The map of screens with search and sort, and the screens in view as cards: a sliding row
 * (home page) or a full grid (screens page). Hovering a card lights its pin.
 */
const props = withDefaults(defineProps<{ layout?: 'row' | 'grid' }>(), { layout: 'row' })
const router = useRouter()

const inView = ref<Screen[]>([])
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
    if (mine === seq) inView.value = res.data // a slower, older map area must not win
  } catch (err) {
    if (mine === seq) error.value = errorMessage(err)
  } finally {
    if (mine === seq) loading.value = false
  }
}
function onMoved(bbox: string) {
  clearTimeout(timer)
  timer = setTimeout(() => load(bbox), 300)
}
onMounted(() => load())
onBeforeUnmount(() => clearTimeout(timer))

type Sort = 'near' | 'cheap' | 'often'
const SORTS: { value: Sort; label: string }[] = [
  { value: 'near', label: 'All' },
  { value: 'cheap', label: 'Cheapest' },
  { value: 'often', label: 'Most shows' },
]
type Shape = 'any' | 'wide' | 'tall'
const SHAPES: { value: Shape; label: string }[] = [
  { value: 'any', label: 'Any shape' },
  { value: 'wide', label: 'Wide' },
  { value: 'tall', label: 'Tall' },
]
const search = ref('')
const sort = ref<Sort>('near')
const shape = ref<Shape>('any')
const hovered = ref<string | null>(null)
const shown = computed(() => {
  const q = search.value.trim().toLowerCase()
  let list = q ? inView.value.filter((s) => `${s.name} ${s.address ?? ''}`.toLowerCase().includes(q)) : [...inView.value]
  if (shape.value !== 'any') list = list.filter((s) => (s.width_px >= s.height_px) === (shape.value === 'wide'))
  if (sort.value === 'cheap') list.sort((a, b) => Number(a.price_per_play) - Number(b.price_per_play))
  if (sort.value === 'often') list.sort((a, b) => b.plays_per_hour - a.plays_per_hour)
  return list
})

const row = ref<HTMLElement>()
const slide = (dir: number) => row.value?.scrollBy({ left: dir * Math.min(340, row.value.clientWidth * 0.8), behavior: 'smooth' })
const count = computed(() => (loading.value ? 'Loading…' : `${shown.value.length} ${shown.value.length === 1 ? 'screen' : 'screens'} in view`))
</script>

<template>
  <div>
    <!-- isolate: the map's layers and the floating panel stay under the sticky header. -->
    <div class="relative isolate">
      <ScreenMap
        :screens="shown"
        :highlight="hovered"
        :height="props.layout === 'grid' ? 'h-[300px] sm:h-[400px]' : 'h-[300px] sm:h-[420px] lg:h-[460px]'"
        rounded="rounded-3xl"
        @moved="onMoved"
        @select="(id) => router.push(`/screens/${id}`)"
      />
      <div class="mt-3 flex flex-col gap-2 sm:absolute sm:top-4 sm:right-4 sm:z-[500] sm:mt-0 sm:flex-row sm:items-center sm:rounded-2xl sm:bg-white/90 sm:p-1.5 sm:shadow-[0_10px_30px_-10px_rgb(38_22_49/0.35)] sm:backdrop-blur">
        <label class="relative block sm:w-56">
          <span class="sr-only">Search screens</span>
          <AppIcon name="search" :size="17" class="absolute top-1/2 left-3 -translate-y-1/2 text-subtle" />
          <input v-model="search" type="search" class="w-full rounded-xl border border-line bg-white py-2 pr-3 pl-9 text-sm outline-none focus:border-plum-700 sm:border-0 sm:bg-transparent" placeholder="Search a place" />
        </label>
        <div class="flex gap-1 rounded-xl bg-canvas p-1" role="radiogroup" aria-label="Sort screens">
          <button
            v-for="s in SORTS"
            :key="s.value"
            type="button"
            role="radio"
            :aria-checked="sort === s.value"
            class="flex-1 rounded-lg px-3 py-1.5 text-xs font-semibold whitespace-nowrap transition"
            :class="sort === s.value ? 'bg-white text-ink shadow-sm' : 'text-subtle hover:text-ink'"
            @click="sort = s.value"
          >
            {{ s.label }}
          </button>
        </div>
      </div>
      <span class="pointer-events-none absolute bottom-4 left-4 z-[500] hidden rounded-full bg-plum-950/85 px-3 py-1.5 text-xs font-semibold text-white backdrop-blur sm:block">{{ count }}</span>
    </div>

    <div class="mt-8 flex flex-wrap items-center justify-between gap-3">
      <p class="text-sm font-medium text-subtle">{{ search ? `Results for “${search.trim()}”` : count }}</p>
      <div v-if="props.layout === 'row'" class="flex gap-2">
        <button type="button" class="grid h-10 w-10 place-items-center rounded-full border border-line text-ink-soft transition hover:border-plum-700 hover:text-plum-700" aria-label="Previous screens" @click="slide(-1)">
          <AppIcon name="back" :size="18" />
        </button>
        <button type="button" class="grid h-10 w-10 place-items-center rounded-full border border-line text-ink-soft transition hover:border-plum-700 hover:text-plum-700" aria-label="More screens" @click="slide(1)">
          <AppIcon name="arrow" :size="18" />
        </button>
      </div>
      <div v-else class="flex gap-1 rounded-xl bg-canvas p-1" role="radiogroup" aria-label="Screen shape">
        <button
          v-for="s in SHAPES"
          :key="s.value"
          type="button"
          role="radio"
          :aria-checked="shape === s.value"
          class="rounded-lg px-3 py-1.5 text-xs font-semibold transition"
          :class="shape === s.value ? 'bg-white text-ink shadow-sm' : 'text-subtle hover:text-ink'"
          @click="shape = s.value"
        >
          {{ s.label }}
        </button>
      </div>
    </div>

    <AlertBox v-if="error" class="mt-4" title="Couldn’t load screens">
      {{ error }}
      <template #action><button type="button" class="link shrink-0 text-sm" @click="load(lastBbox)">Try again</button></template>
    </AlertBox>
    <div v-else-if="loading" class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4"><div v-for="i in 4" :key="i" class="skeleton h-72" /></div>
    <p v-else-if="!shown.length" class="mt-4 rounded-2xl border border-dashed border-line-strong p-8 text-center text-muted">
      {{ search || shape !== 'any' ? 'No screen matches that. Try another place or shape.' : 'No screens here. Zoom out or move the map.' }}
    </p>
    <ul
      v-else-if="props.layout === 'row'"
      ref="row"
      class="-mx-4 mt-4 flex snap-x snap-mandatory scroll-px-4 gap-4 overflow-x-auto scroll-smooth px-4 pt-1 pb-4 [scrollbar-width:none] sm:-mx-6 sm:scroll-px-6 sm:px-6 [&::-webkit-scrollbar]:hidden"
    >
      <li
        v-for="s in shown"
        :key="s.id"
        class="w-[270px] shrink-0 snap-start sm:w-[290px]"
        @mouseenter="hovered = s.id"
        @mouseleave="hovered = null"
        @focusin="hovered = s.id"
        @focusout="hovered = null"
      >
        <ScreenCard :screen="s" />
      </li>
    </ul>
    <ul v-else class="mt-4 grid gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
      <li v-for="s in shown" :key="s.id" class="min-w-0" @mouseenter="hovered = s.id" @mouseleave="hovered = null" @focusin="hovered = s.id" @focusout="hovered = null">
        <ScreenCard :screen="s" />
      </li>
    </ul>
  </div>
</template>
