<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AlertBox from '@/components/ui/AlertBox.vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import { errorMessage, get, post } from '@/lib/http'
import type { Terms } from '@/lib/types'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const route = useRoute()
const router = useRouter()

const terms = ref<Terms | null>(null)
const loadError = ref<string | null>(null)
const agree = ref(false)
const busy = ref(false)
const error = ref<string | null>(null)

const next = computed(() => {
  const n = route.query.next
  return typeof n === 'string' && n.startsWith('/') && !n.startsWith('//') ? n : '/dashboard'
})
const accepted = computed(() => auth.user?.terms_accepted === true)

async function load() {
  loadError.value = null
  try {
    terms.value = (await get<Terms>('/legal/terms')).data
  } catch (err) {
    loadError.value = errorMessage(err)
  }
}

async function accept() {
  if (!terms.value || !agree.value) return
  busy.value = true
  error.value = null
  try {
    await post('/auth/terms/accept', { version: terms.value.version, agree: true })
    await auth.reloadMe()
    router.push(next.value)
  } catch (err) {
    error.value = errorMessage(err)
  } finally {
    busy.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="page-wash">
    <div class="shell py-8 sm:py-12">
      <div class="mx-auto max-w-3xl">
        <div v-if="!terms && !loadError" class="card p-8" aria-busy="true">
          <div class="skeleton h-8 w-2/3" />
          <div class="skeleton mt-4 h-4 w-full" />
          <div class="skeleton mt-2 h-4 w-5/6" />
          <div class="mt-8 grid gap-3 sm:grid-cols-2"><div v-for="i in 6" :key="i" class="skeleton h-20" /></div>
        </div>
        <AlertBox v-else-if="loadError" title="Couldn’t load the terms">
          {{ loadError }}
          <template #action><button type="button" class="link shrink-0 text-sm" @click="load">Try again</button></template>
        </AlertBox>

        <article v-else-if="terms" class="card overflow-hidden shadow-card">
          <header class="border-b border-divider p-6 sm:p-8">
            <p class="eyebrow">Before you start · version {{ terms.version }}</p>
            <h1 class="h-page mt-1.5">{{ terms.title }}</h1>
            <p class="mt-3 text-[15px] text-body">{{ terms.intro }}</p>
          </header>

          <section class="p-6 sm:p-8" aria-labelledby="prohibited">
            <h2 id="prohibited" class="flex items-center gap-2 text-lg font-extrabold text-danger">
              <AppIcon name="ban" /> Never allowed on any screen
            </h2>
            <ul class="mt-4 grid gap-3 sm:grid-cols-2">
              <li v-for="p in terms.prohibited" :key="p.title" class="rounded-xl border border-danger/20 bg-danger/[0.04] p-4">
                <p class="font-bold text-ink">{{ p.title }}</p>
                <p class="mt-1 text-sm text-muted">{{ p.body }}</p>
              </li>
            </ul>
          </section>

          <section class="border-t border-divider p-6 sm:p-8" aria-labelledby="how">
            <h2 id="how" class="text-lg font-extrabold">How it works</h2>
            <ul class="mt-3 space-y-2.5">
              <li v-for="r in terms.rules" :key="r" class="flex gap-2.5 text-[15px] text-body">
                <AppIcon name="check" :size="18" class="mt-0.5 text-success" /> {{ r }}
              </li>
            </ul>
          </section>

          <section class="border-t border-divider bg-notice p-6 sm:p-8" aria-labelledby="legal">
            <h2 id="legal" class="flex items-center gap-2 text-lg font-extrabold text-notice-ink">
              <AppIcon name="gavel" /> Legal action
            </h2>
            <p class="mt-2 text-[15px] text-notice-ink">{{ terms.legal }}</p>
          </section>

          <footer class="border-t border-divider p-6 sm:p-8">
            <template v-if="!auth.isLoggedIn">
              <p class="text-[15px] text-muted">Sign in to accept these terms and start advertising.</p>
              <RouterLink :to="{ name: 'login', query: { next: next } }" class="btn-primary mt-4">Sign in</RouterLink>
            </template>
            <div v-else-if="accepted" class="flex flex-wrap items-center justify-between gap-4">
              <p class="flex items-center gap-2 font-bold text-success"><AppIcon name="check" /> You have accepted this version.</p>
              <RouterLink :to="next" class="btn-primary">Continue</RouterLink>
            </div>
            <form v-else novalidate @submit.prevent="accept">
              <label
                class="flex cursor-pointer gap-3 rounded-xl border p-4 transition"
                :class="agree ? 'border-plum-700 bg-plum-50' : 'border-line-strong hover:border-plum-300'"
              >
                <input v-model="agree" type="checkbox" class="mt-1 h-5 w-5 shrink-0 accent-plum-700" />
                <span class="text-[15px] text-ink">
                  I have read and agree to the terms and content policy. I will not upload sexual, violent, hateful,
                  harassing, illegal or misleading content. I understand that if I do, my ads will be removed, my account
                  suspended and <strong>legal action will be taken</strong>.
                </span>
              </label>
              <AlertBox v-if="error" class="mt-4">{{ error }}</AlertBox>
              <div class="mt-5 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <RouterLink to="/screens" class="btn-outline">Not now</RouterLink>
                <button class="btn-primary sm:min-w-44" :disabled="!agree || busy">{{ busy ? 'Saving…' : 'I agree' }}</button>
              </div>
              <p class="mt-3 text-xs text-subtle">
                Your acceptance is recorded with the date, time and the exact version of these terms.
              </p>
            </form>
          </footer>
        </article>
      </div>
    </div>
  </div>
</template>
