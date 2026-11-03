<script setup lang="ts">
import { ref } from 'vue'
import { formatBytes } from '@/lib/format'
import AppIcon from './AppIcon.vue'

/** A drop zone that is also a normal, keyboard-usable file input. */
const props = withDefaults(defineProps<{ accept: string; hint?: string; error?: string | null; id?: string }>(), {
  hint: undefined,
  error: null,
  id: 'file',
})
const file = defineModel<File | null>({ default: null })
const over = ref(false)
const input = ref<HTMLInputElement>()

function pick(files: FileList | null | undefined) {
  file.value = files?.[0] ?? null
}
function clear() {
  file.value = null
  if (input.value) input.value.value = ''
}
defineExpose({ clear })
</script>

<template>
  <label
    :for="props.id"
    class="relative flex cursor-pointer flex-col items-center justify-center rounded-2xl border-2 border-dashed px-5 py-8 text-center transition has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-plum-700"
    :class="[
      over ? 'border-plum-700 bg-plum-50' : error ? 'border-danger/50 bg-danger/[0.03]' : 'border-line-strong bg-canvas hover:border-plum-300',
    ]"
    @dragover.prevent="over = true"
    @dragleave.prevent="over = false"
    @drop.prevent="(over = false), pick($event.dataTransfer?.files)"
  >
    <input :id="props.id" ref="input" type="file" class="sr-only" :accept="accept" @change="pick(($event.target as HTMLInputElement).files)" />
    <template v-if="file">
      <span class="grid h-12 w-12 place-items-center rounded-xl bg-success/10 text-success"><AppIcon name="check" /></span>
      <span class="mt-3 max-w-full truncate font-bold text-ink">{{ file.name }}</span>
      <span class="text-sm text-subtle">{{ formatBytes(file.size) }} · click to choose another</span>
      <button type="button" class="link mt-2 text-sm" @click.prevent="clear">Remove</button>
    </template>
    <template v-else>
      <span class="grid h-12 w-12 place-items-center rounded-xl bg-plum-100 text-plum-700"><AppIcon name="upload" /></span>
      <span class="mt-3 font-bold text-ink">Drop a file here or <span class="text-plum-700 underline">browse</span></span>
      <span v-if="hint" class="mt-1 text-sm text-subtle">{{ hint }}</span>
    </template>
  </label>
  <p v-if="error" class="field-error">{{ error }}</p>
</template>
