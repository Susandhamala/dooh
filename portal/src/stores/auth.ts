import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { setToken } from '@/lib/http'

export type Role = 'advertiser' | 'owner' | 'admin'

export interface User {
  id: string
  name: string
  email: string
  roles: Role[]
}

export const useAuthStore = defineStore('auth', () => {
  const user = ref<User | null>(null)
  const token = ref<string | null>(null)

  const isLoggedIn = computed(() => token.value !== null)

  function signIn(newToken: string, newUser: User): void {
    token.value = newToken
    user.value = newUser
    setToken(newToken)
  }

  function signOut(): void {
    token.value = null
    user.value = null
    setToken(null)
  }

  function hasRole(role: Role): boolean {
    return user.value?.roles.includes(role) ?? false
  }

  return { user, token, isLoggedIn, signIn, signOut, hasRole }
})
