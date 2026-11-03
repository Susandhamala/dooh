<script setup lang="ts">
const rentall = import.meta.env.VITE_RENTALL_URL ?? 'https://rentallnepal.com'
const year = new Date().getFullYear()
</script>

<template>
  <footer class="mt-16 border-t border-line bg-white">
    <div class="shell flex flex-col gap-4 py-8 text-sm text-subtle sm:flex-row sm:items-center sm:justify-between">
      <div class="flex items-center gap-2.5">
        <img src="/brand/logo-mark.png" width="140" height="148" alt="" class="h-7 w-auto" />
        <p>
          <span class="font-bold text-ink-soft">Screen Ads</span> is a
          <a :href="rentall" class="link">RentALL Nepal</a> service.
        </p>
      </div>
      <nav class="flex flex-wrap gap-x-5 gap-y-2" aria-label="Footer">
        <RouterLink to="/screens" class="hover:text-plum-900">Find screens</RouterLink>
        <RouterLink to="/orgs" class="hover:text-plum-900">List your screens</RouterLink>
        <a :href="rentall" class="hover:text-plum-900">Back to rentallnepal.com</a>
        <span>© {{ year }} RentALL Nepal</span>
      </nav>
    </div>
  </footer>
</template>
