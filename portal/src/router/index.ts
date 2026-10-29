import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import HomeView from '@/views/HomeView.vue'

declare module 'vue-router' {
  interface RouteMeta {
    auth?: boolean
  }
}

export const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/', name: 'home', component: HomeView },
    { path: '/login', name: 'login', component: () => import('@/views/LoginView.vue') },
    { path: '/sso/start', name: 'sso-start', component: () => import('@/views/SsoStartView.vue') },
    { path: '/sso/callback', name: 'sso-callback', component: () => import('@/views/SsoCallbackView.vue') },
    { path: '/screens', name: 'screens', component: () => import('@/views/ScreensView.vue') },
    { path: '/screens/:id', name: 'screen', component: () => import('@/views/ScreenDetailView.vue'), props: true },
    { path: '/security', name: 'security', component: () => import('@/views/SecurityView.vue'), meta: { auth: true } },
    { path: '/orgs', name: 'orgs', component: () => import('@/views/OrgsView.vue'), meta: { auth: true } },
    {
      path: '/orgs/:org/campaigns',
      name: 'campaigns',
      component: () => import('@/views/CampaignsView.vue'),
      props: true,
      meta: { auth: true },
    },
    {
      path: '/orgs/:org/campaigns/:campaign',
      name: 'campaign',
      component: () => import('@/views/CampaignView.vue'),
      props: true,
      meta: { auth: true },
    },
    {
      path: '/orgs/:org/screens',
      name: 'owner-screens',
      component: () => import('@/views/OwnerScreensView.vue'),
      props: true,
      meta: { auth: true },
    },
  ],
})

router.beforeEach((to) => {
  const auth = useAuthStore()
  if (to.meta.auth && !auth.isLoggedIn) {
    return { name: 'login', query: { next: to.fullPath } }
  }
})
