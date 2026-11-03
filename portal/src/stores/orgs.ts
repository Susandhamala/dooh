import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { get, post } from '@/lib/http'
import type { Organisation } from '@/lib/types'

export const useOrgStore = defineStore('orgs', () => {
  const list = ref<Organisation[]>([])
  const loaded = ref(false)

  const advertisers = computed(() => list.value.filter((o) => o.type === 'advertiser'))
  const owners = computed(() => list.value.filter((o) => o.type === 'owner'))
  /** The organisation ads, bookings and the wallet belong to (every phone sign-up gets one). */
  const advertiser = computed(() => advertisers.value[0] ?? null)

  async function load(force = false): Promise<void> {
    if (loaded.value && !force) return
    list.value = (await get<Organisation[]>('/orgs')).data
    loaded.value = true
  }

  async function create(body: { name: string; type: string; pan_number?: string }): Promise<Organisation> {
    const org = (await post<Organisation>('/orgs', body)).data
    await load(true)
    return org
  }

  /** The advertiser organisation, creating one named after the user if they have none yet. */
  async function ensureAdvertiser(name: string): Promise<Organisation> {
    await load()
    return advertiser.value ?? create({ name, type: 'advertiser' })
  }

  function reset(): void {
    list.value = []
    loaded.value = false
  }

  return { list, loaded, advertisers, owners, advertiser, load, create, ensureAdvertiser, reset }
})
