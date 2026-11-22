<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { errorMessage, fieldErrors, post } from '@/lib/http'
import type { Session } from '@/lib/types'
import { useAuthStore } from '@/stores/auth'

/** Admin sign-in (/4dmin): username and password, straight into the admin panel. */
const auth = useAuthStore()
const router = useRouter()

const username = ref('')
const password = ref('')
const show = ref(false)
const error = ref<string | null>(null)
const busy = ref(false)

async function submit() {
  if (!username.value.trim() || !password.value) {
    error.value = 'Enter your username and password.'
    return
  }
  busy.value = true
  error.value = null
  try {
    const res = await post<Session>('/auth/admin/login', { username: username.value.trim(), password: password.value })
    auth.signIn(res.data)
    router.push({ name: 'admin' })
  } catch (err) {
    const f = fieldErrors(err)
    error.value = f.username ?? f.password ?? errorMessage(err)
    password.value = ''
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <div class="page-wash">
    <div class="shell flex justify-center py-12 sm:py-16">
      <section class="w-full max-w-[400px]">
        <form class="card p-6 shadow-card sm:p-8" novalidate @submit.prevent="submit">
          <img src="/brand/logo-mark.png" width="140" height="148" alt="" class="h-11 w-auto" />
          <h1 class="h-page mt-5">Admin sign in</h1>
          <p class="mt-1.5 text-[15px] text-muted">For the review team only.</p>

          <label class="field-label mt-6" for="username">Username</label>
          <input
            id="username"
            v-model="username"
            class="input"
            :class="{ 'input-error': error }"
            autocomplete="username"
            autocapitalize="none"
            spellcheck="false"
            maxlength="50"
            autofocus
          />

          <label class="field-label mt-4" for="password">Password</label>
          <div class="relative">
            <input
              id="password"
              v-model="password"
              class="input pr-16"
              :class="{ 'input-error': error }"
              :type="show ? 'text' : 'password'"
              autocomplete="current-password"
              maxlength="200"
            />
            <button
              type="button"
              class="absolute top-1/2 right-2 -translate-y-1/2 rounded-lg px-2 py-1 text-sm font-bold text-plum-700 hover:bg-plum-50"
              :aria-pressed="show"
              @click="show = !show"
            >
              {{ show ? 'Hide' : 'Show' }}
            </button>
          </div>
          <p v-if="error" class="field-error" role="alert">{{ error }}</p>

          <button class="btn-primary mt-6 w-full" :disabled="busy">{{ busy ? 'Signing in…' : 'Sign in' }}</button>
        </form>
      </section>
    </div>
  </div>
</template>
