import { defineStore } from 'pinia'
import { ref } from 'vue'
import { get } from '@/lib/http'
import type { Wallet } from '@/lib/types'
import { useAuthStore } from './auth'
import { useOrgStore } from './orgs'

/** The advertiser wallet, shared so the header balance updates after a top-up or booking. */
export const useWalletStore = defineStore('wallet', () => {
  const wallet = ref<Wallet | null>(null)
  const orgId = ref<string | null>(null)

  async function load(): Promise<Wallet | null> {
    const auth = useAuthStore()
    const orgs = useOrgStore()
    if (!auth.isLoggedIn) return null
    const org = await orgs.ensureAdvertiser(auth.user?.name ?? 'My business')
    orgId.value = org.id
    wallet.value = (await get<Wallet>(`/orgs/${org.id}/wallet`)).data
    return wallet.value
  }

  function set(w: Wallet): void {
    wallet.value = w
  }

  function reset(): void {
    wallet.value = null
    orgId.value = null
  }

  return { wallet, orgId, load, set, reset }
})
