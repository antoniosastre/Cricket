import { createRouter, createWebHashHistory } from 'vue-router'
import { auth } from './api'
import Login from './views/Login.vue'
import Dashboard from './views/Dashboard.vue'
import Trabajos from './views/Trabajos.vue'
import Archivado from './views/Archivado.vue'
import Admin from './views/Admin.vue'

const routes = [
  { path: '/login', component: Login, meta: { public: true } },
  { path: '/', component: Dashboard },
  { path: '/trabajos', component: Trabajos },
  { path: '/archivado', component: Archivado },
  { path: '/admin', component: Admin },
]

// Hash history: funciona en hosting estatico sin configurar rewrites.
const router = createRouter({ history: createWebHashHistory(), routes })

router.beforeEach((to) => {
  if (!to.meta.public && !auth.access) return '/login'
  if (to.path === '/login' && auth.access) return '/'
})

export default router
