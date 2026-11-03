<script setup lang="ts">
import AppIcon, { type IconName } from '@/components/ui/AppIcon.vue'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()

const steps: { title: string; body: string; icon: IconName }[] = [
  { title: 'Create', body: 'Sign up with your mobile number, load money and upload an image or short video at the screen’s resolution.', icon: 'upload' },
  { title: 'Check', body: 'Automatic file checks, then our review team watches every ad. If it’s refused, you see exactly why.', icon: 'shield' },
  { title: 'Upload', body: 'Pick screens on the map and the hours you want. Each play is charged from your wallet; unplayed time comes back.', icon: 'play' },
]

const promises: { title: string; body: string; icon: IconName }[] = [
  { title: 'Only approved ads play', body: 'A person on our review team approves every ad. Sexual, violent, hateful or illegal content is refused.', icon: 'check' },
  { title: 'Signed playlists', body: 'Screens refuse any playlist the platform hasn’t signed, so no one can slip content in.', icon: 'lock' },
  { title: 'Proof of play', body: 'Each screen reports what it played and when, so you see exactly what you paid for.', icon: 'layers' },
]
</script>

<template>
  <div>
    <section class="page-wash">
      <div class="shell grid items-center gap-10 py-14 sm:py-20 lg:grid-cols-[minmax(0,1.1fr)_minmax(0,0.9fr)]">
        <div class="min-w-0">
          <p class="eyebrow">RentALL Nepal · Screen Ads</p>
          <h1 class="mt-3 text-4xl leading-[1.08] font-extrabold tracking-[-0.03em] text-ink sm:text-5xl">
            Your ad on screens across Nepal, booked in minutes.
          </h1>
          <p class="lede mt-4 text-lg">
            Pick screens on the map, choose the hours, and pay only for the plays your ad gets. Sign up with your
            mobile number in a minute.
          </p>
          <div class="mt-8 flex flex-wrap gap-3">
            <RouterLink to="/screens" class="btn-primary">
              Find screens <AppIcon name="arrow" :size="18" />
            </RouterLink>
            <RouterLink :to="auth.isLoggedIn ? '/dashboard' : '/login'" class="btn-outline">
              {{ auth.isLoggedIn ? 'My dashboard' : 'Sign up free' }}
            </RouterLink>
          </div>
        </div>

        <!-- A screen playing a loop: what the advertiser is buying. -->
        <div class="mx-auto w-full max-w-md" aria-hidden="true">
          <div class="rounded-[22px] bg-plum-950 p-3 shadow-modal">
            <div class="relative aspect-video overflow-hidden rounded-xl bg-gradient-to-br from-plum-700 via-plum-800 to-navy">
              <div class="absolute inset-0 grid place-items-center text-center text-white">
                <div>
                  <p class="text-xs font-bold tracking-[0.18em] text-sand uppercase">Dashain offer</p>
                  <p class="mt-1 text-3xl font-extrabold tracking-tight">Your brand here</p>
                </div>
              </div>
              <span class="absolute top-3 left-3 inline-flex items-center gap-1.5 rounded-md bg-black/35 px-2 py-1 text-[11px] font-bold text-white">
                <span class="h-1.5 w-1.5 rounded-full bg-coral" /> LIVE · 10 s of 120 s
              </span>
            </div>
          </div>
          <div class="mx-auto h-6 w-3 bg-plum-950/80" />
          <div class="mx-auto h-2 w-28 rounded-full bg-plum-950/80" />
        </div>
      </div>
    </section>

    <section class="shell py-14">
      <p class="eyebrow">How it works</p>
      <h2 class="mt-2 text-2xl font-extrabold tracking-[-0.02em] sm:text-3xl">Three steps from idea to screen</h2>
      <ol class="mt-8 grid gap-4 md:grid-cols-3">
        <li v-for="(step, i) in steps" :key="step.title" class="card p-6">
          <div class="flex items-center gap-3">
            <span class="grid h-11 w-11 place-items-center rounded-xl bg-plum-100 text-plum-700">
              <AppIcon :name="step.icon" />
            </span>
            <span class="text-sm font-bold text-subtle">Step {{ i + 1 }}</span>
          </div>
          <h3 class="mt-4 text-lg font-extrabold">{{ step.title }}</h3>
          <p class="mt-1.5 text-[15px] text-muted">{{ step.body }}</p>
        </li>
      </ol>
    </section>

    <section class="border-y border-line bg-white">
      <div class="shell grid gap-10 py-14 lg:grid-cols-[minmax(0,0.8fr)_minmax(0,1.2fr)]">
        <div>
          <p class="eyebrow">Built to be trusted</p>
          <h2 class="mt-2 text-2xl font-extrabold tracking-[-0.02em] sm:text-3xl">Nothing plays that shouldn’t</h2>
          <p class="lede mt-3">
            Public screens are seen by everyone. Every ad is checked, approved by a person and signed before a screen
            will show it.
          </p>
        </div>
        <ul class="grid gap-5 sm:grid-cols-3 lg:grid-cols-1 xl:grid-cols-3">
          <li v-for="p in promises" :key="p.title" class="flex gap-3 sm:flex-col">
            <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-teal-50 text-teal-dark">
              <AppIcon :name="p.icon" />
            </span>
            <div>
              <h3 class="font-extrabold">{{ p.title }}</h3>
              <p class="mt-1 text-sm text-muted">{{ p.body }}</p>
            </div>
          </li>
        </ul>
      </div>
    </section>

    <section class="shell py-14">
      <div class="flex flex-col gap-6 rounded-[22px] bg-plum-900 px-6 py-10 text-white sm:px-10 md:flex-row md:items-center md:justify-between">
        <div class="max-w-xl">
          <p class="text-[13px] font-bold tracking-[0.08em] text-sand uppercase">For screen owners</p>
          <h2 class="mt-2 text-2xl font-extrabold tracking-[-0.02em]">Earn from your screen</h2>
          <p class="mt-2 text-white/80">
            List your screen, set a price per play and the content you won’t show. Every ad is reviewed before it plays.
          </p>
        </div>
        <RouterLink :to="auth.isLoggedIn ? '/orgs' : { name: 'login', query: { next: '/orgs' } }" class="btn shrink-0 bg-white text-plum-900 hover:bg-plum-100">
          List your screen <AppIcon name="arrow" :size="18" />
        </RouterLink>
      </div>
    </section>
  </div>
</template>
