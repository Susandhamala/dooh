<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AlertBox from '@/components/ui/AlertBox.vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import { errorMessage, fieldErrors, post } from '@/lib/http'
import type { Session } from '@/lib/types'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const router = useRouter()
const route = useRoute()

type Step = 'phone' | 'code' | 'name'
const step = ref<Step>('phone')
const phone = ref('')
const code = ref('')
const name = ref('')
const devCode = ref<string | null>(null)
const error = ref<string | null>(null)
const busy = ref(false)
const resendIn = ref(0)
const codeEl = ref<HTMLInputElement>()
const nameEl = ref<HTMLInputElement>()
let timer: ReturnType<typeof setInterval> | undefined

/** Digits only, without +977 or a leading 0. */
const digits = computed(() => phone.value.replace(/\D/g, '').replace(/^(977|0)/, ''))
const phoneValid = computed(() => /^9[678]\d{8}$/.test(digits.value))
const pretty = computed(() => `+977 ${digits.value.slice(0, 3)} ${digits.value.slice(3, 6)} ${digits.value.slice(6)}`)

function next(): string {
  const n = route.query.next
  return typeof n === 'string' && n.startsWith('/') && !n.startsWith('//') ? n : '/dashboard'
}

function startCountdown(seconds: number) {
  resendIn.value = seconds
  clearInterval(timer)
  timer = setInterval(() => {
    resendIn.value = Math.max(0, resendIn.value - 1)
    if (!resendIn.value) clearInterval(timer)
  }, 1000)
}
onBeforeUnmount(() => clearInterval(timer))

async function sendCode() {
  if (!phoneValid.value) {
    error.value = 'Enter a 10-digit Nepali mobile number starting with 96, 97 or 98.'
    return
  }
  busy.value = true
  error.value = null
  try {
    const res = await post<{ expires_in: number; resend_in: number; dev_code?: string }>('/auth/phone/code', { phone: digits.value })
    devCode.value = res.data.dev_code ?? null
    startCountdown(res.data.resend_in)
    step.value = 'code'
    code.value = ''
    await nextTick()
    codeEl.value?.focus()
  } catch (err) {
    error.value = fieldErrors(err).phone ?? errorMessage(err)
  } finally {
    busy.value = false
  }
}

function onCode(e: Event) {
  code.value = (e.target as HTMLInputElement).value.replace(/\D/g, '').slice(0, 6)
  if (code.value.length === 6 && step.value === 'code') verify()
}

async function verify() {
  if (!/^\d{6}$/.test(code.value)) {
    error.value = 'Enter the 6-digit code from the SMS.'
    return
  }
  busy.value = true
  error.value = null
  try {
    const res = await post<Session>('/auth/phone/login', {
      phone: digits.value,
      code: code.value,
      name: step.value === 'name' ? name.value.trim() : undefined,
    })
    auth.signIn(res.data)
    if (res.data.mfa_required) router.push({ name: 'security', query: { next: next() } })
    else router.push(res.data.user.terms_accepted ? next() : { name: 'welcome', query: { next: next() } })
  } catch (err) {
    const fields = fieldErrors(err)
    if (fields.name && step.value === 'code') {
      // A new number: the code is right, now we need a name.
      step.value = 'name'
      await nextTick()
      nameEl.value?.focus()
    } else {
      error.value = fields.code ?? fields.name ?? errorMessage(err)
      if (step.value === 'code') code.value = ''
    }
  } finally {
    busy.value = false
  }
}

function changeNumber() {
  step.value = 'phone'
  error.value = null
  devCode.value = null
}
</script>

<template>
  <div class="page-wash">
    <div class="shell flex justify-center py-12 sm:py-16">
      <section class="w-full max-w-[420px]">
        <div class="card p-6 shadow-card sm:p-8">
          <img src="/brand/logo-mark.png" width="140" height="148" alt="" class="h-11 w-auto" />

          <!-- 1. Phone number -->
          <form v-if="step === 'phone'" class="mt-5" novalidate @submit.prevent="sendCode">
            <h1 class="h-page">Sign in or sign up</h1>
            <p class="mt-1.5 text-[15px] text-muted">Use your mobile number. We’ll text you a code.</p>

            <label class="field-label mt-6" for="phone">Mobile number</label>
            <div
              class="flex items-center overflow-hidden rounded-xl border bg-white transition focus-within:border-plum-700 focus-within:ring-2 focus-within:ring-plum-700/15"
              :class="error ? 'border-danger' : 'border-line-strong'"
            >
              <span class="flex items-center gap-1.5 self-stretch border-r border-line bg-canvas px-3 text-[15px] font-bold text-ink-soft">
                +977
              </span>
              <input
                id="phone"
                v-model="phone"
                class="w-full px-3.5 py-2.5 text-lg font-semibold tracking-wide outline-none"
                type="tel"
                inputmode="numeric"
                autocomplete="tel-national"
                placeholder="98XXXXXXXX"
                maxlength="16"
                autofocus
              />
            </div>
            <p v-if="error" class="field-error">{{ error }}</p>
            <p v-else class="field-hint">NTC, Ncell and Smart numbers.</p>

            <button class="btn-primary mt-6 w-full" :disabled="busy">{{ busy ? 'Sending…' : 'Send code' }}</button>
          </form>

          <!-- 2. Code (and 3. name for a new number) -->
          <form v-else class="mt-5" novalidate @submit.prevent="verify">
            <h1 class="h-page">{{ step === 'name' ? 'Welcome!' : 'Enter the code' }}</h1>
            <p class="mt-1.5 text-[15px] text-muted">
              <template v-if="step === 'name'">This number is new here. What should we call you?</template>
              <template v-else>
                We sent a 6-digit code to <strong class="whitespace-nowrap text-ink">{{ pretty }}</strong>.
              </template>
            </p>

            <AlertBox v-if="devCode && step === 'code'" tone="warning" class="mt-4">
              Lab mode: no SMS is sent. Your code is <strong class="font-mono text-base">{{ devCode }}</strong>
            </AlertBox>

            <template v-if="step === 'code'">
              <label class="sr-only" for="code">6-digit code</label>
              <input
                id="code"
                ref="codeEl"
                :value="code"
                class="input mt-5 text-center font-mono text-2xl tracking-[0.5em]"
                :class="{ 'input-error': error }"
                inputmode="numeric"
                autocomplete="one-time-code"
                maxlength="6"
                placeholder="••••••"
                @input="onCode"
              />
            </template>
            <template v-else>
              <label class="field-label mt-5" for="name">Your name or business name</label>
              <input
                id="name"
                ref="nameEl"
                v-model="name"
                class="input"
                :class="{ 'input-error': error }"
                autocomplete="name"
                maxlength="120"
                placeholder="e.g. Himalayan Java Coffee"
              />
            </template>
            <p v-if="error" class="field-error">{{ error }}</p>

            <button class="btn-primary mt-6 w-full" :disabled="busy || (step === 'name' && name.trim().length < 2)">
              {{ busy ? 'Checking…' : step === 'name' ? 'Create my account' : 'Continue' }}
            </button>

            <div class="mt-4 flex items-center justify-between text-sm">
              <button type="button" class="link inline-flex items-center gap-1" @click="changeNumber">
                <AppIcon name="back" :size="15" /> Change number
              </button>
              <button v-if="step === 'code'" type="button" class="link disabled:text-faint" :disabled="resendIn > 0 || busy" @click="sendCode">
                {{ resendIn ? `Resend in ${resendIn}s` : 'Resend code' }}
              </button>
            </div>
          </form>
        </div>

        <div class="mt-5 flex items-center gap-3 text-xs font-semibold text-subtle">
          <span class="h-px flex-1 bg-line" /> or <span class="h-px flex-1 bg-line" />
        </div>
        <RouterLink :to="{ name: 'sso-start', query: { next: next() } }" class="btn-outline mt-5 w-full">
          Continue with your RentALL Nepal account
        </RouterLink>
        <p class="mt-4 text-center text-xs text-subtle">
          By continuing you agree to the
          <RouterLink to="/welcome" class="link">terms and content policy</RouterLink>.
        </p>
      </section>
    </div>
  </div>
</template>
