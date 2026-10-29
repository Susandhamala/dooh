<script setup lang="ts">
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { errorMessage, fieldErrors, post } from '@/lib/http'
import type { Session } from '@/lib/types'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const router = useRouter()
const route = useRoute()

const labLogin = import.meta.env.VITE_LAB_LOCAL_LOGIN === 'true'
const mode = ref<'login' | 'register'>('login')
const form = ref({ name: '', email: '', password: '' })
const error = ref<string | null>(null)
const errors = ref<Record<string, string>>({})
const busy = ref(false)

function next(): string {
  const n = route.query.next
  return typeof n === 'string' && n.startsWith('/') && !n.startsWith('//') ? n : '/orgs'
}

async function submit() {
  busy.value = true
  error.value = null
  errors.value = {}
  try {
    const res = await post<Session>(`/auth/${mode.value}`, form.value)
    auth.signIn(res.data)
    router.push(res.data.mfa_required ? { name: 'security', query: { next: next() } } : next())
  } catch (err) {
    error.value = errorMessage(err)
    errors.value = fieldErrors(err)
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <section class="mx-auto max-w-sm">
    <h1 class="text-2xl font-bold">Sign in</h1>
    <p class="mt-1 text-sm text-slate-600">Use your rentallnepal account.</p>

    <RouterLink :to="{ name: 'sso-start', query: { next: next() } }" class="btn mt-6 w-full">
      Continue with rentallnepal
    </RouterLink>

    <form v-if="labLogin" class="card mt-8 space-y-4" @submit.prevent="submit">
      <p class="text-xs font-medium tracking-wide text-amber-700 uppercase">Lab login (test only)</p>
      <div v-if="mode === 'register'">
        <label class="label" for="name">Name</label>
        <input id="name" v-model="form.name" class="input" autocomplete="name" required />
        <p v-if="errors.name" class="field-error">{{ errors.name }}</p>
      </div>
      <div>
        <label class="label" for="email">Email</label>
        <input id="email" v-model="form.email" type="email" class="input" autocomplete="email" required />
        <p v-if="errors.email" class="field-error">{{ errors.email }}</p>
      </div>
      <div>
        <label class="label" for="password">Password</label>
        <input
          id="password"
          v-model="form.password"
          type="password"
          class="input"
          :autocomplete="mode === 'login' ? 'current-password' : 'new-password'"
          required
        />
        <p v-if="errors.password" class="field-error">{{ errors.password }}</p>
      </div>
      <p v-if="error && !Object.keys(errors).length" class="text-sm text-red-600">{{ error }}</p>
      <button class="btn w-full" :disabled="busy">{{ mode === 'login' ? 'Sign in' : 'Create account' }}</button>
      <button
        type="button"
        class="w-full text-sm text-slate-600 hover:text-slate-900"
        @click="mode = mode === 'login' ? 'register' : 'login'"
      >
        {{ mode === 'login' ? 'Need a lab account? Register' : 'Have an account? Sign in' }}
      </button>
    </form>
  </section>
</template>
