<script setup lang="ts">
import { RouterLink, RouterView, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useOrgStore } from '@/stores/orgs'

const auth = useAuthStore()
const orgs = useOrgStore()
const router = useRouter()

async function signOut() {
  await auth.signOut()
  orgs.reset()
  router.push('/')
}
</script>

<template>
  <div class="min-h-screen bg-slate-50 text-slate-900">
    <header class="border-b border-slate-200 bg-white">
      <nav class="mx-auto flex max-w-6xl flex-wrap items-center gap-x-6 gap-y-2 px-4 py-3">
        <RouterLink to="/" class="font-semibold text-brand">Rentall Screen Ads</RouterLink>
        <RouterLink to="/screens" class="text-sm text-slate-600 hover:text-slate-900">Screens</RouterLink>
        <template v-if="auth.isLoggedIn">
          <RouterLink to="/orgs" class="text-sm text-slate-600 hover:text-slate-900">My organisations</RouterLink>
          <span class="ml-auto text-sm text-slate-500">{{ auth.user?.name }}</span>
          <RouterLink to="/security" class="text-sm text-slate-600 hover:text-slate-900">Security</RouterLink>
          <button class="text-sm text-slate-600 hover:text-slate-900" @click="signOut">Sign out</button>
        </template>
        <RouterLink v-else to="/login" class="btn ml-auto">Sign in</RouterLink>
      </nav>
    </header>
    <main class="mx-auto max-w-6xl px-4 py-8">
      <RouterView />
    </main>
  </div>
</template>
