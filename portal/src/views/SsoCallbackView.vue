<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
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
  <section class="mx-auto max-w-sm">
    <p v-if="!error" class="text-slate-600">Signing you in…</p>
    <div v-else class="card">
      <p class="text-sm text-red-600">{{ error }}</p>
      <RouterLink to="/login" class="btn mt-4">Back to sign in</RouterLink>
    </div>
  </section>
</template>
