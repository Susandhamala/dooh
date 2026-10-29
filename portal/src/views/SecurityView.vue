<script setup lang="ts">
import QRCode from 'qrcode'
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { errorMessage, post } from '@/lib/http'
import type { Session } from '@/lib/types'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const route = useRoute()
const router = useRouter()

const setup = ref<{ secret: string; qr: string } | null>(null)
const code = ref('')
const error = ref<string | null>(null)
const busy = ref(false)

function done() {
  const n = route.query.next
  if (typeof n === 'string' && n.startsWith('/') && !n.startsWith('//')) router.push(n)
}

async function startSetup() {
  error.value = null
  try {
    const res = await post<{ secret: string; otpauth_url: string }>('/auth/mfa/setup')
    setup.value = { secret: res.data.secret, qr: await QRCode.toDataURL(res.data.otpauth_url) }
  } catch (err) {
    error.value = errorMessage(err)
  }
}

async function submit(action: 'enable' | 'verify') {
  busy.value = true
  error.value = null
  try {
    auth.signIn((await post<Session>(`/auth/mfa/${action}`, { code: code.value })).data)
    setup.value = null
    code.value = ''
    done()
  } catch (err) {
    error.value = errorMessage(err)
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <section class="mx-auto max-w-md">
    <h1 class="text-2xl font-bold">Two-factor authentication</h1>
    <p class="mt-1 text-sm text-slate-600">
      Screen owners and admins need a code from an authenticator app (Google Authenticator, Authy, 1Password) to make
      changes.
    </p>

    <div class="card mt-6">
      <template v-if="auth.user?.mfa_enabled">
        <p v-if="auth.mfaVerified" class="text-sm text-emerald-700">✓ Enabled and verified for this session.</p>
        <form v-else class="space-y-3" @submit.prevent="submit('verify')">
          <label class="label" for="code">Enter the 6-digit code from your app</label>
          <input id="code" v-model="code" class="input" inputmode="numeric" autocomplete="one-time-code" maxlength="6" required />
          <button class="btn" :disabled="busy">Verify</button>
        </form>
      </template>

      <template v-else-if="setup">
        <p class="text-sm">1. Scan this code with your authenticator app.</p>
        <img :src="setup.qr" alt="Two-factor QR code" class="mx-auto my-3 h-48 w-48" />
        <p class="text-xs break-all text-slate-500">Or enter this key: <code>{{ setup.secret }}</code></p>
        <form class="mt-4 space-y-3" @submit.prevent="submit('enable')">
          <label class="label" for="code">2. Enter the 6-digit code it shows</label>
          <input id="code" v-model="code" class="input" inputmode="numeric" autocomplete="one-time-code" maxlength="6" required />
          <button class="btn" :disabled="busy">Turn on two-factor</button>
        </form>
      </template>

      <button v-else class="btn" @click="startSetup">Set up two-factor</button>

      <p v-if="error" class="mt-3 text-sm text-red-600">{{ error }}</p>
    </div>
  </section>
</template>
