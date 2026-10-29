<script setup lang="ts">
const steps = [
  { title: 'Create', body: 'Upload an image or video and preview it at the screen’s real resolution.' },
  { title: 'Check', body: 'Automatic file checks, then the screen owner approves with a written reason.' },
  { title: 'Upload', body: 'Your ad is signed into the screen’s playlist and starts playing.' },
]
</script>

<template>
  <section>
    <h1 class="text-3xl font-bold">Book screen time across Nepal</h1>
    <p class="mt-2 text-slate-600">Advertise on digital screens in three steps.</p>
    <ol class="mt-8 grid gap-4 sm:grid-cols-3">
      <li v-for="(step, i) in steps" :key="step.title" class="rounded-lg border border-slate-200 bg-white p-5">
        <span class="text-sm font-medium text-brand">Step {{ i + 1 }}</span>
        <h2 class="mt-1 text-lg font-semibold">{{ step.title }}</h2>
        <p class="mt-1 text-sm text-slate-600">{{ step.body }}</p>
      </li>
    </ol>
  </section>
</template>
