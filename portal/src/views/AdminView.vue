<script setup lang="ts">
import { computed, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AdminOverview from '@/components/admin/AdminOverview.vue'
import AdReview from '@/components/admin/AdReview.vue'
import BookingAdmin from '@/components/admin/BookingAdmin.vue'
import DocumentReview from '@/components/admin/DocumentReview.vue'
import OrgReview from '@/components/admin/OrgReview.vue'
import UserAdmin from '@/components/admin/UserAdmin.vue'
import AppIcon, { type IconName } from '@/components/ui/AppIcon.vue'

/** The admin panel: dashboard, users, the two review queues, bookings and organisations. */
type Tab = 'overview' | 'users' | 'documents' | 'ads' | 'bookings' | 'orgs'

const TABS: { value: Tab; label: string; icon: IconName; lede: string }[] = [
  { value: 'overview', label: 'Dashboard', icon: 'grid', lede: 'What needs doing, and how the screens are doing.' },
  { value: 'users', label: 'Users', icon: 'user', lede: 'Everyone who signed up with their number. Suspend anyone who breaks the rules.' },
  { value: 'documents', label: 'Identity documents', icon: 'idcard', lede: 'Check each document matches the person or business. Nobody can book until verified.' },
  { value: 'ads', label: 'Ads to review', icon: 'film', lede: 'Watch every ad before it can be booked. Refuse anything sexual, violent, hateful, illegal or misleading, and say why.' },
  { value: 'bookings', label: 'Bookings', icon: 'calendar', lede: 'Every booking on every screen. Cancelling refunds the plays that haven’t happened.' },
  { value: 'orgs', label: 'Organisations', icon: 'building', lede: 'Screen owners and advertisers. Verify owners before their screens go public.' },
]

const route = useRoute()
const router = useRouter()
const tab = computed<Tab>(() => (TABS.some((t) => t.value === route.query.tab) ? (route.query.tab as Tab) : 'overview'))
const current = computed(() => TABS.find((t) => t.value === tab.value)!)
const counts = ref<Partial<Record<Tab, number>>>({})

function go(t: string) {
  router.replace({ query: t === 'overview' ? {} : { tab: t } })
}
</script>

<template>
  <div class="shell py-6 sm:py-8">
    <div class="grid grid-cols-[minmax(0,1fr)] gap-6 lg:grid-cols-[220px_minmax(0,1fr)] lg:items-start">
      <!-- Navigation -->
      <nav aria-label="Admin" class="lg:sticky lg:top-24">
        <p class="eyebrow mb-3 hidden lg:block">Admin panel</p>
        <ul class="flex gap-1 overflow-x-auto pb-1 lg:flex-col lg:overflow-visible">
          <li v-for="t in TABS" :key="t.value" class="shrink-0">
            <button
              type="button"
              class="flex w-full items-center gap-2.5 rounded-xl px-3 py-2.5 text-sm font-bold whitespace-nowrap transition"
              :class="tab === t.value ? 'bg-plum-700 text-white' : 'text-ink-soft hover:bg-plum-50'"
              :aria-current="tab === t.value ? 'page' : undefined"
              @click="go(t.value)"
            >
              <AppIcon :name="t.icon" :size="18" />
              <span class="flex-1 text-left">{{ t.label }}</span>
              <span
                v-if="counts[t.value]"
                class="rounded-full px-1.5 text-xs"
                :class="tab === t.value ? 'bg-white/20' : 'bg-coral-700 text-white'"
              >{{ counts[t.value] }}</span>
            </button>
          </li>
        </ul>
      </nav>

      <main class="min-w-0">
        <header class="mb-6">
          <h1 class="h-page">{{ current.label }}</h1>
          <p class="mt-1.5 text-muted">{{ current.lede }}</p>
        </header>

        <AdminOverview v-if="tab === 'overview'" @go="go" @counts="(c) => ((counts.documents = c.documents), (counts.ads = c.ads))" />
        <UserAdmin v-else-if="tab === 'users'" />
        <DocumentReview v-else-if="tab === 'documents'" @count="(n) => (counts.documents = n)" />
        <AdReview v-else-if="tab === 'ads'" @count="(n) => (counts.ads = n)" />
        <BookingAdmin v-else-if="tab === 'bookings'" />
        <OrgReview v-else />
      </main>
    </div>
  </div>
</template>
