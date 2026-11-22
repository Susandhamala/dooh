<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import { quadTransform } from '@/lib/perspective'

/**
 * A real LED billboard (photo) with sample ads playing on its screen, led by our own RentALL Nepal ad.
 * The ad is laid onto the screen with a perspective transform; traffic lights and signs from the photo
 * sit in front. Visitors can put up their own name and photo (kept in the browser, never sent).
 */
const AD_W = 1000
const AD_H = 546
// The screen's corners in the photo, as fractions of its width and height (clockwise from top left).
const CORNERS: [number, number][] = [
  [0.1577, 0.1232],
  [0.8866, 0.1193],
  [0.8887, 0.8318],
  [0.1417, 0.8325],
]

const frame = ref<HTMLElement>()
const transform = ref('')
let ro: ResizeObserver | undefined

function place() {
  const el = frame.value
  if (!el) return
  // Layout size, not the on-screen box: the tilt must not skew the placement.
  const w = el.offsetWidth
  const h = el.offsetHeight
  transform.value = quadTransform(AD_W, AD_H, CORNERS.map(([x, y]) => [x * w, y * h]))
}

// ---- What's on ----
type Ad = 'rentall' | 'shop' | 'food' | 'trend' | 'yours'
const TABS: { value: Ad; label: string }[] = [
  { value: 'rentall', label: 'RentALL' },
  { value: 'shop', label: 'Shop' },
  { value: 'food', label: 'Food' },
  { value: 'trend', label: 'Trend' },
  { value: 'yours', label: 'Yours' },
]
const LOOP: Ad[] = ['rentall', 'shop', 'food', 'trend']
const current = ref<Ad>('rentall')
const message = ref('')
const photo = ref<string | null>(null)
let timer: ReturnType<typeof setInterval> | undefined
let resume: ReturnType<typeof setTimeout> | undefined
const reduced = typeof window !== 'undefined' && window.matchMedia('(prefers-reduced-motion: reduce)').matches

function rotate() {
  clearInterval(timer)
  if (reduced) return
  timer = setInterval(() => {
    if (current.value === 'yours') return
    current.value = LOOP[(LOOP.indexOf(current.value) + 1) % LOOP.length]!
  }, 4200)
}
function pick(ad: Ad) {
  current.value = ad
  clearInterval(timer)
  clearTimeout(resume)
  if (ad !== 'yours') resume = setTimeout(rotate, 8000)
}
watch(message, (v) => {
  if (v.trim()) current.value = 'yours'
})

const fileEl = ref<HTMLInputElement>()
function onPhoto(e: Event) {
  const f = (e.target as HTMLInputElement).files?.[0]
  if (!f || !f.type.startsWith('image/')) return
  if (photo.value) URL.revokeObjectURL(photo.value)
  photo.value = URL.createObjectURL(f)
  current.value = 'yours'
}
const yourText = computed(() => message.value.trim() || 'Your business here')

// ---- Sliding pill behind the chosen tab ----
const tabEls = ref<HTMLElement[]>([])
const pill = ref({ left: 0, width: 0 })
function movePill() {
  const el = tabEls.value[TABS.findIndex((t) => t.value === current.value)]
  if (el) pill.value = { left: el.offsetLeft, width: el.offsetWidth }
}
watch(current, () => requestAnimationFrame(movePill))

// ---- Slight tilt toward the mouse ----
const tilt = ref('')
function onMove(e: PointerEvent) {
  if (reduced || e.pointerType !== 'mouse') return
  const r = (e.currentTarget as HTMLElement).getBoundingClientRect()
  const x = (e.clientX - r.left) / r.width - 0.5
  const y = (e.clientY - r.top) / r.height - 0.5
  tilt.value = `perspective(1400px) rotateY(${x * 5}deg) rotateX(${-y * 4}deg)`
}

onMounted(() => {
  place()
  ro = new ResizeObserver(() => {
    place()
    movePill()
  })
  if (frame.value) ro.observe(frame.value)
  rotate()
  movePill()
})
onBeforeUnmount(() => {
  ro?.disconnect()
  clearInterval(timer)
  clearTimeout(resume)
  if (photo.value) URL.revokeObjectURL(photo.value)
})
</script>

<template>
  <div>
    <div class="transition-transform duration-500 ease-out will-change-transform" :style="{ transform: tilt }" @pointermove="onMove" @pointerleave="tilt = ''">
      <div ref="frame" class="relative aspect-[16/9] overflow-hidden rounded-[20px] bg-plum-950 shadow-[0_40px_90px_-30px_rgb(38_22_49/0.55)] ring-1 ring-black/5">
        <img src="/photos/billboard.webp" alt="A large LED billboard on a city building, showing a sample message" class="absolute inset-0 h-full w-full object-cover" width="1600" height="900" fetchpriority="high" />

        <!-- The message, laid onto the billboard's screen. -->
        <div class="ad-plane absolute top-0 left-0 origin-top-left overflow-hidden" :style="{ width: `${AD_W}px`, height: `${AD_H}px`, transform }" aria-hidden="true">
          <!-- RentALL Nepal (our own brand) -->
          <div class="ad bg-white" :class="{ on: current === 'rentall' }">
            <div class="absolute inset-y-0 right-0 w-[38%] bg-gradient-to-br from-plum-700 to-plum-950" />
            <div class="absolute top-1/2 right-[19%] grid h-[300px] w-[300px] translate-x-1/2 -translate-y-1/2 place-items-center rounded-full bg-white shadow-2xl">
              <img src="/brand/logo-mark.png" alt="" class="h-[190px] w-auto" />
            </div>
            <div class="absolute top-1/2 left-16 w-[52%] -translate-y-1/2">
              <p class="text-[30px] font-bold tracking-[0.22em] text-plum-700 uppercase">RentALL Nepal</p>
              <p class="mt-3 text-[112px] leading-[0.92] font-extrabold tracking-tight text-plum-950">Need it?<br /><span class="text-coral-700">Rent it.</span></p>
              <p class="mt-6 text-[38px] font-bold text-ink-soft">rentallnepal.com</p>
            </div>
          </div>
          <!-- Restaurant -->
          <div class="ad bg-gradient-to-br from-coral-700 via-coral to-sand" :class="{ on: current === 'food' }">
            <div class="absolute -top-24 -right-24 h-[420px] w-[420px] rounded-full bg-white/15" />
            <div class="absolute -right-10 -bottom-40 h-[380px] w-[380px] rounded-full bg-plum-900/20" />
            <div class="absolute top-1/2 left-16 -translate-y-1/2 text-white">
              <p class="text-[30px] font-bold tracking-[0.25em] uppercase opacity-90">Jhamsikhel · Lalitpur</p>
              <p class="mt-2 text-[150px] leading-[0.9] font-extrabold tracking-tight">Hot momo</p>
              <p class="mt-6 text-[52px] font-extrabold">रू 150 a plate</p>
            </div>
          </div>
          <!-- Social trend: your own photo up there, to film and share -->
          <div class="ad bg-plum-950" :class="{ on: current === 'trend' }">
            <img src="/photos/phone-call.webp" alt="" class="absolute inset-y-0 left-0 h-full w-[52%] object-cover" />
            <div class="absolute inset-y-0 left-[40%] w-[14%] bg-gradient-to-r from-transparent to-plum-950" />
            <div class="absolute inset-y-0 right-14 left-[56%] flex flex-col justify-center text-white">
              <p class="text-[34px] font-bold text-sand">@aarati.creates</p>
              <p class="mt-3 text-[92px] leading-[0.92] font-extrabold tracking-tight">I’m on a billboard!</p>
              <p class="mt-6 text-[34px] font-semibold text-white/75">#ScreenAdsNepal</p>
            </div>
          </div>
          <!-- Shop -->
          <div class="ad" :class="{ on: current === 'shop' }">
            <img src="/photos/kathmandu-shop.webp" alt="" class="absolute inset-0 h-full w-full object-cover" />
            <div class="absolute inset-0 bg-gradient-to-r from-black/85 via-black/45 to-transparent" />
            <div class="absolute top-1/2 left-16 -translate-y-1/2 text-white">
              <p class="text-[30px] font-bold tracking-[0.25em] text-sand uppercase">Thamel Art House</p>
              <p class="mt-3 text-[92px] leading-[0.95] font-extrabold tracking-tight">Hand-painted<br />thangka</p>
              <p class="mt-6 inline-block rounded-xl bg-coral px-6 py-2 text-[38px] font-extrabold">20% off this week</p>
            </div>
          </div>
          <!-- Yours -->
          <div class="ad bg-gradient-to-br from-plum-700 to-plum-950" :class="{ on: current === 'yours' }">
            <img v-if="photo" :src="photo" alt="" class="absolute inset-y-0 left-0 h-full w-[45%] object-cover" />
            <div class="absolute inset-y-0 flex flex-col justify-center px-16 text-white" :class="photo ? 'right-0 left-[45%]' : 'inset-x-0 items-center text-center'">
              <p class="line-clamp-3 text-[96px] leading-[0.95] font-extrabold tracking-tight break-words">{{ yourText }}</p>
              <p class="mt-6 text-[36px] font-bold text-sand">Now on the big screen</p>
            </div>
          </div>
          <!-- LED pixels and a little glare, so it reads as a lit screen. -->
          <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(rgb(0_0_0/0.28)_1px,transparent_1.6px)] bg-[length:5px_5px]" />
          <div class="pointer-events-none absolute inset-0 bg-gradient-to-br from-white/10 via-transparent to-transparent" />
        </div>

        <!-- Things in front of the screen in the photo. -->
        <img src="/photos/billboard-front.webp" alt="" class="pointer-events-none absolute inset-0 h-full w-full" width="1600" height="900" />

        <span class="absolute top-3 left-3 inline-flex items-center gap-2 rounded-full bg-black/55 px-3 py-1.5 text-[11px] font-semibold tracking-wide text-white uppercase backdrop-blur">
          <span class="h-1.5 w-1.5 rounded-full bg-coral [animation:live-pulse_1.6s_ease-in-out_infinite]" /> Live preview
        </span>
      </div>
    </div>

    <!-- Try it -->
    <div class="mt-5 flex flex-col gap-3 sm:flex-row sm:items-center">
      <div class="relative inline-flex self-start rounded-full bg-white p-1 shadow-sm ring-1 ring-line" role="radiogroup" aria-label="Sample on the billboard">
        <span class="absolute top-1 bottom-1 rounded-full bg-plum-700 transition-all duration-300 ease-out" :style="{ left: `${pill.left}px`, width: `${pill.width}px` }" aria-hidden="true" />
        <button
          v-for="t in TABS"
          :key="t.value"
          ref="tabEls"
          type="button"
          role="radio"
          :aria-checked="current === t.value"
          class="relative rounded-full px-3.5 py-1.5 text-xs font-semibold transition-colors duration-300"
          :class="current === t.value ? 'text-white' : 'text-ink-soft hover:text-plum-800'"
          @click="pick(t.value)"
        >
          {{ t.label }}
        </button>
      </div>
      <div class="flex flex-1 gap-2">
        <label class="relative block flex-1">
          <span class="sr-only">Type your business name to see it on the billboard</span>
          <input
            v-model="message"
            maxlength="40"
            class="w-full rounded-full bg-white py-2.5 pr-4 pl-4 text-sm font-medium text-ink shadow-sm ring-1 ring-line transition placeholder:text-faint focus:ring-2 focus:ring-plum-700/40 focus:outline-none"
            placeholder="Your business name…"
          />
        </label>
        <button type="button" class="inline-flex shrink-0 items-center gap-1.5 rounded-full bg-white px-3.5 text-sm font-semibold text-plum-800 shadow-sm ring-1 ring-line transition hover:ring-plum-300" @click="fileEl?.click()">
          <AppIcon name="image" :size="16" /> {{ photo ? 'Change' : 'Photo' }}
        </button>
        <input ref="fileEl" type="file" accept="image/*" class="sr-only" tabindex="-1" aria-label="Add your own photo to the preview" @change="onPhoto" />
      </div>
    </div>
  </div>
</template>

<style scoped>
.ad-plane {
  backface-visibility: hidden;
}
.ad {
  position: absolute;
  inset: 0;
  opacity: 0;
  transition: opacity 0.7s ease;
}
.ad.on {
  opacity: 1;
}
</style>
