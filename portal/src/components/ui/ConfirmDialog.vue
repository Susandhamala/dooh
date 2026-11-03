<script setup lang="ts">
import { ref, watch } from 'vue'

/** A native <dialog>: focus stays inside, Esc closes it, and the page behind is inert. */
const props = withDefaults(
  defineProps<{ open: boolean; title: string; confirmLabel?: string; danger?: boolean; busy?: boolean }>(),
  { confirmLabel: 'Confirm', danger: false, busy: false },
)
const emit = defineEmits<{ confirm: []; cancel: [] }>()

const el = ref<HTMLDialogElement>()

watch(
  () => props.open,
  (open) => {
    if (!el.value) return
    if (open && !el.value.open) el.value.showModal()
    if (!open && el.value.open) el.value.close()
  },
)
</script>

<template>
  <dialog
    ref="el"
    class="m-auto w-[calc(100%-2rem)] max-w-md rounded-2xl border border-line bg-white p-0 text-ink shadow-modal backdrop:bg-plum-950/40"
    @cancel.prevent="emit('cancel')"
  >
    <div class="p-6">
      <h2 class="h-section">{{ title }}</h2>
      <div class="mt-2 text-[15px] text-muted"><slot /></div>
      <div class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
        <button type="button" class="btn-outline" :disabled="busy" @click="emit('cancel')">Keep it</button>
        <button type="button" :class="danger ? 'btn-danger' : 'btn-plum'" :disabled="busy" @click="emit('confirm')">
          {{ confirmLabel }}
        </button>
      </div>
    </div>
  </dialog>
</template>
