<script setup lang="ts">
import { onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { session } from '@/lib/storage'

const route = useRoute()

onMounted(() => {
  // Random state ties the callback to this browser tab (blocks login CSRF).
  const bytes = crypto.getRandomValues(new Uint8Array(32))
  const state = Array.from(bytes, (b) => b.toString(16).padStart(2, '0')).join('')
  session.set('dooh.sso.state', state)
  const next = typeof route.query.next === 'string' ? route.query.next : '/orgs'
  session.set('dooh.sso.next', next)

  const base = import.meta.env.VITE_RENTALL_URL ?? 'https://rentallnepal.com'
  window.location.assign(`${base}/screen-ads/authorize?state=${state}`)
})
</script>

<template>
  <div class="shell flex justify-center py-20">
    <div class="flex flex-col items-center text-center" role="status">
      <span class="h-10 w-10 animate-spin rounded-full border-4 border-plum-200 border-t-plum-700" aria-hidden="true" />
      <p class="mt-4 font-semibold text-ink-soft">Taking you to RentALL Nepal…</p>
    </div>
  </div>
</template>
