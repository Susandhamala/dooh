import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { get, isUnauthorized, post, setToken, setUnauthorizedHandler } from '@/lib/http'
import { session } from '@/lib/storage'
import type { Role, Session, User } from '@/lib/types'

const KEY = 'dooh.session'

export const useAuthStore = defineStore('auth', () => {
  const user = ref<User | null>(null)
  const token = ref<string | null>(null)
  const mfaVerified = ref(false)
  let refreshTimer: ReturnType<typeof setTimeout> | undefined

  const isLoggedIn = computed(() => token.value !== null)

  function apply(s: Pick<Session, 'token' | 'expires_in'> & Partial<Session>): void {
    token.value = s.token
    if (s.user) user.value = s.user
    if (s.mfa_verified !== undefined) mfaVerified.value = s.mfa_verified
    setToken(s.token)
    session.set(KEY, JSON.stringify({ token: s.token, user: user.value, mfa: mfaVerified.value }))

    // Refresh a minute before the short-lived token expires.
    clearTimeout(refreshTimer)
    refreshTimer = setTimeout(refresh, Math.max(30, s.expires_in - 60) * 1000)
  }

  function signIn(s: Session): void {
    apply(s)
  }

  async function refresh(): Promise<void> {
    try {
      apply((await post<{ token: string; expires_in: number }>('/auth/refresh')).data)
    } catch (err) {
      // Only a rejected token ends the session; a dropped connection or a busy server retries shortly.
      if (isUnauthorized(err)) signOut()
      else if (token.value) {
        clearTimeout(refreshTimer)
        refreshTimer = setTimeout(refresh, 30_000)
      }
    }
  }

  /** Restore a session from this tab after a reload. */
  async function restore(): Promise<void> {
    const raw = session.get(KEY)
    if (!raw) return
    let saved: { token: string; user?: User | null; mfa?: boolean }
    try {
      saved = JSON.parse(raw)
    } catch {
      session.remove(KEY)
      return
    }
    setToken(saved.token)
    token.value = saved.token
    try {
      const me = await get<User>('/auth/me')
      user.value = me.data
      mfaVerified.value = me.meta?.mfa_verified === true
    } catch (err) {
      if (isUnauthorized(err)) return signOut()
      // The API is unreachable for now: keep the tab signed in with what we saved.
      user.value = saved.user ?? null
      mfaVerified.value = saved.mfa === true
    }
    await refresh()
  }

  async function signOut(): Promise<void> {
    clearTimeout(refreshTimer)
    if (token.value) post('/auth/logout').catch(() => undefined)
    token.value = null
    user.value = null
    mfaVerified.value = false
    setToken(null)
    session.remove(KEY)
  }

  function hasRole(role: Role): boolean {
    return user.value?.roles.includes(role) ?? false
  }

  /** Re-read the user after something changes their state (terms accepted, document sent, role added). */
  async function reloadMe(): Promise<void> {
    if (!token.value) return
    const me = await get<User>('/auth/me')
    user.value = me.data
    mfaVerified.value = me.meta?.mfa_verified === true
    session.set(KEY, JSON.stringify({ token: token.value, user: user.value, mfa: mfaVerified.value }))
  }

  setUnauthorizedHandler(() => {
    clearTimeout(refreshTimer)
    token.value = null
    user.value = null
    setToken(null)
    session.remove(KEY)
  })

  return { user, token, mfaVerified, isLoggedIn, signIn, signOut, restore, refresh, hasRole, reloadMe }
})
