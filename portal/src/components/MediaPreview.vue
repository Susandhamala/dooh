<script setup lang="ts">
import { ref, watch } from 'vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import type { Creative } from '@/lib/types'

/** Full-size preview of an ad, shown in its real shape. */
const props = defineProps<{ creative: Creative | null }>()
const emit = defineEmits<{ close: [] }>()
const el = ref<HTMLDialogElement>()

watch(
  () => props.creative,
  (c) => {
    if (c && !el.value?.open) el.value?.showModal()
    if (!c && el.value?.open) el.value.close()
  },
)
</script>

<template>
  <dialog
    ref="el"
    class="m-auto max-h-[92dvh] w-[calc(100%-2rem)] max-w-4xl overflow-hidden rounded-2xl bg-plum-950 p-0 text-white shadow-modal backdrop:bg-black/70"
    @cancel.prevent="emit('close')"
    @click.self="emit('close')"
  >
    <div v-if="creative" class="flex flex-col">
      <div class="flex items-center justify-between gap-3 px-4 py-3">
        <div class="min-w-0">
          <p class="truncate font-bold">{{ creative.name }}</p>
          <p class="text-xs text-white/60">
            {{ creative.width_px }}×{{ creative.height_px }} · {{ creative.kind }}<template v-if="creative.duration_s"> · {{ Math.round(creative.duration_s) }} s</template>
          </p>
        </div>
        <button type="button" class="grid h-10 w-10 place-items-center rounded-xl hover:bg-white/10" aria-label="Close preview" @click="emit('close')">
          <AppIcon name="x" />
        </button>
      </div>
      <div class="grid place-items-center bg-black p-2">
        <video
          v-if="creative.kind === 'video' && creative.media_url"
          :src="creative.media_url"
          class="max-h-[75dvh] w-auto max-w-full"
          controls
          autoplay
          muted
          loop
          playsinline
        />
        <img v-else-if="creative.media_url" :src="creative.media_url" :alt="creative.name" class="max-h-[75dvh] w-auto max-w-full" />
      </div>
    </div>
  </dialog>
</template>
