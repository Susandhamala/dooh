<script setup lang="ts">
/** Small stroke icons, drawn inline so the portal needs no icon font. Decorative unless given a label. */
export type IconName = keyof typeof PATHS

const PATHS = {
  screen: 'M3 5h18v11H3zM8 20h8M12 16v4',
  map: 'M9 4 3 6v14l6-2 6 2 6-2V4l-6 2-6-2zM9 4v14M15 6v14',
  pin: 'M12 21s-7-6.2-7-11.5A7 7 0 0 1 19 9.5C19 14.8 12 21 12 21zM12 12a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5z',
  calendar: 'M4 6h16v14H4zM4 10h16M8 3v4M16 3v4',
  clock: 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18zM12 7v5l3 2',
  shield: 'M12 3 4 6v6c0 4.5 3.4 8.3 8 9 4.6-.7 8-4.5 8-9V6l-8-3zM9 12l2 2 4-4',
  lock: 'M6 11h12v9H6zM8 11V8a4 4 0 0 1 8 0v3',
  check: 'M5 12.5 10 17l9-10',
  x: 'M6 6l12 12M18 6 6 18',
  menu: 'M4 7h16M4 12h16M4 17h16',
  back: 'M15 5l-7 7 7 7',
  arrow: 'M5 12h14M13 6l6 6-6 6',
  plus: 'M12 5v14M5 12h14',
  upload: 'M12 16V4M7 9l5-5 5 5M4 16v4h16v-4',
  user: 'M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM4 21a8 8 0 0 1 16 0',
  logout: 'M15 4h4v16h-4M10 8l-4 4 4 4M6 12h10',
  building: 'M5 21V5l7-2v18M12 8h7v13M8 8h1M8 12h1M8 16h1M15 12h1M15 16h1M3 21h18',
  alert: 'M12 4 2.5 20h19L12 4zM12 10v4M12 17.5v.01',
  info: 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18zM12 11v6M12 7.5v.01',
  play: 'M7 5v14l12-7L7 5z',
  inbox: 'M3 13l3-8h12l3 8v6H3v-6zM3 13h5l1 3h6l1-3h5',
  megaphone: 'M3 10v4h3l7 4V6L6 10H3zM17 9a4 4 0 0 1 0 6',
  layers: 'M12 3 2 8l10 5 10-5-10-5zM2 13l10 5 10-5',
  key: 'M14 10a4 4 0 1 0-3.5 4L7 17.5V20h2.5v-2h2v-2l1.5-1.5A4 4 0 0 0 14 10zM15.5 8.5v.01',
  wallet: 'M3 7h15a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7zM3 7l12-3v3M16 13.5h.01',
  film: 'M4 5h16v14H4zM4 9h3M4 15h3M17 9h3M17 15h3M8 5v14M16 5v14',
  image: 'M4 5h16v14H4zM4 16l5-5 4 4 2-2 5 5M15 9.5a1.5 1.5 0 1 0 0-.01',
  idcard: 'M3 6h18v12H3zM7 14.5a2 2 0 1 0 0-.01M5 17c.4-1.4 1.4-2 2-2s1.6.6 2 2M12 10h6M12 13h4',
  trash: 'M4 7h16M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3',
  eye: 'M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12zM12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6z',
  phone: 'M7 3h10v18H7zM11 18h2',
  ban: 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18zM5.6 5.6l12.8 12.8',
  gavel: 'M14 4l6 6M11 7l6 6M12.5 5.5l-6 6M9 15l-6 6M4 20h9',
} as const

withDefaults(defineProps<{ name: IconName; label?: string; size?: number }>(), { size: 20, label: undefined })
</script>

<template>
  <svg
    :width="size"
    :height="size"
    viewBox="0 0 24 24"
    fill="none"
    stroke="currentColor"
    stroke-width="1.9"
    stroke-linecap="round"
    stroke-linejoin="round"
    class="shrink-0"
    :role="label ? 'img' : undefined"
    :aria-label="label"
    :aria-hidden="label ? undefined : 'true'"
    focusable="false"
  >
    <path :d="PATHS[name]" />
  </svg>
</template>
