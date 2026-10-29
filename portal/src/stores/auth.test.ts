import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it } from 'vitest'
import { http } from '@/lib/http'
import { useAuthStore } from './auth'

describe('auth store', () => {
  beforeEach(() => setActivePinia(createPinia()))

  it('tracks roles and attaches the bearer token to requests', async () => {
    const auth = useAuthStore()
    auth.signIn('tok', { id: 'u1', name: 'A', email: 'a@x.np', roles: ['advertiser'] })

    expect(auth.isLoggedIn).toBe(true)
    expect(auth.hasRole('advertiser')).toBe(true)
    expect(auth.hasRole('admin')).toBe(false)

    const handler = (http.interceptors.request as unknown as { handlers: { fulfilled: (c: any) => any }[] }).handlers[0]
    const config = handler.fulfilled({ headers: {} })
    expect(config.headers.Authorization).toBe('Bearer tok')

    auth.signOut()
    expect(handler.fulfilled({ headers: {} }).headers.Authorization).toBeUndefined()
  })
})
