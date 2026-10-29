import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { get, post } from '@/lib/http'
import type { Organisation } from '@/lib/types'

export const useOrgStore = defineStore('orgs', () => {
  const list = ref<Organisation[]>([])
  const loaded = ref(false)

  const advertisers = computed(() => list.value.filter((o) => o.type === 'advertiser'))
  const owners = computed(() => list.value.filter((o) => o.type === 'owner'))

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

  function reset(): void {
    list.value = []
    loaded.value = false
  }

  return { list, loaded, advertisers, owners, load, create, reset }
})
