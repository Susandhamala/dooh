<script setup lang="ts">
import QRCode from 'qrcode'
import { computed, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AlertBox from '@/components/ui/AlertBox.vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
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

const next = computed(() => {
  const n = route.query.next
  return typeof n === 'string' && n.startsWith('/') && !n.startsWith('//') ? n : null
})
const codeValid = computed(() => /^\d{6}$/.test(code.value))

/** Keep digits only, so pasted "123 456" works. */
function onCodeInput(e: Event) {
  code.value = (e.target as HTMLInputElement).value.replace(/\D/g, '').slice(0, 6)
}

async function startSetup() {
  busy.value = true
  error.value = null
  try {
    const res = await post<{ secret: string; otpauth_url: string }>('/auth/mfa/setup')
    setup.value = {
      secret: res.data.secret,
      qr: await QRCode.toDataURL(res.data.otpauth_url, { margin: 1, width: 384, color: { dark: '#241436' } }),
    }
  } catch (err) {
    error.value = errorMessage(err)
  } finally {
    busy.value = false
  }
}

async function submit(action: 'enable' | 'verify') {
  if (!codeValid.value) {
    error.value = 'Enter the 6 digits your app shows.'
    return
  }
  busy.value = true
  error.value = null
  try {
    auth.signIn((await post<Session>(`/auth/mfa/${action}`, { code: code.value })).data)
    setup.value = null
    code.value = ''
    if (next.value) router.push(next.value)
  } catch (err) {
    error.value = errorMessage(err)
    code.value = ''
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <div class="shell py-8 sm:py-10">
    <div class="mx-auto max-w-xl">
      <PageHeader
        eyebrow="Account security"
        title="Two-factor authentication"
        lede="Screen owners and admins confirm changes with a code from an authenticator app, so a stolen password alone can’t publish or approve anything."
      />

      <AlertBox v-if="next && !auth.mfaVerified" tone="info" class="mb-5">
        {{ auth.user?.mfa_enabled ? 'Enter a code to continue.' : 'Turn on two-factor to continue.' }}
        You’ll go straight back to what you were doing.
      </AlertBox>

      <div class="card p-5 shadow-card sm:p-7">
        <!-- Already on -->
        <template v-if="auth.user?.mfa_enabled">
          <div v-if="auth.mfaVerified" class="flex items-start gap-3">
            <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-success/10 text-success">
              <AppIcon name="shield" />
            </span>
            <div>
              <h2 class="font-extrabold">Two-factor is on</h2>
              <p class="mt-0.5 text-sm text-muted">This session is verified. You can make owner and admin changes.</p>
            </div>
          </div>
          <form v-else class="space-y-4" novalidate @submit.prevent="submit('verify')">
            <div class="flex items-start gap-3">
              <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-plum-100 text-plum-700"><AppIcon name="key" /></span>
              <div>
                <h2 class="font-extrabold">Confirm it’s you</h2>
                <p class="mt-0.5 text-sm text-muted">Open your authenticator app and enter the current code.</p>
              </div>
            </div>
            <label class="sr-only" for="code">6-digit code</label>
            <input
              id="code"
              :value="code"
              class="input text-center font-mono text-2xl tracking-[0.5em]"
              inputmode="numeric"
              autocomplete="one-time-code"
              maxlength="6"
              placeholder="000000"
              autofocus
              @input="onCodeInput"
            />
            <AlertBox v-if="error">{{ error }}</AlertBox>
            <button class="btn-plum w-full" :disabled="busy">{{ busy ? 'Checking…' : 'Verify' }}</button>
          </form>
        </template>

        <!-- Setting up -->
        <template v-else-if="setup">
          <ol class="space-y-6">
            <li>
              <p class="font-extrabold"><span class="text-coral-700">1.</span> Scan this with your authenticator app</p>
              <img :src="setup.qr" alt="QR code to add Screen Ads to your authenticator app" class="mx-auto mt-4 h-48 w-48 rounded-xl border border-line p-2" />
              <details class="mt-3 text-sm">
                <summary class="link cursor-pointer">Can’t scan? Enter a key instead</summary>
                <code class="mt-2 block rounded-lg bg-canvas px-3 py-2 font-mono text-sm break-all text-ink">{{ setup.secret }}</code>
              </details>
            </li>
            <li>
              <form class="space-y-3" novalidate @submit.prevent="submit('enable')">
                <label class="block font-extrabold" for="code"><span class="text-coral-700">2.</span> Enter the 6-digit code it shows</label>
                <input
                  id="code"
                  :value="code"
                  class="input text-center font-mono text-2xl tracking-[0.5em]"
                  inputmode="numeric"
                  autocomplete="one-time-code"
                  maxlength="6"
                  placeholder="000000"
                  @input="onCodeInput"
                />
                <AlertBox v-if="error">{{ error }}</AlertBox>
                <button class="btn-plum w-full" :disabled="busy">{{ busy ? 'Checking…' : 'Turn on two-factor' }}</button>
              </form>
            </li>
          </ol>
        </template>

        <!-- Off -->
        <template v-else>
          <div class="flex items-start gap-3">
            <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-plum-100 text-plum-700"><AppIcon name="lock" /></span>
            <div>
              <h2 class="font-extrabold">Two-factor is off</h2>
              <p class="mt-0.5 text-sm text-muted">
                You’ll need Google Authenticator, Authy, 1Password or a similar app on your phone.
              </p>
            </div>
          </div>
          <AlertBox v-if="error" class="mt-4">{{ error }}</AlertBox>
          <button type="button" class="btn-plum mt-5 w-full" :disabled="busy" @click="startSetup">
            {{ busy ? 'Starting…' : 'Set up two-factor' }}
          </button>
        </template>
      </div>
    </div>
  </div>
</template>
