<script setup lang="ts">
import { ref } from 'vue'
import AdReview from '@/components/admin/AdReview.vue'
import DocumentReview from '@/components/admin/DocumentReview.vue'
import OrgReview from '@/components/admin/OrgReview.vue'
import AppIcon, { type IconName } from '@/components/ui/AppIcon.vue'
import PageHeader from '@/components/ui/PageHeader.vue'

type Tab = 'ads' | 'documents' | 'orgs'
const tab = ref<Tab>('ads')
const counts = ref<Record<Tab, number | null>>({ ads: null, documents: null, orgs: null })

const TABS: { value: Tab; label: string; icon: IconName }[] = [
  { value: 'ads', label: 'Ads to review', icon: 'film' },
  { value: 'documents', label: 'Identity documents', icon: 'idcard' },
  { value: 'orgs', label: 'Organisations', icon: 'building' },
]
</script>

<template>
  <div class="shell py-8 sm:py-10">
    <PageHeader
      eyebrow="Review team"
      title="Review"
      lede="Every ad is watched by a person before it plays in public. Refuse anything sexual, violent, hateful, illegal or misleading, and say why."
    />

    <div class="mb-6 grid grid-cols-3 gap-1 rounded-2xl border border-line bg-white p-1" role="tablist" aria-label="Review areas">
      <button
        v-for="t in TABS"
        :key="t.value"
        type="button"
        role="tab"
        :aria-selected="tab === t.value"
        class="flex items-center justify-center gap-2 rounded-xl px-2 py-2.5 text-sm font-bold transition"
        :class="tab === t.value ? 'bg-plum-700 text-white' : 'text-ink-soft hover:bg-plum-50'"
        @click="tab = t.value"
      >
        <AppIcon :name="t.icon" :size="18" class="hidden sm:block" />
        <span class="truncate">{{ t.label }}</span>
        <span
          v-if="counts[t.value]"
          class="rounded-full px-1.5 text-xs"
          :class="tab === t.value ? 'bg-white/20' : 'bg-coral-700 text-white'"
        >{{ counts[t.value] }}</span>
      </button>
    </div>

    <AdReview v-if="tab === 'ads'" @count="(n) => (counts.ads = n)" />
    <DocumentReview v-else-if="tab === 'documents'" @count="(n) => (counts.documents = n)" />
    <OrgReview v-else />
  </div>
</template>
