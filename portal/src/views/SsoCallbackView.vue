<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AlertBox from '@/components/ui/AlertBox.vue'
import { errorMessage, post } from '@/lib/http'
import { session } from '@/lib/storage'
import type { Session } from '@/lib/types'
import { useAuthStore } from '@/stores/auth'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const error = ref<string | null>(null)

onMounted(async () => {
  const expected = session.get('dooh.sso.state')
  const next = session.get('dooh.sso.next') ?? '/orgs'
  session.remove('dooh.sso.state')
  session.remove('dooh.sso.next')

  const { code, state } = route.query
  // Strip the code from the address bar and history straight away.
  router.replace({ query: {} })

  if (!expected || typeof state !== 'string' || state !== expected || typeof code !== 'string') {
    error.value = 'This sign-in link was not started from this browser. Please try again.'
    return
  }

  try {
    const res = await post<Session>('/auth/sso', { code })
    auth.signIn(res.data)
    const safeNext = next.startsWith('/') && !next.startsWith('//') ? next : '/orgs'
    router.replace(res.data.mfa_required ? { name: 'security', query: { next: safeNext } } : safeNext)
  } catch (err) {
    error.value = errorMessage(err)
  }
})
</script>

<template>
  <div class="shell flex justify-center py-20">
    <div v-if="!error" class="flex flex-col items-center text-center" role="status">
      <span class="h-10 w-10 animate-spin rounded-full border-4 border-plum-200 border-t-plum-700" aria-hidden="true" />
      <p class="mt-4 font-semibold text-ink-soft">Signing you in…</p>
    </div>
    <div v-else class="card w-full max-w-[420px] p-6">
      <h1 class="h-section">We couldn’t sign you in</h1>
      <AlertBox class="mt-4">{{ error }}</AlertBox>
      <RouterLink to="/login" class="btn-primary mt-5 w-full">Back to sign in</RouterLink>
    </div>
  </div>
</template>
