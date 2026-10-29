<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { errorMessage, get, post } from '@/lib/http'
import type { Booking, Campaign } from '@/lib/types'

const props = defineProps<{ org: string; campaign: string }>()
const campaign = ref<Campaign | null>(null)
const bookings = ref<Booking[]>([])
const error = ref<string | null>(null)

const base = () => `/orgs/${props.org}/campaigns/${props.campaign}`

async function load() {
  try {
    campaign.value = (await get<Campaign>(base())).data
    bookings.value = (await get<Booking[]>(`${base()}/bookings`)).data
  } catch (err) {
    error.value = errorMessage(err)
  }
}

async function cancel(id: string) {
  if (!confirm('Cancel this booking and release the slot?')) return
  try {
    await post(`${base()}/bookings/${id}/cancel`)
    await load()
  } catch (err) {
    error.value = errorMessage(err)
  }
}

const cancellable = ['draft', 'checking', 'awaiting_owner', 'approved', 'scheduled']

onMounted(load)
</script>

<template>
  <section v-if="campaign">
    <RouterLink :to="`/orgs/${org}/campaigns`" class="text-sm text-slate-600">← Campaigns</RouterLink>
    <h1 class="mt-2 text-2xl font-bold">{{ campaign.name }}</h1>
    <p class="text-slate-600">{{ campaign.start_date }} – {{ campaign.end_date }}</p>

    <div class="mt-6 flex items-center justify-between">
      <h2 class="font-semibold">Bookings</h2>
      <RouterLink to="/screens" class="btn-secondary">Book another screen</RouterLink>
    </div>
    <p v-if="error" class="mt-4 text-sm text-red-600">{{ error }}</p>
    <p v-if="!bookings.length" class="mt-4 text-slate-600">No bookings yet. Pick a screen to get started.</p>
    <div v-else class="mt-4 overflow-x-auto">
      <table class="w-full min-w-[36rem] text-left text-sm">
        <thead class="text-slate-500">
          <tr>
            <th class="py-2">Screen</th>
            <th>Dates</th>
            <th>Status</th>
            <th class="text-right">Price (NPR)</th>
            <th />
          </tr>
        </thead>
        <tbody>
          <tr v-for="b in bookings" :key="b.id" class="border-t border-slate-200">
            <td class="py-3">{{ b.screen?.name }}</td>
            <td>{{ b.start_date }} – {{ b.end_date }}</td>
            <td>
              <span class="badge">{{ b.status.replace('_', ' ') }}</span>
              <span v-if="b.hold_expires_at" class="ml-1 text-xs text-amber-700">
                held until {{ new Date(b.hold_expires_at).toLocaleTimeString() }}
              </span>
            </td>
            <td class="text-right">{{ b.price }}</td>
            <td class="text-right">
              <button v-if="cancellable.includes(b.status)" class="text-sm text-red-600 hover:underline" @click="cancel(b.id)">
                Cancel
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </section>
  <p v-else-if="error" class="text-red-600">{{ error }}</p>
</template>
