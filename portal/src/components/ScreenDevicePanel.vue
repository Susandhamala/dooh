<script setup lang="ts">
import { computed, ref } from 'vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue'
import { formatDateTime, formatTime } from '@/lib/format'
import { errorMessage, post } from '@/lib/http'
import type { Screen } from '@/lib/types'

/** The player (Raspberry Pi) behind one of the owner's screens: status, connect code, disconnect. */
const props = defineProps<{ org: string; screen: Screen }>()
const emit = defineEmits<{ changed: []; mfa: [err: unknown] }>()

const code = ref<{ code: string; expires_at: string } | null>(null)
const busy = ref(false)
const error = ref<string | null>(null)
const confirming = ref(false)

const device = computed(() => props.screen.device ?? null)
const base = computed(() => `/orgs/${props.org}/screens/${props.screen.id}/device`)
const command = computed(
  () => `sudo player/pi/install.sh --api https://<server-ip>:8443 --ca ~/ca.crt --code ${code.value?.code ?? ''}`,
)

async function getCode() {
  busy.value = true
  error.value = null
  try {
    code.value = (await post<{ code: string; expires_at: string }>(`${base.value}/code`)).data
  } catch (err) {
    emit('mfa', err)
    error.value = errorMessage(err)
  } finally {
    busy.value = false
  }
}

async function disconnect() {
  busy.value = true
  error.value = null
  try {
    await post(`${base.value}/revoke`)
    confirming.value = false
    emit('changed')
  } catch (err) {
    confirming.value = false
    emit('mfa', err)
    error.value = errorMessage(err)
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <section class="mt-4 rounded-xl border border-divider p-3 text-sm" aria-label="Screen player">
    <div class="flex flex-wrap items-center justify-between gap-2">
      <p v-if="device" class="flex items-center gap-2 font-semibold">
        <span
          class="inline-block size-2.5 rounded-full"
          :class="device.online ? 'bg-success' : 'bg-danger'"
          aria-hidden="true"
        />
        Player {{ device.online ? 'online' : 'offline' }}
        <span class="font-normal text-subtle">
          · {{ device.last_seen_at ? `last seen ${formatDateTime(device.last_seen_at)}` : 'not seen yet' }}
        </span>
      </p>
      <p v-else class="flex items-center gap-2 text-subtle"><AppIcon name="screen" :size="16" /> No player connected</p>

      <div class="flex gap-2">
        <button type="button" class="btn-ghost btn-sm" :disabled="busy" @click="getCode">
          <AppIcon name="key" :size="15" /> {{ device ? 'Replace player' : 'Connect player' }}
        </button>
        <button v-if="device" type="button" class="btn-ghost btn-sm text-danger" :disabled="busy" @click="confirming = true">
          Disconnect
        </button>
      </div>
    </div>

    <div v-if="code" class="mt-3 rounded-lg bg-plum-50 p-3 text-plum-900">
      <p>
        Connection code, works once until {{ formatTime(code.expires_at) }}:
        <strong class="ml-1 font-mono text-base tracking-widest">{{ code.code }}</strong>
      </p>
      <p class="mt-2 text-xs">On the Raspberry Pi, in the <code>dooh</code> folder:</p>
      <code class="mt-1 block overflow-x-auto rounded bg-white/70 px-2 py-1.5 font-mono text-xs whitespace-nowrap">{{ command }}</code>
      <p v-if="device" class="mt-2 text-xs">The current player stops working as soon as the new one connects.</p>
    </div>

    <p v-if="error" class="field-error mt-2">{{ error }}</p>

    <ConfirmDialog
      :open="confirming"
      title="Disconnect this player?"
      confirm-label="Disconnect"
      danger
      :busy="busy"
      @confirm="disconnect"
      @cancel="confirming = false"
    >
      Its certificate stops working at once and the screen goes blank. Do this if the device is lost or replaced. To use it
      again, connect it with a new code.
    </ConfirmDialog>
  </section>
</template>
