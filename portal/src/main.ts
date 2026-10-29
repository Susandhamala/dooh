import { createPinia } from 'pinia'
import { createApp } from 'vue'
import App from './App.vue'
import { router } from './router'
import { useAuthStore } from './stores/auth'
import './style.css'

const app = createApp(App).use(createPinia())

// Restore the session before the first route guard runs.
useAuthStore()
  .restore()
  .finally(() => app.use(router).mount('#app'))
