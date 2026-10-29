<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { isoDay } from '@/lib/dates'
import { errorMessage, fieldErrors, get, post } from '@/lib/http'
import type { Campaign } from '@/lib/types'

const props = defineProps<{ org: string }>()
const campaigns = ref<Campaign[]>([])
const form = ref({ name: '', start_date: isoDay(1), end_date: isoDay(30) })
const errors = ref<Record<string, string>>({})
const error = ref<string | null>(null)

async function load() {
  try {
    campaigns.value = (await get<Campaign[]>(`/orgs/${props.org}/campaigns`)).data
  } catch (err) {
    error.value = errorMessage(err)
  }
}

async function create() {
  errors.value = {}
  error.value = null
  try {
    await post(`/orgs/${props.org}/campaigns`, form.value)
    form.value.name = ''
    await load()
  } catch (err) {
    errors.value = fieldErrors(err)
    error.value = errorMessage(err)
  }
}

onMounted(load)
</script>

<template>
  <section>
    <h1 class="text-2xl font-bold">Campaigns</h1>

    <form class="card mt-6 grid gap-4 sm:grid-cols-4" @submit.prevent="create">
      <div class="sm:col-span-2">
        <label class="label" for="c-name">New campaign</label>
        <input id="c-name" v-model="form.name" class="input" placeholder="Dashain sale" required />
        <p v-if="errors.name" class="field-error">{{ errors.name }}</p>
      </div>
      <div>
        <label class="label" for="c-start">Start</label>
        <input id="c-start" v-model="form.start_date" type="date" class="input" :min="isoDay(0)" />
        <p v-if="errors.start_date" class="field-error">{{ errors.start_date }}</p>
      </div>
      <div>
        <label class="label" for="c-end">End</label>
        <input id="c-end" v-model="form.end_date" type="date" class="input" :min="form.start_date" />
        <p v-if="errors.end_date" class="field-error">{{ errors.end_date }}</p>
      </div>
      <div class="sm:col-span-4"><button class="btn">Create campaign</button></div>
    </form>
    <p v-if="error && !Object.keys(errors).length" class="mt-4 text-sm text-red-600">{{ error }}</p>

    <ul class="mt-6 space-y-3">
      <li v-for="c in campaigns" :key="c.id">
        <RouterLink :to="`/orgs/${org}/campaigns/${c.id}`" class="card flex items-center justify-between hover:border-brand">
          <span class="font-medium">{{ c.name }}</span>
          <span class="text-sm text-slate-600">{{ c.start_date }} – {{ c.end_date }}</span>
        </RouterLink>
      </li>
    </ul>
  </section>
</template>
