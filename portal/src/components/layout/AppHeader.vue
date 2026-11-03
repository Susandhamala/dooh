<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter, type RouteLocationRaw } from 'vue-router'
import AppIcon, { type IconName } from '@/components/ui/AppIcon.vue'
import { formatRupees } from '@/lib/format'
import { useAuthStore } from '@/stores/auth'
import { useOrgStore } from '@/stores/orgs'
import { useWalletStore } from '@/stores/wallet'

const auth = useAuthStore()
const orgs = useOrgStore()
const wallets = useWalletStore()
const route = useRoute()
const router = useRouter()

const menuOpen = ref(false)
const accountOpen = ref(false)
const accountEl = ref<HTMLElement>()

interface NavItem {
  label: string
  to: RouteLocationRaw
  icon: IconName
  /** Route names that light this link. */
  match: string[]
}

const nav = computed<NavItem[]>(() => {
  const items: NavItem[] = [{ label: 'Find screens', to: { name: 'screens' }, icon: 'map', match: ['screens', 'screen'] }]
  if (auth.isLoggedIn) {
    items.push(
      { label: 'My ads', to: { name: 'ads' }, icon: 'film', match: ['ads'] },
      { label: 'Bookings', to: { name: 'bookings' }, icon: 'calendar', match: ['bookings'] },
    )
  }
  if (auth.hasRole('admin')) items.push({ label: 'Review', to: { name: 'admin' }, icon: 'shield', match: ['admin'] })
  return items
})

const isActive = (item: NavItem) => item.match.includes(String(route.name))

const initials = computed(() =>
  (auth.user?.name ?? '?')
    .split(/\s+/)
    .slice(0, 2)
    .map((w) => w.charAt(0).toUpperCase())
    .join(''),
)

const DOC_LABEL: Record<string, { text: string; cls: string }> = {
  approved: { text: 'Verified', cls: 'text-success' },
  pending: { text: 'Checking', cls: 'text-warning' },
  rejected: { text: 'Refused', cls: 'text-danger' },
  none: { text: 'To do', cls: 'text-coral-700' },
}
const docLabel = computed(() => DOC_LABEL[auth.user?.document_status ?? 'none'] ?? DOC_LABEL.none!)

const loginTo = computed(() =>
  route.name === 'home' || route.name === 'login' ? { name: 'login' } : { name: 'login', query: { next: route.fullPath } },
)

async function signOut() {
  menuOpen.value = false
  accountOpen.value = false
  await auth.signOut()
  orgs.reset()
  wallets.reset()
  router.push({ name: 'home' })
}

watch(
  () => route.fullPath,
  () => {
    menuOpen.value = false
    accountOpen.value = false
  },
)

// The balance in the header: load it once signed in.
watch(
  () => auth.isLoggedIn,
  (inNow) => {
    if (inNow && !wallets.wallet) wallets.load().catch(() => undefined)
  },
  { immediate: true },
)

function onDocClick(e: MouseEvent) {
  if (accountOpen.value && !accountEl.value?.contains(e.target as Node)) accountOpen.value = false
}
function onKey(e: KeyboardEvent) {
  if (e.key === 'Escape') {
    menuOpen.value = false
    accountOpen.value = false
  }
}
onMounted(() => {
  document.addEventListener('click', onDocClick)
  document.addEventListener('keydown', onKey)
})
onBeforeUnmount(() => {
  document.removeEventListener('click', onDocClick)
  document.removeEventListener('keydown', onKey)
})
</script>

<template>
  <header class="sticky top-0 z-40 border-b border-line bg-white/90 backdrop-blur-md supports-[backdrop-filter]:bg-white/80">
    <div class="shell flex h-16 items-center gap-3 sm:h-[72px] lg:gap-5">
      <RouterLink :to="auth.isLoggedIn ? '/dashboard' : '/'" class="flex shrink-0 items-center gap-2.5" aria-label="Screen Ads home">
        <img src="/brand/logo-mark.png" width="140" height="148" alt="" class="h-9 w-auto sm:h-10" draggable="false" />
        <span class="leading-none">
          <span class="block text-[11px] font-semibold tracking-[0.12em] text-subtle uppercase">RentALL Nepal</span>
          <span class="mt-0.5 block text-lg font-extrabold tracking-[-0.02em] text-plum-900">Screen Ads</span>
        </span>
      </RouterLink>

      <nav class="ml-2 hidden items-center gap-0.5 lg:flex" aria-label="Main">
        <RouterLink
          v-for="item in nav"
          :key="item.label"
          :to="item.to"
          class="rounded-full px-3.5 py-2 text-sm font-semibold whitespace-nowrap transition"
          :class="isActive(item) ? 'bg-plum-100 text-plum-900' : 'text-ink-soft hover:bg-canvas hover:text-plum-900'"
          :aria-current="isActive(item) ? 'page' : undefined"
        >
          {{ item.label }}
        </RouterLink>
      </nav>

      <div class="flex-1" />

      <template v-if="auth.isLoggedIn">
        <RouterLink
          to="/wallet"
          class="hidden items-center gap-2 rounded-full border px-3 py-1.5 text-sm font-bold transition sm:flex"
          :class="route.name === 'wallet' ? 'border-plum-700 bg-plum-50 text-plum-900' : 'border-line text-ink-soft hover:border-line-strong'"
          aria-label="Wallet balance"
        >
          <AppIcon name="wallet" :size="17" class="text-plum-700" />
          <span class="[font-variant-numeric:tabular-nums]">{{ wallets.wallet ? formatRupees(wallets.wallet.available) : 'Wallet' }}</span>
        </RouterLink>

        <div ref="accountEl" class="relative hidden lg:block">
          <button
            type="button"
            class="flex items-center gap-2 rounded-full border border-line py-1 pr-3 pl-1 transition hover:border-line-strong"
            :aria-expanded="accountOpen"
            aria-haspopup="menu"
            @click="accountOpen = !accountOpen"
          >
            <span class="grid h-8 w-8 place-items-center rounded-full bg-plum-700 text-xs font-bold text-white">{{ initials }}</span>
            <span class="max-w-[9rem] truncate text-sm font-semibold text-ink-soft">{{ auth.user?.name }}</span>
          </button>
          <div v-if="accountOpen" class="absolute right-0 mt-2 w-72 overflow-hidden rounded-2xl border border-line bg-white shadow-modal" role="menu">
            <div class="border-b border-divider px-4 py-3">
              <p class="truncate text-sm font-bold">{{ auth.user?.name }}</p>
              <p class="truncate text-xs text-subtle">{{ auth.user?.phone_masked ?? auth.user?.email }}</p>
            </div>
            <RouterLink to="/dashboard" class="flex items-center gap-2.5 px-4 py-2.5 text-sm font-semibold text-ink-soft hover:bg-plum-50" role="menuitem">
              <AppIcon name="layers" :size="18" /> Dashboard
            </RouterLink>
            <RouterLink to="/verification" class="flex items-center gap-2.5 px-4 py-2.5 text-sm font-semibold text-ink-soft hover:bg-plum-50" role="menuitem">
              <AppIcon name="idcard" :size="18" /> Identity verification
              <span class="ml-auto text-xs font-bold" :class="docLabel.cls">{{ docLabel.text }}</span>
            </RouterLink>
            <RouterLink to="/orgs" class="flex items-center gap-2.5 px-4 py-2.5 text-sm font-semibold text-ink-soft hover:bg-plum-50" role="menuitem">
              <AppIcon name="screen" :size="18" /> {{ orgs.owners.length ? 'My screens' : 'List your screens' }}
            </RouterLink>
            <RouterLink to="/security" class="flex items-center gap-2.5 px-4 py-2.5 text-sm font-semibold text-ink-soft hover:bg-plum-50" role="menuitem">
              <AppIcon name="key" :size="18" /> Two-factor security
              <span v-if="auth.user?.mfa_enabled" class="ml-auto text-xs font-bold text-success">On</span>
            </RouterLink>
            <RouterLink to="/welcome" class="flex items-center gap-2.5 px-4 py-2.5 text-sm font-semibold text-ink-soft hover:bg-plum-50" role="menuitem">
              <AppIcon name="gavel" :size="18" /> Terms and content policy
            </RouterLink>
            <button
              type="button"
              class="flex w-full items-center gap-2.5 border-t border-divider px-4 py-2.5 text-left text-sm font-semibold text-ink-soft hover:bg-plum-50"
              role="menuitem"
              @click="signOut"
            >
              <AppIcon name="logout" :size="18" /> Sign out
            </button>
          </div>
        </div>
      </template>
      <RouterLink v-else :to="loginTo" class="btn-primary btn-sm hidden lg:inline-flex">Sign in</RouterLink>

      <button
        type="button"
        class="grid h-11 w-11 place-items-center rounded-xl border border-line text-ink-soft lg:hidden"
        :aria-expanded="menuOpen"
        aria-controls="mobile-menu"
        :aria-label="menuOpen ? 'Close menu' : 'Open menu'"
        @click="menuOpen = !menuOpen"
      >
        <AppIcon :name="menuOpen ? 'x' : 'menu'" />
      </button>
    </div>

    <div v-if="menuOpen" id="mobile-menu" class="max-h-[calc(100dvh-4rem)] overflow-y-auto border-t border-line bg-white lg:hidden">
      <nav class="shell flex flex-col py-3" aria-label="Main">
        <RouterLink
          v-if="auth.isLoggedIn"
          to="/wallet"
          class="mb-2 flex items-center justify-between rounded-xl bg-plum-900 px-4 py-3 text-white"
        >
          <span class="flex items-center gap-2 text-sm font-semibold"><AppIcon name="wallet" :size="18" /> Wallet</span>
          <span class="font-extrabold">{{ wallets.wallet ? formatRupees(wallets.wallet.available) : '–' }}</span>
        </RouterLink>
        <RouterLink v-if="auth.isLoggedIn" to="/dashboard" class="flex items-center gap-3 rounded-xl px-3 py-3 text-[15px] font-semibold" :class="route.name === 'dashboard' ? 'bg-plum-100 text-plum-900' : 'text-ink-soft'">
          <AppIcon name="layers" :size="18" /> Dashboard
        </RouterLink>
        <RouterLink
          v-for="item in nav"
          :key="item.label"
          :to="item.to"
          class="flex items-center gap-3 rounded-xl px-3 py-3 text-[15px] font-semibold"
          :class="isActive(item) ? 'bg-plum-100 text-plum-900' : 'text-ink-soft'"
          :aria-current="isActive(item) ? 'page' : undefined"
        >
          <AppIcon :name="item.icon" :size="18" /> {{ item.label }}
        </RouterLink>
        <template v-if="auth.isLoggedIn">
          <RouterLink to="/verification" class="flex items-center gap-3 rounded-xl px-3 py-3 text-[15px] font-semibold text-ink-soft">
            <AppIcon name="idcard" :size="18" /> Identity verification
            <span class="ml-auto text-xs font-bold" :class="docLabel.cls">{{ docLabel.text }}</span>
          </RouterLink>
          <RouterLink to="/orgs" class="flex items-center gap-3 rounded-xl px-3 py-3 text-[15px] font-semibold text-ink-soft">
            <AppIcon name="screen" :size="18" /> {{ orgs.owners.length ? 'My screens' : 'List your screens' }}
          </RouterLink>
          <RouterLink to="/security" class="flex items-center gap-3 rounded-xl px-3 py-3 text-[15px] font-semibold text-ink-soft">
            <AppIcon name="key" :size="18" /> Two-factor security
          </RouterLink>
          <div class="mt-2 flex items-center gap-3 border-t border-divider px-3 pt-4">
            <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-plum-700 text-xs font-bold text-white">{{ initials }}</span>
            <span class="min-w-0 flex-1">
              <span class="block truncate text-sm font-bold">{{ auth.user?.name }}</span>
              <span class="block truncate text-xs text-subtle">{{ auth.user?.phone_masked ?? auth.user?.email }}</span>
            </span>
            <button type="button" class="btn-outline btn-sm" @click="signOut">Sign out</button>
          </div>
        </template>
        <RouterLink v-else :to="loginTo" class="btn-primary mt-2">Sign in</RouterLink>
      </nav>
    </div>
  </header>
</template>
