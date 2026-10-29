import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { get, post, setToken, setUnauthorizedHandler } from '@/lib/http'
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
    } catch {
      signOut()
    }
  }

  /** Restore a session from this tab after a reload. */
  async function restore(): Promise<void> {
    const raw = session.get(KEY)
    if (!raw) return
    try {
      const saved = JSON.parse(raw) as { token: string }
      setToken(saved.token)
      token.value = saved.token
      const me = await get<User>('/auth/me')
      user.value = me.data
      mfaVerified.value = me.meta?.mfa_verified === true
      await refresh()
    } catch {
      signOut()
    }
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

  setUnauthorizedHandler(() => {
    clearTimeout(refreshTimer)
    token.value = null
    user.value = null
    setToken(null)
    session.remove(KEY)
  })

  return { user, token, mfaVerified, isLoggedIn, signIn, signOut, restore, refresh, hasRole }
})
