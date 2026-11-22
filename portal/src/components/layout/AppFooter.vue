<script setup lang="ts">
const rentall = import.meta.env.VITE_RENTALL_URL ?? 'https://rentallnepal.com'
const year = new Date().getFullYear()

const columns = [
  {
    title: 'Explore',
    links: [
      { label: 'Find screens', to: '/screens' },
      { label: 'How it works', to: '/#how' },
      { label: 'FAQ', to: '/#faq' },
    ],
  },
  {
    title: 'Your account',
    links: [
      { label: 'Sign in or sign up', to: '/login' },
      { label: 'My bookings', to: '/bookings' },
      { label: 'Wallet', to: '/wallet' },
      { label: 'Terms and content policy', to: '/welcome' },
    ],
  },
  {
    title: 'Screen owners',
    links: [{ label: 'List your screen', to: '/orgs' }],
  },
]
</script>

<template>
  <footer class="mt-16 border-t border-line bg-canvas">
    <div class="shell grid gap-10 py-14 sm:grid-cols-2 lg:grid-cols-[1.4fr_1fr_1fr_1fr]">
      <div class="max-w-xs">
        <div class="flex items-center gap-2.5">
          <img src="/brand/logo-mark.png" width="140" height="148" alt="" class="h-9 w-auto" />
          <span class="leading-none">
            <span class="block text-[11px] font-semibold tracking-[0.12em] text-subtle uppercase">RentALL Nepal</span>
            <span class="mt-0.5 block text-lg font-extrabold tracking-[-0.02em] text-plum-900">Screen Ads</span>
          </span>
        </div>
        <p class="mt-4 text-sm leading-relaxed text-muted">Business ads on real billboards across Kathmandu valley, booked from your phone.</p>
      </div>
      <nav v-for="c in columns" :key="c.title" :aria-label="c.title">
        <p class="text-xs font-semibold tracking-[0.14em] text-plum-700 uppercase">{{ c.title }}</p>
        <ul class="mt-4 space-y-2.5 text-sm">
          <li v-for="l in c.links" :key="l.label"><RouterLink :to="l.to" class="text-ink-soft transition hover:text-plum-800">{{ l.label }}</RouterLink></li>
        </ul>
      </nav>
    </div>
    <div class="border-t border-line">
      <div class="shell flex flex-col gap-2 py-5 text-xs text-subtle sm:flex-row sm:items-center sm:justify-between">
        <p>© {{ year }} RentALL Nepal. Screen Ads is a <a :href="rentall" class="font-semibold text-plum-700 hover:text-plum-900">RentALL Nepal</a> service.</p>
        <p>Photos: Wikimedia Commons (CC BY-SA 4.0, CC BY 3.0) and Unsplash. <a href="/photos/CREDITS.md" class="underline hover:text-plum-800">Credits</a></p>
      </div>
    </div>
  </footer>
</template>
