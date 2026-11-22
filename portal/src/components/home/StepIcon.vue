<script setup lang="ts">
/** Two-colour pictures for the steps (plum shapes, coral accent), drawn to sit on white. */
defineProps<{ name: 'phone' | 'id' | 'photo' | 'pick' | 'live' }>()
</script>

<template>
  <svg viewBox="0 0 64 64" width="64" height="64" fill="none" aria-hidden="true">
    <!-- Sign up: phone with a code bubble -->
    <template v-if="name === 'phone'">
      <rect x="16" y="6" width="26" height="50" rx="5" class="fill-plum-700" />
      <rect x="19.5" y="12" width="19" height="34" rx="2" fill="#fff" />
      <rect x="25" y="50" width="8" height="2.5" rx="1.25" fill="#fff" opacity=".7" />
      <rect x="22.5" y="18" width="13" height="4" rx="1" class="fill-plum-200" />
      <rect x="22.5" y="25" width="9" height="4" rx="1" class="fill-plum-200" />
      <path d="M36 26h18a4 4 0 0 1 4 4v9a4 4 0 0 1-4 4h-9l-5 5v-5h-4a4 4 0 0 1-4-4v-9a4 4 0 0 1 4-4Z" class="fill-coral" />
      <circle cx="40.5" cy="34.5" r="2" fill="#fff" /><circle cx="46" cy="34.5" r="2" fill="#fff" /><circle cx="51.5" cy="34.5" r="2" fill="#fff" />
    </template>

    <!-- Verify: ID card with a tick -->
    <template v-else-if="name === 'id'">
      <rect x="5" y="14" width="46" height="34" rx="5" class="fill-plum-700" />
      <circle cx="18" cy="28" r="6" class="fill-plum-200" />
      <path d="M9.5 42c1.5-5 5-7.5 8.5-7.5s7 2.5 8.5 7.5" class="fill-plum-200" />
      <rect x="30" y="23" width="15" height="3" rx="1.5" fill="#fff" />
      <rect x="30" y="29.5" width="11" height="3" rx="1.5" fill="#fff" opacity=".7" />
      <rect x="30" y="36" width="13" height="3" rx="1.5" fill="#fff" opacity=".7" />
      <circle cx="49" cy="45" r="11" class="fill-coral" />
      <path d="m43.5 45 3.8 3.8 7-7.3" stroke="#fff" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />
    </template>

    <!-- Upload: picture with an up arrow -->
    <template v-else-if="name === 'photo'">
      <rect x="6" y="12" width="44" height="36" rx="5" class="fill-plum-700" />
      <circle cx="19" cy="23" r="4.5" class="fill-coral" />
      <path d="M10 44l11-12 7 7 6-6 12 11" class="fill-plum-300" />
      <circle cx="48" cy="44" r="11" class="fill-coral" />
      <path d="M48 50V38m-5 5 5-5 5 5" stroke="#fff" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />
    </template>

    <!-- Choose: billboard with a map pin -->
    <template v-else-if="name === 'pick'">
      <rect x="6" y="16" width="40" height="24" rx="3" class="fill-plum-700" />
      <rect x="10" y="20" width="32" height="16" rx="1.5" class="fill-plum-200" />
      <rect x="17" y="40" width="4" height="18" class="fill-plum-700" />
      <rect x="31" y="40" width="4" height="18" class="fill-plum-700" />
      <path d="M50 4c-5.5 0-10 4.3-10 9.7C40 21 50 32 50 32s10-11 10-18.3C60 8.3 55.5 4 50 4Z" class="fill-coral" />
      <circle cx="50" cy="13.5" r="3.8" fill="#fff" />
    </template>

    <!-- Live: screen playing, with signal -->
    <template v-else>
      <rect x="4" y="12" width="44" height="30" rx="4" class="fill-plum-700" />
      <rect x="8" y="16" width="36" height="22" rx="2" class="fill-plum-200" />
      <path d="M22 21.5v11l9.5-5.5-9.5-5.5Z" class="fill-coral" />
      <rect x="22" y="42" width="8" height="10" class="fill-plum-700" />
      <rect x="14" y="52" width="24" height="4" rx="2" class="fill-plum-700" />
      <path d="M53 20a10 10 0 0 1 0 14M57.5 15.5a16 16 0 0 1 0 23" class="stroke-coral" stroke-width="3" stroke-linecap="round" />
    </template>
  </svg>
</template>
