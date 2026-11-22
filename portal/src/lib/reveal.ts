import type { Directive } from 'vue'

/**
 * v-reveal: fade and lift an element in the first time it scrolls into view.
 * Content is only hidden once we know we can show it again (IntersectionObserver present,
 * motion allowed), so nothing stays invisible.
 */
const io =
  typeof window !== 'undefined' && 'IntersectionObserver' in window && !window.matchMedia('(prefers-reduced-motion: reduce)').matches
    ? new IntersectionObserver(
        (entries) => {
          for (const e of entries) {
            if (!e.isIntersecting) continue
            e.target.classList.add('reveal-in')
            io?.unobserve(e.target)
          }
        },
        { rootMargin: '0px 0px -8% 0px' },
      )
    : null

export const vReveal: Directive<HTMLElement, number | undefined> = {
  mounted(el, binding) {
    if (!io) return
    el.classList.add('reveal')
    if (binding.value) el.style.transitionDelay = `${binding.value}ms`
    io.observe(el)
  },
  unmounted(el) {
    io?.unobserve(el)
  },
}
