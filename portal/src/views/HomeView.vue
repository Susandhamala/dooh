<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import BillboardHero from '@/components/home/BillboardHero.vue'
import PhotoScreen from '@/components/home/PhotoScreen.vue'
import StepIcon from '@/components/home/StepIcon.vue'
import ScreenFinder from '@/components/ScreenFinder.vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import { formatRupees } from '@/lib/format'
import { get } from '@/lib/http'
import { vReveal } from '@/lib/reveal'
import type { Screen } from '@/lib/types'
import { useAuthStore } from '@/stores/auth'

/** The front page: billboards for every business, booked from a phone. Finding screens has its own page. */
const auth = useAuthStore()
const router = useRouter()

// All screens, for the prices quoted on the page (the finder below loads its own map area).
const screens = ref<Screen[]>([])
onMounted(async () => {
  try {
    screens.value = (await get<Screen[]>('/screens')).data
  } catch {
    // Prices just aren't quoted; the finder shows the error.
  }
})

/** The lowest price for an hour of ads on any screen. */
const cheapestHour = computed(() => {
  if (!screens.value.length) return null
  const best = [...screens.value].sort((a, b) => a.plays_per_hour * Number(a.price_per_play) - b.plays_per_hour * Number(b.price_per_play))[0]!
  return { price: best.plays_per_hour * Number(best.price_per_play), screen: best }
})
const fromHour = computed(() => (cheapestHour.value ? `from ${formatRupees(cheapestHour.value.price)} an hour` : ''))

// Real billboards in Nepal with sample ads laid onto them (corners measured on each photo).
type Quad = [number, number][]
const BIRATNAGAR_DIGITAL = [{ corners: [[0.3238, 0.3315], [0.6365, 0.2945], [0.6458, 0.6545], [0.3238, 0.6905]] as Quad, w: 1000, h: 620 }]
const JANAKPUR = [{ corners: [[0.6052, 0.1463], [0.899, 0], [0.9125, 0.4046], [0.6094, 0.4639]] as Quad, w: 1000, h: 580, led: false, shade: 0.1 }]
const BIRATNAGAR_STREET = [{ corners: [[0.6, 0.2309], [0.95, 0.1444], [0.9615, 0.4576], [0.5964, 0.4882]] as Quad, w: 1000, h: 620, led: false, shade: 0.28 }]

const ideas = [
  { img: '/photos/kathmandu-shop.webp', title: 'Shops & businesses', text: 'Offers, new stock and openings, near your customers.' },
  { img: '/photos/billboard-tokyo.webp', title: 'Brands & launches', text: 'Be seen at the busiest junctions in the valley.' },
  { img: '/photos/college-graduate.webp', title: 'Colleges & events', text: 'Admissions, results days, concerts and fairs.' },
  { img: '/photos/phone-call.webp', title: 'Social trends', text: 'Put your own photo up, film it, share it.' },
]

const steps: { icon: 'phone' | 'id' | 'photo' | 'pick' | 'live'; title: string; text: string }[] = [
  { icon: 'phone', title: 'Sign up with your number', text: 'We text you a code. No password to remember.' },
  { icon: 'id', title: 'Verify once', text: 'A photo of your citizenship or any ID. Our team checks it.' },
  { icon: 'photo', title: 'Upload your ad', text: 'A picture or short video. We check every one.' },
  { icon: 'pick', title: 'Choose a billboard', text: 'Pick the screen, the days, the time and your exact second in the loop.' },
  { icon: 'live', title: 'Watch it go live', text: 'Pay from your wallet. Missed shows are refunded.' },
]

const faqs = computed(() => [
  { q: 'Do I need a company?', a: 'No. Any shop, business or person with a Nepali mobile number can advertise.' },
  {
    q: 'How much does it cost?',
    a: cheapestHour.value
      ? `An hour on ${cheapestHour.value.screen.name} costs ${formatRupees(cheapestHour.value.price)}. Every screen shows its price before you pay, and you only pay for the hours you pick.`
      : 'Every screen shows its price before you pay, and you only pay for the hours you pick.',
  },
  { q: 'What if someone wants the same time?', a: 'Whoever pays first gets it. Taken hours are crossed out, so you can pick other hours or another day.' },
  { q: 'What if my ad doesn’t show?', a: 'Every show that doesn’t happen is refunded to your wallet automatically.' },
  { q: 'Can I put up my own photo, just for fun?', a: 'Yes. Put your photo up, film it and share it. It goes through the same check as any ad.' },
  { q: 'What isn’t allowed?', a: 'Anything sexual, violent, hateful, illegal or misleading. Some screens also refuse things like alcohol; each screen lists its rules.' },
])
const openFaq = ref(0)

const phone = ref('')
const digits = computed(() => phone.value.replace(/\D/g, '').replace(/^(977|0)/, ''))
function startSignUp() {
  router.push({ name: 'login', query: /^9[678]\d{8}$/.test(digits.value) ? { phone: digits.value } : {} })
}
</script>

<template>
  <div class="overflow-x-clip bg-white">
    <!-- ================= Hero ================= -->
    <section class="relative">
      <div class="pointer-events-none absolute top-10 -right-40 h-[620px] w-[760px] rounded-[48%_52%_44%_56%/55%_45%_55%_45%] bg-plum-100" aria-hidden="true" />
      <div class="pointer-events-none absolute right-[6%] bottom-28 hidden grid-cols-4 gap-2.5 lg:grid" aria-hidden="true">
        <span v-for="i in 12" :key="i" class="h-1.5 w-1.5 rounded-full bg-coral/70" />
      </div>

      <div class="shell relative grid items-center gap-14 pt-14 pb-24 sm:pt-20 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.05fr)] lg:pb-32">
        <div class="min-w-0">
          <p class="rise text-sm font-semibold tracking-[0.14em] text-plum-700 uppercase">Billboards for every business</p>
          <h1 class="mt-5 text-[40px] leading-[1.06] tracking-[-0.035em] sm:text-[54px] lg:text-[42px] xl:text-[52px]">
            <span class="rise block font-medium whitespace-nowrap text-ink" style="animation-delay: 80ms">Put your business</span>
            <span class="rise block text-[1.2em] font-extrabold text-coral-700" style="animation-delay: 160ms">on a billboard</span>
          </h1>
          <p class="rise mt-6 max-w-lg text-lg leading-relaxed text-muted" style="animation-delay: 240ms">
            Real digital billboards across Kathmandu valley. Pick a screen, choose your hours and pay from your phone. No agency needed.
          </p>
          <div class="rise mt-9 flex flex-wrap gap-3" style="animation-delay: 320ms">
            <RouterLink :to="auth.isLoggedIn ? '/dashboard' : '/login'" class="btn-primary h-12 px-7 shadow-[0_12px_28px_-12px_rgb(207_63_45/0.7)]">
              {{ auth.isLoggedIn ? 'My dashboard' : 'Get started' }}
            </RouterLink>
            <RouterLink to="/screens" class="btn-outline h-12 px-6">Find a screen</RouterLink>
          </div>
          <ul class="rise mt-10 flex flex-wrap gap-x-6 gap-y-2 text-sm text-subtle" style="animation-delay: 400ms">
            <li v-if="cheapestHour" class="flex items-center gap-2"><AppIcon name="wallet" :size="16" class="text-plum-700" /> {{ fromHour }}</li>
            <li class="flex items-center gap-2"><AppIcon name="shield" :size="16" class="text-plum-700" /> Checked by our team</li>
            <li class="flex items-center gap-2"><AppIcon name="check" :size="16" class="text-plum-700" /> Missed shows refunded</li>
          </ul>
        </div>

        <div class="rise relative min-w-0 lg:pl-6" style="animation-delay: 200ms">
          <BillboardHero />
          <div class="float-card absolute top-[30%] -left-20 hidden w-36 xl:block" style="animation-delay: 0s">
            <img src="/photos/kathmandu-shop.webp" alt="" class="h-16 w-full rounded-lg object-cover" />
            <p class="mt-2 text-xs font-bold text-ink">Shop offer</p>
            <p class="text-[11px] text-subtle">{{ fromHour || 'Any day, any hour' }}</p>
          </div>
          <div class="float-card absolute -top-[4.5rem] right-8 hidden w-36 lg:block" style="animation-delay: 1.4s">
            <img src="/photos/phone-call.webp" alt="" class="h-14 w-full rounded-lg object-cover object-top" />
            <p class="mt-2 text-xs font-bold text-ink">Social trend</p>
            <p class="text-[11px] text-subtle">Your photo, up there</p>
          </div>
        </div>
      </div>

      <svg class="absolute inset-x-0 bottom-0 h-16 w-full text-white sm:h-24" viewBox="0 0 1440 96" preserveAspectRatio="none" aria-hidden="true">
        <path d="M0 96h1440V40C1180 92 860 104 560 72 330 47 140 20 0 34v62Z" fill="currentColor" />
      </svg>
    </section>

    <!-- ================= Billboards across Nepal ================= -->
    <section class="shell py-14 sm:py-20">
      <div v-reveal class="flex flex-wrap items-end justify-between gap-6">
        <div class="max-w-2xl">
          <p class="text-sm font-semibold tracking-[0.14em] text-plum-700 uppercase">Real billboards in Nepal</p>
          <h2 class="mt-4 text-3xl font-bold tracking-[-0.03em] text-ink sm:text-[42px]">On the streets you <span class="text-coral-700">already know</span></h2>
        </div>
        <p class="max-w-sm text-muted">Real billboards in Biratnagar and Janakpur, shown with sample ads. Your ad could be next.</p>
      </div>

      <div class="mt-12 grid gap-5 lg:grid-cols-[minmax(0,1.15fr)_minmax(0,1fr)] lg:grid-rows-2">
        <!-- Biratnagar: a digital billboard at night -->
        <figure v-reveal class="group relative overflow-hidden rounded-3xl bg-plum-950 shadow-[0_24px_60px_-30px_rgb(38_22_49/0.55)] lg:row-span-2">
          <PhotoScreen src="/photos/nepal-biratnagar-digital.webp" alt="A digital billboard at night in Biratnagar, Nepal, with a sample RentALL Nepal ad" :width="920" :height="518" :screens="BIRATNAGAR_DIGITAL" class="h-full transition duration-700 group-hover:scale-[1.02] lg:!aspect-auto">
            <template #screen-0>
              <div class="relative h-full w-full bg-white">
                <div class="absolute inset-y-0 right-0 w-[36%] bg-gradient-to-br from-plum-700 to-plum-950" />
                <div class="absolute top-1/2 right-[18%] grid h-[280px] w-[280px] translate-x-1/2 -translate-y-1/2 place-items-center rounded-full bg-white shadow-2xl">
                  <img src="/brand/logo-mark.png" alt="" class="h-[180px] w-auto" />
                </div>
                <div class="absolute top-1/2 left-14 w-[54%] -translate-y-1/2">
                  <p class="text-[30px] font-bold tracking-[0.2em] text-plum-700 uppercase">RentALL Nepal</p>
                  <p class="mt-3 text-[118px] leading-[0.9] font-extrabold tracking-tight text-plum-950">Need it?<br /><span class="text-coral-700">Rent it.</span></p>
                  <p class="mt-6 text-[38px] font-bold text-ink-soft">rentallnepal.com</p>
                </div>
              </div>
            </template>
          </PhotoScreen>
          <figcaption class="absolute bottom-4 left-4 inline-flex items-center gap-1.5 rounded-full bg-white/95 px-3 py-1.5 text-xs font-semibold text-ink shadow-sm">
            <AppIcon name="pin" :size="14" class="text-coral-700" /> Biratnagar · digital screen
          </figcaption>
          <span class="absolute right-3 bottom-3 rounded-md bg-black/50 px-1.5 py-0.5 text-[10px] text-white/90 backdrop-blur">Photo: WACN · CC BY 3.0</span>
        </figure>

        <!-- Janakpur: a board over a busy junction -->
        <figure v-reveal="100" class="group relative overflow-hidden rounded-3xl shadow-[0_24px_60px_-30px_rgb(38_22_49/0.55)]">
          <PhotoScreen src="/photos/nepal-janakpur.webp" alt="A billboard on a pole at a busy junction in Janakpur, Nepal, with a sample restaurant ad" :width="1920" :height="1080" :screens="JANAKPUR" class="transition duration-700 group-hover:scale-[1.02]">
            <template #screen-0>
              <div class="relative h-full w-full bg-gradient-to-br from-coral-700 via-coral to-sand">
                <div class="absolute -top-24 -right-24 h-[420px] w-[420px] rounded-full bg-white/15" />
                <div class="absolute top-1/2 left-14 -translate-y-1/2 text-white">
                  <p class="text-[30px] font-bold tracking-[0.22em] uppercase opacity-90">Mithila Kitchen</p>
                  <p class="mt-2 text-[150px] leading-[0.9] font-extrabold tracking-tight">Hot momo</p>
                  <p class="mt-6 text-[52px] font-extrabold">रू 150 a plate</p>
                </div>
              </div>
            </template>
          </PhotoScreen>
          <figcaption class="absolute bottom-4 left-4 inline-flex items-center gap-1.5 rounded-full bg-white/95 px-3 py-1.5 text-xs font-semibold text-ink shadow-sm">
            <AppIcon name="pin" :size="14" class="text-coral-700" /> Janakpur
          </figcaption>
          <span class="absolute right-3 bottom-3 rounded-md bg-black/50 px-1.5 py-0.5 text-[10px] text-white/90 backdrop-blur">Photo: Tulsi Bhagat · CC BY-SA 4.0</span>
        </figure>

        <!-- Biratnagar: a street board at dusk -->
        <figure v-reveal="200" class="group relative overflow-hidden rounded-3xl shadow-[0_24px_60px_-30px_rgb(38_22_49/0.55)]">
          <PhotoScreen src="/photos/nepal-biratnagar-street.webp" alt="A billboard over a street at dusk in Biratnagar, Nepal, with a sample college ad" :width="1920" :height="1440" :screens="BIRATNAGAR_STREET" class="!aspect-[16/9] transition duration-700 group-hover:scale-[1.02]">
            <template #screen-0>
              <div class="relative h-full w-full bg-gradient-to-br from-navy via-plum-900 to-teal-dark">
                <div class="absolute inset-y-0 right-0 w-1/3 bg-[repeating-linear-gradient(135deg,rgb(255_255_255/0.08)_0_24px,transparent_24px_48px)]" />
                <div class="absolute top-1/2 left-14 -translate-y-1/2 text-white">
                  <p class="text-[34px] font-bold tracking-[0.2em] text-sand uppercase">Admissions open 2083</p>
                  <p class="mt-3 text-[104px] leading-[0.95] font-extrabold tracking-tight">BBA · BIT<br />BSc CSIT</p>
                  <p class="mt-6 text-[40px] font-semibold text-white/85">Apply before Magh 15</p>
                </div>
              </div>
            </template>
          </PhotoScreen>
          <figcaption class="absolute bottom-4 left-4 inline-flex items-center gap-1.5 rounded-full bg-white/95 px-3 py-1.5 text-xs font-semibold text-ink shadow-sm">
            <AppIcon name="pin" :size="14" class="text-coral-700" /> Biratnagar
          </figcaption>
          <span class="absolute right-3 bottom-3 rounded-md bg-black/50 px-1.5 py-0.5 text-[10px] text-white/90 backdrop-blur">Photo: Sandeep Raut · CC BY-SA 4.0</span>
        </figure>
      </div>
    </section>

    <!-- ================= About ================= -->
    <section class="shell grid items-center gap-14 py-16 sm:py-24 lg:grid-cols-2">
      <div v-reveal class="relative mx-auto w-full max-w-xl">
        <div class="absolute -top-6 -left-6 h-40 w-40 rounded-full bg-coral/10" aria-hidden="true" />
        <img src="/photos/phone-laugh.webp" alt="A woman smiling at her phone" class="relative aspect-[4/3] w-full rounded-3xl object-cover shadow-[0_30px_70px_-30px_rgb(38_22_49/0.45)]" width="1100" height="736" loading="lazy" />
        <div class="float-card absolute -right-3 -bottom-10 w-56 sm:-right-8" style="animation-delay: 0.5s">
          <div class="relative aspect-video overflow-hidden rounded-lg bg-gradient-to-br from-coral-700 to-plum-900">
            <img src="/photos/kathmandu-shop.webp" alt="" class="absolute inset-0 h-full w-full object-cover opacity-60" />
            <p class="absolute inset-x-2 bottom-1.5 text-[13px] leading-tight font-extrabold text-white">20% off this week</p>
          </div>
          <div class="mt-2 flex items-center justify-between">
            <span class="text-[11px] font-semibold text-ink">New Road · 5–8 pm</span>
            <span class="rounded-full bg-success/10 px-2 py-0.5 text-[10px] font-bold text-success">Booked</span>
          </div>
        </div>
      </div>
      <div v-reveal="120">
        <p class="text-sm font-semibold tracking-[0.14em] text-plum-700 uppercase">About Screen Ads</p>
        <h2 class="mt-4 text-3xl leading-tight font-bold tracking-[-0.03em] text-ink sm:text-[42px]">
          Billboards were for big brands.<br /><span class="text-coral-700">Now they’re for every business.</span>
        </h2>
        <p class="mt-5 max-w-lg text-lg leading-relaxed text-muted">
          We connect billboard owners with shops and businesses that never advertised before. No agency, no phone calls, no big budget. Just your phone.
        </p>
        <a href="#how" class="group mt-7 inline-flex items-center gap-2 font-semibold text-plum-700 hover:text-plum-900">
          See how it works <AppIcon name="arrow" :size="18" class="transition group-hover:translate-x-1" />
        </a>
      </div>
    </section>

    <!-- ================= Choose your screen ================= -->
    <section id="find" class="scroll-mt-16 py-16 sm:py-20">
      <div class="shell">
        <div v-reveal class="flex flex-wrap items-end justify-between gap-6">
          <div>
            <p class="text-sm font-semibold tracking-[0.14em] text-plum-700 uppercase">Billboards near you</p>
            <h2 class="mt-4 text-3xl font-bold tracking-[-0.03em] text-ink sm:text-[42px]">Choose your <span class="text-coral-700">screen</span></h2>
          </div>
          <div class="flex flex-wrap items-center gap-4">
            <p class="max-w-xs text-muted">Drag the map to explore, then pick a screen below.</p>
            <RouterLink to="/screens" class="group inline-flex items-center gap-1.5 font-semibold text-plum-700 hover:text-plum-900">
              All screens <AppIcon name="arrow" :size="18" class="transition group-hover:translate-x-1" />
            </RouterLink>
          </div>
        </div>
        <div v-reveal class="mt-10"><ScreenFinder layout="row" /></div>
      </div>
    </section>

    <!-- ================= Ideas ================= -->
    <section class="shell py-16 sm:py-20">
      <div v-reveal class="mx-auto max-w-2xl text-center">
        <p class="text-sm font-semibold tracking-[0.14em] text-plum-700 uppercase">Who it’s for</p>
        <h2 class="mt-4 text-3xl font-bold tracking-[-0.03em] text-ink sm:text-[42px]">What will you <span class="text-coral-700">put up?</span></h2>
      </div>
      <ul class="mt-12 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
        <li v-for="(idea, i) in ideas" :key="idea.title" v-reveal="i * 90">
          <RouterLink to="/screens" class="group relative block aspect-[3/4] overflow-hidden rounded-3xl bg-plum-950 shadow-[0_20px_50px_-28px_rgb(38_22_49/0.6)]">
            <img :src="idea.img" alt="" class="absolute inset-0 h-full w-full object-cover transition duration-700 group-hover:scale-105" loading="lazy" />
            <div class="absolute inset-0 bg-gradient-to-t from-plum-950/95 via-plum-950/25 to-transparent" />
            <div class="absolute inset-x-0 bottom-0 p-5 text-white">
              <h3 class="text-xl font-bold tracking-[-0.01em]">{{ idea.title }}</h3>
              <p class="mt-1 text-sm text-white/75">{{ idea.text }}</p>
              <span class="mt-4 inline-flex items-center gap-1.5 text-sm font-semibold text-sand">
                {{ fromHour || 'See screens' }} <AppIcon name="arrow" :size="16" class="transition group-hover:translate-x-1" />
              </span>
            </div>
          </RouterLink>
        </li>
      </ul>
    </section>

    <!-- ================= How it works ================= -->
    <section id="how" class="scroll-mt-20 bg-canvas py-20 sm:py-24">
      <div class="shell">
        <div v-reveal class="mx-auto max-w-2xl text-center">
          <p class="text-sm font-semibold tracking-[0.14em] text-plum-700 uppercase">How it works</p>
          <h2 class="mt-4 text-3xl font-bold tracking-[-0.03em] text-ink sm:text-[42px]">From your phone to a <span class="text-coral-700">billboard</span></h2>
        </div>
        <ol class="mt-14 flex flex-wrap justify-center gap-5">
          <li
            v-for="(s, i) in steps"
            :key="s.title"
            v-reveal="i * 80"
            class="group w-full rounded-3xl p-8 text-center transition duration-300 hover:bg-white hover:shadow-[0_24px_60px_-30px_rgb(38_22_49/0.35)] sm:w-[calc(50%-10px)] lg:w-[calc(33.333%-14px)]"
            :class="i === 0 ? 'bg-white shadow-[0_24px_60px_-30px_rgb(38_22_49/0.35)]' : ''"
          >
            <div class="mx-auto grid h-16 w-16 place-items-center transition duration-300 group-hover:-translate-y-1"><StepIcon :name="s.icon" /></div>
            <p class="mt-5 text-xs font-semibold text-faint">Step {{ i + 1 }}</p>
            <h3 class="mt-1 text-lg font-bold text-ink">{{ s.title }}</h3>
            <p class="mx-auto mt-2 max-w-[17rem] text-[15px] leading-relaxed text-muted">{{ s.text }}</p>
          </li>
        </ol>
      </div>
    </section>

    <!-- ================= Questions ================= -->
    <section id="faq" class="shell grid scroll-mt-20 items-center gap-14 py-16 sm:py-24 lg:grid-cols-2">
      <div v-reveal>
        <p class="text-sm font-semibold tracking-[0.14em] text-plum-700 uppercase">What people ask</p>
        <h2 class="mt-4 text-3xl font-bold tracking-[-0.03em] text-ink sm:text-[42px]">Frequently asked<br /><span class="text-coral-700">questions</span></h2>
        <ul class="mt-8 divide-y divide-line border-y border-line">
          <li v-for="(f, i) in faqs" :key="f.q">
            <button type="button" class="flex w-full items-center justify-between gap-4 py-5 text-left font-semibold text-ink" :aria-expanded="openFaq === i" @click="openFaq = openFaq === i ? -1 : i">
              {{ f.q }}
              <AppIcon name="plus" :size="18" class="shrink-0 text-plum-700 transition-transform duration-300" :class="{ 'rotate-45': openFaq === i }" />
            </button>
            <div class="grid transition-[grid-template-rows] duration-300 ease-out" :class="openFaq === i ? 'grid-rows-[1fr]' : 'grid-rows-[0fr]'">
              <p class="overflow-hidden text-muted" :class="openFaq === i ? 'pb-5' : ''">{{ f.a }}</p>
            </div>
          </li>
        </ul>
      </div>
      <div v-reveal="120" class="relative mx-auto w-full max-w-md">
        <div class="absolute inset-6 rounded-[44%_56%_52%_48%/48%_44%_56%_52%] bg-plum-100" aria-hidden="true" />
        <img src="/photos/college-graduate.webp" alt="A graduate in cap and gown" class="relative mx-auto aspect-[3/4] w-[78%] rounded-[32px] object-cover shadow-[0_30px_70px_-30px_rgb(38_22_49/0.5)]" width="720" height="960" loading="lazy" />
        <div class="float-card absolute top-10 -left-2 w-48 sm:-left-6" style="animation-delay: 0.3s">
          <div class="rounded-lg bg-gradient-to-br from-plum-700 to-plum-950 p-3 text-white">
            <p class="text-[10px] font-bold tracking-[0.16em] text-sand uppercase">Kathmandu College</p>
            <p class="mt-1 text-sm leading-tight font-extrabold">Admissions open 2083. Apply now.</p>
          </div>
        </div>
        <div class="float-card absolute -right-2 bottom-12 flex items-center gap-2 !p-2.5 sm:-right-4" style="animation-delay: 1.2s">
          <span class="grid h-8 w-8 place-items-center rounded-full bg-coral-700 text-white"><AppIcon name="play" :size="14" /></span>
          <span class="pr-1 text-xs font-bold text-ink">Live now<br /><span class="font-medium text-subtle">Pulchowk Campus Gate</span></span>
        </div>
      </div>
    </section>

    <!-- ================= Sign up ================= -->
    <section class="shell pb-6 sm:pb-12">
      <div v-reveal class="relative mx-auto max-w-4xl overflow-hidden rounded-[32px] border border-line bg-white px-6 py-12 text-center shadow-[0_30px_80px_-40px_rgb(38_22_49/0.35)] sm:px-14 sm:py-16">
        <div class="pointer-events-none absolute -top-24 -right-24 h-64 w-64 rounded-full bg-plum-100" aria-hidden="true" />
        <div class="pointer-events-none absolute -bottom-20 -left-20 h-48 w-48 rounded-full bg-coral/10" aria-hidden="true" />
        <div class="relative">
          <template v-if="auth.isLoggedIn">
            <h2 class="text-3xl font-bold tracking-[-0.03em] text-ink sm:text-4xl">You’re all set</h2>
            <p class="mt-3 text-muted">Pick a screen and put your ad up.</p>
            <div class="mt-8 flex flex-wrap justify-center gap-3">
              <RouterLink to="/dashboard" class="btn-primary h-12 px-7">My dashboard</RouterLink>
              <RouterLink to="/screens" class="btn-outline h-12 px-6">Find a screen</RouterLink>
            </div>
          </template>
          <template v-else>
            <h2 class="text-3xl font-bold tracking-[-0.03em] text-ink sm:text-4xl">Sign up</h2>
            <p class="mt-3 text-muted">Create your account with your mobile number. It’s free.</p>
            <form class="mx-auto mt-9 flex max-w-lg flex-col gap-3 sm:flex-row" novalidate @submit.prevent="startSignUp">
              <label class="flex flex-1 items-center overflow-hidden rounded-xl border border-line-strong bg-white transition focus-within:border-plum-700 focus-within:ring-2 focus-within:ring-plum-700/15">
                <span class="self-stretch border-r border-line bg-canvas px-3.5 py-3 text-[15px] font-semibold text-ink-soft">+977</span>
                <span class="sr-only">Mobile number</span>
                <input v-model="phone" type="tel" inputmode="numeric" autocomplete="tel-national" maxlength="16" placeholder="98XXXXXXXX" class="w-full px-3.5 py-3 text-[15px] font-semibold outline-none" />
              </label>
              <button class="btn-primary h-[50px] px-7">Get my code</button>
            </form>
            <p class="mt-4 text-xs text-subtle">We’ll text you a 6-digit code. NTC, Ncell and Smart numbers.</p>
          </template>
        </div>
      </div>
    </section>
  </div>
</template>

<style scoped>
/* Small white cards that drift gently around the pictures. */
.float-card {
  border-radius: 1rem;
  background: #fff;
  padding: 0.6rem;
  box-shadow: 0 18px 40px -16px rgb(38 22 49 / 0.35);
  animation: float-y 5s ease-in-out infinite;
  z-index: 10;
}
</style>
