import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import HomeView from '@/views/HomeView.vue'

declare module 'vue-router' {
  interface RouteMeta {
    auth?: boolean
    admin?: boolean
    guestOnly?: boolean
    /** Needs the terms and content policy accepted first. */
    terms?: boolean
    title?: string
  }
}

export const router = createRouter({
  history: createWebHistory(),
  scrollBehavior: (to, _from, saved) => saved ?? (to.hash ? { el: to.hash } : { top: 0 }),
  routes: [
    { path: '/', name: 'home', component: HomeView },
    { path: '/login', name: 'login', component: () => import('@/views/LoginView.vue'), meta: { guestOnly: true, title: 'Sign in' } },
    { path: '/sso/start', name: 'sso-start', component: () => import('@/views/SsoStartView.vue'), meta: { title: 'Signing in' } },
    { path: '/sso/callback', name: 'sso-callback', component: () => import('@/views/SsoCallbackView.vue'), meta: { title: 'Signing in' } },
    { path: '/welcome', name: 'welcome', component: () => import('@/views/WelcomeView.vue'), meta: { title: 'Terms and content policy' } },
    { path: '/dashboard', name: 'dashboard', component: () => import('@/views/DashboardView.vue'), meta: { auth: true, title: 'Dashboard' } },
    { path: '/screens', name: 'screens', component: () => import('@/views/ScreensView.vue'), meta: { title: 'Find screens' } },
    { path: '/screens/:id', name: 'screen', component: () => import('@/views/ScreenDetailView.vue'), props: true, meta: { title: 'Screen' } },
    { path: '/ads', name: 'ads', component: () => import('@/views/AdsView.vue'), meta: { auth: true, terms: true, title: 'My ads' } },
    { path: '/bookings', name: 'bookings', component: () => import('@/views/BookingsView.vue'), meta: { auth: true, title: 'Bookings' } },
    { path: '/wallet', name: 'wallet', component: () => import('@/views/WalletView.vue'), meta: { auth: true, terms: true, title: 'Wallet' } },
    {
      path: '/verification',
      name: 'verification',
      component: () => import('@/views/VerificationView.vue'),
      meta: { auth: true, terms: true, title: 'Identity verification' },
    },
    { path: '/security', name: 'security', component: () => import('@/views/SecurityView.vue'), meta: { auth: true, title: 'Two-factor security' } },
    { path: '/orgs', name: 'orgs', component: () => import('@/views/OrgsView.vue'), meta: { auth: true, title: 'My organisations' } },
    {
      path: '/orgs/:org/screens',
      name: 'owner-screens',
      component: () => import('@/views/OwnerScreensView.vue'),
      props: true,
      meta: { auth: true, title: 'My screens' },
    },
    { path: '/admin', name: 'admin', component: () => import('@/views/AdminView.vue'), meta: { auth: true, admin: true, title: 'Review' } },
    { path: '/:pathMatch(.*)*', name: 'not-found', component: () => import('@/views/NotFoundView.vue'), meta: { title: 'Page not found' } },
  ],
})

router.beforeEach((to) => {
  const auth = useAuthStore()
  if (to.meta.auth && !auth.isLoggedIn) return { name: 'login', query: { next: to.fullPath } }
  if ((to.meta.guestOnly || to.name === 'home') && auth.isLoggedIn) return { name: 'dashboard' }
  // The API enforces this too; the guard only spares non-admins an empty page.
  if (to.meta.admin && !auth.hasRole('admin')) return { name: 'not-found', params: { pathMatch: ['admin'] } }
  if (to.meta.terms && auth.user && !auth.user.terms_accepted) return { name: 'welcome', query: { next: to.fullPath } }
})

router.afterEach((to) => {
  document.title = to.meta.title ? `${to.meta.title} · Screen Ads` : 'Screen Ads · RentALL Nepal'
})
