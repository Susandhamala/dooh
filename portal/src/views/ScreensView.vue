<script setup lang="ts">
import ScreenFinder from '@/components/ScreenFinder.vue'
import AppIcon from '@/components/ui/AppIcon.vue'

/** Find screens: the map and every billboard in view as cards. */
</script>

<template>
  <div>
    <section class="border-b border-line bg-canvas">
      <div class="shell py-10 sm:py-14">
        <p class="text-sm font-semibold tracking-[0.14em] text-plum-700 uppercase">Find screens</p>
        <div class="mt-3 flex flex-wrap items-end justify-between gap-4">
          <h1 class="text-3xl font-bold tracking-[-0.03em] text-ink sm:text-[42px]">Billboards <span class="text-coral-700">near you</span></h1>
          <ul class="flex flex-wrap gap-x-5 gap-y-1.5 text-sm text-subtle">
            <li class="flex items-center gap-1.5"><AppIcon name="map" :size="16" class="text-plum-700" /> Drag the map to search an area</li>
            <li class="flex items-center gap-1.5"><AppIcon name="calendar" :size="16" class="text-plum-700" /> Open a screen to pick days, time and your slot</li>
          </ul>
        </div>
      </div>
    </section>
    <div class="shell py-8 sm:py-10">
      <ScreenFinder layout="grid" />
    </div>
  </div>
</template>
