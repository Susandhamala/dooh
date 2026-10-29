<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { errorMessage, fieldErrors } from '@/lib/http'
import { useOrgStore } from '@/stores/orgs'

const orgs = useOrgStore()
const form = ref({ name: '', type: 'advertiser', pan_number: '' })
const errors = ref<Record<string, string>>({})
const error = ref<string | null>(null)
const showForm = ref(false)

onMounted(() => orgs.load().catch((err) => (error.value = errorMessage(err))))

async function create() {
  errors.value = {}
  try {
    await orgs.create({ ...form.value, pan_number: form.value.pan_number || undefined })
    form.value = { name: '', type: 'advertiser', pan_number: '' }
    showForm.value = false
  } catch (err) {
    errors.value = fieldErrors(err)
    error.value = errorMessage(err)
  }
}
</script>

<template>
  <section>
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-bold">My organisations</h1>
      <button class="btn" @click="showForm = !showForm">New organisation</button>
    </div>

    <form v-if="showForm" class="card mt-6 grid gap-4 sm:grid-cols-3" @submit.prevent="create">
      <div>
        <label class="label" for="org-name">Name</label>
        <input id="org-name" v-model="form.name" class="input" required />
        <p v-if="errors.name" class="field-error">{{ errors.name }}</p>
      </div>
      <div>
        <label class="label" for="org-type">I want to</label>
        <select id="org-type" v-model="form.type" class="input">
          <option value="advertiser">Advertise on screens</option>
          <option value="owner">List my screens</option>
        </select>
      </div>
      <div>
        <label class="label" for="org-pan">PAN (optional)</label>
        <input id="org-pan" v-model="form.pan_number" class="input" inputmode="numeric" maxlength="9" />
        <p v-if="errors.pan_number" class="field-error">{{ errors.pan_number }}</p>
      </div>
      <div class="sm:col-span-3"><button class="btn">Create</button></div>
    </form>

    <p v-if="error && !showForm" class="mt-4 text-sm text-red-600">{{ error }}</p>
    <p v-if="orgs.loaded && !orgs.list.length" class="mt-8 text-slate-600">
      You're not in any organisation yet. Create one to start advertising or to list your screens.
    </p>

    <ul class="mt-6 grid gap-4 sm:grid-cols-2">
      <li v-for="org in orgs.list" :key="org.id" class="card">
        <div class="flex items-center gap-2">
          <h2 class="font-semibold">{{ org.name }}</h2>
          <span class="badge">{{ org.type === 'owner' ? 'Screen owner' : 'Advertiser' }}</span>
          <span v-if="!org.verified" class="badge bg-amber-100 text-amber-800">Awaiting verification</span>
        </div>
        <p class="mt-1 text-sm text-slate-500">Your role: {{ org.my_role }}</p>
        <RouterLink
          :to="org.type === 'owner' ? `/orgs/${org.id}/screens` : `/orgs/${org.id}/campaigns`"
          class="btn-secondary mt-4"
        >
          {{ org.type === 'owner' ? 'Manage screens' : 'Campaigns' }}
        </RouterLink>
      </li>
    </ul>
  </section>
</template>
